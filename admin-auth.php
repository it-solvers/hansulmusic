<?php
declare(strict_types=1);

// [1단계] 상품·영상 관리 화면이 함께 쓰는 세션, DB 연결, 자격 증명 파일 인증 함수입니다.
require_once __DIR__ . '/site-config.php';

session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Strict',
]);
session_start();

/** 관리자 화면 공통 메뉴와 영상·찬송가 구분(section) 정의입니다. */
const ADMIN_MEDIA_SECTIONS = [
    'videos' => '영상 (Concert / YouTube)',
    'hymns' => '찬송가',
    'praise' => '찬양곡',
];

/** 관리자 화면의 동적 값을 HTML에 안전하게 출력합니다. */
function adminEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 필수 DB 환경 변수를 확인하고 상품 관리용 PDO 연결을 반환합니다. */
function adminDatabaseConnection(): PDO
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
        ],
    );
}

/** 현재 세션에 생성된 CSRF 토큰을 반환합니다. */
function adminCsrfToken(): string
{
    $_SESSION['admin_csrf_token'] ??= bin2hex(random_bytes(32));
    return $_SESSION['admin_csrf_token'];
}

/** 웹 공개 디렉터리 바깥의 두 줄 파일에서 관리자 아이디와 비밀번호 해시(password_hash 결과)를 읽습니다. */
function adminCredentials(): array
{
    $credentialsPath = __DIR__ . '/../.admin-products-login';
    if (!is_file($credentialsPath) || !is_readable($credentialsPath)) {
        throw new RuntimeException('The administrator credential file is missing or unreadable.');
    }

    $lines = file($credentialsPath, FILE_IGNORE_NEW_LINES);
    if ($lines === false || count($lines) !== 2) {
        throw new RuntimeException('The administrator credential file must contain exactly two lines.');
    }

    $username = rtrim($lines[0], "\r");
    $password = rtrim($lines[1], "\r");
    if ($username === '' || $password === '') {
        throw new RuntimeException('The administrator credential file contains an empty value.');
    }

    return [$username, $password];
}

/**
 * 관리자 로그인 요청(action=login)을 검증하고 성공하면 인증 세션을 만든 뒤 $redirectPath로 이동합니다.
 *
 * @return string 실패 시 화면에 보여줄 오류 메시지입니다.
 */
function adminAttemptLogin(string $adminUsername, string $adminPasswordHash, string $redirectPath): string
{
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if (hash_equals(adminCsrfToken(), $submittedToken)
        && hash_equals($adminUsername, (string) ($_POST['username'] ?? ''))
        && password_verify((string) ($_POST['password'] ?? ''), $adminPasswordHash)
    ) {
        // [3단계] 인증 성공 시 세션 ID를 교체해 로그인 전 세션 고정 공격을 방지합니다.
        session_regenerate_id(true);
        $_SESSION['product_admin_authenticated'] = true;
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
        header('Location: ' . appBasePath() . $redirectPath, true, 303);
        exit;
    }
    return '관리자 아이디 또는 비밀번호를 확인해 주세요.';
}
