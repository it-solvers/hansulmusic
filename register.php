<?php
declare(strict_types=1);

session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function respondJson(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
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
$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
$errors = [];
$notice = '';
$name = '';
$email = '';
$marketingConsent = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['csrf'])) {
    respondJson(200, ['csrfToken' => $_SESSION['csrf_token']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $emailConfirm = trim((string) ($_POST['email_confirm'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    $marketingConsent = ($_POST['marketing_consent'] ?? '') === '1';
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $errors[] = '요청을 확인할 수 없습니다. 페이지를 새로고침한 후 다시 시도해 주세요.';
    }
    if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
        $errors[] = '이름을 입력해 주세요. 이름은 100자 이하여야 합니다.';
    }
    if (
        $email === ''
        || strlen($email) > 255
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    ) {
        $errors[] = '올바른 이메일 주소를 입력해 주세요.';
    } elseif (!hash_equals($email, $emailConfirm)) {
        $errors[] = '이메일 주소가 서로 일치하지 않습니다.';
    }
    if (mb_strlen($password, '8bit') < 8 || strlen($password) > 72) {
        $errors[] = '비밀번호는 8자 이상, 72바이트 이하여야 합니다.';
    } elseif (!hash_equals($password, $passwordConfirm)) {
        $errors[] = '비밀번호가 서로 일치하지 않습니다.';
    }

    $responseStatus = 422;
    if ($errors === []) {
        try {
            $pdo = databaseConnection();
            $statement = $pdo->prepare(
                'INSERT INTO members (
                    name, email, password_hash, marketing_consent, marketing_consent_updated_at
                 ) VALUES (
                    :name, :email, :password_hash, :marketing_consent, :marketing_consent_updated_at
                 )'
            );
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'marketing_consent' => $marketingConsent ? 1 : 0,
                'marketing_consent_updated_at' => $marketingConsent ? gmdate('Y-m-d H:i:s') : null,
            ]);

            session_regenerate_id(true);
            $_SESSION['member_id'] = (int) $pdo->lastInsertId();
            $_SESSION['member_name'] = $name;
            $_SESSION['member_email'] = $email;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $notice = '가입 및 로그인이 완료되었습니다.';
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $responseStatus = 409;
                $errors[] = '이미 가입된 이메일 주소입니다. Sign in을 이용해 주세요.';
            } else {
                error_log('Member registration database error: ' . $exception->getMessage());
                $responseStatus = 503;
                $errors[] = '회원가입을 처리할 수 없습니다. 잠시 후 다시 시도해 주세요.';
            }
        } catch (RuntimeException $exception) {
            error_log('Member registration configuration error: ' . $exception->getMessage());
            $responseStatus = 503;
            $errors[] = '회원가입을 처리할 수 없습니다. 잠시 후 다시 시도해 주세요.';
        }
    }

    if ($wantsJson) {
        if ($errors !== []) {
            respondJson($responseStatus, ['message' => implode(' ', $errors)]);
        }
        respondJson(201, [
            'message' => $notice,
            'authenticated' => true,
            'name' => $name,
            'email' => $email,
            'marketingConsent' => $marketingConsent,
            'csrfToken' => $_SESSION['csrf_token'],
        ]);
    }
    if ($errors !== []) {
        http_response_code($responseStatus);
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create account | HANSUL MUSIC</title>
  <meta name="description" content="한설뮤직 회원가입">
  <link rel="stylesheet" href="css/style.css?v=21">
  <link rel="stylesheet" href="css/register.css?v=7">
</head>
<body class="register-page">
  <main class="register-card">
    <a class="register-logo" href="index.php">HANSUL MUSIC</a>
    <p class="register-eyebrow">Create an account</p>
    <h1>Create account</h1>
    <p class="register-intro">이름, 이메일 주소, 비밀번호를 입력해 주세요.</p>

    <?php if ($notice !== ''): ?>
      <p class="register-message register-message-success" role="status"><?= escape($notice) ?></p>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
      <div class="register-message register-message-error" role="alert">
        <?php foreach ($errors as $error): ?>
          <p><?= escape($error) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="register.php" class="register-form">
      <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
      <label for="name">이름</label>
      <input id="name" name="name" type="text" maxlength="100" autocomplete="name" value="<?= escape($name) ?>" required>
      <label for="email">이메일 주소</label>
      <input id="email" name="email" type="email" inputmode="email" autocapitalize="off" spellcheck="false" maxlength="255" autocomplete="email" value="<?= escape($email) ?>" required>
      <label for="email-confirm">이메일 주소 확인</label>
      <input id="email-confirm" name="email_confirm" type="email" inputmode="email" autocapitalize="off" spellcheck="false" maxlength="255" autocomplete="email" required>
      <label for="password">비밀번호</label>
      <input id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
      <label for="password-confirm">비밀번호 확인</label>
      <input id="password-confirm" name="password_confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
      <label class="register-consent">
        <input type="checkbox" name="marketing_consent" value="1"<?= $marketingConsent ? ' checked' : '' ?>>
        <span>신규 작품 출시 및 이벤트·광고 소식을 이메일로 받는 데 동의합니다. <strong>(선택)</strong></span>
      </label>
      <button type="submit" class="register-submit">Create account</button>
    </form>
    <a class="register-back" href="index.php">메인으로 돌아가기</a>
  </main>
  <?php require __DIR__ . '/site-footer.php'; ?>
</body>
</html>
