<?php
$headerIsEnglish = $headerIsEnglish ?? false;
$headerActivePage = $headerActivePage ?? '';
$headerBasePath = rtrim((string) ($headerBasePath ?? $basePath ?? appBasePath()), '/');
$headerHomeHref = $headerIsEnglish ? $headerBasePath . '/en/' : $headerBasePath . '/index.php';
$headerLanguageHref = $headerIsEnglish ? $headerBasePath . '/index.php' : $headerBasePath . '/en/';
$headerEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$headerLinks = $headerIsEnglish
    ? [
        ['key' => 'concert', 'label' => 'Concert', 'href' => $headerBasePath . '/concert.php'],
        ['key' => 'news', 'label' => 'News', 'href' => $headerBasePath . '/news.php'],
        [
            'key' => 'shop',
            'label' => 'Scores &amp; Recordings',
            'href' => $headerActivePage === 'home' ? '#featured-products' : $headerBasePath . '/en/shop.php',
        ],
        [
            'key' => 'commission',
            'label' => 'Commission',
            'href' => $headerActivePage === 'home' ? '#contact' : $headerBasePath . '/en/#contact',
        ],
        ['key' => 'youtube', 'label' => 'YouTube', 'href' => $headerBasePath . '/youtube.php'],
        ['key' => 'hymnal', 'label' => 'Hymns', 'href' => $headerBasePath . '/hymnal-parts.php'],
        ['key' => 'praise', 'label' => 'Praise Songs', 'href' => $headerBasePath . '/praise-song-parts.php'],
    ]
    : [
        ['key' => 'concert', 'label' => 'Concert', 'href' => $headerBasePath . '/concert.php'],
        ['key' => 'news', 'label' => 'News', 'href' => $headerBasePath . '/news.php'],
        [
            'key' => 'shop',
            'label' => 'Scores &amp; Recordings',
            'href' => $headerBasePath . '/shop.php',
        ],
        ['key' => 'commission', 'label' => 'Commission', 'href' => $headerBasePath . '/index.php#contact'],
        ['key' => 'youtube', 'label' => 'YouTube', 'href' => $headerBasePath . '/youtube.php'],
        ['key' => 'hymnal', 'label' => 'Hymnal (찬송가)', 'href' => $headerBasePath . '/hymnal-parts.php'],
        ['key' => 'praise', 'label' => 'Praise Song (찬양곡)', 'href' => $headerBasePath . '/praise-song-parts.php'],
    ];
?>
<header class="site-header home-header">
  <a class="logo" href="<?= $headerEscape($headerHomeHref) ?>">
    HANSUL MUSIC<small><?= $headerIsEnglish ? 'COMPOSITION · ARRANGEMENT' : 'HSM · MUSIC STUDIO' ?></small>
  </a>
  <button class="menu-toggle" type="button" aria-label="<?= $headerIsEnglish ? 'Open menu' : '메뉴 열기' ?>" aria-expanded="false" aria-controls="site-nav">
    <span></span>
    <span></span>
    <span></span>
  </button>
  <nav class="site-nav" id="site-nav"<?= $headerIsEnglish ? ' aria-label="Main navigation"' : '' ?>>
    <div class="home-nav-tools">
      <div class="nav-account">
        <button class="signup-nav-button" type="button" data-open-auth>Sign in</button>
        <button class="nav-member-identity" type="button" data-member-identity hidden disabled></button>
      </div>
      <a class="language-switch" href="<?= $headerEscape($headerLanguageHref) ?>" lang="<?= $headerIsEnglish ? 'ko' : 'en' ?>" aria-label="<?= $headerIsEnglish ? 'Switch language to Korean' : 'Switch language to English' ?>">
        <svg class="language-switch-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <circle cx="12" cy="12" r="9"></circle>
          <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path>
        </svg>
        <span><?= $headerIsEnglish ? 'Korean' : 'English' ?></span>
      </a>
    </div>
    <div class="home-nav-links">
      <?php foreach ($headerLinks as $link): ?>
        <a href="<?= $headerEscape($link['href']) ?>"<?= $headerActivePage === $link['key'] ? ' aria-current="page"' : '' ?>><?= $link['label'] ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
</header>
