(() => {
  // 공통 메뉴가 없는 페이지에서는 아무 동작도 하지 않습니다.
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.site-nav');

  // 메뉴 버튼이나 내비게이션 중 하나라도 없으면 메뉴 초기화를 건너뜁니다.
  if (!(toggle instanceof HTMLButtonElement) || !(nav instanceof HTMLElement)) {
    return;
  }

  const setMenuOpen = (open) => {
    // 시각적 메뉴 상태와 스크린 리더용 버튼 상태를 함께 갱신합니다.
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? '메뉴 닫기' : '메뉴 열기');
    nav.classList.toggle('is-open', open);
  };

  toggle.addEventListener('click', () => {
    setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true');
  });

  nav.addEventListener('click', (event) => {
    // 메뉴 항목을 선택하면 모바일 메뉴를 닫습니다.
    // 클릭된 요소가 링크 또는 인증 열기 버튼 안에 있을 때만 닫습니다.
    if (event.target instanceof Element
      && event.target.closest('a, [data-open-auth]')) {
      setMenuOpen(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    // Escape 키로 열린 메뉴를 닫을 수 있게 합니다.
    // 다른 키 입력은 메뉴 상태에 영향을 주지 않습니다.
    if (event.key === 'Escape') setMenuOpen(false);
  });

  // 데스크톱 너비로 바뀌면 모바일 메뉴 상태를 초기화합니다.
  window.matchMedia('(min-width: 901px)').addEventListener('change', () => {
    setMenuOpen(false);
  });
})();
