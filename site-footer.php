<?php
// [1단계] 페이지가 지정한 언어에 맞춰 사이트 푸터 템플릿을 연결합니다.
$footerLanguage = $footerLanguage ?? 'ko';
// [2단계] 영문 요청은 영문 템플릿으로, 그 밖의 요청은 한국어 템플릿으로 전달합니다.
$footerTemplate = $footerLanguage === 'en' ? __DIR__ . '/en/site-footer.php' : __DIR__ . '/site-footer-ko.php';
// [3단계] 경로를 파일 시스템 기준으로 고정해 현재 페이지 깊이에 영향을 받지 않게 합니다.
require $footerTemplate;
