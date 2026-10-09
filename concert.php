<?php
declare(strict_types=1);

require_once __DIR__ . '/site-config.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

function youtubeVideoId(string $url): ?string
{
    $parts = parse_url($url);
    if (
        $parts === false
        || strtolower($parts['scheme'] ?? '') !== 'https'
        || !isset($parts['host'])
    ) {
        return null;
    }

    $host = strtolower($parts['host']);
    $path = trim($parts['path'] ?? '', '/');
    if ($host === 'youtu.be') {
        $videoId = $path;
    } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
        if ($path === 'watch') {
            parse_str($parts['query'] ?? '', $query);
            $videoId = is_string($query['v'] ?? null) ? $query['v'] : '';
        } elseif (preg_match('~^(?:embed|shorts|live)/([^/]+)$~', $path, $matches) === 1) {
            $videoId = $matches[1];
        } else {
            return null;
        }
    } else {
        return null;
    }

    return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1 ? $videoId : null;
}

$videos = [];
$seenVideoIds = [];
$loadError = false;
$basePath = '';
try {
    $basePath = appBasePath();
    $pdo = databaseConnection();
    $statement = $pdo->prepare(
        'SELECT video_title, video_url
         FROM videos
         WHERE video_type = :video_type AND role = :role AND is_active = 1
         ORDER BY sort_order ASC, video_id ASC'
    );
    $statement->execute(['video_type' => 1, 'role' => 1]);

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $videoId = youtubeVideoId((string) $row['video_url']);
        if ($videoId === null) {
            error_log('Concert video row contains an invalid YouTube URL.');
            $loadError = true;
            continue;
        }
        if (isset($seenVideoIds[$videoId])) {
            continue;
        }
        $seenVideoIds[$videoId] = true;
        $videos[] = [
            'id' => $videoId,
            'title' => trim((string) ($row['video_title'] ?? '')),
            'url' => 'https://www.youtube.com/watch?v=' . rawurlencode($videoId),
        ];
    }
} catch (PDOException | RuntimeException $exception) {
    error_log('Concert videos database error: ' . $exception->getMessage());
    http_response_code(503);
    $loadError = true;
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Concert videos by Dr. Sangeun Han | Hansul Music</title>
  <meta name="description" content="The compositions and concert videos of Dr. Sangeun Han.">
  <link rel="stylesheet" href="<?= escape($basePath) ?>/css/style.css?v=34">
  <link rel="stylesheet" href="<?= escape($basePath) ?>/css/register.css?v=6">
</head>
<body class="site-nav-page">
  <?php
  $headerActivePage = 'concert';
  require __DIR__ . '/site-header.php';
  ?>

  <main class="concert-page">
    <div class="wrap">
      <section class="concert-heading">
        <div class="section-label">Live Performance</div>
        <h1>Concert videos by Dr. Sangeun Han</h1>
        <p>한설뮤직의 공연 영상을 감상해 보세요.</p>
      </section>

      <?php if ($loadError): ?>
        <p class="concert-error" role="alert">영상 목록을 불러오는 중 문제가 발생했습니다. 관리자에게 문의해 주세요.</p>
      <?php endif; ?>

      <?php if ($videos === [] && !$loadError): ?>
        <p class="concert-empty">등록된 공연 영상이 없습니다.</p>
      <?php else: ?>
        <section class="concert-grid" aria-label="공연 영상">
          <?php foreach ($videos as $index => $video): ?>
            <?php $videoTitle = $video['title'] !== '' ? $video['title'] : 'Concert Video ' . ($index + 1); ?>
            <article class="concert-video">
              <button
                class="concert-video-frame"
                type="button"
                data-video-id="<?= escape($video['id']) ?>"
                data-video-title="<?= escape($videoTitle) ?>"
                aria-label="<?= escape($videoTitle) ?> 모달에서 재생">
                <img
                  src="https://i.ytimg.com/vi/<?= escape($video['id']) ?>/hqdefault.jpg"
                  alt=""
                  loading="lazy"
                  decoding="async">
                <span class="concert-play-icon" aria-hidden="true"></span>
              </button>
              <div class="concert-video-caption">
                <h2><?= escape($videoTitle) ?></h2>
                <a href="<?= escape($video['url']) ?>" target="_blank" rel="noopener noreferrer">YouTube에서 보기 ↗</a>
              </div>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    </div>
  </main>

  <?php require __DIR__ . '/site-footer.php'; ?>

  <dialog class="concert-player" aria-label="공연 영상 플레이어">
    <div class="concert-player-content">
      <button class="concert-player-close" type="button" aria-label="영상 닫기">&times;</button>
      <div class="concert-player-frame"></div>
    </div>
  </dialog>

  <?php require __DIR__ . '/auth-modal.php'; ?>
  <script src="<?= escape($basePath) ?>/js/signup-modal.js?v=13" defer></script>
  <script src="<?= escape($basePath) ?>/js/navigation.js" defer></script>
  <script src="<?= escape($basePath) ?>/js/concert.js?v=6" defer></script>
</body>
</html>
