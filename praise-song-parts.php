<?php
declare(strict_types=1);

// [1단계] 찬양곡 파트 연습 페이지의 공통 설정과 카탈로그 처리를 준비합니다.
require_once __DIR__ . '/site-config.php';

// [2단계] 화면에 출력할 문자열을 HTML 문맥에 맞게 이스케이프합니다.
/** 찬양곡 화면에 출력할 문자열의 HTML 특수 문자를 이스케이프합니다. */
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// [2단계] 추가 파트 링크 JSON을 화면용 파트-주소 배열로 변환합니다.
/** 추가 파트 링크 JSON을 파트명을 키로 하는 URL 배열로 변환합니다. */
function decodeAdditionalPartLinks(?string $value): array
{
    // [3단계] 추가 링크 값이 비어 있으면 빈 배열로 처리합니다.
    if ($value === null || $value === '') {
        return [];
    }

    // [3단계] 각 항목에 문자열 파트명과 URL이 있는지 확인합니다.
    $items = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($items)) {
        throw new UnexpectedValueException('Invalid additional praise-song part links.');
    }

    $links = [];
    foreach ($items as $item) {
        if (!is_array($item)
            || !isset($item['part'], $item['url'])
            || !is_string($item['part'])
            || !is_string($item['url'])
        ) {
            throw new UnexpectedValueException('Invalid additional praise-song part link.');
        }
        $links[$item['part']] = $item['url'];
    }

    return $links;
}

// [2단계] DB 접속 정보를 확인하고 예외 기반 PDO 연결을 구성합니다.
/** 필수 DB 환경 변수를 검증하고 찬양곡 카탈로그용 PDO 연결을 반환합니다. */
function databaseConnection(): PDO
{
    $user = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');
    if ($user === false || $user === '' || $password === false) {
        throw new RuntimeException('DB_USER and DB_PASSWORD must be configured.');
    }

    $host = getenv('DB_HOST') ?: 'db';
    $database = getenv('DB_NAME') ?: 'hansul_db';
    return new PDO(
        "mysql:host={$host};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

// [1단계] 활성 찬양곡을 조회해 탭별 목록과 절기 선택 데이터를 구성합니다.
$basePath = appBasePath();
$songs = [];
$loadError = false;
try {
    // [3단계] hymnal에서 활성 찬양곡(catalog_type 2)을 분류·순서대로 가져와 $songs에 저장합니다.
    // 아래 목록 반복문은 제목·절기·파트별 영상 링크를 카드 데이터와 화면에 연결합니다.
    $statement = databaseConnection()->query(
        'SELECT hymnal_id, hymnal_type, hymn_title, section_title,
                video_url, soprano_url, alto_url, tenor_url, bass_url, chorus_url,
                piano_url, soprano_1_url, soprano_2_url, tenor_1_url, tenor_2_url,
                bass_1_url, bass_2_url, other_part_links
         FROM hymnal
         WHERE catalog_type = 2 AND is_active = 1
         ORDER BY hymnal_type ASC, sort_order ASC, hymnal_id ASC'
    );
    $songs = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException | RuntimeException $exception) {
    // [3단계] 카탈로그 조회에 실패하면 503 상태와 화면 오류 플래그를 설정합니다.
    error_log('Praise song catalog database error: ' . $exception->getMessage());
    http_response_code(503);
    $loadError = true;
}

$tabs = [
    1 => '절기별',
    2 => '가나다순',
    3 => '알파벳순',
];
$seasons = [];
$seasonOrder = [];
// [2단계] 절기형 자료의 고유 분류와 원래 순서를 기록한 뒤 표시용으로 정렬합니다.
foreach ($songs as $song) {
    // [3단계] 절기형 탭에 속하고 분류명이 있는 자료만 절기 필터에 포함합니다.
    if ((int) $song['hymnal_type'] === 1
        && is_string($song['section_title'])
        && $song['section_title'] !== ''
    ) {
        $seasons[$song['section_title']] = $song['section_title'];
        if (!isset($seasonOrder[$song['section_title']])) {
            $seasonOrder[$song['section_title']] = count($seasonOrder);
        }
    }
}
natcasesort($seasons);
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Praise Songs | Hansul Music</title>
  <meta name="description" content="절기별·한글·영문 목록에서 찬양곡과 파트 연습 영상을 찾아보세요.">
  <link rel="stylesheet" href="<?= escape($basePath) ?>/css/style.css?v=34">
  <link rel="stylesheet" href="<?= escape($basePath) ?>/css/register.css?v=6">
  <link rel="stylesheet" href="<?= escape($basePath) ?>/css/hymnal-parts.css?v=6">
</head>
<body class="site-nav-page">
  <?php
  $headerActivePage = 'praise';
  $headerIsEnglish = ($_GET['lang'] ?? '') === 'en';
  // [3단계] lang 쿼리 값에 따라 언어별 공통 탐색 메뉴를 선택합니다.
  require __DIR__ . ($headerIsEnglish ? '/en/site-header.php' : '/site-header-ko.php');
  ?>

  <!-- [1단계] 절기·가나다·알파벳 탭과 검색·정렬·페이지 이동 영역을 렌더링합니다. -->
  <main class="hymnal-page">
    <div class="wrap">
      <section class="hymnal-heading">
        <div class="section-label">Praise Song Parts</div>
        <h1>찬양곡 파트 연습</h1>
        <p>찬양곡 제목을 검색하고 파트별 연습 영상을 확인하세요.</p>
      </section>

      <div class="hymnal-tabs" role="tablist" aria-label="찬양곡 목록 종류">
        <?php foreach ($tabs as $type => $label): ?>
          <button
            class="hymnal-tab"
            id="praise-tab-<?= $type ?>"
            type="button"
            role="tab"
            aria-controls="hymnal-panel"
            aria-selected="<?= $type === 1 ? 'true' : 'false' ?>"
            tabindex="<?= $type === 1 ? '0' : '-1' ?>"
            data-hymnal-tab
            data-label="<?= escape($label) ?>"
            data-type="<?= $type ?>"><?= escape($label) ?></button>
        <?php endforeach; ?>
      </div>
      <p class="hymnal-parts-legend">S 소프라노 · A 알토 · T 테너 · B 베이스 · C 합창 · P 피아노</p>

      <section id="hymnal-panel" role="tabpanel" aria-labelledby="praise-tab-1" data-seasonal-title-sort>
        <?php if ($loadError): ?>
          <p class="hymnal-message" role="alert">찬양곡 목록을 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.</p>
        <?php elseif ($songs === []): ?>
          <p class="hymnal-message">등록된 찬양곡 자료가 없습니다.</p>
        <?php else: ?>
          <div class="hymnal-tools">
            <div class="hymnal-season-filter" data-hymnal-season-filter>
              <label for="hymnal-season">절기</label>
              <select id="hymnal-season" class="hymnal-season-select" data-hymnal-season>
                <option value="">전체 절기</option>
                <?php foreach ($seasons as $season): ?>
                  <option value="<?= escape($season) ?>"><?= escape($season) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <label class="hymnal-search-label" for="hymnal-search">곡명 또는 작곡가 검색</label>
            <input id="hymnal-search" class="hymnal-search" type="search" placeholder="곡명 또는 작곡가">
            <div class="hymnal-sort" role="group" aria-label="목록 정렬">
              <span>정렬</span>
              <button type="button" data-hymnal-sort="number" data-number-label="절기별 가나다순" aria-pressed="true">절기별 가나다순</button>
              <button type="button" data-hymnal-sort="title" aria-pressed="false" aria-label="제목순 오름차순">제목순 ↑</button>
            </div>
          </div>
          <div class="hymnal-list" data-hymnal-list>
            <!-- [3단계] $songs의 각 DB 행을 곡 카드의 제목·절기·파트별 링크로 출력합니다. -->
            <?php foreach ($songs as $song): ?>
              <?php
                $type = (int) $song['hymnal_type'];
                // [2단계] 기본 파트 주소에 등록된 추가 링크를 합쳐 곡별 영상 목록을 만듭니다.
                $partLinks = [
                    'S' => $song['soprano_url'],
                    'A' => $song['alto_url'],
                    'T' => $song['tenor_url'],
                    'B' => $song['bass_url'],
                    'C' => $song['chorus_url'],
                    'P' => $song['piano_url'],
                    'S1' => $song['soprano_1_url'],
                    'S2' => $song['soprano_2_url'],
                    'T1' => $song['tenor_1_url'],
                    'T2' => $song['tenor_2_url'],
                    'B1' => $song['bass_1_url'],
                    'B2' => $song['bass_2_url'],
                ];
                $partLinks = array_merge(
                    $partLinks,
                    decodeAdditionalPartLinks(
                        is_string($song['other_part_links']) ? $song['other_part_links'] : null
                    )
                );
                $hasPartLinks = count(array_filter($partLinks)) > 0;
              ?>
              <article
                class="hymnal-card"
                data-hymnal-card
                data-type="<?= $type ?>"
                data-number=""
                data-title="<?= escape((string) $song['hymn_title']) ?>"
                data-section="<?= escape((string) ($song['section_title'] ?? '')) ?>"
                data-section-order="<?= escape((string) ($seasonOrder[$song['section_title'] ?? ''] ?? '')) ?>">
                <div class="hymnal-card-title">
                  <?php if (is_string($song['section_title']) && $song['section_title'] !== ''): ?>
                    <span class="hymnal-section-badge"><?= escape($song['section_title']) ?></span>
                  <?php endif; ?>
                  <h2><?= escape((string) $song['hymn_title']) ?></h2>
                </div>
                <div class="hymnal-links" aria-label="연습 영상">
                  <!-- [3단계] 파트별 링크를 우선 제공하고, 없으면 공통 영상 또는 준비 중 안내를 표시합니다. -->
                  <?php if ($hasPartLinks): ?>
                    <?php foreach ($partLinks as $part => $url): ?>
                      <?php if (is_string($url) && $url !== ''): ?>
                        <a href="<?= escape($url) ?>" target="_blank" rel="noopener noreferrer" data-youtube-popup aria-label="<?= escape((string) $song['hymn_title']) ?> <?= escape($part) ?> 파트 영상 팝업에서 재생"><?= escape($part) ?></a>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php elseif (is_string($song['video_url']) && $song['video_url'] !== ''): ?>
                    <a class="hymnal-video-link" href="<?= escape((string) $song['video_url']) ?>" target="_blank" rel="noopener noreferrer" data-youtube-popup aria-label="<?= escape((string) $song['hymn_title']) ?> 영상 팝업에서 재생">영상 보기 ↗</a>
                  <?php else: ?>
                    <span class="hymnal-no-link">영상 준비 중</span>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
          <p class="hymnal-no-results" data-hymnal-empty hidden>검색 결과가 없습니다.</p>
          <nav class="hymnal-pagination" aria-label="찬양곡 목록 페이지">
            <button type="button" data-hymnal-previous disabled>이전</button>
            <span data-hymnal-page aria-live="polite"></span>
            <button type="button" data-hymnal-next>다음</button>
          </nav>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <?php
  $footerLanguage = $headerIsEnglish ? 'en' : 'ko';
  require __DIR__ . '/site-footer.php';
  ?>

  <dialog class="concert-player" aria-label="찬양곡 영상 플레이어">
    <div class="concert-player-content">
      <button class="concert-player-close" type="button" aria-label="영상 닫기">&times;</button>
      <div class="concert-player-frame"></div>
    </div>
  </dialog>

  <?php require __DIR__ . '/auth-modal.php'; ?>
  <script src="<?= escape($basePath) ?>/js/signup-modal.js?v=13" defer></script>
  <script src="<?= escape($basePath) ?>/js/navigation.js?v=3" defer></script>
  <script src="<?= escape($basePath) ?>/js/concert.js?v=6" defer></script>
  <script src="<?= escape($basePath) ?>/js/hymnal-parts.js?v=7" defer></script>
</body>
</html>
