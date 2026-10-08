(() => {
  // 영상 역할 탭이 있는 YouTube 페이지에서만 탭 동작을 연결합니다.
  const tabList = document.querySelector('[data-video-role-tabs]');
  // 탭 컨테이너가 없거나 올바른 HTML 요소가 아니면 이 스크립트를 종료합니다.
  if (!(tabList instanceof HTMLElement)) return;

  const tabs = [...tabList.querySelectorAll('[data-video-role-tab]')]
    .filter((tab) => tab instanceof HTMLButtonElement);
  const activateTab = (selectedTab, moveFocus = false) => {
    // 선택 상태, 키보드 포커스 순서, 연결된 영상 패널을 함께 전환합니다.
    tabs.forEach((tab) => {
      const selected = tab === selectedTab;
      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;
      const panelId = tab.getAttribute('aria-controls');
      const panel = panelId ? document.getElementById(panelId) : null;
      // 연결된 패널이 실제 요소일 때만 표시 상태를 바꿉니다.
      if (panel instanceof HTMLElement) panel.hidden = !selected;
    });
    // 키보드 탐색으로 탭을 바꾼 경우에만 포커스도 옮깁니다.
    if (moveFocus) selectedTab.focus();
  };

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => activateTab(tab));
    tab.addEventListener('keydown', (event) => {
      // 화살표와 Home/End 키로 탭 사이를 이동할 수 있게 합니다.
      // 그 외 키는 브라우저의 기본 동작을 유지합니다.
      let nextIndex = index;
      if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length; // 다음 탭을 선택합니다.
      else if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length; // 이전 탭을 선택합니다.
      else if (event.key === 'Home') nextIndex = 0; // 첫 번째 탭을 선택합니다.
      else if (event.key === 'End') nextIndex = tabs.length - 1; // 마지막 탭을 선택합니다.
      else return; // 지원하지 않는 키는 기본 동작에 맡깁니다.

      event.preventDefault();
      activateTab(tabs[nextIndex], true);
    });
  });
})();
