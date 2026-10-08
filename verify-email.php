<?php
declare(strict_types=1);

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$message = '인증 링크가 올바르지 않거나 만료되었습니다. 회원가입을 다시 요청해 주세요.';
$verified = false;
$token = (string) ($_GET['token'] ?? '');

if (preg_match('/\A[a-f0-9]{64}\z/', $token) === 1) {
    try {
        $user = getenv('DB_USER');
        $password = getenv('DB_PASSWORD');
        if ($user === false || $user === '' || $password === false) {
            throw new RuntimeException('DB_USER and DB_PASSWORD must be configured.');
        }

        $host = getenv('DB_HOST') ?: 'db';
        $database = getenv('DB_NAME') ?: 'hansul_db';
        $pdo = new PDO(
            "mysql:host={$host};dbname={$database};charset=utf8mb4",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo->beginTransaction();
        $statement = $pdo->prepare(
            'SELECT verification_id, member_id, expires_at, verified_at
             FROM member_email_verification_tokens
             WHERE token_hash = :token_hash
             FOR UPDATE'
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $verification = $statement->fetch(PDO::FETCH_ASSOC);

        if ($verification !== false && $verification['verified_at'] !== null) {
            $verified = true;
        } elseif (
            $verification !== false
            && strtotime($verification['expires_at'] . ' UTC') > time()
        ) {
            $updateMember = $pdo->prepare(
                'UPDATE members
                 SET email_verified_at = UTC_TIMESTAMP()
                 WHERE member_id = :member_id'
            );
            $updateMember->execute(['member_id' => $verification['member_id']]);

            $markToken = $pdo->prepare(
                'UPDATE member_email_verification_tokens
                 SET verified_at = UTC_TIMESTAMP()
                 WHERE verification_id = :verification_id'
            );
            $markToken->execute(['verification_id' => $verification['verification_id']]);
            $verified = true;
        }
        $pdo->commit();

        if ($verified) {
            $message = '이메일 인증이 완료되었습니다. 이제 회원가입이 완료되었습니다.';
        }
    } catch (PDOException | RuntimeException $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Email verification error: ' . $exception->getMessage());
        http_response_code(500);
        $message = '인증을 처리하는 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.';
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>이메일 인증 | HANSUL MUSIC</title>
  <link rel="stylesheet" href="css/style.css?v=21">
  <link rel="stylesheet" href="css/register.css?v=7">
</head>
<body class="register-page">
  <main class="register-card">
    <a class="register-logo" href="index.php">HANSUL MUSIC</a>
    <p class="register-eyebrow">Email verification</p>
    <h1><?= $verified ? '인증 완료' : '이메일 인증' ?></h1>
    <p class="register-intro" role="status"><?= escape($message) ?></p>
    <a class="register-submit register-home-link" href="index.php">메인으로 돌아가기</a>
  </main>
  <?php require __DIR__ . '/site-footer.php'; ?>
</body>
</html>
