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
$songs = [];
$loadError = false;
try {
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
foreach ($songs as $song) {
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
  require __DIR__ . '/site-header.php';
  ?>

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
            <?php foreach ($songs as $song): ?>
              <?php
                $type = (int) $song['hymnal_type'];
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

  <?php require __DIR__ . '/site-footer.php'; ?>

  <dialog class="concert-player" aria-label="찬양곡 영상 플레이어">
    <div class="concert-player-content">
      <button class="concert-player-close" type="button" aria-label="영상 닫기">&times;</button>
      <div class="concert-player-frame"></div>
    </div>
  </dialog>

  <?php require __DIR__ . '/auth-modal.php'; ?>
  <script src="<?= escape($basePath) ?>/js/signup-modal.js?v=13" defer></script>
  <script src="<?= escape($basePath) ?>/js/navigation.js?v=2" defer></script>
  <script src="<?= escape($basePath) ?>/js/concert.js?v=6" defer></script>
  <script src="<?= escape($basePath) ?>/js/hymnal-parts.js?v=7" defer></script>
</body>
</html>
