<?php
declare(strict_types=1);

require_once __DIR__ . '/site-config.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$basePath = escape(appBasePath());
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>작곡가 한상은 프로필 | HANSUL MUSIC</title>
  <meta name="description" content="작곡가 한상은의 수상 및 국내외 작품 발표 경력입니다.">
  <link rel="stylesheet" href="<?= $basePath ?>/css/style.css?v=21">
  <link rel="stylesheet" href="<?= $basePath ?>/css/register.css?v=6">
</head>
<body>
  <header>
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

  <main class="composer-page">
    <div class="wrap">
      <section class="composer-heading">
        <div class="section-label">Composer</div>
        <h1>작곡가 한상은</h1>
        <p>About · Academic Background · Major Publications · Major Profile</p>
      </section>
      <section class="composer-section composer-about" aria-labelledby="composer-about-title">
        <h2 id="composer-about-title">About the Composer</h2>
        <div class="composer-about-layout">
          <img class="composer-bio-portrait" src="<?= $basePath ?>/images/sehan.jpg" alt="작곡가 한상은" loading="lazy" decoding="async">
          <div class="composer-about-copy">
            <h3>About the Composer...</h3>
            <p lang="en">The Composer, SangEun Han, holds a Bachelor and Master of Music degree from Yonsei University and a Doctor of Musical Art degree in Composition from the University of North Texas. He has received numerous awards for his music. Among them, most significantly, he won the First Prize from the Jung-Ang Music Concours and won the Very Best Prize from the Nan-Pa Music Competition. His compositions for various media have been performed, recorded, and published by leading orchestras, choruses, chamber ensembles, and famous music companies throughout Korea and other countries like Japan, Germany, and the USA.</p>
            <p lang="en">He used to be teaching at the University of North Texas, and at the Keimyung University as a visiting professor and is serving as a member of various musical societies in Korea and Asia.</p>
            <h3>작곡가 한상은 <span lang="en">Sangeun Han, composer</span></h3>
            <p>작곡가 한상은은 연세대학교 및 동 대학원 작곡과를 졸업하고 미국 University of North Texas에서 음악박사학위를 받았다.</p>
            <p>그는 중앙음악콩쿠르 1위 입상, 난파음악제 최우수상 수상 등 많은 작곡콩쿠르의 입상 경력을 갖고 있으며, 그의 작품은 국립합창단, 안산시립합창단, 인천시립합창단, KBS교향악단, PRIME 교향악단, 원주시립교향악단, 시흥시교향악단, TIMF 앙상블, BE 앙상블, 아미띠에 앙상블 등 한국의 전문 연주단체에서 연주되고 있다.</p>
            <p>그의 작품은 주식회사 현대음반, 수문당 출판사, 기독언어문화사, GCM(기독교작곡가협회) 등을 통해 시판·출판되고 있으며, 한국뿐 아니라 미국, 일본, 독일 등 외국에서도 널리 연주되고 있다.</p>
            <p>그는 미국 북텍사스 주립대학교(UNT)에서 관현악법, 작곡실기, 음악이론 등을 강의하였으며, 계명대학교 초빙교수를 역임하였다. 동아시아 작곡가 협회, 21세기악회, 대전 현대음악협회(DCMA), 창악회, 우리가곡 연구회, 기독교 작곡가협회(GCM), 신음악학회, 아시아 예술학회, 한일 가곡 교류회 회원 및 임원으로 활동하고 있다.</p>
          </div>
        </div>
      </section>

      <section class="composer-section" aria-labelledby="composer-education-title">
        <h2 id="composer-education-title">학력 <span lang="en">Academic Background</span></h2>
        <ul class="composer-bio-list composer-education-list">
          <li><span>연세대학교 작곡과 졸업 (학사)</span><span lang="en">The Yeonsei University (Bachelor of Music Degree)</span></li>
          <li><span>연세대학교 본대학원 음악과 졸업 (작곡전공, 음악석사)</span><span lang="en">The Graduate School of Yeonsei University (Master of Music Degree)</span></li>
          <li><span>미국 북 텍사스 주립대학교 대학원 졸업 (음악박사)</span><span lang="en">The University of North Texas (Doctor of Musical Art Degree)</span></li>
        </ul>
      </section>

      <section class="composer-section" aria-labelledby="composer-publications-title">
        <h2 id="composer-publications-title">주요 출판물 <span lang="en">Major Publications</span></h2>
        <ul class="composer-bio-list composer-publication-list">
          <li><span>GCM 성가 제2집부터 제20집에 성가합창곡 발표</span><span lang="en">Published hymnals in the GCM hymnbooks 2nd to 20th.</span></li>
          <li><span lang="en">Symphonic Suite, “Four Seasons” (ISBN: 978-11-951355-6-1 03670)</span></li>
          <li><span lang="en">“Holiday” for Wind Orchestra (ISBN: 979-11-951355-5-4 03670)</span></li>
          <li><span lang="en">Symphonic Poem, “The Land of Morning Calm” (ISBN: 978-11-951355-3-0 03670)</span></li>
          <li><span lang="en">Piano Trio No. 2 (ISBN: 978-11-951355-2-3 03670)</span></li>
          <li><span lang="en">“Landscape” for Five Instruments (ISBN: 978-11-951355-2-3 03670)</span></li>
          <li><span lang="en">“The Creation” for Double Chorale and String Orchestra (ISBN: 978-11-951355-1-6 03670)</span></li>
          <li><span>새 찬송가에 신작 찬송가 수록 (344장, 한국찬송가공회)</span><span lang="en">New hymns included in the new hymnbook (chapter 344, Korean Hymn Society)</span></li>
          <li><span>칸타타, 영광영광! <span lang="en">(Cantata, Glory Glory!)</span> (ISSN 979-11-88168-01-9)</span></li>
          <li><span>관현악법의 역사 <span lang="en">(The History of Orchestration)</span> (ISBN: 978-89-90398-72-7)</span></li>
          <li><span lang="en">Symphonic Poem, “The Clouds” (Sheet Music Publication ISBN: 979-11-951355-7-8 93670)</span></li>
        </ul>
      </section>

      <section class="composer-section" aria-labelledby="composer-profile-title">
        <h2 id="composer-profile-title">주요 음악 활동 경력 <span lang="en">Major Profile</span></h2>
        <ul class="composer-bio-list">
          <li><span>중앙음악 콩쿠르 서양음악 작곡 부문 1위 입상 (중앙일보 주최)</span><span lang="en">1st Prize in Western Music Composition at JoongAng Music Concours (Hosted by JoongAng Daily Newspaper)</span></li>
          <li><span>난파음악제 작곡부문 최우수상 수상 (한국예총 주최)</span><span lang="en">Received the grand prize in composition at the Nanpa Music Festival (Hosted by Federation of Korean Arts and Culture Organizations)</span></li>
          <li><span>신인음악회 작품발표 (조선일보 주최)</span><span lang="en">Presentation of new music at the Debut Concert (Hosted by Chosun Daily Newspaper)</span></li>
          <li><span>국립합창단 정기공연에 작품발표 (국립극장, 서울)</span><span lang="en">Presentation of new music at the regular concert of the National Choir (National Theater of Korea, Seooul)</span></li>
          <li><span>KBS 교향악단 새봄맞이 콘서트 작품발표 (호암아트홀, 서울)</span><span lang="en">Presentation of new music at the KBS Symphony Orchestra's New Spring Concert (Hoam Art Hall, Seoul)</span></li>
          <li><span>대한민국 실내악 작곡제전에 작품발표 (예술의 전당, 서울)</span><span lang="en">Presentation of new music at the Korea Chamber Music Composition Festival (Seoul Arts Center)</span></li>
          <li><span>대한민국 관현악축제에 관현악곡 발표 (예술의 전당, 서울)</span><span lang="en">Presentation of orchestral music at the Korean Orchestra Festival (Seoul Arts Center)</span></li>
          <li><span>동아시아 국제음악제에 다수의 관현악곡 발표 (해담콘서트홀, 대구)</span><span lang="en">Presentation of orchestral music at the East Asia International Music Festival (Haedam Concert Hall, Daegu)</span></li>
          <li><span>대전현대음악제에 작품발표 (문화예술의 전당, 대전)</span><span lang="en">Presentation of new music at the Daejeon Contemporary Music Festival (Culture and Arts Center, Daejeon)</span></li>
          <li><span>한·일 창작가곡 교류음악제에 다수의 작품발표 (후쿠오카 시민회관, 일본)</span><span lang="en">Presentation of new music at the Korea-Japan Creative Art Song Festival (Fukuoka Citizen's Hall, Japan)</span></li>
          <li><span>독일 관현악 연주회에 작품발표 (데트몰트 마리엔 경홀, 독일)</span><span lang="en">Presentation of orchestral music at the German Orchestral Concert (St. Marien in Detmold, Germany)</span></li>
          <li><span>독일 크리스마스 콘서트에 작품발표 (벨레르센 마이놀프 컬크 경홀, 독일)</span><span lang="en">Presentation of orchestral music at the German Christmas Concert (St.Meinolfus Kirche in Bellersen, Germany)</span></li>
          <li><span>연세신포니에타 정기연주회 위촉작품 (예술의전당, 서울)</span><span lang="en">Presentation of orchestral music (commissioned work) at the Yonsei Sinfonietta Orchestra's regular concert (Seoul Arts Center)</span></li>
          <li><span>원주시립교향악단 정기연주회 (백운아트홀, 원주)</span><span lang="en">Presentation of orchestral music at the Wonju Symphony Orchestra's regular concert (Baegun Art Hall, Wonju)</span></li>
          <li><span>창악회 50주년 기념음악회 (예술의 전당, 서울)</span><span lang="en">Presentation of new music at the Contemporary Music Society in Seoul's 50th Anniversary concert (Seoul Arts Center)</span></li>
          <li><span>안산시립합창단 연주회에 작품발표 (영산아트홀, 서울)</span><span lang="en">Presentation of choral music at the Ansan City Choir's regular concert (Youngsan Art Hall, Seoul)</span></li>
          <li><span>미국에서 현대음악 세미나 주제발표 (메릴 엘리스 인터미디어 극장, 미국)</span><span lang="en">Presentation of thesis at the Contemporary Music Seminar (The Merrill Ellis Intermedia Theater, USA)</span></li>
          <li><span>UNT Orchestra 정기연주회에 작품발표 (머치슨 연주회장, 미국)</span><span lang="en">Presentation of orchestral music at the UNT Orchestra's regular concert (Murchison Performing Arts Center, USA)</span></li>
          <li><span>인천시립합창단 정기연주회 작품발표 (인천예술회관, 인천)</span><span lang="en">Presentation of new choral music at the Incheon City Choir's regular concert (Arts Center, Incheon)</span></li>
          <li><span>아시아예술학회에 작품발표 (영산아트홀, 서울)</span><span lang="en">Presentation of new music at the Asian Art Society (Youngsan Art Hall, Seoul)</span></li>
          <li><span>MBC 대학가곡제 입선 (리틀엔젤스 회관, 서울)</span><span lang="en">Selected in the MBC College Art Song Festival (Little Angels Hall, Seoul)</span></li>
        </ul>
      </section>
    </div>
  </main>

  <?php require __DIR__ . '/site-footer.php'; ?>
  <?php require __DIR__ . '/auth-modal.php'; ?>
  <script src="<?= $basePath ?>/js/signup-modal.js?v=11" defer></script>
  <script src="<?= $basePath ?>/js/navigation.js?v=2" defer></script>
</body>
</html>
