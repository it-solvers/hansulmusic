<?php
// [1단계] 영문 사이트의 공통 내비게이션 상태와 링크를 구성합니다.
$headerActivePage = $headerActivePage ?? '';
// [2단계] 가능한 앱 기준 경로에서 후행 슬래시를 정리합니다.
$headerBasePath = rtrim((string) ($headerBasePath ?? $basePath ?? appBasePath()), '/');
$headerHomeHref = $headerBasePath . '/en/';
// [2단계] 동적 경로를 속성에 출력할 때 재사용할 HTML 이스케이프 함수입니다.
$headerEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
// [2단계] 언어 전환을 포함한 영문 내비게이션 항목을 정의합니다.
$headerLinks = [
    ['key' => 'concert', 'label' => 'Concert (Korean)', 'href' => $headerBasePath . '/concert.php?lang=en'],
    ['key' => 'news', 'label' => 'News (Korean)', 'href' => $headerBasePath . '/news.php?lang=en'],
    [
        'key' => 'shop',
        'label' => 'Scores &amp; Recordings',
        // [3단계] 홈에서는 상품 섹션으로 이동하고 다른 페이지에서는 영문 상점으로 이동합니다.
        'href' => $headerActivePage === 'home' ? '#featured-products' : $headerBasePath . '/en/shop.php',
    ],
    [
        'key' => 'commission',
        'label' => 'Commission',
        // [3단계] 홈의 문의 영역은 현재 문서 내 앵커로 연결합니다.
        'href' => $headerActivePage === 'home' ? '#contact' : $headerBasePath . '/en/#contact',
    ],
    ['key' => 'youtube', 'label' => 'YouTube (Korean)', 'href' => $headerBasePath . '/youtube.php?lang=en'],
    ['key' => 'hymnal', 'label' => 'Hymns (Korean)', 'href' => $headerBasePath . '/hymnal-parts.php?lang=en'],
    ['key' => 'praise', 'label' => 'Praise Songs (Korean)', 'href' => $headerBasePath . '/praise-song-parts.php?lang=en'],
];
?>
<!-- [1단계] 영문 브랜드, 계정 도구와 페이지 내비게이션을 렌더링합니다. -->
<header class="site-header home-header">
  <a class="logo" href="<?= $headerEscape($headerHomeHref) ?>">HANSUL MUSIC<small>COMPOSITION · ARRANGEMENT</small></a>
  <button class="menu-toggle" type="button" aria-label="Open menu" data-open-label="Open menu" data-close-label="Close menu" aria-expanded="false" aria-controls="site-nav">
    <span></span>
    <span></span>
    <span></span>
  </button>
  <nav class="site-nav" id="site-nav" data-language="en" aria-label="Main navigation">
    <div class="home-nav-tools">
      <div class="nav-account">
        <button class="signup-nav-button" type="button" data-open-auth>Sign in</button>
        <button class="nav-member-identity" type="button" data-member-identity hidden disabled></button>
      </div>
      <a class="language-switch" href="<?= $headerEscape($headerBasePath . '/index.php') ?>" lang="ko" aria-label="Switch language to Korean">
        <svg class="language-switch-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <circle cx="12" cy="12" r="9"></circle>
          <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path>
        </svg>
        <span>Korean</span>
      </a>
    </div>
    <div class="home-nav-links">
      <?php foreach ($headerLinks as $link): ?>
        <a href="<?= $headerEscape($link['href']) ?>"<?= $headerActivePage === $link['key'] ? ' aria-current="page"' : '' ?>><?= $link['label'] ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
</header>
