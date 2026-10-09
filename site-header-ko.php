<?php
// [1단계] 한국어 사이트의 공통 내비게이션 상태와 링크를 구성합니다.
$headerActivePage = $headerActivePage ?? '';
// [2단계] 가능한 앱 기준 경로에서 후행 슬래시를 정리합니다.
$headerBasePath = rtrim((string) ($headerBasePath ?? $basePath ?? appBasePath()), '/');
$headerHomeHref = $headerBasePath . '/index.php';
// [2단계] 동적 경로를 속성에 출력할 때 재사용할 HTML 이스케이프 함수입니다.
$headerEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
// [2단계] 주요 페이지와 언어 전환을 위한 한국어 내비게이션 항목을 정의합니다.
$headerLinks = [
    ['key' => 'concert', 'label' => 'Concert', 'href' => $headerBasePath . '/concert.php'],
    ['key' => 'news', 'label' => 'News', 'href' => $headerBasePath . '/news.php'],
    [
        'key' => 'shop',
        'label' => 'Scores &amp; Recordings',
        // [3단계] 홈에서는 상품 섹션으로 이동하고 다른 페이지에서는 상점으로 이동합니다.
        'href' => $headerActivePage === 'home' ? '#featured-products' : $headerBasePath . '/shop.php',
    ],
    ['key' => 'commission', 'label' => 'Commission', 'href' => $headerBasePath . '/index.php#contact'],
    ['key' => 'youtube', 'label' => 'YouTube', 'href' => $headerBasePath . '/youtube.php'],
    ['key' => 'hymnal', 'label' => 'Hymnal (찬송가)', 'href' => $headerBasePath . '/hymnal-parts.php'],
    ['key' => 'praise', 'label' => 'Praise Song (찬양곡)', 'href' => $headerBasePath . '/praise-song-parts.php'],
];
?>
<!-- [1단계] 한국어 브랜드, 계정 도구와 페이지 내비게이션을 렌더링합니다. -->
<header class="site-header home-header">
  <a class="logo" href="<?= $headerEscape($headerHomeHref) ?>">HANSUL MUSIC<small>HSM · MUSIC STUDIO</small></a>
  <button class="menu-toggle" type="button" aria-label="메뉴 열기" data-open-label="메뉴 열기" data-close-label="메뉴 닫기" aria-expanded="false" aria-controls="site-nav">
    <span></span>
    <span></span>
    <span></span>
  </button>
  <nav class="site-nav" id="site-nav" data-language="ko">
    <div class="home-nav-tools">
      <div class="nav-account">
        <button class="signup-nav-button" type="button" data-open-auth>Sign in</button>
        <button class="nav-member-identity" type="button" data-member-identity hidden disabled></button>
      </div>
      <a class="language-switch" href="<?= $headerEscape($headerBasePath . '/en/') ?>" lang="en" aria-label="Switch language to English">
        <svg class="language-switch-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <circle cx="12" cy="12" r="9"></circle>
          <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path>
        </svg>
        <span>English</span>
      </a>
    </div>
    <div class="home-nav-links">
      <?php foreach ($headerLinks as $link): ?>
        <a href="<?= $headerEscape($link['href']) ?>"<?= $headerActivePage === $link['key'] ? ' aria-current="page"' : '' ?>><?= $link['label'] ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
</header>
