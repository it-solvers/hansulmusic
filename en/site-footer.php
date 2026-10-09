<?php
// [1단계] 영어 페이지 하단의 브랜드, 채널 및 사업자 정보를 출력합니다.
// [2단계] 앱 기준 경로에서 영문 디렉터리를 제거해 사이트 루트 링크를 만듭니다.
$footerScriptBase = function_exists('appBasePath') ? rtrim(appBasePath(), '/') : '';
$footerSiteBase = dirname($footerScriptBase);
// [3단계] 루트 설치와 현재 디렉터리 표기는 링크 접두사에서 제거합니다.
if ($footerSiteBase === '/' || $footerSiteBase === '.') {
    $footerSiteBase = '';
}
// [2단계] 내부 링크에 사용할 경로를 HTML 속성 문맥에 맞게 이스케이프합니다.
$footerSiteBase = htmlspecialchars($footerSiteBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!-- [2단계] 영문 사이트 푸터와 한국어 홈페이지 전환 링크를 표시합니다. -->
<footer>
  <div class="wrap site-footer-inner">
    <div class="site-footer-brand">
      <a href="<?= $footerSiteBase ?>/en/" aria-label="HANSUL MUSIC home">
        <img src="https://static.wixstatic.com/media/d49565_b4834284a8f1429e84ca2524a187b9e0.png/v1/fill/w_72,h_41,al_c,q_85,usm_0.66_1.00_0.01,enc_avif,quality_auto/d49565_b4834284a8f1429e84ca2524a187b9e0.png" alt="HANSUL MUSIC (HSM)" width="72" height="41" loading="lazy">
      </a>
      <span class="site-footer-president">Composer: Dr. Sangeun Han</span>
    </div>
    <div class="site-footer-social">
      <a class="site-footer-youtube" href="https://www.youtube.com/channel/UC3TyN3LI9tszetiNJeGegyg/videos?view=0&amp;sort=p&amp;flow=grid" target="_blank" rel="noopener noreferrer" aria-label="HANSUL MUSIC YouTube channel">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4l6.3 3.6-6.3 3.6Z"/></svg>
        <span>HANSUL MUSIC YouTube channel</span>
      </a>
    </div>
    <div class="site-footer-meta">
      <p>Corporate Registration No.: 271-46-00745</p>
      <p>Mail Order Business Report No.: 2025-인천연수구-2420</p>
      <p>8069, 262 Aengogae-ro, Yeonsu-gu, Incheon, Republic of Korea</p>
      <p><a href="mailto:sangeun@hansulmusic.com">sangeun@hansulmusic.com</a></p>
      <p><a href="<?= $footerSiteBase ?>/index.php" lang="ko">Korean homepage</a></p>
    </div>
    <p class="site-footer-copyright">Copyright © 2016 HANSUL MUSIC. All rights reserved.</p>
  </div>
</footer>
