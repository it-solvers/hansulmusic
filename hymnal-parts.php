<?php
declare(strict_types=1);

require_once __DIR__ . '/site-config.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function decodeAdditionalPartLinks(?string $value): array
{
    if ($value === null || $value === '') {
        return [];
    }

    $items = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($items)) {
        throw new UnexpectedValueException('Invalid additional hymnal part links.');
    }

    $links = [];
    foreach ($items as $item) {
        if (!is_array($item)
            || !isset($item['part'], $item['url'])
            || !is_string($item['part'])
            || !is_string($item['url'])
        ) {
            throw new UnexpectedValueException('Invalid additional hymnal part link.');
        }
        $links[$item['part']] = $item['url'];
    }

    return $links;
}

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

$basePath = appBasePath();
$hymns = [];
$loadError = false;
try {
    $statement = databaseConnection()->query(
        'SELECT hymnal_id, hymnal_type, hymn_number, hymn_title,
                video_url, soprano_url, alto_url, tenor_url, bass_url, chorus_url,
                piano_url, soprano_1_url, soprano_2_url, tenor_1_url, tenor_2_url,
                bass_1_url, bass_2_url, other_part_links
         FROM hymnal
         WHERE catalog_type = 1 AND is_active = 1
         ORDER BY hymnal_type ASC, sort_order ASC, hymn_number ASC, hymnal_id ASC'
    );
    $hymns = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException | RuntimeException $exception) {
    error_log('Hymnal catalog database error: ' . $exception->getMessage());
    http_response_code(503);
    $loadError = true;
}

$tabs = [
    1 => '찬송가',
    2 => '새찬송',
    3 => '영문찬송',
];
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hymnal | Hansul Music</title>
  <meta name="description" content="찬송가, 새찬송가, 영문찬송가의 파트별 연습 영상을 확인하세요.">
  <link rel="stylesheet" href="<?= escape($basePath) ?>/css/style.css?v=21">
  <link rel="stylesheet" href="<?= escape($basePath) ?>/css/register.css?v=6">
  <link rel="stylesheet" href="<?= escape($basePath) ?>/css/hymnal-parts.css?v=6">
</head>
<body>
  <header>
    <a class="logo" href="<?= escape($basePath) ?>/index.php">HANSUL MUSIC<small>HSM · MUSIC STUDIO</small></a>
    <button class="menu-toggle" type="button" aria-label="메뉴 열기" aria-expanded="false" aria-controls="site-nav">
      <span></span>
      <span></span>
      <span></span>
    </button>
    <nav class="site-nav" id="site-nav">
      <a href="<?= escape($basePath) ?>/concert.php">Concert</a>
      <a href="<?= escape($basePath) ?>/news.php">News</a>
      <a href="<?= escape($basePath) ?>/shop.php">Scores &amp; Recordings</a>
      <a href="<?= escape($basePath) ?>/index.php#contact">Commission</a>
      <a href="<?= escape($basePath) ?>/youtube.php">YouTube</a>
      <a href="<?= escape($basePath) ?>/hymnal-parts.php" aria-current="page">Hymnal (찬송가)</a>
      <a href="<?= escape($basePath) ?>/praise-song-parts.php">Praise Song (찬양곡)</a>
      <div class="nav-account">
        <button class="signup-nav-button" type="button" data-open-auth>Sign in</button>
        <button class="nav-member-identity" type="button" data-member-identity hidden disabled></button>
      </div>
    </nav>
  </header>

  <main class="hymnal-page">
    <div class="wrap">
      <section class="hymnal-heading">
        <div class="section-label">Hymnal Parts</div>
        <h1>찬송가 파트 연습</h1>
        <p>찬송가별 파트 연습 영상 링크를 확인하세요.</p>
      </section>

      <div class="hymnal-tabs" role="tablist" aria-label="찬송가 종류">
        <?php foreach ($tabs as $type => $label): ?>
          <button
            class="hymnal-tab"
            id="hymnal-tab-<?= $type ?>"
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
      <p class="hymnal-parts-legend">S 소프라노 · A 알토 · T 테너 · B 베이스 · C 합창</p>

      <section id="hymnal-panel" role="tabpanel" aria-labelledby="hymnal-tab-1">
        <?php if ($loadError): ?>
          <p class="hymnal-message" role="alert">찬송가 목록을 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.</p>
        <?php elseif ($hymns === []): ?>
          <p class="hymnal-message">등록된 찬송가 자료가 없습니다.</p>
        <?php else: ?>
          <div class="hymnal-tools">
            <label class="hymnal-search-label" for="hymnal-search">곡명 또는 장 번호 검색</label>
            <input id="hymnal-search" class="hymnal-search" type="search" placeholder="곡명 또는 장 번호">
            <div class="hymnal-sort" role="group" aria-label="목록 정렬">
              <span>정렬</span>
              <button type="button" data-hymnal-sort="number" data-number-label="장번호순" aria-pressed="true">장번호순</button>
              <button type="button" data-hymnal-sort="title" aria-pressed="false" aria-label="제목순 오름차순">제목순 ↑</button>
            </div>
          </div>
          <div class="hymnal-list" data-hymnal-list>
            <?php foreach ($hymns as $hymn): ?>
              <?php
                $type = (int) $hymn['hymnal_type'];
                $partLinks = [
                    'S' => $hymn['soprano_url'],
                    'A' => $hymn['alto_url'],
                    'T' => $hymn['tenor_url'],
                    'B' => $hymn['bass_url'],
                    'C' => $hymn['chorus_url'],
                    'P' => $hymn['piano_url'],
                    'S1' => $hymn['soprano_1_url'],
                    'S2' => $hymn['soprano_2_url'],
                    'T1' => $hymn['tenor_1_url'],
                    'T2' => $hymn['tenor_2_url'],
                    'B1' => $hymn['bass_1_url'],
                    'B2' => $hymn['bass_2_url'],
                ];
                $partLinks = array_merge(
                    $partLinks,
                    decodeAdditionalPartLinks(
                        is_string($hymn['other_part_links']) ? $hymn['other_part_links'] : null
                    )
                );
                $hasPartLinks = count(array_filter($partLinks)) > 0;
              ?>
              <article
                class="hymnal-card"
                data-hymnal-card
                data-type="<?= $type ?>"
                data-number="<?= escape((string) ($hymn['hymn_number'] ?? '')) ?>"
                data-title="<?= escape((string) $hymn['hymn_title']) ?>">
                <div class="hymnal-card-title">
                  <?php if ($hymn['hymn_number'] !== null): ?>
                    <span class="hymnal-number"><?= escape((string) $hymn['hymn_number']) ?>장</span>
                  <?php endif; ?>
                  <h2><?= escape((string) $hymn['hymn_title']) ?></h2>
                </div>
                <div class="hymnal-links" aria-label="연습 영상">
                  <?php if ($hasPartLinks): ?>
                    <?php foreach ($partLinks as $part => $url): ?>
                      <?php if (is_string($url) && $url !== ''): ?>
                        <a href="<?= escape($url) ?>" target="_blank" rel="noopener noreferrer" data-youtube-popup aria-label="<?= escape((string) $hymn['hymn_title']) ?> <?= escape($part) ?> 파트 영상 팝업에서 재생"><?= escape($part) ?></a>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php elseif (is_string($hymn['video_url']) && $hymn['video_url'] !== ''): ?>
                    <a class="hymnal-video-link" href="<?= escape((string) $hymn['video_url']) ?>" target="_blank" rel="noopener noreferrer" data-youtube-popup aria-label="<?= escape((string) $hymn['hymn_title']) ?> 영상 팝업에서 재생">영상 보기 ↗</a>
                  <?php else: ?>
                    <span class="hymnal-no-link">영상 준비 중</span>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
          <p class="hymnal-no-results" data-hymnal-empty hidden>검색 결과가 없습니다.</p>
          <nav class="hymnal-pagination" aria-label="찬송가 목록 페이지">
            <button type="button" data-hymnal-previous disabled>이전</button>
            <span data-hymnal-page aria-live="polite"></span>
            <button type="button" data-hymnal-next>다음</button>
          </nav>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <?php require __DIR__ . '/site-footer.php'; ?>

  <dialog class="concert-player" aria-label="찬송가 영상 플레이어">
    <div class="concert-player-content">
      <button class="concert-player-close" type="button" aria-label="영상 닫기">&times;</button>
      <div class="concert-player-frame"></div>
    </div>
  </dialog>

  <?php require __DIR__ . '/auth-modal.php'; ?>
  <script src="<?= escape($basePath) ?>/js/signup-modal.js?v=11" defer></script>
  <script src="<?= escape($basePath) ?>/js/navigation.js?v=2" defer></script>
  <script src="<?= escape($basePath) ?>/js/concert.js?v=6" defer></script>
  <script src="<?= escape($basePath) ?>/js/hymnal-parts.js?v=5" defer></script>
</body>
</html>
