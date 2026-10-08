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
  <title>News | Hansul Music</title>
  <meta name="description" content="한설뮤직의 새 소식과 대표 작품을 소개합니다.">
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
      <a href="<?= $basePath ?>/news.php" aria-current="page">News</a>
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

  <main class="news-page">
    <div class="wrap">
      <section class="news-heading">
        <div class="section-label">Hansul Music</div>
        <h1>News</h1>
        <p>한설뮤직의 소식과 작품을 주제별로 소개합니다.<br>
          News and selected works from Hansul Music.</p>
      </section>

      <section class="news-section" aria-labelledby="news-updates-title">
        <div class="news-section-heading">
          <div>
            <div class="section-label">Announcements &amp; Education</div>
            <h2 id="news-updates-title">소식 및 교육 안내</h2>
            <p>출간 소식과 작곡 레슨 정보를 확인하세요.<br>
              Book announcements and composition lesson information.</p>
          </div>
        </div>
        <div class="news-card-grid news-announcement-grid">
          <article class="news-card">
            <span class="news-card-label">Book · 출간</span>
            <h3>새책을 출간하였습니다 !!!<br><span lang="en">(A new book has been published.)</span></h3>
            <p>제목 : 관현악법의 역사</p>
            <p lang="en">Title : The History of Orchestration</p>
            <div class="news-book-carousel" data-book-carousel aria-label="관현악법의 역사 책 표지">
              <img
                class="news-book-image"
                src="<?= $basePath ?>/images/관현악법의역사1.png"
                alt="관현악법의 역사 책 표지 1"
                data-covers="<?= escape(json_encode([
                    $basePath . '/images/관현악법의역사1.png',
                    $basePath . '/images/관현악법의역사2.png',
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>"
                data-cover-index="0"
                loading="lazy"
                decoding="async">
            </div>
            <p>바로크시대부터 현대에 이르기까지 관현악법의 역사적 변천 과정을 서술하였습니다.</p>
            <p lang="en">(It describes the historical transition process of orchestration techniques from the Baroque period to the present day.)</p>
            <p class="news-card-detail">기독언어문화사 출판 · 정가 $20 · 할인가 $10<br>
              Published by Christian Language and Culture Publishing · List price $20 · Sale price $10</p>
          </article>
          <article class="news-card">
            <span class="news-card-label">Lessons · 레슨</span>
            <h3>고3 작곡과 입시 마무리 레슨 합니다!<br><span lang="en">(Final Composition Lessons for College Entrance Exams.)</span></h3>
            <ol class="news-lesson-list">
              <li>화성학이론을 총정리 하고,</li>
              <li>기출문제 위주로 청음을 훈련하며 (단성, 2성, 4성),</li>
              <li>여러유형의 모티브를 발전시키는 법을 터득한다.</li>
            </ol>
            <p><strong>최근 서울대·연세대·이화여대 작곡과에 각 1~2명이 입학했습니다.</strong></p>
            <p>(원거리 학생은 온라인 레슨도 가능합니다.)</p>
            <p lang="en">Lessons for The Harmony, Ear-Training, Motivic development techniques and so on.</p>
          </article>
        </div>
      </section>

      <section class="news-section" aria-labelledby="news-works-title">
        <div class="news-section-heading">
          <div>
            <div class="section-label">Music Catalogue</div>
            <h2 id="news-works-title">음악 작품</h2>
            <p>편곡 작품과 직접 작곡한 성악·현대음악을 장르별로 소개합니다.<br>
              Arrangements and original vocal and contemporary compositions, grouped by genre.</p>
          </div>
        </div>
        <div class="news-card-grid">
          <article class="news-card">
            <span class="news-card-label">Arrangements · 편곡</span>
            <h3>대표 편곡 음악</h3>
            <p>한상은 편곡 대표 음악</p>
            <p lang="en">Representative Music Arranged by Sangeun Han</p>
            <ul class="news-work-list">
              <li>나의 찬양 <span>My Tribute</span><a class="news-work-link" href="https://youtu.be/j_C8GUvrNso" aria-label="나의 찬양 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>아침이슬 <span>Morning Dew</span><a class="news-work-link" href="https://www.youtube.com/watch?v=MRMcjcIOcTw&amp;t=1s" aria-label="아침이슬 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>I SEE 흥 <span>Siheung city theme song</span><a class="news-work-link" href="https://www.youtube.com/watch?v=_auDi-7oltY&amp;t=1s" aria-label="I SEE 흥 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>임을위한 행진곡 <span>March for People</span><a class="news-work-link" href="https://www.youtube.com/watch?v=nfwe3OZCn28&amp;t=4s" aria-label="임을위한 행진곡 영상 모달에서 감상">▶ 감상 / Watch</a></li>
            </ul>
          </article>
          <article class="news-card">
            <span class="news-card-label">Vocal Compositions · 성악곡</span>
            <h3>한국 가곡</h3>
            <p>한상은 작곡 대표 성악곡 (한국가곡)</p>
            <p lang="en">Representative Vocal Music Composed by Dr. Sangeun Han</p>
            <ul class="news-work-list">
              <li>그리움 <span>Nostalgic Sweetness</span><a class="news-work-link" href="https://www.youtube.com/watch?v=FcvnPlXvefI" aria-label="그리움 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>마지막 사랑 <span>The Last Love</span><a class="news-work-link" href="https://www.youtube.com/watch?v=SS3pXIHGl1g&amp;t=5s" aria-label="마지막 사랑 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>가는 길 <span>The Way I am Going</span><a class="news-work-link" href="https://youtu.be/vVDwVAxcj8I" aria-label="가는 길 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>가을의 기도 <span>Autumn Prayer</span><a class="news-work-link" href="https://www.youtube.com/watch?v=jeCSSCV9ITM&amp;t=0s" aria-label="가을의 기도 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>신마산 연애다리 <span>The Sinmasan Love Bridge</span><a class="news-work-link" href="https://www.youtube.com/watch?v=UCYMDVun2X0&amp;t=2s" aria-label="신마산 연애다리 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>당신만 보면 <span>When I See You</span><a class="news-work-link" href="https://www.youtube.com/watch?v=4LVlBw8W3bs" aria-label="당신만 보면 영상 모달에서 감상">▶ 감상 / Watch</a></li>
            </ul>
          </article>
          <article class="news-card">
            <span class="news-card-label">Contemporary Compositions · 현대음악</span>
            <h3>기악 · 합창 작품</h3>
            <p>한상은 작곡 대표 현대음악</p>
            <p lang="en">Representative Contemporary Music Composed by Dr. Sangeun Han</p>
            <ul class="news-work-list">
              <li>환상곡 <span>Fantasia for Piano Solo</span><a class="news-work-link" href="https://youtu.be/nkOp4VZtDOk" aria-label="환상곡 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>시편 23편 <span>Psalms 23 for Choir</span><a class="news-work-link" href="https://youtu.be/_lgaC1KMxzI" aria-label="시편 23편 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>풍경 <span>Landscape for Piano Solo</span><a class="news-work-link" href="https://youtu.be/0fTAFGd-czM" aria-label="풍경 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>주의 사랑 <span>Love of My Lord for Choir</span><a class="news-work-link" href="https://www.youtube.com/watch?v=0BakAcaFGC0" aria-label="주의 사랑 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>진혼곡 <span>Requiem for Violin and Cello</span><a class="news-work-link" href="https://youtu.be/ripELPFLBHw" aria-label="진혼곡 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>경치 <span>Scenery for Piano Solo</span><a class="news-work-link" href="https://youtu.be/jFdLz9V7yYc" aria-label="경치 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>낮과 밤 <span>Day and Night for Chamber Orchestra</span><a class="news-work-link" href="https://youtu.be/0PUKrtNwshc" aria-label="낮과 밤 영상 모달에서 감상">▶ 감상 / Watch</a></li>
              <li>고요한 아침의 나라 <span>The Land of Morning Calm for Orchestra</span><a class="news-work-link" href="https://youtu.be/GWMxZGTX89k" aria-label="고요한 아침의 나라 영상 모달에서 감상">▶ 감상 / Watch</a></li>
            </ul>
          </article>
        </div>
      </section>
    </div>
  </main>

  <?php require __DIR__ . '/site-footer.php'; ?>

  <dialog class="concert-player" aria-label="YouTube 영상 플레이어">
    <div class="concert-player-content">
      <button class="concert-player-close" type="button" aria-label="영상 닫기">&times;</button>
      <div class="concert-player-frame"></div>
    </div>
  </dialog>

  <?php require __DIR__ . '/auth-modal.php'; ?>
  <script src="<?= $basePath ?>/js/signup-modal.js?v=11" defer></script>
  <script src="<?= $basePath ?>/js/navigation.js" defer></script>
  <script src="<?= $basePath ?>/js/news.js" defer></script>
  <script src="<?= $basePath ?>/js/concert.js?v=6" defer></script>
</body>
</html>
