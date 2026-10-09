<?php
declare(strict_types=1);

// [1단계] 공연 영상 페이지의 공통 설정과 조회 데이터를 준비합니다.
require_once __DIR__ . '/site-config.php';

// [2단계] 페이지 출력용 문자열을 HTML 문맥에 맞게 이스케이프합니다.
/** 공연 페이지에 출력할 문자열을 HTML에 안전하게 이스케이프합니다. */
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// [2단계] DB 접속 환경을 확인하고 예외 기반 PDO 연결을 생성합니다.
/** 필수 DB 환경 변수를 검증하고 공연 영상 조회용 PDO 연결을 반환합니다. */
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

// [2단계] 지원하는 YouTube 주소에서 안전하게 영상 ID를 추출합니다.
/** 허용된 HTTPS YouTube 주소에서 유효한 영상 ID를 추출하고, 아니면 null을 반환합니다. */
function youtubeVideoId(string $url): ?string
{
    $parts = parse_url($url);
    // [3단계] HTTPS가 아니거나 호스트가 없는 주소는 영상 주소로 취급하지 않습니다.
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

    // [3단계] 각 URL 형식에서 추출한 값의 11자 영상 ID 형식을 확인합니다.
    return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1 ? $videoId : null;
}

// [1단계] 활성 공연 영상 목록을 검증·중복 제거하여 페이지 표시 데이터로 만듭니다.
$videos = [];
$seenVideoIds = [];
$loadError = false;
$basePath = '';
try {
    $basePath = appBasePath();
    $pdo = databaseConnection();
    // [3단계] videos에서 활성 공연(role 1) 행만 정렬해 가져오고, 아래 목록용으로 ID·제목을 가공합니다.
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
            // [3단계] 잘못 등록된 주소는 목록에서 제외하고 관리자 확인용 로그를 남깁니다.
            error_log('Concert video row contains an invalid YouTube URL.');
            $loadError = true;
            continue;
        }
        if (isset($seenVideoIds[$videoId])) {
            // [3단계] 같은 영상 ID는 한 번만 노출합니다.
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
  $headerIsEnglish = ($_GET['lang'] ?? '') === 'en';
  // [3단계] URL의 언어 선택에 따라 공통 탐색 메뉴를 선택합니다.
  require __DIR__ . ($headerIsEnglish ? '/en/site-header.php' : '/site-header-ko.php');
  ?>

  <!-- [1단계] 공연 영상 목록과 조회 상태별 안내를 렌더링합니다. -->
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

      <!-- [2단계] 조회 결과가 있으면 카드 목록을, 없으면 빈 상태 안내를 표시합니다. -->
      <?php if ($videos === [] && !$loadError): ?>
        <p class="concert-empty">등록된 공연 영상이 없습니다.</p>
      <?php else: ?>
        <section class="concert-grid" aria-label="공연 영상">
          <!-- [3단계] 조회된 각 영상은 재생 가능한 카드와 외부 YouTube 링크로 표시합니다. -->
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

  <?php
  $footerLanguage = $headerIsEnglish ? 'en' : 'ko';
  require __DIR__ . '/site-footer.php';
  ?>

  <dialog class="concert-player" aria-label="공연 영상 플레이어">
    <div class="concert-player-content">
      <button class="concert-player-close" type="button" aria-label="영상 닫기">&times;</button>
      <div class="concert-player-frame"></div>
    </div>
  </dialog>

  <?php require __DIR__ . '/auth-modal.php'; ?>
  <script src="<?= escape($basePath) ?>/js/signup-modal.js?v=13" defer></script>
  <script src="<?= escape($basePath) ?>/js/navigation.js?v=3" defer></script>
  <script src="<?= escape($basePath) ?>/js/concert.js?v=6" defer></script>
</body>
</html>
