<?php
declare(strict_types=1);

// [1단계] YouTube 페이지 공통 설정과 영상 조회에 필요한 기능을 준비합니다.
require_once __DIR__ . '/site-config.php';

// [2단계] 동적 텍스트를 HTML 출력에 안전한 문자열로 변환합니다.
/** 영상 페이지에 출력할 문자열을 HTML에 안전하게 이스케이프합니다. */
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// [2단계] DB 접속 설정을 검증하고 PDO 연결을 구성합니다.
/** 필수 DB 환경 변수를 검증하고 영상 목록 조회용 PDO 연결을 반환합니다. */
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

// [2단계] 지원하는 YouTube 링크에서 영상 ID를 추출합니다.
/** 허용된 HTTPS YouTube 주소에서 유효한 영상 ID를 추출하고, 아니면 null을 반환합니다. */
function youtubeVideoId(string $url): ?string
{
    $parts = parse_url($url);
    // [3단계] HTTPS 및 허용 호스트 조건을 만족하지 않는 URL은 제외합니다.
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

    // [3단계] 추출된 ID가 YouTube의 영상 ID 문자·길이 규칙에 맞는지 검증합니다.
    return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1 ? $videoId : null;
}

// [1단계] 활성 영상을 역할별 배열로 구성하고 잘못된 자료를 걸러냅니다.
$videosByRole = [1 => [], 2 => []];
$loadError = false;
$basePath = '';
try {
    $basePath = appBasePath();
    $pdo = databaseConnection();
    // [3단계] videos에서 활성 YouTube 페이지 영상 두 역할을 조회해 역할별 카드 배열로 나눕니다.
    $statement = $pdo->prepare(
        'SELECT role, video_title, video_url
         FROM videos
         WHERE video_type = :video_type AND is_active = 1
         ORDER BY role ASC, sort_order ASC, video_id ASC'
    );
    $statement->execute(['video_type' => 2]);

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $videoId = youtubeVideoId((string) $row['video_url']);
        if ($videoId === null) {
            // [3단계] 유효하지 않은 영상 주소는 건너뛰되 목록 오류 상태를 남깁니다.
            error_log('YouTube video row contains an invalid YouTube URL.');
            $loadError = true;
            continue;
        }
        $role = (int) $row['role'];
        if (!isset($videosByRole[$role])) {
            // [3단계] 화면에서 지원하지 않는 역할 값은 목록에 넣지 않습니다.
            error_log('YouTube video row contains an unsupported role.');
            $loadError = true;
            continue;
        }
        $videosByRole[$role][] = [
            'id' => $videoId,
            'title' => trim((string) ($row['video_title'] ?? '')),
            'url' => 'https://www.youtube.com/watch?v=' . rawurlencode($videoId),
        ];
    }
} catch (PDOException | RuntimeException $exception) {
    error_log('YouTube videos database error: ' . $exception->getMessage());
    http_response_code(503);
    $loadError = true;
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>YouTube videos | Hansul Music</title>
  <meta name="description" content="한설뮤직의 YouTube 영상 모음">
  <link rel="stylesheet" href="<?= escape($basePath) ?>/css/style.css?v=34">
  <link rel="stylesheet" href="<?= escape($basePath) ?>/css/register.css?v=6">
</head>
<body class="site-nav-page">
  <?php
  $headerActivePage = 'youtube';
  $headerIsEnglish = ($_GET['lang'] ?? '') === 'en';
  // [3단계] lang 쿼리 값에 따라 언어별 공통 탐색 메뉴를 불러옵니다.
  require __DIR__ . ($headerIsEnglish ? '/en/site-header.php' : '/site-header-ko.php');
  ?>

  <!-- [1단계] 영상 역할 탭과 선택된 역할별 영상 목록을 렌더링합니다. -->
  <main class="concert-page">
    <div class="wrap">
      <section class="concert-heading">
        <div class="section-label">YouTube</div>
        <h1>YouTube videos</h1>
        <p>한설뮤직의 YouTube 영상을 감상해 보세요.</p>
      </section>

      <?php if ($loadError): ?>
        <p class="concert-error" role="alert">영상 목록을 불러오는 중 문제가 발생했습니다. 관리자에게 문의해 주세요.</p>
      <?php endif; ?>

      <div class="video-role-tabs" role="tablist" aria-label="영상 분류" data-video-role-tabs>
        <?php foreach ([1 => 'Compositions', 2 => 'Music Arranged'] as $role => $roleTitle): ?>
          <button
            class="video-role-tab"
            id="video-role-tab-<?= $role ?>"
            type="button"
            role="tab"
            aria-controls="video-role-panel-<?= $role ?>"
            aria-selected="<?= $role === 1 ? 'true' : 'false' ?>"
            tabindex="<?= $role === 1 ? '0' : '-1' ?>"
            data-video-role-tab>
            <?= escape($roleTitle) ?>
          </button>
        <?php endforeach; ?>
      </div>
      <!-- [2단계] 역할별 패널은 탭 스크립트가 전환하며 비활성 패널은 숨깁니다. -->
      <?php foreach ([1 => 'Compositions', 2 => 'Music Arranged'] as $role => $roleTitle): ?>
        <section
          class="video-role-panel"
          id="video-role-panel-<?= $role ?>"
          role="tabpanel"
          aria-labelledby="video-role-tab-<?= $role ?>"
          data-video-role-panel
          <?= $role === 1 ? '' : 'hidden' ?>>
          <?php if ($videosByRole[$role] === []): ?>
            <p class="concert-empty">이 분류에 등록된 YouTube 영상이 없습니다.</p>
          <?php else: ?>
            <div class="concert-grid" aria-label="<?= escape($roleTitle) ?>">
              <!-- [3단계] 역할별 조회 배열을 현재 탭의 영상 카드와 YouTube 링크로 출력합니다. -->
              <?php foreach ($videosByRole[$role] as $index => $video): ?>
                <?php $videoTitle = $video['title'] !== '' ? $video['title'] : $roleTitle . ' Video ' . ($index + 1); ?>
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
                    <h3><?= escape($videoTitle) ?></h3>
                    <a href="<?= escape($video['url']) ?>" target="_blank" rel="noopener noreferrer">YouTube에서 보기 ↗</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    </div>
  </main>

  <?php
  $footerLanguage = $headerIsEnglish ? 'en' : 'ko';
  require __DIR__ . '/site-footer.php';
  ?>

  <dialog class="concert-player" aria-label="YouTube 영상 플레이어">
    <div class="concert-player-content">
      <button class="concert-player-close" type="button" aria-label="영상 닫기">&times;</button>
      <div class="concert-player-frame"></div>
    </div>
  </dialog>

  <?php require __DIR__ . '/auth-modal.php'; ?>
  <script src="<?= escape($basePath) ?>/js/signup-modal.js?v=13" defer></script>
  <script src="<?= escape($basePath) ?>/js/navigation.js?v=3" defer></script>
  <script src="<?= escape($basePath) ?>/js/concert.js?v=6" defer></script>
  <script src="<?= escape($basePath) ?>/js/youtube-tabs.js?v=1" defer></script>
</body>
</html>
