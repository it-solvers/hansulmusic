<?php
declare(strict_types=1);

$products = require __DIR__ . '/products.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function productAssetExists(?string $path): bool
{
    return $path !== null && is_file(__DIR__ . '/' . ltrim($path, '/'));
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>악보 · 음원 구매 | HANSUL MUSIC</title>
  <meta name="description" content="한설뮤직의 악보와 음원을 미리 확인하고 구매할 수 있습니다.">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Noto+Serif+KR:wght@400;500;600&display=swap');
    :root { --bg:#f7f5f1; --paper:#fff; --ink:#2a2a28; --soft:#5c5a55; --muted:#8a877f; --line:#e4e0d8; --accent:#a68b5b; --dark:#2c2b29; }
    * { box-sizing:border-box; }
    body { margin:0; color:var(--ink); background:var(--bg); font-family:Inter,'Noto Sans KR',sans-serif; -webkit-font-smoothing:antialiased; }
    a { color:inherit; text-decoration:none; }
    .wrap { width:min(1080px, calc(100% - 48px)); margin:0 auto; }
    header { height:76px; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .logo { font-size:15px; font-weight:600; letter-spacing:.14em; }
    .back { font-size:12px; color:var(--soft); }
    main { padding:68px 0 100px; }
    .eyebrow,.kind { color:var(--accent); font-size:10px; letter-spacing:.16em; text-transform:uppercase; }
    h1,h2,h3,p { margin-top:0; }
    h1 { margin:12px 0 14px; font:400 clamp(32px,5vw,48px)/1.3 'Noto Serif KR',serif; }
    .intro { max-width:620px; margin-bottom:38px; color:var(--soft); font-size:14px; line-height:1.8; }
    .tabs { display:flex; gap:8px; border-bottom:1px solid var(--line); margin-bottom:28px; }
    .tab { border:0; border-bottom:2px solid transparent; padding:12px 14px; margin-bottom:-1px; background:transparent; color:var(--muted); font:inherit; font-size:13px; cursor:pointer; }
    .tab[aria-selected="true"] { border-bottom-color:var(--ink); color:var(--ink); }
    .catalog { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
    .product { min-width:0; border:1px solid var(--line); background:var(--paper); padding:20px; }
    .product[hidden] { display:none; }
    .cover { height:190px; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:10px; margin-bottom:20px; background:#efede7; border:1px solid var(--line); color:var(--muted); text-align:center; }
    .cover img { width:100%; height:100%; object-fit:cover; }
    .score-viewer { margin-bottom:24px; background:#a6dcef; }
    .score-stage { height:330px; padding:12px; display:flex; align-items:center; justify-content:center; }
    .score-current-link { width:100%; height:100%; display:flex; align-items:center; justify-content:center; }
    .score-current { display:block; max-width:100%; max-height:100%; width:auto; height:auto; object-fit:contain; box-shadow:0 2px 14px rgba(29,62,76,.16); }
    .score-controls { display:flex; align-items:center; gap:12px; padding:0 14px 14px; }
    .score-thumbnails { display:flex; flex:1; min-width:0; justify-content:center; gap:8px; overflow-x:auto; padding:2px; }
    .score-thumb { flex:0 0 52px; width:52px; height:68px; padding:0; border:2px solid transparent; background:#fff; cursor:pointer; }
    .score-thumb[aria-pressed="true"] { border-color:var(--ink); }
    .score-thumb img { display:block; width:100%; height:100%; object-fit:contain; }
    .score-nav { flex:0 0 36px; width:36px; height:48px; border:0; background:transparent; color:var(--ink); font:32px/1 Georgia,serif; cursor:pointer; }
    .score-nav:focus-visible,.score-thumb:focus-visible { outline:2px solid var(--ink); outline-offset:2px; }
    .cover-mark { font:36px Georgia,serif; color:var(--accent); }
    .cover-note { font-size:9px; letter-spacing:.16em; }
    .kind { margin-bottom:8px; }
    h2 { margin-bottom:4px; font:400 22px/1.45 'Noto Serif KR',serif; }
    .subtitle { margin-bottom:12px; color:var(--muted); font-size:11px; letter-spacing:.04em; }
    .description { min-height:44px; margin-bottom:14px; color:var(--soft); font-size:13px; line-height:1.7; }
    audio { display:block; width:100%; height:40px; margin:4px 0 14px; }
    .preview-limit { margin:0 0 14px; color:var(--muted); font-size:11px; }
    .preview-missing { min-height:40px; margin:4px 0 14px; color:var(--muted); font-size:12px; }
    .purchase { display:flex; align-items:center; justify-content:space-between; gap:12px; padding-top:14px; border-top:1px solid var(--line); }
    .price { font-size:14px; font-weight:600; }
    .price.pending { color:var(--muted); font-size:12px; font-weight:400; }
    .buy { display:inline-block; border:0; padding:11px 16px; background:var(--dark); color:#fff; font:inherit; font-size:12px; text-align:center; }
    .notice { margin-top:24px; color:var(--muted); font-size:11px; line-height:1.7; }
    @media (max-width:900px) { .catalog{grid-template-columns:repeat(2,minmax(0,1fr))} }
    @media (max-width:680px) { .wrap{width:calc(100% - 32px)} header{height:64px} main{padding:48px 0 72px} .catalog{grid-template-columns:1fr} .cover{height:210px} .score-stage{height:min(110vw,420px);min-height:300px} }
  </style>
</head>
<body>
  <div class="wrap">
    <header>
      <a class="logo" href="index.html">HANSUL MUSIC</a>
      <a class="back" href="index.html">메인으로 돌아가기</a>
    </header>
    <main>
      <div class="eyebrow">Scores &amp; Recordings</div>
      <h1>악보와 음원</h1>
      <p class="intro">상품별 구매 문의를 보내 주세요. 판매 가격과 파일 제공 방법은 이메일로 안내해 드립니다.</p>
      <div class="tabs" role="tablist" aria-label="상품 종류">
        <button class="tab" type="button" role="tab" aria-selected="true" data-filter="score">악보</button>
        <button class="tab" type="button" role="tab" aria-selected="false" data-filter="audio">음원</button>
      </div>
      <section class="catalog" aria-label="상품 목록">
        <?php foreach ($products as $product): ?>
          <?php $isAudio = $product['type'] === 'audio'; ?>
          <?php
            $subject = '[한설뮤직] 구매 문의 - ' . $product['name'];
            $body = implode("\n", [
                '상품명: ' . $product['name'],
                '유형: ' . ($isAudio ? '음원' : '악보'),
                '가격: ' . (is_int($product['price_krw']) ? number_format($product['price_krw']) . '원' : '문의'),
                '',
                '성함:',
                '연락처:',
                '문의 내용:',
            ]);
            $inquiryUrl = 'mailto:sangeun@hansulmusic.com?' . http_build_query(
                ['subject' => $subject, 'body' => $body],
                '',
                '&',
                PHP_QUERY_RFC3986
            );
          ?>
          <article class="product" data-type="<?= escape($product['type']) ?>"<?= $isAudio ? ' hidden' : '' ?>>
            <?php if (!$isAudio && !empty($product['preview_images'])): ?>
              <div class="score-viewer" data-score-viewer>
                <div class="score-stage">
                  <a class="score-current-link" href="<?= escape($product['preview_images'][0]['path']) ?>" target="_blank" rel="noopener noreferrer" data-score-current-link>
                    <img class="score-current" src="<?= escape($product['preview_images'][0]['path']) ?>" alt="<?= escape($product['name']) ?> <?= escape($product['preview_images'][0]['label']) ?> 미리보기" data-score-current>
                  </a>
                </div>
                <div class="score-controls">
                  <button class="score-nav" type="button" data-score-previous aria-label="이전 악보 페이지">&lsaquo;</button>
                  <div class="score-thumbnails" role="group" aria-label="악보 페이지 선택">
                    <?php foreach ($product['preview_images'] as $index => $page): ?>
                      <?php if (productAssetExists($page['path'])): ?>
                        <button class="score-thumb" type="button" data-score-page data-src="<?= escape($page['path']) ?>" data-alt="<?= escape($product['name']) ?> <?= escape($page['label']) ?> 미리보기" aria-label="<?= escape($page['label']) ?> 보기" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>">
                          <img src="<?= escape($page['path']) ?>" alt="" loading="lazy">
                        </button>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                  <button class="score-nav" type="button" data-score-next aria-label="다음 악보 페이지">&rsaquo;</button>
                </div>
              </div>
            <?php else: ?>
              <div class="cover">
                <?php if (productAssetExists($product['cover'])): ?>
                  <img src="<?= escape($product['cover']) ?>" alt="<?= escape($product['name']) ?> 표지">
              <?php else: ?>
                <span class="cover-mark"><?= $isAudio ? '♪' : '𝄞' ?></span>
                <span class="cover-note"><?= $isAudio ? 'AUDIO · PREVIEW AVAILABLE' : 'SHEET MUSIC · COVER IMAGE NEEDED' ?></span>
              <?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="kind"><?= $isAudio ? 'Digital Audio' : 'Digital Score' ?></div>
            <h2><?= escape($product['name']) ?></h2>
            <div class="subtitle"><?= escape($product['subtitle']) ?></div>
            <p class="description"><?= escape($product['description']) ?></p>
            <?php if ($isAudio): ?>
              <?php if (productAssetExists($product['preview'])): ?>
                <audio controls preload="none" controlsList="nodownload" data-preview-limit="60" src="<?= escape($product['preview']) ?>">브라우저에서 오디오 재생을 지원하지 않습니다.</audio>
                <p class="preview-limit">미리듣기 1분</p>
              <?php else: ?>
                <div class="preview-missing">미리듣기 음원 등록 예정</div>
              <?php endif; ?>
            <?php endif; ?>
            <div class="purchase">
              <?php if (is_int($product['price_krw']) && $product['price_krw'] > 0): ?>
                <span class="price"><?= number_format($product['price_krw']) ?>원</span>
              <?php else: ?>
                <span class="price pending">가격 문의</span>
              <?php endif; ?>
              <a class="buy" href="<?= escape($inquiryUrl) ?>">이메일로 구매 문의</a>
            </div>
          </article>
        <?php endforeach; ?>
      </section>
      <p class="notice">구매 후 다운로드되는 파일은 저작권자의 허락 없이 복제하거나 재배포할 수 없습니다.</p>
    </main>
  </div>
  <script>
    document.querySelectorAll('[data-score-viewer]').forEach((viewer) => {
      const pages = [...viewer.querySelectorAll('[data-score-page]')];
      const currentImage = viewer.querySelector('[data-score-current]');
      const currentLink = viewer.querySelector('[data-score-current-link]');
      let activeIndex = 0;

      const showPage = (requestedIndex) => {
        activeIndex = (requestedIndex + pages.length) % pages.length;
        const page = pages[activeIndex];
        currentImage.src = page.dataset.src;
        currentImage.alt = page.dataset.alt;
        currentLink.href = page.dataset.src;
        pages.forEach((item, index) => item.setAttribute('aria-pressed', String(index === activeIndex)));
        page.scrollIntoView({ block: 'nearest', inline: 'nearest' });
      };

      pages.forEach((page, index) => page.addEventListener('click', () => showPage(index)));
      viewer.querySelector('[data-score-previous]').addEventListener('click', () => showPage(activeIndex - 1));
      viewer.querySelector('[data-score-next]').addEventListener('click', () => showPage(activeIndex + 1));
      viewer.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') showPage(activeIndex - 1);
        if (event.key === 'ArrowRight') showPage(activeIndex + 1);
      });
    });

    document.querySelectorAll('audio[data-preview-limit]').forEach((player) => {
      const limit = Number(player.dataset.previewLimit);

      player.addEventListener('timeupdate', () => {
        if (player.currentTime >= limit) {
          player.pause();
          player.currentTime = limit;
        }
      });

      player.addEventListener('seeking', () => {
        if (player.currentTime > limit) player.currentTime = limit;
      });

      player.addEventListener('play', () => {
        if (player.currentTime >= limit) player.currentTime = 0;
      });
    });

    document.querySelectorAll('.tab').forEach((tab) => {
      tab.addEventListener('click', () => {
        const filter = tab.dataset.filter;
        document.querySelectorAll('.tab').forEach((item) => item.setAttribute('aria-selected', String(item === tab)));
        document.querySelectorAll('.product').forEach((product) => {
          product.hidden = filter !== 'all' && product.dataset.type !== filter;
        });
      });
    });
  </script>
</body>
</html>