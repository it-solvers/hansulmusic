<?php
declare(strict_types=1);

// [1단계] 상품 관리와 같은 인증을 사용해 Concert/YouTube 영상, 찬송가, 찬양곡 링크를 관리합니다.
require_once __DIR__ . '/admin-auth.php';

/** hymnal 테이블의 파트별 링크 컬럼과 화면 라벨입니다. */
const ADMIN_PART_COLUMNS = [
    'soprano_url' => '소프라노',
    'alto_url' => '알토',
    'tenor_url' => '테너',
    'bass_url' => '베이스',
    'chorus_url' => '합창',
    'piano_url' => '피아노',
    'soprano_1_url' => '소프라노 1',
    'soprano_2_url' => '소프라노 2',
    'tenor_1_url' => '테너 1',
    'tenor_2_url' => '테너 2',
    'bass_1_url' => '베이스 1',
    'bass_2_url' => '베이스 2',
];

/** 목록 구분 select에 보여줄 hymnal_type 이름을 catalog_type별로 반환합니다. */
function adminHymnalTypes(int $catalogType): array
{
    return $catalogType === 1
        ? [1 => '찬송가', 2 => '새찬송가', 3 => '영문찬송가']
        : [1 => '절기별', 2 => '가나다순', 3 => '알파벳순'];
}

/** 빈 값이면 null, 아니면 http/https 주소(500자 이하)만 허용해 반환합니다. */
function adminOptionalUrl(mixed $value, string $label): ?string
{
    $url = trim((string) $value);
    if ($url === '') {
        return null;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (mb_strlen($url) > 500
        || !in_array($scheme, ['http', 'https'], true)
        || filter_var($url, FILTER_VALIDATE_URL) === false
    ) {
        throw new RuntimeException($label . ' 링크는 500자 이하의 http(s) 주소로 입력해 주세요.');
    }
    return $url;
}

/** "파트명|URL" 형식의 줄들을 other_part_links JSON 문자열로 변환합니다(없으면 null). */
function adminParseOtherLinks(string $text): ?string
{
    $items = [];
    foreach (preg_split('/\R/', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $pieces = explode('|', $line, 2);
        $part = trim($pieces[0]);
        $url = adminOptionalUrl($pieces[1] ?? '', '기타 파트');
        if ($part === '' || mb_strlen($part) > 20 || $url === null) {
            throw new RuntimeException('기타 파트는 "파트명|링크" 형식으로 한 줄씩 입력해 주세요.');
        }
        $items[] = ['part' => $part, 'url' => $url];
    }
    return $items === [] ? null : json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

/** other_part_links JSON을 편집용 "파트명|URL" 텍스트로 되돌립니다. */
function adminFormatOtherLinks(?string $json): string
{
    if ($json === null || $json === '') {
        return '';
    }
    $items = json_decode($json, true);
    $lines = [];
    foreach (is_array($items) ? $items : [] as $item) {
        if (is_array($item) && isset($item['part'], $item['url'])) {
            $lines[] = $item['part'] . '|' . $item['url'];
        }
    }
    return implode("\n", $lines);
}

// [2단계] 자격 증명 파일을 읽을 수 없으면 누구도 로그인할 수 없도록 화면을 닫습니다.
$configurationError = false;
$adminUsername = '';
$adminPassword = '';
try {
    [$adminUsername, $adminPassword] = adminCredentials();
} catch (RuntimeException $exception) {
    error_log('Media administration credential configuration error: ' . $exception->getMessage());
    $configurationError = true;
}

$basePath = appBasePath();
$section = (string) ($_GET['section'] ?? $_POST['section'] ?? 'videos');
if (!isset(ADMIN_MEDIA_SECTIONS[$section])) {
    $section = 'videos';
}
$catalogType = $section === 'praise' ? 2 : 1;
$errorMessage = '';
$successMessage = (string) ($_SESSION['admin_media_flash'] ?? '');
unset($_SESSION['admin_media_flash']);
$isAuthenticated = ($_SESSION['product_admin_authenticated'] ?? false) === true;
$items = [];
$selected = null;
$editingNew = ($_GET['new'] ?? '') === '1';

if ($configurationError) {
    http_response_code(503);
    $errorMessage = '관리자 계정 파일 설정이 없습니다. 서버에 .admin-products-login 파일을 확인해 주세요.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'login') {
        $errorMessage = adminAttemptLogin($adminUsername, $adminPassword, '/admin-media.php');
    } elseif (!hash_equals(adminCsrfToken(), (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        $errorMessage = '요청을 확인할 수 없습니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.';
    } elseif (!$isAuthenticated) {
        http_response_code(401);
        $errorMessage = '관리자 로그인이 필요합니다.';
    } elseif ($action === 'logout') {
        unset($_SESSION['product_admin_authenticated'], $_SESSION['admin_csrf_token']);
        session_regenerate_id(true);
        header('Location: ' . $basePath . '/admin-media.php', true, 303);
        exit;
    } elseif ($action === 'save') {
        // [3단계] 입력값을 검증한 뒤 videos 또는 hymnal 행을 INSERT/UPDATE합니다(삭제 없음).
        try {
            $rawId = (string) ($_POST['item_id'] ?? '');
            $itemId = $rawId === '' ? null : filter_var($rawId, FILTER_VALIDATE_INT);
            if ($itemId === false || ($itemId !== null && $itemId < 1)) {
                throw new RuntimeException('항목 번호가 올바르지 않습니다.');
            }
            $sortOrder = filter_var($_POST['sort_order'] ?? null, FILTER_VALIDATE_INT);
            if ($sortOrder === false || $sortOrder < 0 || $sortOrder > 2147483647) {
                throw new RuntimeException('표시 순서를 확인해 주세요.');
            }
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $pdo = adminDatabaseConnection();

            if ($section === 'videos') {
                $videoType = filter_var($_POST['video_type'] ?? null, FILTER_VALIDATE_INT);
                $role = filter_var($_POST['role'] ?? null, FILTER_VALIDATE_INT);
                $title = trim((string) ($_POST['video_title'] ?? ''));
                $url = adminOptionalUrl($_POST['video_url'] ?? '', '영상');
                if (!in_array($videoType, [1, 2], true) || !in_array($role, [1, 2], true)
                    || $url === null || mb_strlen($title) > 255
                ) {
                    throw new RuntimeException('표시 페이지, 분류, 영상 링크를 확인해 주세요.');
                }
                $values = [
                    'video_type' => $videoType, 'role' => $role, 'video_title' => $title === '' ? null : $title,
                    'video_url' => $url, 'sort_order' => $sortOrder, 'is_active' => $isActive,
                ];
                if ($itemId === null) {
                    $statement = $pdo->prepare(
                        'INSERT INTO videos (video_type, role, video_title, video_url, sort_order, is_active)
                         VALUES (:video_type, :role, :video_title, :video_url, :sort_order, :is_active)'
                    );
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE videos SET video_type = :video_type, role = :role, video_title = :video_title,
                                video_url = :video_url, sort_order = :sort_order, is_active = :is_active
                         WHERE video_id = :id'
                    );
                    $values['id'] = $itemId;
                }
            } else {
                $hymnalType = filter_var($_POST['hymnal_type'] ?? null, FILTER_VALIDATE_INT);
                $number = trim((string) ($_POST['hymn_number'] ?? ''));
                $number = $number === '' ? null : filter_var($number, FILTER_VALIDATE_INT);
                $title = trim((string) ($_POST['hymn_title'] ?? ''));
                $sectionTitle = trim((string) ($_POST['section_title'] ?? ''));
                if (!in_array($hymnalType, [1, 2, 3], true) || $title === '' || mb_strlen($title) > 255
                    || mb_strlen($sectionTitle) > 160
                    || $number === false || ($number !== null && ($number < 1 || $number > 65535))
                ) {
                    throw new RuntimeException('목록 구분, 곡명, 번호를 확인해 주세요.');
                }
                $values = [
                    'catalog_type' => $catalogType, 'hymnal_type' => $hymnalType, 'hymn_number' => $number,
                    'hymn_title' => $title, 'section_title' => $sectionTitle === '' ? null : $sectionTitle,
                    'video_url' => adminOptionalUrl($_POST['video_url'] ?? '', '대표 영상'),
                    'source_url' => adminOptionalUrl($_POST['source_url'] ?? '', '출처'),
                    'other_part_links' => adminParseOtherLinks((string) ($_POST['other_part_links'] ?? '')),
                    'sort_order' => $sortOrder, 'is_active' => $isActive,
                ];
                foreach (ADMIN_PART_COLUMNS as $column => $label) {
                    $values[$column] = adminOptionalUrl($_POST[$column] ?? '', $label);
                }
                $columns = array_keys($values);
                if ($itemId === null) {
                    $statement = $pdo->prepare(
                        'INSERT INTO hymnal (' . implode(', ', $columns) . ')
                         VALUES (:' . implode(', :', $columns) . ')'
                    );
                } else {
                    // catalog_type은 수정하지 않으므로 다른 구분의 행이 바뀌지 않도록 WHERE에 함께 건다.
                    unset($values['catalog_type']);
                    $assignments = array_map(static fn (string $c): string => "$c = :$c", array_keys($values));
                    $statement = $pdo->prepare(
                        'UPDATE hymnal SET ' . implode(', ', $assignments)
                        . ' WHERE hymnal_id = :id AND catalog_type = :catalog_type'
                    );
                    $values['id'] = $itemId;
                    $values['catalog_type'] = $catalogType;
                }
            }
            $statement->execute($values);
            if ($itemId === null) {
                $itemId = (int) $pdo->lastInsertId();
            }
            $_SESSION['admin_media_flash'] = '저장했습니다.';
            header('Location: ' . $basePath . '/admin-media.php?section=' . $section . '&id=' . $itemId, true, 303);
            exit;
        } catch (PDOException | RuntimeException | JsonException $exception) {
            error_log('Media administration error: ' . $exception->getMessage());
            http_response_code($exception instanceof PDOException ? 500 : 422);
            $errorMessage = $exception instanceof PDOException
                ? '저장 중 데이터베이스 오류가 발생했습니다.'
                : $exception->getMessage();
            $editingNew = ($rawId ?? '') === '';
            $selected = $_POST;
            $selected['_failed'] = true;
        }
    } else {
        http_response_code(400);
        $errorMessage = '지원하지 않는 요청입니다.';
    }
    $isAuthenticated = ($_SESSION['product_admin_authenticated'] ?? false) === true;
}

if (!$configurationError && $isAuthenticated) {
    // [3단계] 구분별 목록을 조회합니다. is_active와 무관하게 숨김 항목도 관리자에게는 모두 보여줍니다.
    try {
        $pdo = adminDatabaseConnection();
        if ($section === 'videos') {
            $items = $pdo->query(
                'SELECT video_id AS id, video_title AS title, video_url, video_type, role, sort_order, is_active
                 FROM videos ORDER BY video_type, role, sort_order, video_id'
            )->fetchAll(PDO::FETCH_ASSOC);
            $idColumn = 'video_id';
            $table = 'videos';
        } else {
            $statement = $pdo->prepare(
                'SELECT hymnal_id AS id, hymn_title AS title, hymnal_type, hymn_number, sort_order, is_active
                 FROM hymnal WHERE catalog_type = :catalog_type
                 ORDER BY hymnal_type, sort_order, hymn_number, hymnal_id'
            );
            $statement->execute(['catalog_type' => $catalogType]);
            $items = $statement->fetchAll(PDO::FETCH_ASSOC);
            $idColumn = 'hymnal_id';
            $table = 'hymnal';
        }
        $requestedId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if ($selected === null && $requestedId !== false && $requestedId !== null) {
            $query = "SELECT * FROM $table WHERE $idColumn = :id" . ($section === 'videos' ? '' : ' AND catalog_type = :catalog_type');
            $statement = $pdo->prepare($query);
            $params = ['id' => $requestedId] + ($section === 'videos' ? [] : ['catalog_type' => $catalogType]);
            $statement->execute($params);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                $selected = $row;
                $selected['item_id'] = $row[$idColumn];
            }
        }
    } catch (PDOException | RuntimeException $exception) {
        error_log('Media administration load error: ' . $exception->getMessage());
        http_response_code(503);
        $errorMessage = '목록을 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.';
    }
}

$form = $selected ?? [
    'item_id' => '', 'video_type' => 1, 'role' => 1, 'video_title' => '', 'video_url' => '', 'hymnal_type' => 1,
    'hymn_number' => '', 'hymn_title' => '', 'section_title' => '', 'source_url' => '', 'other_part_links' => '',
    'sort_order' => 0, 'is_active' => 1,
];
if (($form['_failed'] ?? false) === true) {
    $form['is_active'] = isset($_POST['is_active']) ? 1 : 0;
}
$showForm = $selected !== null || $editingNew;
$currentId = (string) ($form['item_id'] ?? '');
$otherLinksText = adminFormatOtherLinks(isset($form['other_part_links']) ? (string) $form['other_part_links'] : null);
if (($form['_failed'] ?? false) === true) {
    $otherLinksText = (string) ($_POST['other_part_links'] ?? '');
}
$value = static fn (string $key): string => adminEscape((string) ($form[$key] ?? ''));
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>영상·찬송가 관리 | HANSUL MUSIC</title>
  <style>
    :root { color-scheme:light; font-family:Inter,'Noto Sans KR',sans-serif; color:#292825; background:#f5f3ef; }
    * { box-sizing:border-box; }
    body { margin:0; }
    a { color:inherit; }
    button,input,select,textarea { font:inherit; }
    .admin-shell { width:min(1180px,calc(100% - 32px)); margin:0 auto; padding:36px 0 64px; }
    .admin-top { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom:22px; }
    h1,h2,p { margin-top:0; }
    h1 { margin-bottom:6px; font:400 30px/1.3 'Noto Serif KR',serif; }
    .muted { color:#77746d; font-size:13px; line-height:1.6; }
    .admin-nav { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:22px; }
    .admin-layout { display:grid; grid-template-columns:minmax(220px,.8fr) minmax(0,1.6fr); gap:22px; align-items:start; }
    .panel { padding:22px; border:1px solid #e2ded5; background:#fff; }
    .item-list { display:grid; gap:8px; margin-bottom:18px; max-height:640px; overflow:auto; }
    .item-list a { display:grid; gap:4px; padding:10px 12px; border:1px solid #e8e4dc; text-decoration:none; }
    .item-list a[aria-current="true"] { border-color:#a68b5b; background:#faf8f3; }
    .item-list small { color:#77746d; }
    .admin-button { display:inline-flex; align-items:center; justify-content:center; min-height:40px; padding:9px 14px; border:1px solid #343330; background:#343330; color:#fff; text-decoration:none; cursor:pointer; }
    .admin-button.secondary { border-color:#d8d3ca; background:#fff; color:#292825; }
    .admin-button.secondary[aria-current="page"] { border-color:#a68b5b; background:#faf8f3; }
    .form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .field { display:grid; gap:7px; min-width:0; }
    .field.full { grid-column:1/-1; }
    .field label { font-size:13px; font-weight:600; }
    .field input,.field select,.field textarea { width:100%; min-height:42px; padding:9px 10px; border:1px solid #d8d3ca; background:#fff; color:#292825; }
    .field textarea { min-height:90px; resize:vertical; }
    .check-row { display:flex; align-items:center; gap:8px; font-size:13px; }
    .check-row input { width:auto; min-height:0; }
    .form-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:20px; }
    .message { margin:0 0 18px; padding:12px 14px; border:1px solid #dccfb1; background:#faf6e9; line-height:1.5; }
    .message.error { border-color:#e4c2bc; background:#fff3f0; color:#7f2f25; }
    .login-panel { width:min(440px,100%); margin:70px auto; }
    .login-panel form { display:grid; gap:16px; }
    @media (max-width:760px) { .admin-layout { grid-template-columns:1fr; } .form-grid { grid-template-columns:1fr; } .field.full { grid-column:auto; } }
  </style>
</head>
<body>
  <main class="admin-shell">
    <header class="admin-top">
      <?php if ($isAuthenticated): ?>
        <div>
          <h1>영상·찬송가 관리</h1>
          <p class="muted">Concert/YouTube 영상, 찬송가, 찬양곡 링크를 관리합니다. 삭제는 없으며 진열 상태를 해제하면 숨김 처리됩니다.</p>
        </div>
        <form method="post" action="<?= adminEscape($basePath) ?>/admin-media.php?section=<?= adminEscape($section) ?>">
          <input type="hidden" name="csrf_token" value="<?= adminEscape(adminCsrfToken()) ?>">
          <input type="hidden" name="action" value="logout">
          <button class="admin-button secondary" type="submit">로그아웃</button>
        </form>
      <?php endif; ?>
    </header>
    <?php if ($errorMessage !== ''): ?>
      <p class="message error" role="alert"><?= adminEscape($errorMessage) ?></p>
    <?php endif; ?>
    <?php if ($successMessage !== ''): ?>
      <p class="message" role="status"><?= adminEscape($successMessage) ?></p>
    <?php endif; ?>

    <?php if (!$isAuthenticated): ?>
      <section class="panel login-panel">
        <h2>관리자 로그인</h2>
        <p class="muted">상품 관리와 같은 관리자 계정으로 로그인해 주세요.</p>
        <form method="post" action="<?= adminEscape($basePath) ?>/admin-media.php">
          <input type="hidden" name="action" value="login">
          <input type="hidden" name="csrf_token" value="<?= adminEscape(adminCsrfToken()) ?>">
          <div class="field"><label for="username">관리자 아이디</label><input id="username" name="username" autocomplete="username" required></div>
          <div class="field"><label for="password">비밀번호</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
          <button class="admin-button" type="submit">로그인</button>
        </form>
      </section>
    <?php else: ?>
      <nav class="admin-nav" aria-label="관리 메뉴">
        <a class="admin-button secondary" href="<?= adminEscape($basePath) ?>/admin-products.php">상품 관리</a>
        <?php foreach (ADMIN_MEDIA_SECTIONS as $key => $label): ?>
          <a class="admin-button secondary" href="<?= adminEscape($basePath) ?>/admin-media.php?section=<?= $key ?>" <?= $key === $section ? 'aria-current="page"' : '' ?>><?= adminEscape($label) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="admin-layout">
        <aside class="panel" aria-label="목록">
          <h2><?= adminEscape(ADMIN_MEDIA_SECTIONS[$section]) ?> 목록</h2>
          <nav class="item-list">
            <?php foreach ($items as $item): ?>
              <a href="<?= adminEscape($basePath) ?>/admin-media.php?section=<?= $section ?>&amp;id=<?= (int) $item['id'] ?>"
                 aria-current="<?= $currentId !== '' && (int) $currentId === (int) $item['id'] ? 'true' : 'false' ?>">
                <strong><?= adminEscape((string) ($item['title'] ?? '') !== '' ? (string) $item['title'] : (string) ($item['video_url'] ?? '(제목 없음)')) ?></strong>
                <small>
                  <?php if ($section === 'videos'): ?>
                    <?= (int) $item['video_type'] === 1 ? 'Concert' : 'YouTube' ?> · <?= (int) $item['role'] === 1 ? 'Compositions' : 'Music Arranged' ?>
                  <?php else: ?>
                    <?= adminEscape(adminHymnalTypes($catalogType)[(int) $item['hymnal_type']] ?? '') ?><?= $item['hymn_number'] !== null ? ' · ' . (int) $item['hymn_number'] . '장' : '' ?>
                  <?php endif; ?>
                  · <?= (int) $item['is_active'] === 1 ? '진열 중' : '숨김' ?> · 순서 <?= (int) $item['sort_order'] ?>
                </small>
              </a>
            <?php endforeach; ?>
          </nav>
          <a class="admin-button secondary" href="<?= adminEscape($basePath) ?>/admin-media.php?section=<?= $section ?>&amp;new=1">새로 등록</a>
        </aside>

        <?php if ($showForm): ?>
          <section class="panel">
            <h2><?= $currentId === '' ? '새로 등록' : '수정' ?></h2>
            <form method="post" action="<?= adminEscape($basePath) ?>/admin-media.php?section=<?= $section ?>">
              <input type="hidden" name="csrf_token" value="<?= adminEscape(adminCsrfToken()) ?>">
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="section" value="<?= adminEscape($section) ?>">
              <input type="hidden" name="item_id" value="<?= adminEscape($currentId) ?>">
              <div class="form-grid">
                <?php if ($section === 'videos'): ?>
                  <div class="field"><label for="video_type">표시 페이지</label>
                    <select id="video_type" name="video_type">
                      <?php foreach ([1 => 'Concert', 2 => 'YouTube'] as $k => $l): ?>
                        <option value="<?= $k ?>" <?= (int) $form['video_type'] === $k ? 'selected' : '' ?>><?= $l ?></option>
                      <?php endforeach; ?>
                    </select></div>
                  <div class="field"><label for="role">영상 분류</label>
                    <select id="role" name="role">
                      <?php foreach ([1 => 'Compositions', 2 => 'Music Arranged'] as $k => $l): ?>
                        <option value="<?= $k ?>" <?= (int) $form['role'] === $k ? 'selected' : '' ?>><?= $l ?></option>
                      <?php endforeach; ?>
                    </select></div>
                  <div class="field full"><label for="video_title">영상 제목</label><input id="video_title" name="video_title" maxlength="255" value="<?= $value('video_title') ?>"></div>
                  <div class="field full"><label for="video_url">YouTube 영상 링크</label><input id="video_url" name="video_url" maxlength="500" required value="<?= $value('video_url') ?>"></div>
                <?php else: ?>
                  <div class="field"><label for="hymnal_type">목록 구분</label>
                    <select id="hymnal_type" name="hymnal_type">
                      <?php foreach (adminHymnalTypes($catalogType) as $k => $l): ?>
                        <option value="<?= $k ?>" <?= (int) $form['hymnal_type'] === $k ? 'selected' : '' ?>><?= adminEscape($l) ?></option>
                      <?php endforeach; ?>
                    </select></div>
                  <div class="field"><label for="hymn_number">번호 (선택)</label><input id="hymn_number" name="hymn_number" type="number" min="1" max="65535" value="<?= $value('hymn_number') ?>"></div>
                  <div class="field full"><label for="hymn_title">곡명</label><input id="hymn_title" name="hymn_title" maxlength="255" required value="<?= $value('hymn_title') ?>"></div>
                  <div class="field full"><label for="section_title">분류명 (선택)</label><input id="section_title" name="section_title" maxlength="160" value="<?= $value('section_title') ?>"></div>
                  <div class="field full"><label for="video_url">대표 영상 링크</label><input id="video_url" name="video_url" maxlength="500" value="<?= $value('video_url') ?>"></div>
                  <?php foreach (ADMIN_PART_COLUMNS as $column => $label): ?>
                    <div class="field"><label for="<?= $column ?>"><?= adminEscape($label) ?> 링크</label><input id="<?= $column ?>" name="<?= $column ?>" maxlength="500" value="<?= $value($column) ?>"></div>
                  <?php endforeach; ?>
                  <div class="field full"><label for="other_part_links">기타 파트 링크 (한 줄에 "파트명|링크")</label><textarea id="other_part_links" name="other_part_links"><?= adminEscape($otherLinksText) ?></textarea></div>
                  <div class="field full"><label for="source_url">자료 출처 페이지 (선택)</label><input id="source_url" name="source_url" maxlength="500" value="<?= $value('source_url') ?>"></div>
                <?php endif; ?>
                <div class="field"><label for="sort_order">표시 순서 (작을수록 먼저)</label><input id="sort_order" name="sort_order" type="number" min="0" required value="<?= $value('sort_order') ?>"></div>
                <div class="field"><span class="field-title">진열 상태</span>
                  <label class="check-row"><input type="checkbox" name="is_active" value="1" <?= (int) $form['is_active'] === 1 ? 'checked' : '' ?>> 사이트에 진열 (해제하면 숨김 처리)</label></div>
              </div>
              <div class="form-actions"><button class="admin-button" type="submit">저장</button></div>
            </form>
          </section>
        <?php else: ?>
          <section class="panel">
            <h2>관리할 <?= $section === 'videos' ? '영상을' : ($section === 'hymns' ? '찬송가를' : '찬양곡을') ?> 선택하세요</h2>
            <p class="muted">왼쪽 목록에서 항목을 고르거나 새로 등록할 수 있습니다. 저장한 내용은 해당 페이지에 바로 반영되며, 진열 상태를 해제하면 사이트에서 숨겨집니다.</p>
          </section>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
