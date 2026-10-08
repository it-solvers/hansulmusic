(() => {
  const tabList = document.querySelector('[data-video-role-tabs]');
  if (!(tabList instanceof HTMLElement)) return;

  const tabs = [...tabList.querySelectorAll('[data-video-role-tab]')]
    .filter((tab) => tab instanceof HTMLButtonElement);
  const activateTab = (selectedTab, moveFocus = false) => {
    tabs.forEach((tab) => {
      const selected = tab === selectedTab;
      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;
      const panelId = tab.getAttribute('aria-controls');
      const panel = panelId ? document.getElementById(panelId) : null;
      if (panel instanceof HTMLElement) panel.hidden = !selected;
    });
    if (moveFocus) selectedTab.focus();
  };

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => activateTab(tab));
    tab.addEventListener('keydown', (event) => {
      let nextIndex = index;
      if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
      else if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
      else if (event.key === 'Home') nextIndex = 0;
      else if (event.key === 'End') nextIndex = tabs.length - 1;
      else return;

      event.preventDefault();
      activateTab(tabs[nextIndex], true);
    });
  });
})();
