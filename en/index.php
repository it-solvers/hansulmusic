<?php
declare(strict_types=1);

// [1단계] 영문 홈페이지에 필요한 공통 설정과 동적 콘텐츠를 준비합니다.
require_once __DIR__ . '/../site-config.php';
require_once __DIR__ . '/../product-localization.php';

// [2단계] 템플릿에 출력하는 문자열을 안전하게 이스케이프합니다.
/** 영문 홈페이지 콘텐츠를 HTML에 안전하게 출력하도록 이스케이프합니다. */
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// [2단계] 영문 홈페이지 콘텐츠 조회용 DB 연결을 생성합니다.
/** 영문 홈페이지 콘텐츠 조회에 사용할 PDO 연결을 반환합니다. */
function englishHomeDatabaseConnection(): PDO
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
function englishHomeYouTubeVideoId(string $url): ?string
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

// [1단계] 영문 URL 경로와 홈페이지 표시용 영상·상품 데이터를 준비합니다.
$basePath = dirname(appBasePath());
if ($basePath === '/' || $basePath === '.') {
    $basePath = '';
}
$basePath = escape($basePath);
$authFormBasePath = $basePath;
$homeVideo = null;
$homeVideoError = false;
$homeConcertVideos = [];
$homeConcertError = false;
$homeScoreProducts = [];
$homeAudioProducts = [];
$homeProductsError = false;
try {
    // [2단계] 작곡 영상 하나와 중복 없는 공연 영상 목록을 구성합니다.
    $pdo = englishHomeDatabaseConnection();
    // [3단계] videos에서 활성 작곡 영상을 읽고, 아래 대표 영상 영역에 쓸 ID·제목만 보관합니다.
    $statement = $pdo->query(
        'SELECT video_title, video_url
         FROM videos
         WHERE video_type = 2 AND role = 1 AND is_active = 1'
    );
    $compositionVideos = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $videoId = englishHomeYouTubeVideoId((string) $row['video_url']);
        if ($videoId === null) {
            // [3단계] 잘못된 영상 주소는 제외하고 서버 로그에 기록합니다.
            error_log('English homepage composition video row contains an invalid YouTube URL.');
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
        $videoId = englishHomeYouTubeVideoId((string) $row['video_url']);
        if ($videoId === null) {
            error_log('English homepage concert video row contains an invalid YouTube URL.');
            continue;
        }
        // [3단계] 같은 영상이 중복 등록된 경우 첫 항목만 유지합니다.
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
    error_log('English homepage video database error: ' . $exception->getMessage());
    $homeVideoError = true;
    $homeConcertError = true;
}

// [2단계] 상품 문구를 영문화하고 종류별로 나누어 미리보기 목록을 구성합니다.
try {
    // [3단계] products에서 활성 악보·음원과 가격을, product_assets에서 첫 미리보기 경로를 가져옵니다.
    // 결과는 아래 Scores와 Recordings 영역의 카드에 표시합니다.
    // 영문 번역 문구는 product_type(1=악보, 2=음원)으로 찾습니다.
    $productRows = englishHomeDatabaseConnection()->query(
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
        // [3단계] 미리보기와 표지 경로가 모두 유효하지 않으면 이미지 없이 표시합니다.
        if ($imagePath !== null
            && (!is_string($imagePath) || !is_file(__DIR__ . '/../' . ltrim($imagePath, '/')))) {
            $imagePath = null;
        }
        $copy = englishProductCopy(
            (int) $row['product_type'],
            (string) $row['name'],
            (string) $row['subtitle'],
            '',
        );
        $product = [
            'id' => (int) $row['product_id'],
            'name' => $copy['name'],
            'subtitle' => $copy['subtitle'],
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
    error_log('English homepage shop products database error: ' . $exception->getMessage());
    $homeProductsError = true;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Custom Composition, Orchestration &amp; Arrangements | Hansul Music</title>
  <meta name="description" content="Commission custom music by Dr. Sangeun Han: composition, orchestration, choir and instrumental arrangements, and performance-ready scores. Request an international quote.">
  <link rel="canonical" href="https://www.hansulmusic.com/en/">
  <link rel="alternate" hreflang="en" href="https://www.hansulmusic.com/en/">
  <link rel="alternate" hreflang="ko" href="https://www.hansulmusic.com/">
  <link rel="alternate" hreflang="x-default" href="https://www.hansulmusic.com/">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="en_US">
  <meta property="og:site_name" content="HANSUL MUSIC">
  <meta property="og:title" content="Custom Composition, Orchestration &amp; Arrangements | Hansul Music">
  <meta property="og:description" content="Commission custom music by Dr. Sangeun Han, from orchestral and choral arrangements to original compositions.">
  <meta property="og:url" content="https://www.hansulmusic.com/en/">
  <meta property="og:image" content="https://www.hansulmusic.com/images/sehan.jpg">
  <meta property="og:image:alt" content="Composer Dr. Sangeun Han">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="Custom Composition, Orchestration &amp; Arrangements | Hansul Music">
  <meta name="twitter:description" content="Commission custom music by Dr. Sangeun Han, from orchestral and choral arrangements to original compositions.">
  <meta name="twitter:image" content="https://www.hansulmusic.com/images/sehan.jpg">
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "WebPage",
        "@id": "https://www.hansulmusic.com/en/#webpage",
        "url": "https://www.hansulmusic.com/en/",
        "name": "Custom Composition, Orchestration & Arrangements | Hansul Music",
        "description": "Commission custom music by Dr. Sangeun Han: composition, orchestration, choir and instrumental arrangements, and performance-ready scores.",
        "inLanguage": "en",
        "about": {
          "@id": "https://www.hansulmusic.com/en/#organization"
        }
      },
      {
        "@type": "Organization",
        "@id": "https://www.hansulmusic.com/en/#organization",
        "name": "HANSUL MUSIC",
        "alternateName": "Hansul Music (한설뮤직)",
        "url": "https://www.hansulmusic.com/",
        "email": "sangeun@hansulmusic.com",
        "description": "Custom composition, orchestration, choral and instrumental arrangements, and score preparation by composer Dr. Sangeun Han.",
        "contactPoint": {
          "@type": "ContactPoint",
          "contactType": "commission inquiries",
          "email": "sangeun@hansulmusic.com",
          "availableLanguage": ["English", "Korean"]
        },
        "founder": {
          "@type": "Person",
          "name": "Sangeun Han",
          "alternateName": "한상은",
          "jobTitle": "Composer"
        },
        "sameAs": [
          "https://www.youtube.com/channel/UC3TyN3LI9tszetiNJeGegyg"
        ]
      }
    ]
  }
  </script>
  <link rel="stylesheet" href="<?= $basePath ?>/css/style.css?v=34">
  <link rel="stylesheet" href="<?= $basePath ?>/css/register.css?v=6">
</head>
<body class="home-page">
  <div class="wrap">
    <?php
    $headerIsEnglish = true;
    $headerActivePage = 'home';
    require __DIR__ . '/site-header.php';
    ?>

    <main>
      <!-- [1단계] 영문 홈페이지의 소개와 서비스·작품·상품 정보를 구성합니다. -->
      <section class="hero">
        <div>
          <div class="eyebrow">Composition, orchestration, arrangement,<br>scoring, and private lessons</div>
          <h1>Music composed and arranged<br>to bring your ideas to life.</h1>
          <p class="hero-services">Composition, orchestration, choral and instrumental arrangements, score and audio production, and composition lessons</p>
          <p class="lead">HANSUL MUSIC, led by composer Dr. Sangeun Han, provides original compositions, orchestration, choral and instrumental arrangements, scores, audio production, and professional music lessons.</p>
          <div class="buttons">
            <a class="btn primary" href="#contact">Request a Commission</a>
            <a class="btn ghost" href="#services">Explore Services</a>
          </div>
        </div>
        <!-- [3단계] 대표 영상이 없을 때는 조회 오류와 미등록 상태를 나누어 안내합니다. -->
        <!-- [3단계] DB에서 고른 대표 작곡 영상은 이 영역에 썸네일·제목으로 표시합니다. -->
        <?php if ($homeVideo !== null): ?>
          <?php $homeVideoTitle = $homeVideo['title'] !== '' ? $homeVideo['title'] : 'Composition'; ?>
          <article class="concert-video">
            <button class="concert-video-frame" type="button" data-video-id="<?= escape($homeVideo['id']) ?>" data-video-title="<?= escape($homeVideoTitle) ?>" aria-label="Play <?= escape($homeVideoTitle) ?>">
              <img src="https://i.ytimg.com/vi/<?= escape($homeVideo['id']) ?>/hqdefault.jpg" alt="" fetchpriority="high" decoding="async">
              <span class="concert-play-icon" aria-hidden="true"></span>
            </button>
            <div class="concert-video-caption">
              <h2><?= escape($homeVideoTitle) ?></h2>
              <a href="<?= $basePath ?>/youtube.php">More on YouTube ↗</a>
            </div>
          </article>
        <?php elseif ($homeVideoError): ?>
          <p class="hero-video-error" role="status">Unable to load a composition video. <a href="<?= $basePath ?>/youtube.php">Browse videos on YouTube.</a></p>
        <?php else: ?>
          <p class="hero-video-error">No composition videos are available yet. <a href="<?= $basePath ?>/youtube.php">Browse YouTube videos.</a></p>
        <?php endif; ?>
      </section>

      <section class="intro" aria-labelledby="about-title">
        <div class="intro-grid">
          <div><div class="section-label">About Hansul Music</div></div>
          <div>
            <h2 id="about-title">From a musical idea<br>to music ready to perform.</h2>
            <p>From churches, schools, orchestras, and choirs to individual projects and screen music, we develop music around your instrumentation and performers’ abilities, then prepare it as scores and audio.</p>
          </div>
        </div>
      </section>
    </main>
  </div>

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
          <h3>Composition</h3>
          <p>Original music for concerts, advertisements, film and theatre soundtracks, school songs, and personal projects, tailored to your purpose and ensemble.</p>
        </article>
        <article class="service">
          <div class="num">02</div>
          <h3>Orchestration</h3>
          <p>Orchestral arrangements for churches, schools, and amateur ensembles, adapted to the playing ability of each musician.</p>
        </article>
        <article class="service">
          <div class="num">03</div>
          <h3>Choir &amp; Instrumental Arrangements</h3>
          <p>Arrange hymns, choral works, art songs, children’s songs, and other existing music for a different choir or instrumental ensemble.</p>
        </article>
        <article class="service">
          <div class="num">04</div>
          <h3>Score Preparation</h3>
          <p>Prepare scores for new and arranged music, including transcription where appropriate, so performers can rehearse and perform.</p>
        </article>
        <article class="service">
          <div class="num">05</div>
          <h3>Music Production</h3>
          <p>Produce audio recordings of commissioned compositions and arrangements for practical use.</p>
        </article>
        <article class="service">
          <div class="num">06</div>
          <h3>Lessons</h3>
          <p>Lessons in harmony, composition, ear training, and piano, for entrance exams, further study, overseas study, or personal interest.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="work" id="works">
    <div class="wrap">
      <div class="section-label">Concert</div>
      <h2>Watch performances of selected works.</h2>
      <!-- [3단계] 공연 영상의 오류와 정상적인 빈 목록을 구분해 안내합니다. -->
      <?php if ($homeConcertVideos !== []): ?>
        <div class="work-grid">
          <!-- [3단계] 조회·검증된 공연 영상만 카드와 재생 버튼으로 표시합니다. -->
          <?php foreach ($homeConcertVideos as $index => $video): ?>
            <?php $concertTitle = $video['title'] !== '' ? $video['title'] : 'Concert Video ' . ($index + 1); ?>
            <article class="work-card">
              <button class="concert-video-frame" type="button" data-video-id="<?= escape($video['id']) ?>" data-video-title="<?= escape($concertTitle) ?>" aria-label="Play <?= escape($concertTitle) ?>">
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
        <p class="hero-video-error" role="status">Unable to load concert videos. <a href="<?= $basePath ?>/concert.php">Visit the Concert page.</a></p>
      <?php else: ?>
        <p class="hero-video-error">No concert videos are available yet. <a href="<?= $basePath ?>/concert.php">View the Concert page.</a></p>
      <?php endif; ?>
      <a class="work-more" href="<?= $basePath ?>/concert.php">More concert videos <span aria-hidden="true">→</span></a>
    </div>
  </section>

  <section class="home-products" id="featured-products">
    <div class="wrap">
      <div class="home-products-heading">
        <div>
          <div class="section-label">Scores &amp; Recordings</div>
          <h2>Scores and recordings</h2>
        </div>
        <a class="work-more" href="<?= $basePath ?>/en/shop.php">Browse all products <span aria-hidden="true">→</span></a>
      </div>
      <?php if (!$homeProductsError && ($homeScoreProducts !== [] || $homeAudioProducts !== [])): ?>
        <p class="currency-note" data-currency-note>
          USD prices are estimates converted from KRW. Orders are requested by email, paid by PayPal invoice, and delivered by email after payment is confirmed. Visit the store for the order steps and estimated PayPal fees.
          <span data-currency-status>Loading the latest available exchange rate…</span>
        </p>
      <?php endif; ?>
      <!-- [3단계] 상품 조회 실패와 정상적인 상품 미등록 상태를 구분해 안내합니다. -->
      <?php if ($homeProductsError): ?>
        <p class="hero-video-error" role="status">Unable to load products. <a href="<?= $basePath ?>/en/shop.php">Visit the store.</a></p>
      <?php else: ?>
        <!-- [3단계] 조회 결과를 악보·음원 그룹별 카드로 출력합니다. -->
        <?php foreach ([
            ['title' => 'Scores', 'label' => 'Scores', 'products' => $homeScoreProducts, 'mark' => '𝄞'],
            ['title' => 'Recordings', 'label' => 'Recordings', 'products' => $homeAudioProducts, 'mark' => '♪'],
        ] as $productGroup): ?>
          <section class="home-product-group" aria-label="<?= escape($productGroup['label']) ?>">
            <div class="home-product-group-heading">
              <h3><?= escape($productGroup['title']) ?></h3>
              <span><?= escape($productGroup['label']) ?></span>
            </div>
            <?php if ($productGroup['products'] !== []): ?>
              <div class="home-product-grid">
                <?php foreach ($productGroup['products'] as $product): ?>
                  <a class="home-product-card" href="<?= $basePath ?>/en/shop.php?product_id=<?= $product['id'] ?>#product-<?= $product['id'] ?>">
                    <div class="home-product-image">
                      <?php if ($product['image'] !== null): ?>
                        <img src="<?= $basePath ?>/<?= escape(ltrim($product['image'], '/')) ?>" alt="" loading="lazy" decoding="async">
                      <?php else: ?>
                        <!-- [3단계] 이미지가 없는 상품은 종류에 맞는 기호로 대신 표시합니다. -->
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
                          <del data-krw-price="<?= $product['regular_price'] ?>">Loading USD…</del>
                        <?php endif; ?>
                        <strong data-krw-price="<?= $product['sale_price'] ?>">Loading USD…</strong>
                      </div>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="hero-video-error">No products in this category yet. <a href="<?= $basePath ?>/en/shop.php">Visit the store.</a></p>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="profile" id="composer">
    <div class="wrap profile-grid">
      <img class="portrait" src="<?= $basePath ?>/images/sehan.jpg" alt="Dr. Sangeun Han, composer" loading="lazy" decoding="async">
      <div class="profile-copy">
        <div class="section-label">Composer</div>
        <h2>Dr. Sangeun Han<small>Composer · HANSUL MUSIC</small></h2>
        <p>Dr. Sangeun Han graduated from Yonsei University and its graduate school, then earned a Doctor of Musical Arts degree in composition from the University of North Texas. He received First Prize in composition at the Jung-Ang Music Concours and the top prize at the Nan-Pa Music Festival. His works have been performed and presented by professional orchestras, choirs, and ensembles in Korea and abroad.</p>
        <div class="facts">
          <div class="fact">
            <b>Education</b>
            <span>Yonsei University<br>University of North Texas, DMA</span>
          </div>
          <div class="fact">
            <b>Awards</b>
            <span>First Prize, Jung-Ang Music Concours<br>Top Prize, Nan-Pa Music Festival</span>
          </div>
          <div class="fact">
            <b>Activity</b>
            <span>Works performed by orchestras, choirs, and ensembles in Korea and internationally</span>
          </div>
          <div class="fact">
            <b>Teaching</b>
            <span>Former faculty, University of North Texas<br>Former visiting professor, Keimyung University</span>
          </div>
        </div>
        <a class="profile-link" href="<?= $basePath ?>/composer.php">View biography and selected works →</a>
      </div>
    </div>
  </section>

  <section class="cta" id="contact">
    <div class="wrap">
      <div class="section-label">Commission</div>
      <h2>Custom composition and arrangement commissions</h2>
      <p class="commission-intro">Commission music tailored to your instrumentation, performers’ abilities, intended use, and occasion.</p>

      <div class="commission-services">
        <article class="commission-service">
          <span class="commission-number">01</span>
          <h3>Orchestration for any Instrument Combination</h3>
          <p>We arrange orchestral music for churches, schools, and amateur ensembles without an in-house composer. Tell us each musician’s level from 1 (beginner) to 5 (advanced), and we will tailor the arrangement to the ensemble.</p>
        </article>
        <article class="commission-service">
          <span class="commission-number">02</span>
          <h3>Arrangements for Vocal Ensembles</h3>
          <p>We can arrange hymns, choral works, art songs, children’s songs, and other existing music for women’s, men’s, or mixed choir, or adapt them for an instrumental ensemble—even when no score is available.</p>
        </article>
        <article class="commission-service">
          <span class="commission-number">03</span>
          <h3>Original Music for Your Instrument or Occasion</h3>
          <p>Whether you are an amateur or professional musician or singer, we can compose an original work suited to your abilities, instrument, performance, family event, or other occasion.</p>
        </article>
      </div>

      <div class="commission-bottom">
        <div class="commission-contact">
          <h3>Composition &amp; Arrangement Inquiries</h3>
          <p>Fees depend on the number of bars or duration, instrumentation, difficulty, and deadline. We will review your request, agree on the scope, quote, and schedule, and then provide payment instructions.</p>
          <p>Complete the form to open a draft email in your email app. Please review and send it there; submitting the form does not send it automatically.</p>
          <form class="commission-form" data-commission-form>
            <div class="field">
              <label for="commission-name">Name *</label>
              <input id="commission-name" name="name" type="text" maxlength="100" autocomplete="name" required>
            </div>
            <div class="field">
              <label for="commission-email">Email *</label>
              <input id="commission-email" name="email" type="email" maxlength="255" autocomplete="email" required>
            </div>
            <div class="field">
              <label for="commission-phone">Phone (optional)</label>
              <input id="commission-phone" name="phone" type="tel" maxlength="40" autocomplete="tel">
            </div>
            <div class="field">
              <label for="commission-subject">Project title *</label>
              <input id="commission-subject" name="subject" type="text" maxlength="150" required>
            </div>
            <div class="field">
              <label for="commission-message">Project details *</label>
              <textarea id="commission-message" name="message" maxlength="5000" required></textarea>
            </div>
            <button class="submit" type="submit">Prepare inquiry email</button>
            <p class="commission-form-note" role="status" aria-live="polite">Your email app will open with a draft. Please review and send the email from your app.</p>
          </form>
          <a class="commission-email" href="mailto:sangeun@hansulmusic.com">sangeun@hansulmusic.com</a>
          <section class="commission-payment" aria-labelledby="commission-payment-title">
            <h4 id="commission-payment-title" class="commission-bank-heading">Commission process &amp; payment</h4>
            <p class="commission-bank-intro">This process applies to custom composition and arrangement commissions, not regular score or recording purchases.</p>
            <ol>
              <li>
                <span><strong>Discuss your project and review the quote:</strong> We will email the agreed scope, price, schedule, number of revisions, deliverables, and cancellation/refund terms. Please review and approve these details before proceeding.</span>
              </li>
              <li>
                <span><strong>Payment and project start:</strong> For commissions in Korea, transfer the deposit or full amount stated in the quote. For international commissions, we will send a PayPal invoice by email after you approve the quote. The invoice will show the final amount and any applicable fees. Work begins on the agreed schedule after payment is confirmed.</span>
              </li>
              <li>
                <span><strong>Review a preview:</strong> We will share a preview during production and make revisions within the scope and number agreed in the quote.</span>
              </li>
              <li>
                <span><strong>Final payment and delivery:</strong> If a balance is due, transfer the stated amount. Once payment is confirmed, we will email the final score and audio files.</span>
              </li>
            </ol>
          </section>
        </div>
      </div>
    </div>
  </section>

  <?php
  $footerLanguage = 'en';
  require __DIR__ . '/../site-footer.php';
  ?>
  <?php
  $authLanguage = 'en';
  require __DIR__ . '/../auth-modal.php';
  ?>
  <dialog class="concert-player" aria-label="Selected work video player">
    <div class="concert-player-content">
      <button class="concert-player-close" type="button" aria-label="Close video">&times;</button>
      <div class="concert-player-frame"></div>
    </div>
  </dialog>
  <script src="<?= $basePath ?>/js/signup-modal.js?v=13" defer></script>
  <script src="<?= $basePath ?>/js/currency-estimate.js?v=1" defer></script>
  <script src="<?= $basePath ?>/js/navigation.js?v=3" defer></script>
  <script src="<?= $basePath ?>/js/concert.js?v=7" defer></script>
  <script src="<?= $basePath ?>/js/commission-inquiry.js?v=1" defer></script>
</body>
</html>
