<?php
declare(strict_types=1);

// [1단계] 공통 인증 함수는 admin-auth.php에서 불러와 상품관리를 보호합니다.
require_once __DIR__ . '/admin-auth.php';

/** 상품 종류(products.product_type)와 화면 이름입니다. 종류마다 상품 한 개만 등록하며, 영문 상점 문구(product-localization.php)도 이 값으로 연결됩니다. */
const ADMIN_PRODUCT_KINDS = [
    1 => '악보',
    2 => '음원',
];

/**
 * 업로드 파일을 허용된 MIME 형식으로 검증한 뒤 임의 파일명으로 저장합니다.
 *
 * @param array<string, mixed>|null $file PHP 단일 파일 업로드 구조입니다.
 * @param list<string> $allowedMimes 허용할 파일 MIME 형식입니다.
 * @return string|null 사이트 루트 기준 저장 경로이며, 파일이 없으면 null입니다.
 */
function adminStoreUpload(?array $file, string $directory, array $allowedMimes, int $maxBytes): ?string
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('파일 업로드에 실패했습니다. 파일 크기와 서버 업로드 제한을 확인해 주세요.');
    }
    if (!isset($file['tmp_name'], $file['size'])
        || !is_string($file['tmp_name'])
        || !is_uploaded_file($file['tmp_name'])
        || !is_int($file['size'])
        || $file['size'] <= 0
        || $file['size'] > $maxBytes
    ) {
        throw new RuntimeException('파일이 비어 있거나 허용 크기를 초과했습니다.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    // [3단계] 브라우저가 보낸 MIME 값은 신뢰하지 않고 파일 내용 검사 결과만 허용 목록과 비교합니다.
    if (!is_string($mime) || !in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('허용되지 않은 파일 형식입니다.');
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/mp4' => 'm4a',
        'audio/ogg' => 'ogg',
        'application/pdf' => 'pdf',
        'application/zip' => 'zip',
        'application/x-zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
    ];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('파일 확장자를 확인할 수 없습니다.');
    }

    // $directory는 product-media/ 아래의 자료 폴더(covers, scores, scoreszip, music)입니다.
    $absoluteDirectory = __DIR__ . '/product-media/' . $directory;
    if (!is_dir($absoluteDirectory)
        && !mkdir($absoluteDirectory, 0750, true)
        && !is_dir($absoluteDirectory)
    ) {
        throw new RuntimeException('업로드 저장 폴더를 만들 수 없습니다.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $absolutePath = $absoluteDirectory . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new RuntimeException('업로드 파일을 저장할 수 없습니다.');
    }

    return 'product-media/' . $directory . '/' . $fileName;
}

/** 여러 파일 업로드 중 실제 선택된 파일을 순서대로 저장합니다. */
function adminStoreMultipleUploads(?array $files, string $directory, array $allowedMimes, int $maxBytes): array
{
    if ($files === null || !isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $stored = [];
    foreach ($files['name'] as $index => $name) {
        if (!is_string($name) || $name === '') {
            continue;
        }
        $file = [
            'name' => $name,
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
        $path = adminStoreUpload($file, $directory, $allowedMimes, $maxBytes);
        if ($path !== null) {
            $label = pathinfo($name, PATHINFO_FILENAME);
            $stored[] = ['path' => $path, 'label' => $label !== '' ? $label : 'Preview'];
        }
    }
    return $stored;
}

// [2단계] 자격 증명 파일을 읽을 수 없으면 누구도 로그인할 수 없도록 관리 화면을 닫습니다.
$configurationError = false;
$adminUsername = '';
$adminPassword = '';
try {
    [$adminUsername, $adminPassword] = adminCredentials();
} catch (RuntimeException $exception) {
    error_log('Product administration credential configuration error: ' . $exception->getMessage());
    $configurationError = true;
}
$errorMessage = '';
$successMessage = '';
$products = [];
$selectedProduct = null;
$selectedAssets = [];
$submittedProduct = null;
$editingNewProduct = ($_GET['new'] ?? '') === '1';

if ($configurationError) {
    http_response_code(503);
    $errorMessage = '관리자 계정 파일 설정이 없습니다. 서버에 .admin-products-login 파일을 확인해 주세요.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [2단계] 로그인·로그아웃은 CSRF를 검사하고, 상품 변경은 관리자 세션과 토큰을 모두 요구합니다.
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'login') {
        $errorMessage = adminAttemptLogin($adminUsername, $adminPassword, '/admin-products.php');
    } else {
        $submittedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals(adminCsrfToken(), $submittedToken)) {
            http_response_code(403);
            $errorMessage = '요청을 확인할 수 없습니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.';
        } elseif (($_SESSION['product_admin_authenticated'] ?? false) !== true) {
            http_response_code(401);
            $errorMessage = '관리자 로그인이 필요합니다.';
        } elseif ($action === 'logout') {
            unset($_SESSION['product_admin_authenticated'], $_SESSION['admin_csrf_token']);
            session_regenerate_id(true);
            header('Location: ' . appBasePath() . '/admin-products.php', true, 303);
            exit;
        } else {
            // [3단계] 상품 저장 요청은 파일, DB, 입력값을 검증한 뒤 PRG 방식으로 완료합니다.
            $newFiles = [];
            $pdo = null;
            $productId = null;
            $currentProduct = null;
            try {
                $rawProductId = (string) ($_POST['product_id'] ?? '');
                $productId = $rawProductId === '' ? null : filter_var($rawProductId, FILTER_VALIDATE_INT);
                if ($productId === false || ($productId !== null && $productId < 1)) {
                    throw new RuntimeException('상품 번호가 올바르지 않습니다.');
                }
                $pdo = adminDatabaseConnection();
                if ($action !== 'save') {
                    throw new RuntimeException('지원하지 않는 요청입니다.');
                }

                // 상품 종류(product_type)는 허용 목록(ADMIN_PRODUCT_KINDS)의 값만 받습니다.
                $type = filter_var($_POST['product_type'] ?? null, FILTER_VALIDATE_INT);
                if (!is_int($type) || !isset(ADMIN_PRODUCT_KINDS[$type])) {
                    throw new RuntimeException('상품 종류를 선택해 주세요.');
                }
                $name = trim((string) ($_POST['name'] ?? ''));
                $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $regularPrice = filter_var($_POST['regular_price_krw'] ?? null, FILTER_VALIDATE_INT);
                $salePrice = filter_var($_POST['sale_price_krw'] ?? null, FILTER_VALIDATE_INT);
                $sortOrder = filter_var($_POST['sort_order'] ?? null, FILTER_VALIDATE_INT);
                if ($name === ''
                    || mb_strlen($name) > 255
                    || mb_strlen($subtitle) > 255
                    || $regularPrice === false
                    || $regularPrice < 0
                    || $regularPrice > 4294967295
                    || $salePrice === false
                    || $salePrice < 0
                    || $salePrice > 4294967295
                    || $sortOrder === false
                    || $sortOrder < 0
                    || $sortOrder > 2147483647
                ) {
                    throw new RuntimeException('상품명, 가격, 정렬 순서를 확인해 주세요.');
                }
                if ($productId !== false && $productId !== null && $productId > 0) {
                    $currentStatement = $pdo->prepare('SELECT * FROM products WHERE product_id = :product_id');
                    $currentStatement->execute(['product_id' => $productId]);
                    $currentProduct = $currentStatement->fetch(PDO::FETCH_ASSOC);
                    if ($currentProduct === false) {
                        throw new RuntimeException('수정할 상품을 찾을 수 없습니다.');
                    }
                } else {
                    $currentProduct = [
                        'cover_path' => null,
                        'preview_audio_path' => null,
                        'download_path' => '',
                    ];
                }

                $pdo->beginTransaction();
                $coverPath = adminStoreUpload(
                    $_FILES['cover'] ?? null,
                    'covers',
                    ['image/jpeg', 'image/png', 'image/webp'],
                    12 * 1024 * 1024,
                );
                if ($coverPath !== null) {
                    $newFiles[] = __DIR__ . '/' . $coverPath;
                } else {
                    $coverPath = $currentProduct['cover_path'];
                }

                $audioPath = adminStoreUpload(
                    $_FILES['preview_audio'] ?? null,
                    'music',
                    ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/ogg'],
                    50 * 1024 * 1024,
                );
                if ($audioPath !== null) {
                    $newFiles[] = __DIR__ . '/' . $audioPath;
                } else {
                    $audioPath = $currentProduct['preview_audio_path'];
                }

                // 악보(1) 다운로드는 scoreszip/, 음원(2) 다운로드는 music/ 폴더에 저장합니다.
                $downloadPath = adminStoreUpload(
                    $_FILES['download_file'] ?? null,
                    $type === 1 ? 'scoreszip' : 'music',
                    $type === 1
                        ? ['application/pdf', 'application/zip', 'application/x-zip', 'application/x-zip-compressed']
                        : [
                            'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/ogg',
                            'application/zip', 'application/x-zip', 'application/x-zip-compressed',
                        ],
                    100 * 1024 * 1024,
                );
                if ($downloadPath !== null) {
                    $newFiles[] = __DIR__ . '/' . $downloadPath;
                } else {
                    $downloadPath = $currentProduct['download_path'];
                }
                if ($downloadPath === '') {
                    throw new RuntimeException('다운로드 파일을 업로드해 주세요.');
                }

                $isActive = isset($_POST['is_active']) ? 1 : 0;
                if ($productId === null) {
                    $statement = $pdo->prepare(
                        'INSERT INTO products (
                            product_type, name, subtitle, description,
                            regular_price_krw, sale_price_krw, cover_path,
                            preview_audio_path, download_path, is_active, sort_order
                         ) VALUES (
                            :product_type, :name, :subtitle, :description,
                            :regular_price_krw, :sale_price_krw, :cover_path,
                            :preview_audio_path, :download_path, :is_active, :sort_order
                         )'
                    );
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE products SET
                            product_type = :product_type, name = :name,
                            subtitle = :subtitle, description = :description,
                            regular_price_krw = :regular_price_krw, sale_price_krw = :sale_price_krw,
                            cover_path = :cover_path, preview_audio_path = :preview_audio_path,
                            download_path = :download_path, is_active = :is_active, sort_order = :sort_order
                         WHERE product_id = :product_id'
                    );
                }
                $values = [
                    'product_type' => $type,
                    'name' => $name,
                    'subtitle' => $subtitle,
                    'description' => $description,
                    'regular_price_krw' => $regularPrice,
                    'sale_price_krw' => $salePrice,
                    'cover_path' => $coverPath,
                    'preview_audio_path' => $audioPath,
                    'download_path' => $downloadPath,
                    'is_active' => $isActive,
                    'sort_order' => $sortOrder,
                ];
                if ($productId !== null) {
                    $values['product_id'] = $productId;
                }
                $statement->execute($values);
                if ($productId === null) {
                    $productId = (int) $pdo->lastInsertId();
                }

                $assetOrders = is_array($_POST['asset_order'] ?? null) ? $_POST['asset_order'] : [];
                $assetLabels = is_array($_POST['asset_label'] ?? null) ? $_POST['asset_label'] : [];
                $updateAsset = $pdo->prepare(
                    "UPDATE product_assets
                     SET label = :label, sort_order = :sort_order
                     WHERE asset_id = :asset_id
                       AND product_id = :product_id
                       AND asset_type = 'preview_image'"
                );
                foreach ($assetOrders as $assetId => $assetOrder) {
                    $assetId = filter_var($assetId, FILTER_VALIDATE_INT);
                    $assetOrder = filter_var($assetOrder, FILTER_VALIDATE_INT);
                    $assetLabel = $assetLabels[(string) $assetId] ?? '';
                    if ($assetId === false
                        || $assetId < 1
                        || $assetOrder === false
                        || $assetOrder < 0
                        || $assetOrder > 2147483647
                        || !is_string($assetLabel)
                        || mb_strlen($assetLabel) > 255
                    ) {
                        throw new RuntimeException('악보 미리보기의 라벨과 표시 순서를 확인해 주세요.');
                    }
                    $updateAsset->execute([
                        'label' => $assetLabel,
                        'sort_order' => $assetOrder,
                        'asset_id' => $assetId,
                        'product_id' => $productId,
                    ]);
                }

                $previewImages = adminStoreMultipleUploads(
                    $_FILES['preview_images'] ?? null,
                    'scores',
                    ['image/jpeg', 'image/png', 'image/webp'],
                    15 * 1024 * 1024,
                );
                foreach ($previewImages as $previewImage) {
                    $newFiles[] = __DIR__ . '/' . $previewImage['path'];
                    $sortStatement = $pdo->prepare(
                        "SELECT COALESCE(MAX(sort_order), 0) + 1
                         FROM product_assets
                         WHERE product_id = :product_id AND asset_type = 'preview_image'"
                    );
                    $sortStatement->execute(['product_id' => $productId]);
                    $assetStatement = $pdo->prepare(
                        "INSERT INTO product_assets (product_id, asset_type, asset_path, label, sort_order)
                         VALUES (:product_id, 'preview_image', :asset_path, :label, :sort_order)"
                    );
                    $assetStatement->execute([
                        'product_id' => $productId,
                        'asset_path' => $previewImage['path'],
                        'label' => mb_substr($previewImage['label'], 0, 255),
                        'sort_order' => (int) $sortStatement->fetchColumn(),
                    ]);
                }
                $pdo->commit();
                $_SESSION['admin_product_flash'] = '상품 정보를 저장했습니다.';
                header('Location: ' . appBasePath() . '/admin-products.php?product_id=' . $productId, true, 303);
                exit;
            } catch (PDOException | RuntimeException $exception) {
                if ($pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($newFiles as $newFile) {
                    if (is_file($newFile)) {
                        unlink($newFile);
                    }
                }
                error_log('Product administration error: ' . $exception->getMessage());
                $errorMessage = $exception instanceof PDOException
                    ? ($exception->getCode() === '23000'
                        ? '이미 등록된 상품 종류입니다. 종류마다 한 개씩만 등록할 수 있습니다.'
                        : '상품 정보를 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.')
                    : $exception->getMessage();
                if ($action === 'save') {
                    $submittedProduct = [
                        'product_id' => $rawProductId === '' || $productId === false || $productId === null
                            ? ''
                            : $productId,
                        'product_type' => filter_var($_POST['product_type'] ?? 1, FILTER_VALIDATE_INT) ?: 1,
                        'name' => (string) ($_POST['name'] ?? ''),
                        'subtitle' => (string) ($_POST['subtitle'] ?? ''),
                        'description' => (string) ($_POST['description'] ?? ''),
                        'regular_price_krw' => (string) ($_POST['regular_price_krw'] ?? ''),
                        'sale_price_krw' => (string) ($_POST['sale_price_krw'] ?? ''),
                        'cover_path' => $currentProduct['cover_path'] ?? null,
                        'preview_audio_path' => $currentProduct['preview_audio_path'] ?? null,
                        'download_path' => $currentProduct['download_path'] ?? '',
                        'is_active' => isset($_POST['is_active']) ? 1 : 0,
                        'sort_order' => (string) ($_POST['sort_order'] ?? 0),
                    ];
                    $editingNewProduct = $rawProductId === '';
                }
                http_response_code(422);
            }
        }
    }
}

if (isset($_SESSION['admin_product_flash'])) {
    $successMessage = (string) $_SESSION['admin_product_flash'];
    unset($_SESSION['admin_product_flash']);
}

if (!$configurationError && ($_SESSION['product_admin_authenticated'] ?? false) === true) {
    // [2단계] 관리자 세션이 있을 때만 DB 상품·미리보기 데이터를 읽습니다.
    try {
        $pdo = adminDatabaseConnection();
        $products = $pdo->query(
            'SELECT product_id, product_type, name, is_active, sort_order
             FROM products ORDER BY sort_order ASC, product_id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        // [3단계] 선택 상품의 상세 필드와 악보 자산만 추가 조회해 편집 폼에 전달합니다.
        $requestedProductId = $_GET['product_id'] ?? (
            $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['product_id'] ?? null) : null
        );
        $selectedId = filter_var($requestedProductId, FILTER_VALIDATE_INT);
        if ($selectedId !== false && $selectedId !== null && $selectedId > 0) {
            $statement = $pdo->prepare('SELECT * FROM products WHERE product_id = :product_id');
            $statement->execute(['product_id' => $selectedId]);
            $selectedProduct = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($selectedProduct !== null) {
                $assetStatement = $pdo->prepare(
                    "SELECT asset_id, asset_path, label, sort_order
                     FROM product_assets
                     WHERE product_id = :product_id AND asset_type = 'preview_image'
                     ORDER BY sort_order ASC, asset_id ASC"
                );
                $assetStatement->execute(['product_id' => $selectedId]);
                $selectedAssets = $assetStatement->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException | RuntimeException $exception) {
        error_log('Product administration catalog read error: ' . $exception->getMessage());
        http_response_code(503);
        $errorMessage = '상품 정보를 불러오지 못했습니다. 데이터베이스 설정을 확인해 주세요.';
    }
}

$basePath = appBasePath();
$isAuthenticated = !$configurationError && ($_SESSION['product_admin_authenticated'] ?? false) === true;
$formProduct = $submittedProduct ?? $selectedProduct ?? [
    'product_id' => '',
    'product_type' => 1,
    'name' => '',
    'subtitle' => '',
    'description' => '',
    'regular_price_krw' => '',
    'sale_price_krw' => '',
    'cover_path' => null,
    'preview_audio_path' => null,
    'download_path' => '',
    'is_active' => 1,
    'sort_order' => 0,
];
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>상품관리 | HANSUL MUSIC</title>
  <style>
    :root { color-scheme:light; font-family:Inter,'Noto Sans KR',sans-serif; color:#292825; background:#f5f3ef; }
    * { box-sizing:border-box; }
    body { margin:0; }
    a { color:inherit; }
    button,input,select,textarea { font:inherit; }
    .admin-shell { width:min(1180px,calc(100% - 32px)); margin:0 auto; padding:36px 0 64px; }
    .admin-top { display:flex; justify-content:space-between; align-items:center; gap:20px; margin-bottom:26px; }
    h1,h2,h3,p { margin-top:0; }
    h1 { margin-bottom:6px; font:400 30px/1.3 'Noto Serif KR',serif; }
    .muted,.field-hint { color:#77746d; font-size:13px; line-height:1.6; }
    .admin-layout { display:grid; grid-template-columns:minmax(220px,.7fr) minmax(0,1.6fr); gap:22px; align-items:start; }
    .panel { padding:22px; border:1px solid #e2ded5; background:#fff; }
    .product-list { display:grid; gap:8px; margin-bottom:18px; }
    .product-list a { display:grid; gap:4px; padding:12px; border:1px solid #e8e4dc; text-decoration:none; }
    .product-list a[aria-current="true"] { border-color:#a68b5b; background:#faf8f3; }
    .product-list small { color:#77746d; }
    .admin-button { display:inline-flex; align-items:center; justify-content:center; min-height:40px; padding:9px 14px; border:1px solid #343330; background:#343330; color:#fff; text-decoration:none; cursor:pointer; }
    .admin-button.secondary { border-color:#d8d3ca; background:#fff; color:#292825; }
    .admin-nav { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:22px; }
    .admin-button.secondary[aria-current="page"] { border-color:#a68b5b; background:#faf8f3; }
    .form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .field { display:grid; gap:7px; min-width:0; }
    .field.full { grid-column:1/-1; }
    .field label,.field-title { font-size:13px; font-weight:600; }
    .field input,.field select,.field textarea { width:100%; min-height:42px; padding:9px 10px; border:1px solid #d8d3ca; background:#fff; color:#292825; }
    .field textarea { min-height:100px; resize:vertical; }
    .field input[type=file] { padding:6px; }
    .check-row { display:flex; align-items:center; gap:8px; font-size:13px; }
    .path-note { overflow-wrap:anywhere; margin:0; color:#77746d; font-size:12px; }
    .asset-list { display:grid; gap:8px; }
    .asset-row { display:grid; grid-template-columns:72px minmax(0,1fr) 110px; align-items:center; gap:10px; padding:8px; border:1px solid #e8e4dc; }
    .asset-row img { width:72px; height:60px; object-fit:contain; background:#eee; }
    .asset-edit { display:grid; gap:6px; min-width:0; }
    .asset-edit input,.asset-row > input[type=number] { width:100%; min-width:0; padding:7px; border:1px solid #d8d3ca; }
    .form-actions { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:20px; }
    .message { margin:0 0 18px; padding:12px 14px; border:1px solid #dccfb1; background:#faf6e9; line-height:1.5; }
    .message.error { border-color:#e4c2bc; background:#fff3f0; color:#7f2f25; }
    .login-panel { width:min(440px,100%); margin:70px auto; }
    .login-panel form { display:grid; gap:16px; }
    @media (max-width:760px) { .admin-layout { grid-template-columns:1fr; } }
    @media (max-width:520px) { .admin-shell { width:calc(100% - 24px); padding-top:22px; } .form-grid { grid-template-columns:1fr; } .field.full { grid-column:auto; } .asset-row { grid-template-columns:56px minmax(0,1fr); } .asset-row img { width:56px; } .asset-row > input[type=number] { grid-column:2/-1; } }
  </style>
</head>
<body>
  <main class="admin-shell">
    <header class="admin-top">
      <?php if ($isAuthenticated): ?>
        <div>
          <h1>상품관리</h1>
          <p class="muted">악보·음원 상품을 관리합니다. 삭제는 없으며 진열 상태를 해제하면 숨김 처리됩니다.</p>
        </div>
        <form method="post" action="<?= adminEscape($basePath) ?>/admin-products.php">
          <input type="hidden" name="csrf_token" value="<?= adminEscape(adminCsrfToken()) ?>">
          <input type="hidden" name="action" value="logout">
          <button class="admin-button secondary" type="submit">로그아웃</button>
        </form>
      <?php endif; ?>
    </header>
    <?php if ($errorMessage !== ''): ?>
      <p class="message error" role="alert"><?= adminEscape($errorMessage) ?></p>
    <?php endif; ?>
    <?php if ($successMessage !== ''): ?>
      <p class="message" role="status"><?= adminEscape($successMessage) ?></p>
    <?php endif; ?>

    <?php if (!$isAuthenticated): ?>
      <section class="panel login-panel">
        <h2>관리자 로그인</h2>
        <p class="muted">서버에 설정된 관리자 계정으로 로그인해 주세요.</p>
        <form method="post" action="<?= adminEscape($basePath) ?>/admin-products.php">
          <input type="hidden" name="action" value="login">
          <input type="hidden" name="csrf_token" value="<?= adminEscape(adminCsrfToken()) ?>">
          <div class="field">
            <label for="username">관리자 아이디</label>
            <input id="username" name="username" autocomplete="username" required>
          </div>
          <div class="field">
            <label for="password">비밀번호</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
          </div>
          <button class="admin-button" type="submit">로그인</button>
        </form>
      </section>
    <?php else: ?>
      <nav class="admin-nav" aria-label="관리 메뉴">
        <a class="admin-button secondary" href="<?= adminEscape($basePath) ?>/admin-products.php" aria-current="page">상품관리</a>
        <?php foreach (ADMIN_MEDIA_SECTIONS as $key => $label): ?>
          <a class="admin-button secondary" href="<?= adminEscape($basePath) ?>/admin-media.php?section=<?= $key ?>"><?= adminEscape($label) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="admin-layout">
        <aside class="panel" aria-label="상품 목록">
          <h2>등록된 상품</h2>
          <nav class="product-list">
            <?php foreach ($products as $product): ?>
              <a href="<?= adminEscape($basePath) ?>/admin-products.php?product_id=<?= (int) $product['product_id'] ?>"
                 aria-current="<?= $selectedProduct !== null && (int) $selectedProduct['product_id'] === (int) $product['product_id'] ? 'true' : 'false' ?>">
                <strong><?= adminEscape((string) $product['name']) ?></strong>
                <small><?= (int) $product['product_type'] === 1 ? '악보' : '음원' ?> · <?= (int) $product['is_active'] === 1 ? '진열 중' : '숨김' ?></small>
              </a>
            <?php endforeach; ?>
          </nav>
          <a class="admin-button secondary" href="<?= adminEscape($basePath) ?>/admin-products.php?new=1">새 상품 등록</a>
        </aside>

        <?php if ($selectedProduct !== null || $editingNewProduct): ?>
          <section class="panel">
            <h2><?= $selectedProduct === null ? '새 상품 등록' : '상품 정보 수정' ?></h2>
            <form method="post" enctype="multipart/form-data" action="<?= adminEscape($basePath) ?>/admin-products.php">
              <input type="hidden" name="csrf_token" value="<?= adminEscape(adminCsrfToken()) ?>">
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="product_id" value="<?= adminEscape((string) $formProduct['product_id']) ?>">
              <div class="form-grid">
                <div class="field full">
                  <label for="product_type">상품 종류</label>
                  <select id="product_type" name="product_type" required>
                    <option value="">선택하세요</option>
                    <?php foreach (ADMIN_PRODUCT_KINDS as $kindType => $kindLabel): ?>
                      <option value="<?= $kindType ?>"<?= (int) $formProduct['product_type'] === $kindType ? ' selected' : '' ?>><?= $kindType ?>: <?= adminEscape($kindLabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <span class="field-hint">종류마다 한 개씩만 등록할 수 있습니다. 영문 상점 문구도 이 종류(1=악보, 2=음원)로 연결됩니다.</span>
                </div>
                <div class="field full">
                  <label for="name">상품명</label>
                  <input id="name" name="name" maxlength="255" value="<?= adminEscape((string) $formProduct['name']) ?>" required>
                </div>
                <div class="field full">
                  <label for="subtitle">부제</label>
                  <input id="subtitle" name="subtitle" maxlength="255" value="<?= adminEscape((string) $formProduct['subtitle']) ?>">
                </div>
                <div class="field full">
                  <label for="description">상품 설명</label>
                  <textarea id="description" name="description"><?= adminEscape((string) $formProduct['description']) ?></textarea>
                </div>
                <div class="field">
                  <label for="regular_price_krw">정가 (원)</label>
                  <input id="regular_price_krw" name="regular_price_krw" type="number" min="0" max="4294967295" value="<?= adminEscape((string) $formProduct['regular_price_krw']) ?>" required>
                </div>
                <div class="field">
                  <label for="sale_price_krw">판매가 (원)</label>
                  <input id="sale_price_krw" name="sale_price_krw" type="number" min="0" max="4294967295" value="<?= adminEscape((string) $formProduct['sale_price_krw']) ?>" required>
                </div>
                <div class="field">
                  <label for="sort_order">진열 순서</label>
                  <input id="sort_order" name="sort_order" type="number" min="0" value="<?= adminEscape((string) $formProduct['sort_order']) ?>" required>
                </div>
                <div class="field">
                  <span class="field-title">진열 상태</span>
                  <label class="check-row"><input type="checkbox" name="is_active" value="1"<?= (int) $formProduct['is_active'] === 1 ? ' checked' : '' ?>> 상점에 진열 (해제하면 숨김 처리)</label>
                </div>
                <div class="field full">
                  <label for="cover">커버 이미지 (JPG, PNG, WebP · 최대 12MB)</label>
                  <input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp">
                  <?php if (is_string($formProduct['cover_path']) && $formProduct['cover_path'] !== ''): ?>
                    <p class="path-note">현재: <?= adminEscape($formProduct['cover_path']) ?> · 새 파일을 올리지 않으면 유지됩니다.</p>
                  <?php endif; ?>
                </div>
                <div class="field full">
                  <label for="preview_audio">미리듣기 음원 (MP3, WAV, M4A, OGG · 최대 50MB)</label>
                  <input id="preview_audio" name="preview_audio" type="file" accept="audio/mpeg,audio/wav,audio/mp4,audio/ogg">
                  <?php if (is_string($formProduct['preview_audio_path']) && $formProduct['preview_audio_path'] !== ''): ?>
                    <p class="path-note">현재: <?= adminEscape($formProduct['preview_audio_path']) ?> · 새 파일을 올리지 않으면 유지됩니다.</p>
                  <?php endif; ?>
                </div>
                <div class="field full">
                  <label for="download_file">구매 후 다운로드 파일 (악보: PDF/ZIP, 음원: MP3·WAV·M4A·OGG/ZIP · 최대 100MB)</label>
                  <input id="download_file" name="download_file" type="file" accept="application/pdf,application/zip,application/x-zip-compressed,audio/mpeg,audio/wav,audio/mp4,audio/ogg">
                  <p class="path-note">현재: <?= adminEscape((string) $formProduct['download_path']) ?><?= $selectedProduct !== null ? ' · 새 파일을 올리지 않으면 유지됩니다.' : '' ?></p>
                </div>
                <?php if ($selectedProduct !== null): ?>
                  <div class="field full">
                    <span class="field-title">악보 미리보기 이미지 (JPG, PNG, WebP · 파일당 최대 15MB)</span>
                    <?php if ($selectedAssets !== []): ?>
                      <div class="asset-list">
                        <?php foreach ($selectedAssets as $asset): ?>
                          <div class="asset-row">
                            <img src="<?= adminEscape($basePath) ?>/<?= adminEscape(ltrim((string) $asset['asset_path'], '/')) ?>" alt="">
                            <div class="asset-edit">
                              <input name="asset_label[<?= (int) $asset['asset_id'] ?>]" value="<?= adminEscape((string) $asset['label']) ?>" maxlength="255" aria-label="미리보기 페이지 이름">
                              <span class="path-note"><?= adminEscape((string) $asset['asset_path']) ?></span>
                            </div>
                            <input type="number" name="asset_order[<?= (int) $asset['asset_id'] ?>]" min="0" max="2147483647" value="<?= (int) $asset['sort_order'] ?>" aria-label="미리보기 순서">
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <p class="path-note">등록된 악보 미리보기 이미지가 없습니다.</p>
                    <?php endif; ?>
                    <input name="preview_images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
                    <span class="field-hint">여러 파일을 한 번에 선택하면 파일 이름 순서대로 미리보기 페이지가 추가됩니다.</span>
                  </div>
                <?php elseif ($editingNewProduct): ?>
                  <div class="field full">
                    <label for="preview_images">악보 미리보기 이미지 (선택 · 파일당 최대 15MB)</label>
                    <input id="preview_images" name="preview_images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
                  </div>
                <?php endif; ?>
              </div>
              <div class="form-actions">
                <button class="admin-button" type="submit">저장</button>
              </div>
            </form>
          </section>
        <?php else: ?>
          <section class="panel">
            <h2>관리할 상품을 선택하세요</h2>
            <p class="muted">왼쪽 목록에서 상품을 고르거나 새 상품을 등록할 수 있습니다. 상점에 표시할 이미지는 커버 이미지에 업로드하면 홈페이지와 한글·영문 상점에서 함께 사용됩니다.</p>
          </section>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
