<?php
$host = 'db'; // docker-compose 내부망에서는 서비스 이름인 'db'로 접근합니다.
$user = 'root';
$pass = 'root1234';
$dbname = 'hansul_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<h1>🎉 한설뮤직 MariaDB 연결 성공!</h1>";
    echo "<p>PHP 8.2와 MariaDB 10.x 컨테이너가 내부망으로 완벽하게 연동되었습니다.</p>";
} catch (PDOException $e) {
    echo "<h1>❌ DB 연결 실패</h1>";
    echo "<p>에러 내용: " . $e->getMessage() . "</p>";
}
?>
