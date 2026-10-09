<?php
declare(strict_types=1);

// [1단계] 영문 상점 진입점을 공통 상점 템플릿에 연결합니다.
// [2단계] 언어 설정을 전달한 뒤 중복된 상점 구현을 피하도록 템플릿을 포함합니다.
// [3단계] 이 플래그로 공통 템플릿이 영문 문구와 /en 경로를 선택합니다.
$shopIsEnglish = true;
require __DIR__ . '/../shop.php';
