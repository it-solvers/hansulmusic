<?php
declare(strict_types=1);

// [1단계] 홈페이지에 필요한 공통 설정과 동적 콘텐츠를 준비합니다.
require_once __DIR__ . '/site-config.php';

// [2단계] HTML 출력에 사용할 문자열을 안전하게 이스케이프합니다.
/** 홈페이지 콘텐츠를 HTML에 안전하게 출력하도록 이스케이프합니다. */
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// [2단계] 홈페이지 영상과 상품 조회에 사용할 DB 연결을 생성합니다.
/** 홈페이지 영상·상품 조회에 사용할 PDO 연결을 반환합니다. */
function homeDatabaseConnection(): PDO
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

// [2단계] 지원하는 YouTube 주소에서 영상 ID를 추출합니다.
/** 허용된 HTTPS YouTube 주소에서 유효한 영상 ID를 추출하고, 아니면 null을 반환합니다. */
function homeYouTubeVideoId(string $url): ?string
{
    $parts = parse_url($url);
    // [3단계] HTTPS 주소와 호스트가 확인되지 않으면 허용하지 않습니다.
    if ($parts === false
        || strtolower($parts['scheme'] ?? '') !== 'https'
        || !isset($parts['host'])) {
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

    // [3단계] 추출 결과가 YouTube 영상 ID 형식과 일치하는지 검증합니다.
    return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1 ? $videoId : null;
}

// [1단계] 홈페이지에 표시할 영상·상품 데이터를 조회하고 표시용으로 가공합니다.
$basePath = escape(appBasePath());
$homeVideo = null;
$homeVideoError = false;
$homeConcertVideos = [];
$homeConcertError = false;
$homeScoreProducts = [];
$homeAudioProducts = [];
$homeProductsError = false;
try {
    // [2단계] 유효한 작곡 영상 하나와 중복 없는 공연 영상 목록을 구성합니다.
    $pdo = homeDatabaseConnection();
    // [3단계] videos에서 활성 작곡 영상을 읽고, 아래 대표 영상 영역에 쓸 ID·제목만 보관합니다.
    $statement = $pdo->query(
        'SELECT video_title, video_url
         FROM videos
         WHERE video_type = 2 AND role = 1 AND is_active = 1'
    );
    $compositionVideos = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $videoId = homeYouTubeVideoId((string) $row['video_url']);
        if ($videoId === null) {
            // [3단계] 잘못된 영상 주소는 제외하고 서버 로그에 기록합니다.
            error_log('Homepage composition video row contains an invalid YouTube URL.');
            continue;
        }
        $compositionVideos[] = [
            'id' => $videoId,
            'title' => trim((string) ($row['video_title'] ?? '')),
        ];
    }

    if ($compositionVideos !== []) {
        $homeVideo = $compositionVideos[random_int(0, count($compositionVideos) - 1)];
    }

    // [2단계] videos에서 활성 공연 영상을 조회해 중복 제거 후 무작위로 최대 세 개만 노출합니다.
    // [3단계] 가공한 $homeConcertVideos는 아래 Concert 카드 반복문에서 제목과 썸네일로 표시합니다.
    $statement = $pdo->query(
        'SELECT video_title, video_url
         FROM videos
         WHERE video_type = 1 AND role = 1 AND is_active = 1'
    );
    $concertVideoIds = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $videoId = homeYouTubeVideoId((string) $row['video_url']);
        if ($videoId === null) {
            error_log('Homepage concert video row contains an invalid YouTube URL.');
            continue;
        }
        // [3단계] 같은 영상이 여러 번 등록된 경우 첫 항목만 유지합니다.
        if (isset($concertVideoIds[$videoId])) {
            continue;
        }
        $concertVideoIds[$videoId] = true;
        $homeConcertVideos[] = [
            'id' => $videoId,
            'title' => trim((string) ($row['video_title'] ?? '')),
        ];
    }
    shuffle($homeConcertVideos);
    $homeConcertVideos = array_slice($homeConcertVideos, 0, 3);
} catch (PDOException | RuntimeException $exception) {
    error_log('Homepage composition video database error: ' . $exception->getMessage());
    $homeVideoError = true;
    $homeConcertError = true;
}

// [2단계] 활성 상품을 종류별로 나누고, 노출할 이미지와 목록을 정리합니다.
try {
    // [3단계] products에서 활성 악보·음원과 가격을, product_assets에서 첫 악보 미리보기 경로를 가져옵니다.
    // 조회 결과는 아래 상품 소개 영역에서 $homeScoreProducts와 $homeAudioProducts 카드로 출력합니다.
    $productRows = homeDatabaseConnection()->query(
        'SELECT product_id, product_type, name, subtitle, regular_price_krw, sale_price_krw,
                cover_path,
                (SELECT asset_path
                 FROM product_assets
                 WHERE product_id = products.product_id AND asset_type = \'preview_image\'
                 ORDER BY sort_order ASC, asset_id ASC
                 LIMIT 1) AS preview_image
         FROM products
         WHERE is_active = 1 AND product_type IN (1, 2)'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($productRows as $row) {
        $imagePath = $row['preview_image'] ?: $row['cover_path'];
        // [3단계] 미리보기와 표지 모두 사용할 수 없는 경로는 이미지 없이 표시합니다.
        if ($imagePath !== null
            && (!is_string($imagePath) || !is_file(__DIR__ . '/' . ltrim($imagePath, '/')))) {
            $imagePath = null;
        }

        $product = [
            'id' => (int) $row['product_id'],
            'name' => (string) $row['name'],
            'subtitle' => (string) $row['subtitle'],
            'regular_price' => (int) $row['regular_price_krw'],
            'sale_price' => (int) $row['sale_price_krw'],
            'image' => $imagePath,
        ];
        if ((int) $row['product_type'] === 1) {
            $homeScoreProducts[] = $product;
        } else {
            $homeAudioProducts[] = $product;
        }
    }

    shuffle($homeScoreProducts);
    shuffle($homeAudioProducts);
    $homeScoreProducts = array_slice($homeScoreProducts, 0, 3);
    $homeAudioProducts = array_slice($homeAudioProducts, 0, 3);
} catch (PDOException | RuntimeException $exception) {
    error_log('Homepage shop products database error: ' . $exception->getMessage());
    $homeProductsError = true;
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Composition &amp; Arrangement | 작곡·편곡·관현악편곡 | HANSUL MUSIC</title>
<meta name="description" content="한설뮤직은 작곡가 한상은의 작곡, 관현악편곡, 합창·기악 편곡, 악보·음원 제작 및 작곡 레슨을 제공합니다.">
<link rel="canonical" href="https://www.hansulmusic.com/">
<link rel="alternate" hreflang="ko" href="https://www.hansulmusic.com/">
<link rel="alternate" hreflang="en" href="https://www.hansulmusic.com/en/">
<link rel="alternate" hreflang="x-default" href="https://www.hansulmusic.com/">
<meta property="og:type" content="website">
<meta property="og:locale" content="ko_KR">
<meta property="og:site_name" content="한설뮤직 HANSUL MUSIC">
<meta property="og:title" content="Composition &amp; Arrangement | 작곡·편곡·관현악편곡 | HANSUL MUSIC">
<meta property="og:description" content="작곡가 한상은의 작곡, 관현악편곡, 합창·기악 편곡, 악보·음원 제작 및 작곡 레슨.">
<meta property="og:url" content="https://www.hansulmusic.com/">
<meta property="og:image" content="https://www.hansulmusic.com/images/sehan.jpg">
<meta property="og:image:alt" content="작곡가 한상은">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="Composition &amp; Arrangement | 작곡·편곡·관현악편곡 | HANSUL MUSIC">
<meta name="twitter:description" content="작곡가 한상은의 작곡, 관현악편곡, 합창·기악 편곡, 악보·음원 제작 및 작곡 레슨.">
<meta name="twitter:image" content="https://www.hansulmusic.com/images/sehan.jpg">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "한설뮤직",
  "alternateName": "HANSUL MUSIC",
  "url": "https://www.hansulmusic.com/",
  "description": "작곡가 한상은의 작곡, 관현악편곡, 합창·기악 편곡, 악보·음원 제작 및 작곡 레슨.",
  "founder": {
    "@type": "Person",
    "name": "한상은",
    "alternateName": "Sangeun Han"
  },
  "sameAs": [
    "https://www.youtube.com/channel/UC3TyN3LI9tszetiNJeGegyg"
  ]
}
</script>
<link rel="stylesheet" href="<?= $basePath ?>/css/style.css?v=34">
<link rel="stylesheet" href="<?= $basePath ?>/css/register.css?v=6">
</head>
<body class="home-page">
<div class="wrap">
  <?php
  $headerActivePage = 'home';
  require __DIR__ . '/site-header-ko.php';
  ?>

  <main>
    <!-- [1단계] 홈페이지의 대표 소개와 주요 서비스·작품·상품을 구성합니다. -->
    <!-- Hero -->
    <section class="hero">
      <div>
        <div class="eyebrow">Composition, orchestration, arrangement,<br>scoring, and private lessons</div>
        <h1>작곡과 편곡으로<br>음악을 완성합니다.</h1>
        <p class="hero-services">작곡, 관현악편곡, 성가곡/일반곡 편곡, 악보 및 음원 제작, 작곡입시레슨</p>
        <p class="lead">한설뮤직은 작곡가 한상은을 중심으로 작곡, 관현악편곡, 합창·기악 편곡, 악보·음원 제작과 전문 음악 레슨을 제공합니다.</p>
        <div class="buttons">
          <a class="btn primary" href="#contact">작곡·편곡 의뢰</a>
          <a class="btn ghost" href="shop.php">악보 · 음원 구매</a>
          <a class="btn ghost" href="#services">서비스 보기</a>
        </div>
      </div>
      <!-- [3단계] 대표 영상이 없을 때는 조회 오류와 미등록 상태를 구분해 안내합니다. -->
      <!-- [3단계] DB에서 고른 대표 작곡 영상은 이 영역에 썸네일·제목으로 표시합니다. -->
      <?php if ($homeVideo !== null): ?>
        <?php $homeVideoTitle = $homeVideo['title'] !== '' ? $homeVideo['title'] : 'Composition'; ?>
        <article class="concert-video">
          <button class="concert-video-frame" type="button" data-video-id="<?= escape($homeVideo['id']) ?>" data-video-title="<?= escape($homeVideoTitle) ?>" aria-label="<?= escape($homeVideoTitle) ?> 영상 재생">
            <img src="https://i.ytimg.com/vi/<?= escape($homeVideo['id']) ?>/hqdefault.jpg" alt="" fetchpriority="high" decoding="async">
            <span class="concert-play-icon" aria-hidden="true"></span>
          </button>
          <div class="concert-video-caption">
            <h3><?= escape($homeVideoTitle) ?></h3>
            <a href="<?= $basePath ?>/youtube.php">YouTube에서 더 보기 ↗</a>
          </div>
        </article>
      <?php elseif ($homeVideoError): ?>
        <p class="hero-video-error" role="status">작곡 영상을 불러오지 못했습니다. <a href="<?= $basePath ?>/youtube.php">YouTube 페이지에서 감상해 주세요.</a></p>
      <?php else: ?>
        <p class="hero-video-error">등록된 작곡 영상이 없습니다. <a href="<?= $basePath ?>/youtube.php">YouTube 페이지 보기</a></p>
      <?php endif; ?>
    </section>

    <!-- About -->
    <section class="intro" id="about">
      <div class="intro-grid">
        <div>
          <div class="section-label">About Hansul Music</div>
        </div>
        <div>
          <h2>한 곡의 아이디어를<br>연주 가능한 음악으로.</h2>
          <p>교회·학교·오케스트라·합창단부터 개인 작품과 영상음악까지. 필요한 편성과 연주자의 수준을 고려해 음악을 설계하고, 악보와 음원으로 완성합니다.</p>
        </div>
      </div>
    </section>
  </main>
</div>

<!-- Services (full width bg) -->
<section class="services" id="services">
  <div class="wrap">
    <div class="services-head">
      <div>
        <div class="section-label">What We Do</div>
        <h2>Services</h2>
      </div>
      <div class="section-label">01 — 06</div>
    </div>
    <div class="grid">
      <article class="service">
        <div class="num">01</div>
        <h3>작곡<span>Composition</span></h3>
        <p>연주회, 광고, 드라마·연극 OST, 교가 및 개인 작품 등 목적과 편성에 맞춘 맞춤형 작곡.</p>
      </article>
      <article class="service">
        <div class="num">02</div>
        <h3>관현악편곡<span>Orchestration</span></h3>
        <p>아마추어부터 전공자까지 연주자의 수준을 고려한 오케스트라 편곡.</p>
      </article>
      <article class="service">
        <div class="num">03</div>
        <h3>편곡<span>Arrangement</span></h3>
        <p>성가곡, 찬송가, 합창곡 및 기악곡을 원하는 편성에 맞게 편곡.</p>
      </article>
      <article class="service">
        <div class="num">04</div>
        <h3>악보 제작<span>Scoring</span></h3>
        <p>기존 악보가 없는 음악의 채보와 편곡, 연주에 필요한 악보 제작.</p>
      </article>
      <article class="service">
        <div class="num">05</div>
        <h3>음원 제작<span>Audio</span></h3>
        <p>작곡·편곡된 음악을 실제 활용할 수 있도록 음원으로 제작합니다.</p>
      </article>
      <article class="service">
        <div class="num">06</div>
        <h3>개인 레슨<span>Private Lesson</span></h3>
        <p>화성학, 작곡, 시창·청음, 피아노. 대학·대학원·해외 유학 준비 및 취미 레슨.</p>
      </article>
    </div>
  </div>
</section>

<!-- Concert -->
<section class="work" id="works">
  <div class="wrap">
    <div class="section-label">Concert</div>
    <h2>작품 연주 영상을 만나보세요.</h2>
    <!-- [3단계] 공연 영상 목록의 오류·빈 상태는 성공 조회와 별도로 표시합니다. -->
    <?php if ($homeConcertVideos !== []): ?>
      <div class="work-grid">
        <!-- [3단계] 조회 단계에서 검증한 공연 영상만 카드·재생 버튼·YouTube 제목 링크로 표시합니다. -->
        <?php foreach ($homeConcertVideos as $index => $video): ?>
          <?php $concertTitle = $video['title'] !== '' ? $video['title'] : 'Concert Video ' . ($index + 1); ?>
          <article class="work-card">
            <button class="concert-video-frame" type="button" data-video-id="<?= escape($video['id']) ?>" data-video-title="<?= escape($concertTitle) ?>" aria-label="<?= escape($concertTitle) ?> 영상 재생">
              <img src="https://i.ytimg.com/vi/<?= escape($video['id']) ?>/hqdefault.jpg" alt="" loading="lazy" decoding="async">
              <span class="concert-play-icon" aria-hidden="true"></span>
            </button>
            <div class="work-card-copy">
              <div class="work-title"><?= escape($concertTitle) ?></div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php elseif ($homeConcertError): ?>
      <p class="hero-video-error" role="status">공연 영상을 불러오지 못했습니다. <a href="<?= $basePath ?>/concert.php">Concert 페이지에서 감상해 주세요.</a></p>
    <?php else: ?>
      <p class="hero-video-error">등록된 공연 영상이 없습니다. <a href="<?= $basePath ?>/concert.php">Concert 페이지 보기</a></p>
    <?php endif; ?>
    <a class="work-more" href="concert.php">공연 영상 더 보기 <span aria-hidden="true">→</span></a>
  </div>
</section>

<!-- Scores & Recordings -->
<section class="home-products" id="featured-products">
  <div class="wrap">
    <div class="home-products-heading">
      <div>
        <div class="section-label">Scores &amp; Recordings</div>
        <h2>악보와 음원</h2>
      </div>
      <a class="work-more" href="<?= $basePath ?>/shop.php">전체 상품 보기 <span aria-hidden="true">→</span></a>
    </div>
    <!-- [3단계] 상품 조회 실패와 정상적인 상품 미등록 상태를 구분해 안내합니다. -->
    <?php if ($homeProductsError): ?>
      <p class="hero-video-error" role="status">상품을 불러오지 못했습니다. <a href="<?= $basePath ?>/shop.php">상점에서 확인해 주세요.</a></p>
    <?php else: ?>
      <!-- [3단계] 앞서 조회한 상품 배열을 악보·음원 그룹의 상품 카드로 출력합니다. -->
      <?php foreach ([
          ['title' => '악보', 'label' => 'Scores', 'products' => $homeScoreProducts, 'mark' => '𝄞'],
          ['title' => '음원', 'label' => 'Recordings', 'products' => $homeAudioProducts, 'mark' => '♪'],
      ] as $productGroup): ?>
        <section class="home-product-group" aria-label="<?= escape($productGroup['label']) ?>">
          <div class="home-product-group-heading">
            <h3><?= escape($productGroup['title']) ?></h3>
            <span><?= escape($productGroup['label']) ?></span>
          </div>
          <?php if ($productGroup['products'] !== []): ?>
            <div class="home-product-grid">
              <?php foreach ($productGroup['products'] as $product): ?>
                <a class="home-product-card" href="<?= $basePath ?>/shop.php?product_id=<?= $product['id'] ?>#product-<?= $product['id'] ?>">
                  <div class="home-product-image">
                    <?php if ($product['image'] !== null): ?>
                      <img src="<?= $basePath ?>/<?= escape(ltrim($product['image'], '/')) ?>" alt="" loading="lazy" decoding="async">
                    <?php else: ?>
                      <!-- [3단계] 이미지가 없는 상품은 종류별 기호를 대체 표시합니다. -->
                      <span aria-hidden="true"><?= $productGroup['mark'] ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="home-product-copy">
                    <h4><?= escape($product['name']) ?></h4>
                    <?php if ($product['subtitle'] !== ''): ?>
                      <p><?= escape($product['subtitle']) ?></p>
                    <?php endif; ?>
                    <div class="home-product-price">
                      <?php if ($product['regular_price'] > $product['sale_price']): ?>
                        <del><?= number_format($product['regular_price']) ?>원</del>
                      <?php endif; ?>
                      <strong><?= number_format($product['sale_price']) ?>원</strong>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="hero-video-error">등록된 상품이 없습니다. <a href="<?= $basePath ?>/shop.php">상점 보기</a></p>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<!-- Composer -->
<section class="profile" id="composer">
  <div class="wrap">
    <div class="profile-grid">
      <img class="portrait" src="images/sehan.jpg" alt="작곡가 한상은" loading="lazy" decoding="async">
      <div class="profile-copy">
        <div class="section-label">Composer</div>
        <h2>Dr. Sangeun Han<small>작곡가 한상은</small></h2>
        <p>연세대학교 작곡과 및 동 대학원 작곡전공을 졸업하고 미국 University of North Texas에서 음악박사학위를 받았습니다. 중앙음악콩쿠르 서양음악 작곡 부문 1위, 난파음악제 작곡부문 최우수상 등 수상 경력이 있으며, 국내외 전문 연주단체에서 작품이 연주·발표되었습니다.</p>
        <div class="facts">
          <div class="fact">
            <b>Education</b>
            <span>연세대학교 음악대학<br>University of North Texas, DMA</span>
          </div>
          <div class="fact">
            <b>Awards</b>
            <span>중앙음악콩쿠르 1위<br>난파음악제 최우수상</span>
          </div>
          <div class="fact">
            <b>Activity</b>
            <span>국내외 오케스트라·합창단·앙상블 작품 발표</span>
          </div>
          <div class="fact">
            <b>Teaching</b>
            <span>University of North Texas 강의<br>계명대학교 초빙교수 역임</span>
          </div>
        </div>
        <a class="profile-link" href="composer.php">작곡가 상세 프로필 보기 →</a>
      </div>
    </div>
  </div>
</section>

<!-- Commission -->
<section class="cta" id="contact">
  <div class="wrap">
    <div class="section-label">Commission</div>
    <h2>고객맞춤 음악 의뢰</h2>
    <p class="commission-intro">원하시는 편성, 연주자의 연주 수준, 사용 목적과 상황에 맞춘 작곡 및 편곡을 의뢰하실 수 있습니다.</p>

    <div class="commission-services">
      <article class="commission-service">
        <span class="commission-number">01</span>
        <h3>Orchestration for any Combination of Instruments</h3>
        <p>교회나 학교, 일반 아마추어 오케스트라 등 전임작곡가가 없는 단체의 관현악편곡(Orchestration)을 각 연주자의 실력에 맞게 해드립니다. 악기연주자의 실력을 숫자 1(초보자)부터 5(전공자)까지로 구분해서 표시해주면 각각의 실력에 맞게 관현악편곡을 해드립니다.</p>
      </article>
      <article class="commission-service">
        <span class="commission-number">02</span>
        <h3>Arrangement for Vocal Ensemble for Male/Female/Mixed Choir</h3>
        <p>악보가 없어도 기존의 성가곡, 합창곡, 가곡, 동요, 찬송가 등을 다른 편성의 합창곡(여성/남성/혼성 합창)이나 기악곡으로 편곡해서 악보를 만들어 드립니다.</p>
      </article>
      <article class="commission-service">
        <span class="commission-number">03</span>
        <h3>Composing Music for any Orchestral Instrument, for Amateur/Professional Performer, for any Occasion (such as Armature Concert, Family Party, Informal Gatherings, and so on)</h3>
        <p>만일 당신이 아마추어(amateur)든 전공자(professional)든 간에 악기를 다루거나 성악에 관심이 있다면, 이 세상에 하나뿐이고 자기 실력에 꼭 맞는 자기만의 곡을 연주할 수 있도록 작곡해드립니다.</p>
      </article>
    </div>

    <div class="commission-bottom">
      <div class="commission-contact">
        <h3>작곡·편곡 의뢰 및 문의 <span>Composition &amp; Arrangement Inquiries</span></h3>
        <p>작곡·편곡 비용은 마디 수, 악기 편성, 난이도와 납기 등에 따라 달라집니다. 먼저 의뢰 내용을 확인한 뒤 작업 범위와 견적, 일정을 협의하고 결제 방법을 안내해 드립니다.</p>
        <p lang="en">Fees depend on the length, instrumentation, difficulty, and deadline. We will confirm the scope, quote, and schedule before sending payment instructions.</p>
        <form class="commission-form" data-commission-form>
          <div class="field">
            <label for="commission-name">이름 Name *</label>
            <input id="commission-name" name="name" type="text" maxlength="100" autocomplete="name" required>
          </div>
          <div class="field">
            <label for="commission-email">이메일 Email *</label>
            <input id="commission-email" name="email" type="email" maxlength="255" autocomplete="email" required>
          </div>
          <div class="field">
            <label for="commission-phone">연락처 Mobile</label>
            <input id="commission-phone" name="phone" type="tel" maxlength="40" autocomplete="tel">
          </div>
          <div class="field">
            <label for="commission-subject">의뢰 제목 Subject *</label>
            <input id="commission-subject" name="subject" type="text" maxlength="150" required>
          </div>
          <div class="field">
            <label for="commission-message">의뢰 내용 Message *</label>
            <textarea id="commission-message" name="message" maxlength="5000" required></textarea>
          </div>
          <button class="submit" type="submit">문의 메일 작성하기 · Compose Email</button>
          <p class="commission-form-note" role="status" aria-live="polite">제출하면 기본 메일 앱이 열립니다. 메일 앱에서 내용을 확인한 뒤 전송해 주세요.</p>
        </form>
        <a class="commission-email" href="mailto:sangeun@hansulmusic.com">sangeun@hansulmusic.com</a>
        <section class="commission-payment" aria-labelledby="commission-payment-title">
          <h4 id="commission-payment-title" class="commission-bank-heading">결제 안내 <span>Payment</span></h4>
          <p class="commission-bank-intro">이 안내는 작곡·편곡 맞춤 의뢰에 적용되며, 악보·음원 등 일반 상품 결제와는 별도입니다.</p>
          <ol>
            <li>
              <span><strong>상담 및 견적 확인:</strong> 의뢰 내용을 확인한 뒤 작업 범위, 금액, 일정, 수정 횟수, 제공 파일과 취소·환불 조건을 이메일로 안내해 드립니다. 내용을 충분히 확인하고 동의한 뒤 진행 여부를 결정해 주세요.</span>
              <span lang="en"><strong>Discuss and review the quote:</strong> We will email the scope, price, schedule, number of revisions, deliverables, and cancellation/refund terms. Review and approve these details before proceeding.</span>
            </li>
            <li>
              <span><strong>국내 결제 및 작업 시작:</strong> 진행에 동의하시면 견적서에 안내된 착수금 또는 전액을 계좌이체해 주세요. 입금이 확인되면 협의한 일정에 따라 작업을 시작합니다. 계좌 예금주가 한상은(한설뮤직)인지 확인해 주세요.</span>
              <span lang="en"><strong>Payment in Korea and project start:</strong> Once you approve the quote, transfer the deposit or full amount stated in it. Work begins on the agreed schedule after payment is confirmed. The account holder is Sangeun Han (HANSUL MUSIC).</span>
            </li>
            <li>
              <span><strong>시안 확인:</strong> 진행 중 시안을 공유해 드리며, 견적서에 정한 수정 횟수와 범위 안에서 의견을 반영합니다.</span>
              <span lang="en"><strong>Review a preview:</strong> We will share a preview during production and make revisions within the scope and number agreed in the quote.</span>
            </li>
            <li>
              <span><strong>잔금 결제 및 최종 파일 전달:</strong> 잔금이 있는 경우 안내된 금액을 이체해 주세요. 결제가 확인되면 최종 악보와 음원을 이메일로 보내드립니다.</span>
              <span lang="en"><strong>Final payment and delivery:</strong> If a balance is due, transfer the stated amount. Once payment is confirmed, we will email the final score and audio files.</span>
            </li>
            <li>
              <span><strong>해외 의뢰:</strong> 견적 확정 후 PayPal 인보이스를 이메일로 보내드립니다. 최종 결제 금액과 적용 수수료를 인보이스에서 확인한 뒤 결제하시면 됩니다.</span>
              <span lang="en"><strong>International commissions:</strong> After the quote is approved, we will email a PayPal invoice. Review the final amount and any applicable fees on the invoice before paying.</span>
            </li>
          </ol>
          <div class="commission-bank-details">
            <h4>국내 의뢰 계좌이체 <span>Bank Transfer in Korea</span></h4>
            <dl>
              <div><dt>은행명 · Bank Name</dt><dd>KB 국민은행 (KOOKMIN BANK OF KOREA)</dd></div>
              <div><dt>계좌번호 · Account No.</dt><dd>73950100069238</dd></div>
              <div><dt>예금주 · Account Holder</dt><dd>한상은 (한설뮤직 HANSUL MUSIC)</dd></div>
            </dl>
          </div>
        </section>
      </div>
    </div>
  </div>
</section>

<?php
$footerLanguage = 'ko';
require __DIR__ . '/site-footer.php';
?>

<dialog class="signup-modal" aria-labelledby="auth-title">
  <section class="register-card">
    <button class="signup-close" type="button" aria-label="Sign in 창 닫기" data-close-auth>&times;</button>
    <p class="register-eyebrow" data-auth-eyebrow>Member access</p>
    <h2 id="auth-title" data-auth-title>Sign in</h2>
    <p class="register-intro" data-auth-intro>이메일 주소와 비밀번호를 입력해 주세요.</p>
    <p class="register-message" role="status" aria-live="polite" data-auth-status hidden></p>
    <div class="auth-success" role="status" aria-live="polite" data-auth-success hidden>
      <h3 data-auth-success-greeting></h3>
      <p>회원가입과 동시에 자동으로 로그인되었습니다.<br>이제 회원 기능을 이용하실 수 있습니다.</p>
      <p class="auth-success-hint">창을 닫으려면 오른쪽 위의 ×를 눌러 주세요.</p>
    </div>
    <form method="post" action="auth.php" class="register-form" data-login-form>
      <input type="hidden" name="csrf_token">
      <input type="hidden" name="action" value="login">
      <label for="login-email">이메일 주소</label>
      <input id="login-email" name="email" type="email" inputmode="email" autocapitalize="off" spellcheck="false" maxlength="255" autocomplete="email" required>
      <label for="login-password">비밀번호</label>
      <input id="login-password" name="password" type="password" autocomplete="current-password" required>
      <button type="submit" class="register-submit">Sign in</button>
      <button type="button" class="auth-switch" data-show-signup>Create account</button>
    </form>
    <form method="post" action="register.php" class="register-form" data-signup-form hidden>
      <input type="hidden" name="csrf_token">
      <label for="signup-name">이름</label>
      <input id="signup-name" name="name" type="text" maxlength="100" autocomplete="name" required>
      <label for="signup-email">이메일 주소</label>
      <input id="signup-email" name="email" type="email" inputmode="email" autocapitalize="off" spellcheck="false" maxlength="255" autocomplete="email" required>
      <label for="signup-email-confirm">이메일 주소 확인</label>
      <input id="signup-email-confirm" name="email_confirm" type="email" inputmode="email" autocapitalize="off" spellcheck="false" maxlength="255" autocomplete="email" required>
      <label for="signup-password">비밀번호</label>
      <input id="signup-password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
      <label for="signup-password-confirm">비밀번호 확인</label>
      <input id="signup-password-confirm" name="password_confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
      <label class="register-consent">
        <input type="checkbox" name="marketing_consent" value="1">
        <span>신규 작품 출시 및 이벤트·광고 소식을 이메일로 받는 데 동의합니다. <strong>(선택)</strong></span>
      </label>
      <button type="submit" class="register-submit">Create account</button>
      <button type="button" class="auth-switch" data-show-login>Already have an account? Sign in</button>
    </form>
    <p class="register-footnote">이메일 인증, 간편 로그인 및 소식 이메일 발송은 추후 지원 예정입니다.</p>
  </section>
</dialog>
<dialog class="signup-modal consent-modal" aria-labelledby="consent-title" data-consent-modal>
  <section class="register-card">
    <button class="signup-close" type="button" aria-label="수신 동의 창 닫기" data-close-consent>&times;</button>
    <p class="register-eyebrow">Email preferences</p>
    <h2 id="consent-title">소식 수신 설정</h2>
    <p class="register-intro" data-consent-message></p>
    <p class="register-message" role="status" aria-live="polite" data-consent-status hidden></p>
    <button type="button" class="register-submit" data-grant-consent hidden>수신에 동의하기</button>
    <button type="button" class="register-submit" data-withdraw-consent hidden>수신 동의 철회</button>
  </section>
</dialog>
<dialog class="concert-player" aria-label="대표 작품 영상 플레이어">
  <div class="concert-player-content">
    <button class="concert-player-close" type="button" aria-label="영상 닫기">&times;</button>
    <div class="concert-player-frame"></div>
  </div>
</dialog>
<script src="<?= $basePath ?>/js/signup-modal.js?v=13" defer></script>
<script src="<?= $basePath ?>/js/navigation.js?v=3" defer></script>
<script src="<?= $basePath ?>/js/concert.js?v=7" defer></script>
<script src="<?= $basePath ?>/js/commission-inquiry.js?v=1" defer></script>
</body>
</html>
