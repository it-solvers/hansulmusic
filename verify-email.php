<?php
declare(strict_types=1);

// [1단계] 이메일 인증 토큰을 확인하고 결과 페이지를 출력합니다.
// [2단계] 출력 문맥에 맞게 동적 문구를 이스케이프합니다.
/** 인증 결과 문구를 HTML에 출력할 수 있도록 이스케이프합니다. */
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$message = '인증 링크가 올바르지 않거나 만료되었습니다. 회원가입을 다시 요청해 주세요.';
$verified = false;
$token = (string) ($_GET['token'] ?? '');

// [2단계] 토큰 형식이 유효한 경우에만 DB에서 인증 정보를 확인합니다.
if (preg_match('/\A[a-f0-9]{64}\z/', $token) === 1) {
    try {
        // [3단계] DB 접속 자격 증명이 누락되면 인증 처리를 진행하지 않습니다.
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
        // [2단계] 토큰 행을 잠근 트랜잭션 안에서 회원과 토큰의 인증 상태를 함께 갱신합니다.
        $pdo->beginTransaction();
        // [3단계] 전달된 토큰의 해시로 인증 테이블에서 만료·완료 상태와 회원 ID를 찾습니다.
        // 조회한 행은 아래 두 UPDATE의 대상이 되며, 성공 여부는 결과 안내 문구에 표시됩니다.
        $statement = $pdo->prepare(
            'SELECT verification_id, member_id, expires_at, verified_at
             FROM member_email_verification_tokens
             WHERE token_hash = :token_hash
             FOR UPDATE'
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $verification = $statement->fetch(PDO::FETCH_ASSOC);

        // [3단계] 이미 인증된 토큰은 성공으로 간주하고, 미인증 토큰은 만료 여부를 확인합니다.
        if ($verification !== false && $verification['verified_at'] !== null) {
            $verified = true;
        } elseif (
            $verification !== false
            && strtotime($verification['expires_at'] . ' UTC') > time()
        ) {
            // [3단계] 조회된 토큰의 회원 이메일 인증 시각과 토큰 완료 시각을 한 트랜잭션에서 기록합니다.
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

        // [2단계] 검증 결과에 따라 페이지에 표시할 안내 문구를 선택합니다.
        if ($verified) {
            $message = '이메일 인증이 완료되었습니다. 이제 회원가입이 완료되었습니다.';
        }
    } catch (PDOException | RuntimeException $exception) {
        // [3단계] 처리 도중 실패하면 열린 트랜잭션을 되돌려 부분 갱신을 방지합니다.
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
<!-- [1단계] 인증 결과를 사용자에게 보여 주고 공통 사이트 푸터를 포함합니다. -->
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
