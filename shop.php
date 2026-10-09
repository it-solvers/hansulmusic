<?php
declare(strict_types=1);

// [1단계] 요청 환경과 공통 페이지 설정을 준비합니다.
require_once __DIR__ . '/site-config.php';
require_once __DIR__ . '/product-localization.php';

$shopIsEnglish = $shopIsEnglish ?? false;
// [2단계] 언어별 상점 주소를 구성하고, 설치 경로가 루트일 때는 빈 경로로 정리합니다.
$shopBasePath = $shopIsEnglish ? dirname(appBasePath()) : appBasePath();
if ($shopBasePath === '/' || $shopBasePath === '.') {
    $shopBasePath = '';
}
$shopBasePath = escape($shopBasePath);
$authFormBasePath = $shopBasePath;

// [2단계] 템플릿 출력과 상품 자산 확인에 사용하는 공통 도구입니다.
/** 상품 문구를 HTML에 안전하게 출력하도록 이스케이프합니다. */
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 상품 자산 경로가 지정되어 있고 실제 파일이 존재하는지 확인합니다. */
function productAssetExists(?string $path): bool
{
    return $path !== null && is_file(__DIR__ . '/' . ltrim($path, '/'));
}

/** 미리보기 페이지 라벨을 현재 상점 언어에 맞게 반환합니다. */
function shopPageLabel(string $label, bool $isEnglish): string
{
    // [3단계] 영문 화면에서는 페이지 번호를 영어 표기로 변환합니다.
    if ($isEnglish && preg_match('/^(\d+)쪽$/u', $label, $matches) === 1) {
        return 'Page ' . $matches[1];
    }
    return $label;
}

// [2단계] DB 설정을 검증하고 예외 발생 시 명확히 실패하도록 연결을 생성합니다.
/** 필수 DB 환경 변수를 검증하고 상점 카탈로그용 PDO 연결을 반환합니다. */
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

// [1단계] 활성 상품과 미리보기 자산을 불러와 페이지 렌더링용 데이터로 만듭니다.
$products = [];
$loadError = false;
$selectedProductId = filter_var($_GET['product_id'] ?? null, FILTER_VALIDATE_INT);
$selectedProduct = null;
try {
    // [2단계] 상품 기본 정보와 언어별 표시 문구를 조회합니다.
    $pdo = databaseConnection();
    // [3단계] products에서 활성 상품의 이름·가격·자산 경로를 가져와 $products에 담습니다.
    // 아래 카탈로그 반복문이 이 데이터를 상품 설명·가격·구매 동작으로 출력합니다.
    // 영문 페이지에서는 product_type(1=악보, 2=음원)으로 번역 문구를 찾습니다.
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
        $copy = $shopIsEnglish
            ? englishProductCopy(
                (int) $row['product_type'],
                (string) $row['name'],
                (string) $row['subtitle'],
                (string) $row['description'],
            )
            : [
                'name' => (string) $row['name'],
                'subtitle' => (string) $row['subtitle'],
                'description' => (string) $row['description'],
            ];
        $products[$productId] = [
            'id' => $productId,
            'type' => (int) $row['product_type'],
            'name' => $copy['name'],
            'subtitle' => $copy['subtitle'],
            'description' => $copy['description'],
            'regular_price_krw' => (int) $row['regular_price_krw'],
            'sale_price_krw' => (int) $row['sale_price_krw'],
            'cover' => $row['cover_path'] !== null ? (string) $row['cover_path'] : null,
            'preview' => $row['preview_audio_path'] !== null ? (string) $row['preview_audio_path'] : null,
            'download_file' => (string) $row['download_path'],
            'preview_images' => [],
        ];
    }

    // [3단계] 영문 상세 주소에 유효한 상품 ID가 있을 때만 상세 상품을 선택합니다.
    if ($shopIsEnglish && $selectedProductId !== false && $selectedProductId !== null) {
        $selectedProduct = $products[$selectedProductId] ?? null;
    }

    // [2단계] 카탈로그에 포함된 상품의 악보 미리보기 페이지를 연결합니다.
    if ($products !== []) {
        // [3단계] product_assets에서 악보 이미지 경로와 페이지 라벨을 읽어 각 상품의 preview_images에 추가합니다.
        // 이 배열은 카드 미리보기와 확대 팝업의 페이지 순서를 함께 제공합니다.
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
    // [3단계] DB 오류는 로그에 남기고 서비스 불가 상태를 페이지에 전달합니다.
    error_log('Shop products database error: ' . $exception->getMessage());
    http_response_code(503);
    $loadError = true;
}
?>
<!-- [1단계] 상점 문서와 언어별 공통 화면을 출력합니다. -->
<!doctype html>
<html lang="<?= $shopIsEnglish ? 'en' : 'ko' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $shopIsEnglish ? 'Scores &amp; Recordings | HANSUL MUSIC' : '악보 · 음원 구매 | HANSUL MUSIC' ?></title>
  <link rel="stylesheet" href="<?= $shopBasePath ?>/css/style.css?v=34">
  <link rel="stylesheet" href="<?= $shopBasePath ?>/css/register.css?v=6">
  <meta name="description" content="<?= $shopIsEnglish ? 'Browse digital scores and recordings from HANSUL MUSIC. USD prices are estimates based on the latest available KRW/USD reference rate.' : '한설뮤직의 악보와 음원을 미리 확인하고 구매할 수 있습니다.' ?>">
  <link rel="canonical" href="https://www.hansulmusic.com<?= $shopIsEnglish ? '/en/shop.php' : '/shop.php' ?>">
  <link rel="alternate" hreflang="en" href="https://www.hansulmusic.com/en/shop.php">
  <link rel="alternate" hreflang="ko" href="https://www.hansulmusic.com/shop.php">
  <link rel="alternate" hreflang="x-default" href="https://www.hansulmusic.com/shop.php">
  <style>
    /* [1단계] 상점 전용 레이아웃과 상품 표시 스타일 */
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
    .paypal-fee-source { margin:-20px 0 26px; font-size:11px; }
    .paypal-fee-source a { color:var(--accent); text-decoration:underline; text-underline-offset:3px; }
    .paypal-fee-estimate { color:var(--muted); font-size:10px; font-weight:400; }
    .order-process { margin:0 0 36px; padding:24px; border:1px solid var(--line); background:var(--paper); }
    .order-process h2 { margin:0 0 18px; font:400 22px/1.4 'Noto Serif KR',serif; }
    .order-process ol { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin:0; padding:0; list-style:none; counter-reset:order-step; }
    .order-process.is-english ol { grid-template-columns:minmax(0,1fr); gap:20px; }
    .order-process li { display:flex; flex-direction:column; gap:8px; min-width:0; counter-increment:order-step; }
    .order-process li::before { content:counter(order-step,decimal-leading-zero); color:var(--accent); font-size:12px; letter-spacing:.08em; }
    .order-process li strong { font-size:13px; font-weight:600; }
    .order-process li span { color:var(--soft); font-size:12px; line-height:1.7; }
    .order-process.is-english li { display:grid; grid-template-columns:30px minmax(0,1fr); column-gap:12px; row-gap:4px; }
    .order-process.is-english li::before { grid-row:1 / span 2; padding-top:1px; }
    .order-process.is-english li strong,.order-process.is-english li span { grid-column:2; }
    .selected-product-layout { display:grid; grid-template-columns:minmax(0,1.45fr) minmax(280px,.8fr); align-items:start; gap:24px; }
    .selected-product-layout .catalog { grid-template-columns:minmax(0,1fr); }
    .selected-product-layout .order-process { position:sticky; top:24px; margin:0; }
    .back-to-products { display:inline-block; margin:0 0 20px; color:var(--soft); font-size:13px; }
    .back-to-products:hover,.product-details-link:hover { color:var(--ink); text-decoration:underline; text-underline-offset:3px; }
    .intro { max-width:620px; margin-bottom:38px; color:var(--soft); font-size:14px; line-height:1.8; }
    .tabs { display:flex; gap:8px; border-bottom:1px solid var(--line); margin-bottom:28px; }
    .tab { border:0; border-bottom:2px solid transparent; padding:12px 14px; margin-bottom:-1px; background:transparent; color:var(--muted); font:inherit; font-size:13px; cursor:pointer; }
    .tab[aria-selected="true"] { border-bottom-color:var(--ink); color:var(--ink); }
    .catalog { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
    .product { min-width:0; border:1px solid var(--line); background:var(--paper); padding:20px; }
    .product[hidden] { display:none; }
    .cover { height:190px; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:10px; margin-bottom:20px; background:#efede7; border:1px solid var(--line); color:var(--muted); text-align:center; }
    .cover img { width:100%; height:100%; object-fit:cover; }
    /* [2단계] 악보 목록 미리보기는 상단 기준으로 가로 폭을 채우고 넘치는 아래쪽을 자릅니다. */
    .score-viewer { margin-bottom:24px; background:#a6dcef; }
    .score-stage { position:relative; height:330px; display:flex; align-items:flex-start; justify-content:center; overflow:hidden; }
    .score-current-link { position:relative; z-index:1; width:100%; height:100%; display:flex; align-items:flex-start; justify-content:center; overflow:hidden; touch-action:pan-y; }
    button.score-current-link { border:0; padding:0; background:transparent; cursor:zoom-in; }
    .score-current { display:block; max-width:none; max-height:none; width:100%; height:auto; object-fit:contain; box-shadow:0 2px 14px rgba(29,62,76,.16); }
    .score-slide-incoming { position:absolute; z-index:2; top:0; left:0; width:100%; height:auto; max-height:none; pointer-events:none; }
    .score-preview-dialog { position:fixed; inset:0; width:100vw; height:100vh; max-width:none; max-height:none; margin:0; padding:8px; border:0; background:#222; color:#fff; }
    @supports (height:100dvh) { .score-preview-dialog { height:100dvh; } }
    .score-preview-dialog[open] { display:flex; flex-direction:column; }
    .score-preview-dialog::backdrop { background:rgba(0,0,0,.82); }
    .score-preview-toolbar { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:10px; color:#fff; font-size:13px; }
    .score-preview-close { display:grid; place-items:center; border:0; background:transparent; color:inherit; cursor:pointer; }
    .score-preview-close { width:40px; height:40px; font-size:30px; }
    .score-preview-body { display:block; flex:1; min-width:0; min-height:0; }
    .score-preview-stage { position:relative; display:flex; align-items:center; justify-content:center; width:100%; height:100%; min-width:0; min-height:0; overflow:hidden; touch-action:pan-y; }
    .score-preview-image { position:relative; z-index:1; display:block; width:100%; height:100%; object-fit:contain; user-select:none; -webkit-user-drag:none; }
    .score-modal-slide { position:absolute; z-index:2; inset:0; width:100%; height:100%; object-fit:contain; pointer-events:none; }
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
    .product-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:6px; }
    .product-actions .buy,.download { padding:10px 12px; cursor:pointer; }
    .product-details-link { align-self:center; color:var(--soft); font-size:12px; white-space:nowrap; }
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
    @media (max-width:900px) { .catalog{grid-template-columns:repeat(2,minmax(0,1fr))} .selected-product-layout{grid-template-columns:minmax(0,1fr)} .selected-product-layout .order-process{position:static} }
    @media (max-width:900px) { .order-process ol{grid-template-columns:repeat(2,minmax(0,1fr))} .order-process.is-english ol{grid-template-columns:minmax(0,1fr)} }
    @media (max-width:680px) { .wrap{width:calc(100% - 32px)} main{padding:48px 0 72px} .catalog{grid-template-columns:1fr} .cover{height:210px} .score-stage{height:min(110vw,420px);min-height:300px} .order-process{padding:20px} .order-process ol{grid-template-columns:1fr} .score-preview-toolbar{margin-bottom:4px} .score-preview-close{width:36px;height:36px} }
  </style>
</head>
<body class="<?= $shopIsEnglish ? 'home-page' : 'site-nav-page' ?>">
  <!-- [2단계] 공통 내비게이션과 상점 본문 -->
  <?php
  $headerIsEnglish = $shopIsEnglish;
  $headerActivePage = 'shop';
  $headerBasePath = $shopBasePath;
  require __DIR__ . ($shopIsEnglish ? '/en/site-header.php' : '/site-header-ko.php');
  ?>
  <div class="wrap">
    <main>
      <div class="eyebrow">Scores &amp; Recordings</div>
      <h1><?= $shopIsEnglish ? 'Scores and recordings' : '악보와 음원' ?></h1>
      <?php if ($shopIsEnglish): ?>
        <div class="purchase-info" aria-label="Store and exchange-rate information">
          <strong>Orders are completed by email and PayPal invoice</strong>
          <span>This website does not take card payments. The final invoice amount may vary with the exchange rate and applicable PayPal fees.</span>
        </div>
        <p class="currency-note" data-currency-note>
          USD amounts are estimates converted from KRW. For a typical international commercial payment received in USD by a South Korean PayPal business account, the estimated receiving fee is 4.4% + $0.30 per transaction, calculated here against one item’s reference price. This fee estimate is shown separately, not added to the item price. Actual fees can vary by sender country, currency, account terms, and PayPal updates; we will confirm the invoice total before payment.
          <span data-currency-status>Loading the latest available exchange rate…</span>
        </p>
        <p class="paypal-fee-source">
          <a href="https://www.paypal.com/kr/business/paypal-business-fees?locale.x=en_KR" target="_blank" rel="noopener noreferrer">See PayPal’s current business fees</a>
        </p>
      <?php else: ?>
        <div class="purchase-info" aria-label="구매 및 소식 안내">
          <strong>회원·비회원 모두 구매 가능</strong>
          <span>신작·이벤트 소식은 수신 동의 회원에게 이메일로 안내합니다.</span>
        </div>
      <?php endif; ?>
      <?php if ($shopIsEnglish && $selectedProduct !== null): ?>
        <a class="back-to-products" href="<?= $shopBasePath ?>/en/shop.php">← All scores &amp; recordings</a>
        <div class="selected-product-layout">
      <?php elseif ($shopIsEnglish): ?>
        <?php require __DIR__ . '/en/order-process.php'; ?>
      <?php endif; ?>
      <?php if (!$shopIsEnglish || $selectedProduct === null): ?>
        <div class="tabs" role="tablist" aria-label="Product type">
          <button class="tab" type="button" role="tab" aria-selected="true" data-filter="1">Scores</button>
          <button class="tab" type="button" role="tab" aria-selected="false" data-filter="2"><?= $shopIsEnglish ? 'Recordings' : 'Records' ?></button>
        </div>
      <?php endif; ?>
      <section class="catalog<?= $shopIsEnglish && $selectedProduct !== null ? ' selected-catalog' : '' ?>" aria-label="<?= $shopIsEnglish ? 'Product catalog' : '상품 목록' ?>">
        <?php if ($loadError): ?>
          <p class="concert-error" role="alert"><?= $shopIsEnglish ? 'Unable to load the product catalog. Please try again later.' : '상품 목록을 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.' ?></p>
        <?php elseif ($products === []): ?>
          <p class="concert-empty"><?= $shopIsEnglish ? 'There are no products available right now.' : '현재 진열 중인 상품이 없습니다.' ?></p>
        <?php endif; ?>
        <!-- [3단계] 요청한 상세 상품이 있으면 그 상품만, 그 외에는 카탈로그 전체를 표시합니다. -->
        <!-- 조회된 $products 항목의 이름·설명·가격·구매 버튼을 상품 카드로 렌더링합니다. -->
        <?php foreach ($products as $product): ?>
          <?php if ($shopIsEnglish && $selectedProduct !== null && $product['id'] !== $selectedProduct['id']) continue; ?>
          <?php $isAudio = $product['type'] === 2; ?>
          <article class="product" id="product-<?= $product['id'] ?>" data-type="<?= escape((string) $product['type']) ?>"<?= $isAudio && $selectedProduct === null ? ' hidden' : '' ?>>
            <?php if (!$isAudio && !empty($product['preview_images'])): ?>
              <div class="score-viewer" data-score-viewer>
                <!-- [3단계] 작은 미리보기는 첫 페이지만 보여주고 확대 버튼으로 팝업을 엽니다. -->
                <div class="score-stage">
                  <button class="score-current-link" type="button" aria-label="<?= $shopIsEnglish ? 'Open score preview' : '악보 미리보기 크게 보기' ?>" data-score-open>
                    <img class="score-current" src="<?= $shopBasePath ?>/<?= escape(ltrim($product['preview_images'][0]['path'], '/')) ?>" alt="<?= escape($product['name']) ?> <?= escape(shopPageLabel($product['preview_images'][0]['label'], $shopIsEnglish)) ?> <?= $shopIsEnglish ? 'preview' : '미리보기' ?>" data-score-current>
                  </button>
                </div>
                <div hidden>
                  <?php foreach ($product['preview_images'] as $page): ?>
                    <?php if (productAssetExists($page['path'])): ?>
                      <?php $pageLabel = shopPageLabel($page['label'], $shopIsEnglish); ?>
                      <span data-score-page data-src="<?= $shopBasePath ?>/<?= escape(ltrim($page['path'], '/')) ?>" data-alt="<?= escape($product['name']) ?> <?= escape($pageLabel) ?> <?= $shopIsEnglish ? 'preview' : '미리보기' ?>" data-score-label="<?= escape($pageLabel) ?>"></span>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php else: ?>
              <div class="cover">
                <?php if (productAssetExists($product['cover'])): ?>
                  <img src="<?= $shopBasePath ?>/<?= escape(ltrim($product['cover'], '/')) ?>" alt="<?= escape($product['name']) ?> <?= $shopIsEnglish ? 'cover' : '표지' ?>">
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
                <audio controls preload="none" controlsList="nodownload" data-preview-limit="60" src="<?= $shopBasePath ?>/<?= escape(ltrim($product['preview'], '/')) ?>"><?= $shopIsEnglish ? 'Your browser does not support audio playback.' : '브라우저에서 오디오 재생을 지원하지 않습니다.' ?></audio>
                <p class="preview-limit"><?= $shopIsEnglish ? 'One-minute preview' : '미리듣기 1분' ?></p>
              <?php else: ?>
                <div class="preview-missing"><?= $shopIsEnglish ? 'Audio preview coming soon.' : '미리듣기 음원 등록 예정' ?></div>
              <?php endif; ?>
            <?php endif; ?>
            <div class="purchase">
              <div class="product-prices">
                <?php if ($shopIsEnglish): ?>
                  <span class="regular-price">Regular <s data-krw-price="<?= $product['regular_price_krw'] ?>">Loading USD…</s></span>
                  <span class="sale-price">Sale <span data-krw-price="<?= $product['sale_price_krw'] ?>" data-paypal-base-price>Loading USD…</span></span>
                  <span class="paypal-fee-estimate" data-paypal-fee-estimate data-krw-price="<?= $product['sale_price_krw'] ?>">Estimated PayPal fee: calculating…</span>
                <?php else: ?>
                  <span class="regular-price">정가 <s><?= number_format($product['regular_price_krw']) ?>원</s></span>
                  <span class="sale-price">할인가 <?= number_format($product['sale_price_krw']) ?>원</span>
                <?php endif; ?>
              </div>
              <div class="product-actions">
                <?php if ($shopIsEnglish): ?>
                  <?php
                  $requestSubject = 'Product order request: ' . $product['name'];
                  $requestBody = "Hello HANSUL MUSIC,\n\nI would like to order this item:\n"
                      . $product['name'] . "\n"
                      . ($isAudio ? 'Digital recording' : 'Digital score')
                      . "\n\nPlease email me a PayPal invoice and let me know the final total.\n\n"
                      . "Name:\nCountry/Region:\n";
                  $requestHref = 'mailto:sangeun@hansulmusic.com?subject='
                      . rawurlencode($requestSubject)
                      . '&body=' . rawurlencode($requestBody);
                  ?>
                  <a class="buy" href="<?= escape($requestHref) ?>">Request</a>
                <?php else: ?>
                  <button class="buy" type="button" data-purchase-button>구매하기</button>
                  <a
                    class="download is-disabled"
                    href="<?= $shopBasePath ?>/<?= escape(ltrim($product['download_file'], '/')) ?>"
                    download
                    aria-disabled="true"
                    tabindex="-1"
                    data-download-link>다운받기</a>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($shopIsEnglish && $selectedProduct === null): ?>
              <a class="product-details-link" href="<?= $shopBasePath ?>/en/shop.php?product_id=<?= $product['id'] ?>#product-<?= $product['id'] ?>">View product details →</a>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </section>
      <?php if ($shopIsEnglish && $selectedProduct !== null): ?>
        <?php require __DIR__ . '/en/order-process.php'; ?>
        </div>
      <?php endif; ?>
      <p class="notice"><?= $shopIsEnglish ? 'After delivery, digital files may not be copied or redistributed without the copyright holder’s permission.' : '구매 후 다운로드되는 파일은 저작권자의 허락 없이 복제하거나 재배포할 수 없습니다.' ?></p>
    </main>
  </div>
  <dialog class="score-preview-dialog" data-score-preview-dialog aria-label="<?= $shopIsEnglish ? 'Score preview' : '악보 미리보기' ?>">
    <div class="score-preview-toolbar">
      <span data-score-preview-count></span>
      <button class="score-preview-close" type="button" data-score-preview-close aria-label="<?= $shopIsEnglish ? 'Close preview' : '미리보기 닫기' ?>">&times;</button>
    </div>
    <div class="score-preview-body">
      <div class="score-preview-stage" data-score-preview-stage>
        <img class="score-preview-image" data-score-preview-image alt="" draggable="false">
      </div>
    </div>
  </dialog>
  <?php
  $footerLanguage = $shopIsEnglish ? 'en' : 'ko';
  require __DIR__ . '/site-footer.php';
  ?>

  <?php if (!$shopIsEnglish): ?>
    <!-- [2단계] 한글 상점의 데모 구매 완료 안내 -->
    <dialog class="purchase-dialog" aria-labelledby="purchase-dialog-title" aria-describedby="purchase-dialog-message" data-purchase-dialog>
      <h2 id="purchase-dialog-title">구매해 주셔서 감사합니다</h2>
      <p id="purchase-dialog-message">테스트 구매가 완료되었습니다. 실제 결제는 진행되지 않았으며, 구매한 상품을 다운로드할 수 있습니다.</p>
      <button type="button" data-close-purchase-dialog>확인</button>
    </dialog>
  <?php endif; ?>
  <script>
    // [1단계] 악보 미리보기, 상품 분류, 데모 구매 동작을 연결합니다.
    let navigateActiveScorePreview = null;

    // [2단계] 악보 뷰어별 페이지 전환과 확대 팝업을 초기화합니다.
    document.querySelectorAll('[data-score-viewer]').forEach((viewer) => {
      const pages = [...viewer.querySelectorAll('[data-score-page]')];
      const currentImage = viewer.querySelector('[data-score-current]');
      if (pages.length === 0) return;
      const inlineStage = viewer.querySelector('.score-stage');
      const scoreOpenButton = viewer.querySelector('[data-score-open]');
      const previewDialog = document.querySelector('[data-score-preview-dialog]');
      const previewImage = previewDialog?.querySelector('[data-score-preview-image]');
      const previewStage = previewDialog?.querySelector('[data-score-preview-stage]');
      const previewCount = previewDialog?.querySelector('[data-score-preview-count]');
      let activeIndex = 0;
      let isAnimating = false;
      const pageImageCache = pages.map((page) => {
        const image = new Image();
        image.src = page.dataset.src;
        return image;
      });

      const updateControls = () => {
        scoreOpenButton.disabled = isAnimating;
      };

      const navigate = async (direction, inModal = false) => {
        const nextIndex = activeIndex + direction;
        // [3단계] 애니메이션 중이거나 페이지 범위를 벗어난 요청은 무시합니다.
        if (isAnimating || nextIndex < 0 || nextIndex >= pages.length) return;
        const targetPage = pages[nextIndex];
        const stage = inModal ? previewStage : inlineStage;
        const displayedImage = inModal ? previewImage : currentImage;
        if (!(stage instanceof HTMLElement) || !(displayedImage instanceof HTMLImageElement)) return;

        isAnimating = true;
        updateControls();
        const incoming = pageImageCache[nextIndex].cloneNode();
        incoming.className = inModal ? 'score-modal-slide' : 'score-slide-incoming';
        incoming.alt = '';
        try {
          await incoming.decode();
        } catch (error) {
          isAnimating = false;
          updateControls();
          console.error('Unable to load score preview page:', error);
          return;
        }

        stage.append(incoming);
        const duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 1 : 360;
        const easing = 'cubic-bezier(.22,.75,.25,1)';
        const oldPageAnimation = displayedImage.animate(
          [{ transform: 'translateX(0)' }, { transform: `translateX(${-direction * 100}%)` }],
          { duration, easing, fill: 'both' },
        );
        const newPageAnimation = incoming.animate(
          [{ transform: `translateX(${direction * 100}%)` }, { transform: 'translateX(0)' }],
          { duration, easing, fill: 'both' },
        );
        await Promise.all([oldPageAnimation.finished, newPageAnimation.finished]);
        displayedImage.src = targetPage.dataset.src;
        displayedImage.alt = targetPage.dataset.alt;
        oldPageAnimation.cancel();
        newPageAnimation.cancel();
        incoming.remove();
        activeIndex = nextIndex;
        if (inModal) {
          currentImage.src = targetPage.dataset.src;
          currentImage.alt = targetPage.dataset.alt;
        } else if (previewDialog instanceof HTMLDialogElement && previewDialog.open && previewImage instanceof HTMLImageElement) {
          previewImage.src = targetPage.dataset.src;
          previewImage.alt = targetPage.dataset.alt;
        }
        if (previewCount instanceof HTMLElement && previewDialog instanceof HTMLDialogElement && previewDialog.open) {
          previewCount.textContent = `${targetPage.dataset.scoreLabel} · ${activeIndex + 1}/${pages.length}`;
        }
        isAnimating = false;
        updateControls();
      };

      viewer.querySelector('[data-score-open]').addEventListener('click', () => {
        if (!(previewDialog instanceof HTMLDialogElement) || !(previewImage instanceof HTMLImageElement)) {
          throw new Error(<?= json_encode(
              $shopIsEnglish ? 'Unable to open the score preview.' : '악보 미리보기를 열 수 없습니다.',
              JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
          ) ?>);
        }
        const page = pages[activeIndex];
        previewImage.src = page.dataset.src;
        previewImage.alt = page.dataset.alt;
        if (previewCount instanceof HTMLElement) previewCount.textContent = `${page.dataset.scoreLabel} · ${activeIndex + 1}/${pages.length}`;
        navigateActiveScorePreview = (direction) => navigate(direction, true);
        previewDialog.showModal();
        previewDialog.querySelector('[data-score-preview-close]').focus();
        updateControls();
      });

      const bindSwipe = (surface, onSwipe) => {
        // [3단계] 포인터 캡처로 마우스·터치 드래그가 표면 밖에서 끝나도 전환을 감지합니다.
        let start = null;
        surface.addEventListener('pointerdown', (event) => {
          if (!event.isPrimary || (event.pointerType === 'mouse' && event.button !== 0)) return;
          start = { x: event.clientX, y: event.clientY, pointerId: event.pointerId };
          surface.setPointerCapture(event.pointerId);
          if (event.pointerType === 'mouse') event.preventDefault();
        });
        surface.addEventListener('pointerup', (event) => {
          if (start === null || event.pointerId !== start.pointerId) return;
          const deltaX = event.clientX - start.x;
          const deltaY = event.clientY - start.y;
          start = null;
          if (Math.abs(deltaX) < 40 || Math.abs(deltaX) <= Math.abs(deltaY)) return;
          onSwipe(deltaX < 0 ? 1 : -1);
        });
        surface.addEventListener('pointercancel', () => { start = null; });
        surface.addEventListener('lostpointercapture', () => { start = null; });
      };
      bindSwipe(previewStage, (direction) => navigate(direction, true));
      updateControls();
    });

    const scorePreviewDialog = document.querySelector('[data-score-preview-dialog]');
    scorePreviewDialog?.querySelector('[data-score-preview-close]')?.addEventListener('click', () => {
      if (scorePreviewDialog instanceof HTMLDialogElement) scorePreviewDialog.close();
    });
    scorePreviewDialog?.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && scorePreviewDialog instanceof HTMLDialogElement) {
        scorePreviewDialog.close();
        return;
      }
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      navigateActiveScorePreview?.(event.key === 'ArrowLeft' ? -1 : 1);
      event.preventDefault();
    });
    scorePreviewDialog?.addEventListener('close', () => {
      navigateActiveScorePreview = null;
    });
    scorePreviewDialog?.addEventListener('click', (event) => {
      if (event.target === scorePreviewDialog && scorePreviewDialog instanceof HTMLDialogElement) scorePreviewDialog.close();
    });

    // [2단계] 음원 미리듣기는 상품별 제한 시간 이후 재생되지 않도록 제어합니다.
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

    // [2단계] 영문 상세 상품 링크로 진입한 경우 해당 상품을 필터링하고 화면에 맞춥니다.
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

    // [2단계] 데모 구매 확인 뒤 해당 상품의 다운로드 버튼만 활성화합니다.
    document.querySelectorAll('[data-purchase-button]').forEach((button) => {
      button.addEventListener('click', () => {
        const product = button.closest('.product');
        if (!(purchaseDialog instanceof HTMLDialogElement) || !(product instanceof HTMLElement)) {
          throw new Error(<?= json_encode(
              $shopIsEnglish ? 'Unable to open the demo purchase message.' : '구매 완료 안내를 열 수 없습니다.',
              JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
          ) ?>);
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
        purchaseButton.textContent = <?= json_encode(
            $shopIsEnglish ? 'Demo complete' : '구매 완료',
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        ) ?>;
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
  <?php
  $authLanguage = $shopIsEnglish ? 'en' : 'ko';
  require __DIR__ . '/auth-modal.php';
  ?>
  <?php if ($shopIsEnglish): ?>
    <script src="<?= $shopBasePath ?>/js/currency-estimate.js?v=1" defer></script>
  <?php endif; ?>
  <script src="<?= $shopBasePath ?>/js/signup-modal.js?v=13" defer></script>
  <script src="<?= $shopBasePath ?>/js/navigation.js?v=3" defer></script>
</body>
</html>