<?php
declare(strict_types=1);

require_once __DIR__ . '/site-config.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

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

function homeYouTubeVideoId(string $url): ?string
{
    $parts = parse_url($url);
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

    return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1 ? $videoId : null;
}

$basePath = escape(appBasePath());
$homeVideo = null;
$homeVideoError = false;
$homeConcertVideos = [];
$homeConcertError = false;
try {
    $pdo = homeDatabaseConnection();
    $statement = $pdo->query(
        'SELECT video_title, video_url
         FROM videos
         WHERE video_type = 2 AND role = 1 AND is_active = 1'
    );
    $compositionVideos = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $videoId = homeYouTubeVideoId((string) $row['video_url']);
        if ($videoId === null) {
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
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HANSUL MUSIC | 작곡 · 관현악편곡 · 편곡 · 악보 · 레슨</title>
<meta name="description" content="한설뮤직 — 작곡, 관현악편곡, 성가곡·일반곡 편곡, 악보 및 음원 제작, 작곡 입시 레슨">
<link rel="stylesheet" href="<?= $basePath ?>/css/style.css?v=27">
<link rel="stylesheet" href="<?= $basePath ?>/css/register.css?v=6">
</head>
<body>
<div class="wrap">
  <header class="site-header">
    <a class="logo" href="<?= $basePath ?>/index.php">HANSUL MUSIC<small>HSM · MUSIC STUDIO</small></a>
    <button class="menu-toggle" type="button" aria-label="메뉴 열기" aria-expanded="false" aria-controls="site-nav">
      <span></span>
      <span></span>
      <span></span>
    </button>
    <nav class="site-nav" id="site-nav">
      <a href="<?= $basePath ?>/concert.php">Concert</a>
      <a href="<?= $basePath ?>/news.php">News</a>
      <a href="<?= $basePath ?>/shop.php">Scores &amp; Recordings</a>
      <a href="<?= $basePath ?>/index.php#contact">Commission</a>
      <a href="<?= $basePath ?>/youtube.php">YouTube</a>
      <a href="<?= $basePath ?>/hymnal-parts.php">Hymnal (찬송가)</a>
      <a href="<?= $basePath ?>/praise-song-parts.php">Praise Song (찬양곡)</a>
      <div class="nav-account">
        <button class="signup-nav-button" type="button" data-open-auth>Sign in</button>
        <button class="nav-member-identity" type="button" data-member-identity hidden disabled></button>
      </div>
    </nav>
  </header>

  <main>
    <!-- Hero -->
    <section class="hero">
      <div>
        <div class="eyebrow">Composition, orchestration, arrangement,<br>scoring, and private lessons</div>
        <h1>음악을<br>완성하는 일.</h1>
        <p class="hero-services">작곡, 관현악편곡, 성가곡/일반곡 편곡, 악보 및 음원 제작, 작곡입시레슨</p>
        <p class="lead">한설뮤직은 작곡가 한상은을 중심으로 작곡, 관현악편곡, 합창·기악 편곡, 악보·음원 제작과 전문 음악 레슨을 제공합니다.</p>
        <div class="buttons">
          <a class="btn primary" href="#contact">작곡·편곡 의뢰</a>
          <a class="btn ghost" href="shop.php">악보 · 음원 구매</a>
          <a class="btn ghost" href="#services">서비스 보기</a>
        </div>
      </div>
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
    <?php if ($homeConcertVideos !== []): ?>
      <div class="work-grid">
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
      <section class="commission-payment" aria-labelledby="commission-payment-title">
        <h3 id="commission-payment-title">결제 안내 <span>Payment</span></h3>
        <div class="commission-paypal">
          <strong>페이팔 (PAYPAL)</strong>
          <p>페이팔(PayPal)에 가입하시면 안전한 결제를 하실 수 있습니다.</p>
          <p lang="en">You can make secure payments by signing up for PayPal.</p>
          <a href="https://www.paypal.com/kr/home" target="_blank" rel="noopener noreferrer">PayPal 가입 및 결제 ↗</a>
        </div>
        <h4 class="commission-bank-heading">계좌이체 <span>Bank Transfer</span></h4>
        <p class="commission-bank-intro">계좌이체(Bank Transfer)를 원하실 때는 아래 순서로 진행하시면 됩니다.</p>
        <p class="commission-bank-intro" lang="en">If you prefer to pay via bank transfer, please follow the steps below.</p>
        <ol>
          <li>
            <span>입력 내용을 확인한 후 주문합니다.</span>
            <span lang="en">After confirming your input, place an order.</span>
          </li>
          <li>
            <span>주문 후 안내된 입금 계좌로 현금을 이체합니다.</span>
            <span lang="en">After placing your order, transfer the payment to the provided deposit account.</span>
          </li>
          <li>
            <span>한설뮤직에서 입금을 확인한 후 구매자의 이메일로 상품을 보내드리거나 작업을 시작합니다.</span>
            <span lang="en">After confirming the payment, Hansul Music will send the product to the buyer's email or begin the work.</span>
          </li>
        </ol>
        <div class="commission-bank-details">
          <h4>주문 계좌 <span>Bank Account Information</span></h4>
          <dl>
            <div><dt>구매안전서비스 · SWIFT CODE</dt><dd>CZNBKRSEXXX</dd></div>
            <div><dt>은행명 · Bank Name</dt><dd>KB 국민은행 (KOOKMIN BANK OF KOREA)</dd></div>
            <div><dt>계좌번호 · Account No.</dt><dd>73950100069238</dd></div>
            <div><dt>예금주 · Account Holder</dt><dd>한상은 (한설뮤직 HANSUL MUSIC)</dd></div>
          </dl>
        </div>
        <div class="commission-rate">
          <strong>참고 환율 <span>USD / KRW</span></strong>
          <output data-usd-krw-rate aria-live="polite">환율 정보를 불러오는 중...</output>
          <small data-usd-krw-updated></small>
          <a href="https://www.exchangerate-api.com/" target="_blank" rel="noopener noreferrer">환율 정보 제공: ExchangeRate-API</a>
        </div>
      </section>
      <div class="commission-contact">
        <h3>의뢰 및 문의</h3>
        <p>의뢰 내용을 남겨주시면 입력하신 메일 앱에서 문의 메일을 작성할 수 있습니다.</p>
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
      </div>
    </div>
  </div>
</section>

<footer>
  <div class="wrap site-footer-inner">
    <div class="site-footer-brand">
      <a href="https://www.hansulmusic.com/" aria-label="HANSUL MUSIC 홈">
        <img src="https://static.wixstatic.com/media/d49565_b4834284a8f1429e84ca2524a187b9e0.png/v1/fill/w_72,h_41,al_c,q_85,usm_0.66_1.00_0.01,enc_avif,quality_auto/d49565_b4834284a8f1429e84ca2524a187b9e0.png" alt="HANSUL MUSIC (HSM)" width="72" height="41" loading="lazy">
      </a>
      <span class="site-footer-president">한설뮤직 대표: 한상은<br>(SANGEUN HAN, President)</span>
    </div>
    <div class="site-footer-social">
      <a class="site-footer-youtube" href="https://www.youtube.com/channel/UC3TyN3LI9tszetiNJeGegyg/videos?view=0&amp;sort=p&amp;flow=grid" target="_blank" rel="noopener noreferrer" aria-label="한설뮤직 YouTube 채널">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4l6.3 3.6-6.3 3.6Z"/></svg>
        <span>한설뮤직 YouTube 채널</span>
      </a>
    </div>
    <div class="site-footer-meta">
      <p>사업자등록번호: 271-46-00745 (Corporate Registration No.)</p>
      <p>통신판매신고업 신고번호: 2025-인천연수구- 2420</p>
      <p>주소: 인천, 연수구 앵고개로 262, 8069호 (오피스밸리)</p>
    </div>
    <p class="site-footer-copyright">Copyright(c)2016 by HANSUL MUSIC. All rights reserved.</p>
  </div>
</footer>

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
<script src="<?= $basePath ?>/js/signup-modal.js?v=11" defer></script>
<script src="<?= $basePath ?>/js/navigation.js?v=2" defer></script>
<script src="<?= $basePath ?>/js/concert.js?v=7" defer></script>
<script src="<?= $basePath ?>/js/commission-rates.js?v=1" defer></script>
<script src="<?= $basePath ?>/js/commission-inquiry.js?v=1" defer></script>
</body>
</html>
