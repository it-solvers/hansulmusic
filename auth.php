<?php
declare(strict_types=1);

// [1단계] 회원 인증과 마케팅 수신 동의 API를 처리합니다.
// [2단계] 세션 쿠키 보안 옵션과 JSON 응답 환경을 설정합니다.
session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

// [2단계] 상태 코드와 JSON 본문을 반환하고 요청 처리를 종료합니다.
/** HTTP 상태와 JSON 응답을 전송한 뒤 현재 요청 처리를 종료합니다. */
function respondJson(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// [2단계] 환경 변수의 DB 설정을 확인한 뒤 PDO 연결을 생성합니다.
/** 필수 DB 환경 변수를 검증하고 인증 API용 PDO 연결을 반환합니다. */
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

// [2단계] 세션 요청 위조 방지 토큰을 세션별로 초기화합니다.
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

// [1단계] GET 요청에는 현재 로그인 상태와 수신 동의 정보를 제공합니다.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $marketingConsent = false;
    if (isset($_SESSION['member_id'])) {
        try {
            $pdo = databaseConnection();
            // [3단계] 세션의 member_id로 members에서 수신 동의 값만 읽어 아래 JSON 응답과 로그인 모달 상태에 전달합니다.
            $statement = $pdo->prepare(
                'SELECT marketing_consent
                 FROM members
                 WHERE member_id = :member_id
                 LIMIT 1'
            );
            $statement->execute(['member_id' => (int) $_SESSION['member_id']]);
            $member = $statement->fetch(PDO::FETCH_ASSOC);
            if ($member === false) {
                // [3단계] DB에 없는 회원은 세션을 정리해 만료된 로그인 상태를 해제합니다.
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

// [1단계] POST 요청은 CSRF 검증 후 로그아웃, 동의 변경, 로그인으로 분기합니다.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    respondJson(405, ['message' => '요청 방식을 확인해 주세요.']);
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
// [3단계] 세션 토큰과 요청 토큰을 상수 시간 비교해 위조 요청을 거부합니다.
if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respondJson(403, ['message' => '요청을 확인할 수 없습니다. 페이지를 새로고침한 후 다시 시도해 주세요.']);
}

$action = (string) ($_POST['action'] ?? '');
if ($action === 'logout') {
    // [2단계] 로그아웃 시 인증 정보를 지우고 세션 ID와 CSRF 토큰을 교체합니다.
    unset($_SESSION['member_id'], $_SESSION['member_name'], $_SESSION['member_email']);
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    respondJson(200, ['authenticated' => false, 'csrfToken' => $_SESSION['csrf_token']]);
}

if (in_array($action, ['withdraw_marketing_consent', 'grant_marketing_consent'], true)) {
    // [2단계] 로그인 회원의 마케팅 수신 동의 상태를 갱신합니다.
    if (!isset($_SESSION['member_id'])) {
        respondJson(401, ['message' => '로그인이 필요합니다.']);
    }
    $marketingConsent = $action === 'grant_marketing_consent';
    try {
        $pdo = databaseConnection();
        // [3단계] members의 동의 여부와 변경 시각을 갱신하며, 갱신 결과는 아래 JSON 응답으로 모달에 반영됩니다.
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
            // [3단계] 값이 그대로여서 변경 행이 없어도 실제 회원 존재 여부를 확인합니다.
            // 이 확인 결과는 사용자 표시 데이터가 아니라 세션 회원의 유효성 판단에만 사용합니다.
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

// [2단계] 로그인 입력을 정규화하고 형식과 크기를 먼저 검증합니다.
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
    // [2단계] 이메일로 회원을 찾고 비밀번호를 검증한 뒤 새 로그인 세션을 발급합니다.
    $pdo = databaseConnection();
    // [3단계] members에서 이메일이 일치하는 인증 필드를 읽고, 성공 시 이름·이메일·수신 동의를 세션과 JSON에 넣습니다.
    $statement = $pdo->prepare(
        'SELECT member_id, name, email, password_hash, marketing_consent
         FROM members
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $member = $statement->fetch(PDO::FETCH_ASSOC);

    if (
        // [3단계] 존재하지 않는 회원과 잘못된 해시를 같은 인증 실패 응답으로 처리합니다.
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
