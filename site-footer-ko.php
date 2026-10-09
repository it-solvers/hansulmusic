<?php
// [1단계] 한국어 사이트 하단의 브랜드, 채널 및 사업자 정보를 출력합니다.
// [2단계] 공통 기준 경로가 제공되는 환경에서는 내부 링크의 경로 접두사로 사용합니다.
$footerScriptBase = function_exists('appBasePath') ? appBasePath() : '';
// [3단계] 링크 속성에 삽입하기 전에 경로를 HTML 이스케이프합니다.
$footerSiteBase = htmlspecialchars($footerScriptBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!-- [2단계] 브랜드 링크와 대표자 정보를 표시합니다. -->
<footer>
  <div class="wrap site-footer-inner">
    <div class="site-footer-brand">
      <a href="<?= $footerSiteBase ?>/index.php" aria-label="HANSUL MUSIC 홈">
        <img src="https://static.wixstatic.com/media/d49565_b4834284a8f1429e84ca2524a187b9e0.png/v1/fill/w_72,h_41,al_c,q_85,usm_0.66_1.00_0.01,enc_avif,quality_auto/d49565_b4834284a8f1429e84ca2524a187b9e0.png" alt="HANSUL MUSIC (HSM)" width="72" height="41" loading="lazy">
      </a>
      <span class="site-footer-president">한설뮤직 대표: 한상은<br>(SANGEUN HAN, President)</span>
    </div>
    <div class="site-footer-social">
      <a class="site-footer-youtube" href="https://www.youtube.com/channel/UC3TyN3LI9tszetiNJeGegyg/videos?view=0&amp;sort=p&amp;flow=grid" target="_blank" rel="noopener noreferrer" aria-label="한설뮤직 YouTube 채널">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4l6.3 3.6-6.3 3.6Z"/></svg>
        <span>한설뮤직 YouTube 채널</span>
      </a>
    </div>
    <div class="site-footer-meta">
      <p>사업자등록번호: 271-46-00745 (Corporate Registration No.)</p>
      <p>통신판매신고업 신고번호: 2025-인천연수구- 2420</p>
      <p>주소: 인천, 연수구 앵고개로 262, 8069호 (오피스밸리)</p>
      <p><a href="mailto:sangeun@hansulmusic.com">sangeun@hansulmusic.com</a></p>
    </div>
    <p class="site-footer-copyright">
      <span>Copyright © 2016 HANSUL MUSIC. All rights reserved.</span>
      <a class="site-footer-admin-dot" href="<?= $footerSiteBase ?>/admin-products.php" aria-label="관리자 상품 관리">·</a>
    </p>
  </div>
</footer>
