<?php
declare(strict_types=1);

session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

function respondJson(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $marketingConsent = false;
    if (isset($_SESSION['member_id'])) {
        try {
            $pdo = databaseConnection();
            $statement = $pdo->prepare(
                'SELECT marketing_consent
                 FROM members
                 WHERE member_id = :member_id
                 LIMIT 1'
            );
            $statement->execute(['member_id' => (int) $_SESSION['member_id']]);
            $member = $statement->fetch(PDO::FETCH_ASSOC);
            if ($member === false) {
                unset($_SESSION['member_id'], $_SESSION['member_name'], $_SESSION['member_email']);
                respondJson(401, ['message' => '회원 정보를 확인할 수 없습니다. 다시 로그인해 주세요.']);
            }
            $marketingConsent = (bool) $member['marketing_consent'];
        } catch (PDOException | RuntimeException $exception) {
            error_log('Member consent lookup error: ' . $exception->getMessage());
            respondJson(503, ['message' => '회원 정보를 불러올 수 없습니다. 잠시 후 다시 시도해 주세요.']);
        }
    }
    respondJson(200, [
        'csrfToken' => $_SESSION['csrf_token'],
        'authenticated' => isset($_SESSION['member_id']),
        'name' => $_SESSION['member_name'] ?? null,
        'email' => $_SESSION['member_email'] ?? null,
        'marketingConsent' => $marketingConsent,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    respondJson(405, ['message' => '요청 방식을 확인해 주세요.']);
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respondJson(403, ['message' => '요청을 확인할 수 없습니다. 페이지를 새로고침한 후 다시 시도해 주세요.']);
}

$action = (string) ($_POST['action'] ?? '');
if ($action === 'logout') {
    unset($_SESSION['member_id'], $_SESSION['member_name'], $_SESSION['member_email']);
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    respondJson(200, ['authenticated' => false, 'csrfToken' => $_SESSION['csrf_token']]);
}

if (in_array($action, ['withdraw_marketing_consent', 'grant_marketing_consent'], true)) {
    if (!isset($_SESSION['member_id'])) {
        respondJson(401, ['message' => '로그인이 필요합니다.']);
    }
    $marketingConsent = $action === 'grant_marketing_consent';
    try {
        $pdo = databaseConnection();
        $statement = $pdo->prepare(
            'UPDATE members
             SET marketing_consent = :marketing_consent,
                 marketing_consent_updated_at = UTC_TIMESTAMP()
             WHERE member_id = :member_id'
        );
        $statement->execute([
            'marketing_consent' => $marketingConsent ? 1 : 0,
            'member_id' => (int) $_SESSION['member_id'],
        ]);
        if ($statement->rowCount() === 0) {
            $check = $pdo->prepare('SELECT member_id FROM members WHERE member_id = :member_id');
            $check->execute(['member_id' => (int) $_SESSION['member_id']]);
            if ($check->fetchColumn() === false) {
                respondJson(401, ['message' => '회원 정보를 확인할 수 없습니다. 다시 로그인해 주세요.']);
            }
        }
        respondJson(200, ['marketingConsent' => $marketingConsent]);
    } catch (PDOException | RuntimeException $exception) {
        error_log('Marketing consent update error: ' . $exception->getMessage());
        respondJson(503, [
            'message' => $marketingConsent
                ? '소식 수신에 동의할 수 없습니다. 잠시 후 다시 시도해 주세요.'
                : '수신 동의를 철회할 수 없습니다. 잠시 후 다시 시도해 주세요.',
        ]);
    }
}

if ($action !== 'login') {
    respondJson(400, ['message' => '요청을 처리할 수 없습니다.']);
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
if (
    $email === ''
    || strlen($email) > 255
    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    || $password === ''
    || strlen($password) > 4096
) {
    respondJson(422, ['message' => '이메일 주소와 비밀번호를 확인해 주세요.']);
}

try {
    $pdo = databaseConnection();
    $statement = $pdo->prepare(
        'SELECT member_id, name, email, password_hash, marketing_consent
         FROM members
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $member = $statement->fetch(PDO::FETCH_ASSOC);

    if (
        $member === false
        || !is_string($member['password_hash'])
        || !password_verify($password, $member['password_hash'])
    ) {
        respondJson(401, ['message' => '이메일 주소 또는 비밀번호를 확인해 주세요.']);
    }

    session_regenerate_id(true);
    $_SESSION['member_id'] = (int) $member['member_id'];
    $_SESSION['member_name'] = (string) $member['name'];
    $_SESSION['member_email'] = (string) $member['email'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    respondJson(200, [
        'authenticated' => true,
        'name' => $_SESSION['member_name'],
        'email' => $_SESSION['member_email'],
        'marketingConsent' => (bool) $member['marketing_consent'],
        'csrfToken' => $_SESSION['csrf_token'],
    ]);
} catch (PDOException | RuntimeException $exception) {
    error_log('Member sign-in error: ' . $exception->getMessage());
    respondJson(503, ['message' => '로그인을 처리할 수 없습니다. 잠시 후 다시 시도해 주세요.']);
}
