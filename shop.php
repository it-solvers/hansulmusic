<?php
declare(strict_types=1);

require_once __DIR__ . '/site-config.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function productAssetExists(?string $path): bool
{
    return $path !== null && is_file(__DIR__ . '/' . ltrim($path, '/'));
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

$products = [];
$loadError = false;
try {
    $pdo = databaseConnection();
    $productRows = $pdo->query(
        'SELECT product_id, product_type, name, subtitle, description,
                regular_price_krw, sale_price_krw, cover_path,
                preview_audio_path, download_path
         FROM products
         WHERE is_active = 1
         ORDER BY sort_order ASC, product_id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($productRows as $row) {
        $productId = (int) $row['product_id'];
        $products[$productId] = [
            'id' => $productId,
            'type' => (int) $row['product_type'],
            'name' => (string) $row['name'],
            'subtitle' => (string) $row['subtitle'],
            'description' => (string) $row['description'],
            'regular_price_krw' => (int) $row['regular_price_krw'],
            'sale_price_krw' => (int) $row['sale_price_krw'],
            'cover' => $row['cover_path'] !== null ? (string) $row['cover_path'] : null,
            'preview' => $row['preview_audio_path'] !== null ? (string) $row['preview_audio_path'] : null,
            'download_file' => (string) $row['download_path'],
            'preview_images' => [],
        ];
    }

    if ($products !== []) {
        $assetStatement = $pdo->prepare(
            "SELECT product_id, asset_path, label
             FROM product_assets
             WHERE asset_type = 'preview_image'
             ORDER BY sort_order ASC, asset_id ASC"
        );
        $assetStatement->execute();
        foreach ($assetStatement->fetchAll(PDO::FETCH_ASSOC) as $asset) {
            $productId = (int) $asset['product_id'];
            if (isset($products[$productId])) {
                $products[$productId]['preview_images'][] = [
                    'path' => (string) $asset['asset_path'],
                    'label' => (string) $asset['label'],
                ];
            }
        }
    }
} catch (PDOException | RuntimeException $exception) {
    error_log('Shop products database error: ' . $exception->getMessage());
    http_response_code(503);
    $loadError = true;
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>악보 · 음원 구매 | HANSUL MUSIC</title>
  <link rel="stylesheet" href="css/style.css?v=21">
  <link rel="stylesheet" href="css/register.css?v=6">
  <meta name="description" content="한설뮤직의 악보와 음원을 미리 확인하고 구매할 수 있습니다.">
  <style>
    :root { --bg:#f7f5f1; --paper:#fff; --card:#fff; --ink:#2a2a28; --soft:#5c5a55; --muted:#8a877f; --line:#e4e0d8; --accent:#a68b5b; --dark:#2c2b29; }
    * { box-sizing:border-box; }
    body { margin:0; color:var(--ink); background:var(--bg); font-family:Inter,'Noto Sans KR',sans-serif; -webkit-font-smoothing:antialiased; }
    a { color:inherit; text-decoration:none; }
    .wrap { width:min(1080px, calc(100% - 48px)); margin:0 auto; }
    main { padding:68px 0 100px; }
    .eyebrow,.kind { color:var(--accent); font-size:10px; letter-spacing:.06em; }
    h1,h2,h3,p { margin-top:0; }
    h1 { margin:12px 0 14px; font:400 clamp(32px,5vw,48px)/1.3 'Noto Serif KR',serif; }
    .purchase-info { display:flex; flex-wrap:wrap; align-items:center; gap:4px 12px; margin:0 0 24px; padding:10px 14px; border-left:2px solid var(--accent); background:rgba(255,255,255,.58); color:var(--soft); font-size:12px; line-height:1.5; }
    .purchase-info strong { color:var(--ink); font-weight:500; }
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
    .product-prices { display:grid; gap:3px; font-size:12px; white-space:nowrap; }
    .regular-price { color:var(--muted); }
    .sale-price { color:#9b3f32; font-size:14px; font-weight:600; }
    .product-actions { display:flex; gap:6px; }
    .product-actions .buy,.download { padding:10px 12px; cursor:pointer; }
    .download { border:1px solid var(--line); background:#fff; color:var(--muted); font:inherit; font-size:12px; }
    .download.is-disabled { cursor:not-allowed; opacity:.55; pointer-events:none; }
    .download:not(.is-disabled) { border-color:var(--dark); background:var(--dark); color:#fff; font-weight:500; }
    .download:not(.is-disabled):hover { background:#45433f; }
    .purchase-dialog { width:min(420px,calc(100% - 32px)); padding:30px; border:1px solid var(--line); background:var(--paper); color:var(--ink); text-align:center; }
    .purchase-dialog::backdrop { background:rgba(24,23,21,.56); }
    .purchase-dialog h2 { margin-bottom:10px; }
    .purchase-dialog p { margin-bottom:20px; color:var(--soft); font-size:13px; line-height:1.7; }
    .purchase-dialog button { border:0; padding:11px 24px; background:var(--dark); color:#fff; font:inherit; font-size:13px; cursor:pointer; }
    .notice { margin-top:24px; color:var(--muted); font-size:11px; line-height:1.7; }
    @media (max-width:900px) { .catalog{grid-template-columns:repeat(2,minmax(0,1fr))} }
    @media (max-width:680px) { .wrap{width:calc(100% - 32px)} main{padding:48px 0 72px} .catalog{grid-template-columns:1fr} .cover{height:210px} .score-stage{height:min(110vw,420px);min-height:300px} }
  </style>
</head>
<body>
  <header class="site-header">
    <a class="logo" href="index.php">HANSUL MUSIC<small>HSM · MUSIC STUDIO</small></a>
    <button class="menu-toggle" type="button" aria-label="메뉴 열기" aria-expanded="false" aria-controls="site-nav">
      <span></span>
      <span></span>
      <span></span>
    </button>
    <nav class="site-nav" id="site-nav">
      <a href="concert.php">Concert</a>
      <a href="news.php">News</a>
      <a href="shop.php" aria-current="page">Scores &amp; Recordings</a>
      <a href="index.php#contact">Commission</a>
      <a href="youtube.php">YouTube</a>
      <a href="hymnal-parts.php">Hymnal (찬송가)</a>
      <a href="praise-song-parts.php">Praise Song (찬양곡)</a>
      <div class="nav-account">
        <button class="signup-nav-button" type="button" data-open-auth>Sign in</button>
        <button class="nav-member-identity" type="button" data-member-identity hidden disabled></button>
      </div>
    </nav>
  </header>
  <div class="wrap">
    <main>
      <div class="eyebrow">Scores &amp; Recordings</div>
      <h1>악보와 음원</h1>
      <div class="purchase-info" aria-label="구매 및 소식 안내">
        <strong>회원·비회원 모두 구매 가능</strong>
        <span>신작·이벤트 소식은 수신 동의 회원에게 이메일로 안내합니다.</span>
      </div>
      <div class="tabs" role="tablist" aria-label="Product type">
        <button class="tab" type="button" role="tab" aria-selected="true" data-filter="1">Scores</button>
        <button class="tab" type="button" role="tab" aria-selected="false" data-filter="2">Records</button>
      </div>
      <section class="catalog" aria-label="상품 목록">
        <?php if ($loadError): ?>
          <p class="concert-error" role="alert">상품 목록을 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.</p>
        <?php elseif ($products === []): ?>
          <p class="concert-empty">현재 진열 중인 상품이 없습니다.</p>
        <?php endif; ?>
        <?php foreach ($products as $product): ?>
          <?php $isAudio = $product['type'] === 2; ?>
          <article class="product" id="product-<?= $product['id'] ?>" data-type="<?= escape((string) $product['type']) ?>"<?= $isAudio ? ' hidden' : '' ?>>
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
                <span class="cover-note"><?= $isAudio ? 'Audio · Preview available' : 'Sheet music · Cover image needed' ?></span>
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
              <div class="product-prices">
                <span class="regular-price">정가 <s><?= number_format($product['regular_price_krw']) ?>원</s></span>
                <span class="sale-price">할인가 <?= number_format($product['sale_price_krw']) ?>원</span>
              </div>
              <div class="product-actions">
                <button class="buy" type="button" data-purchase-button>구매하기</button>
                <a
                  class="download is-disabled"
                  href="<?= escape($product['download_file']) ?>"
                  download
                  aria-disabled="true"
                  tabindex="-1"
                  data-download-link>다운받기</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </section>
      <p class="notice">구매 후 다운로드되는 파일은 저작권자의 허락 없이 복제하거나 재배포할 수 없습니다.</p>
    </main>
  </div>
  <?php require __DIR__ . '/site-footer.php'; ?>

  <dialog class="purchase-dialog" aria-labelledby="purchase-dialog-title" aria-describedby="purchase-dialog-message" data-purchase-dialog>
    <h2 id="purchase-dialog-title">구매해 주셔서 감사합니다</h2>
    <p id="purchase-dialog-message">테스트 구매가 완료되었습니다. 실제 결제는 진행되지 않았으며, 구매한 상품을 다운로드할 수 있습니다.</p>
    <button type="button" data-close-purchase-dialog>확인</button>
  </dialog>
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

    const requestedProductId = new URLSearchParams(window.location.search).get('product_id');
    const requestedProduct = requestedProductId
      ? document.getElementById(`product-${requestedProductId}`)
      : null;
    // 요청한 상품이 목록에 있을 때 해당 상품의 분류를 열고 위치로 이동합니다.
    if (requestedProduct instanceof HTMLElement) {
      const productTab = document.querySelector(`.tab[data-filter="${requestedProduct.dataset.type}"]`);
      // 상품 분류 탭을 찾은 경우에만 클릭해 목록 필터를 적용합니다.
      if (productTab instanceof HTMLButtonElement) productTab.click();
      requestedProduct.scrollIntoView({ block: 'center' });
    }

    const purchaseDialog = document.querySelector('[data-purchase-dialog]');
    let completedProduct = null;

    document.querySelectorAll('[data-purchase-button]').forEach((button) => {
      button.addEventListener('click', () => {
        const product = button.closest('.product');
        if (!(purchaseDialog instanceof HTMLDialogElement) || !(product instanceof HTMLElement)) {
          throw new Error('구매 완료 안내를 열 수 없습니다.');
        }
        completedProduct = product;
        purchaseDialog.showModal();
      });
    });

    const enableCompletedDownload = () => {
      if (!(completedProduct instanceof HTMLElement)) return;
      const downloadLink = completedProduct.querySelector('[data-download-link]');
      const purchaseButton = completedProduct.querySelector('[data-purchase-button]');
      if (downloadLink instanceof HTMLAnchorElement) {
        downloadLink.classList.remove('is-disabled');
        downloadLink.removeAttribute('aria-disabled');
        downloadLink.removeAttribute('tabindex');
      }
      if (purchaseButton instanceof HTMLButtonElement) {
        purchaseButton.textContent = '구매 완료';
        purchaseButton.disabled = true;
      }
      completedProduct = null;
    };

    document.querySelector('[data-close-purchase-dialog]')?.addEventListener('click', () => {
      enableCompletedDownload();
      if (purchaseDialog instanceof HTMLDialogElement) purchaseDialog.close();
    });

    purchaseDialog?.addEventListener('close', enableCompletedDownload);
  </script>
  <?php require __DIR__ . '/auth-modal.php'; ?>
  <script src="js/signup-modal.js?v=11" defer></script>
  <script src="js/navigation.js?v=2" defer></script>
</body>
</html>