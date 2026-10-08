(() => {
  const tabs = [...document.querySelectorAll('[data-hymnal-tab]')];
  const cards = [...document.querySelectorAll('[data-hymnal-card]')];
  const search = document.querySelector('#hymnal-search');
  const emptyMessage = document.querySelector('[data-hymnal-empty]');
  const previousButton = document.querySelector('[data-hymnal-previous]');
  const nextButton = document.querySelector('[data-hymnal-next]');
  const pageStatus = document.querySelector('[data-hymnal-page]');
  const panel = document.querySelector('#hymnal-panel');
  const sortButtons = [...document.querySelectorAll('[data-hymnal-sort]')];
  const seasonFilter = document.querySelector('[data-hymnal-season-filter]');
  const seasonSelect = document.querySelector('[data-hymnal-season]');
  const pageSize = 50;
  const titleCollator = new Intl.Collator('ko', { numeric: true, sensitivity: 'base' });
  const sortByType = new Map();
  let selectedType = '1';
  let currentPage = 0;

  tabs.forEach((tab) => {
    const type = tab.dataset.type ?? '';
    const count = cards.filter((card) => card.dataset.type === type).length;
    const label = tab.dataset.label ?? tab.textContent.trim();
    tab.textContent = `${label} (${count.toLocaleString()}개)`;
  });

  const getSortState = () => {
    if (!sortByType.has(selectedType)) {
      sortByType.set(selectedType, { order: 'number', titleDirection: 'asc' });
    }
    return sortByType.get(selectedType);
  };

  const updateSortControls = () => {
    const sortState = getSortState();
    sortButtons.forEach((button) => {
      const selected = button.dataset.hymnalSort === sortState.order;
      button.setAttribute('aria-pressed', String(selected));
      if (button.dataset.hymnalSort === 'number') {
        button.textContent = button.dataset.numberLabel ?? '장번호순';
      }
      if (button.dataset.hymnalSort === 'title') {
        const direction = sortState.titleDirection;
        button.textContent = `제목순 ${direction === 'asc' ? '↑' : '↓'}`;
        button.setAttribute(
          'aria-label',
          `제목순 ${direction === 'asc' ? '오름차순' : '내림차순'}`
        );
      }
    });
  };

  const updateList = () => {
    const query = search instanceof HTMLInputElement ? search.value.trim().toLocaleLowerCase() : '';
    const season = seasonSelect instanceof HTMLSelectElement ? seasonSelect.value : '';
    const matches = cards.filter((card) => card.dataset.type === selectedType
      && (selectedType !== '1' || season === '' || card.dataset.section === season)
      && (query === '' || card.textContent.toLocaleLowerCase().includes(query)));
    const sortState = getSortState();
    matches.sort((first, second) => {
      if (selectedType === '1' && panel?.hasAttribute('data-seasonal-title-sort')) {
        const sectionComparison = Number(first.dataset.sectionOrder) - Number(second.dataset.sectionOrder);
        if (sectionComparison !== 0) return sectionComparison;
        const titleComparison = titleCollator.compare(first.dataset.title ?? '', second.dataset.title ?? '');
        return sortState.order === 'title' && sortState.titleDirection === 'desc'
          ? -titleComparison
          : titleComparison;
      }
      if (sortState.order === 'title') {
        const titleComparison = titleCollator.compare(first.dataset.title ?? '', second.dataset.title ?? '');
        if (titleComparison !== 0) {
          return sortState.titleDirection === 'asc' ? titleComparison : -titleComparison;
        }
      } else {
        const firstNumber = Number(first.dataset.number);
        const secondNumber = Number(second.dataset.number);
        const firstHasNumber = first.dataset.number !== '';
        const secondHasNumber = second.dataset.number !== '';
        if (firstHasNumber !== secondHasNumber) return firstHasNumber ? -1 : 1;
        if (firstHasNumber && firstNumber !== secondNumber) return firstNumber - secondNumber;
      }
      return 0;
    });
    matches.forEach((card, index) => {
      card.style.order = String(index);
    });
    const pageCount = Math.max(1, Math.ceil(matches.length / pageSize));
    currentPage = Math.min(currentPage, pageCount - 1);
    const start = currentPage * pageSize;
    const visibleCards = new Set(matches.slice(start, start + pageSize));
    cards.forEach((card) => {
      card.hidden = !visibleCards.has(card);
    });
    if (emptyMessage instanceof HTMLElement) emptyMessage.hidden = matches.length !== 0;
    if (pageStatus instanceof HTMLElement) {
      pageStatus.textContent = `${currentPage + 1} / ${pageCount} 페이지`;
    }
    if (previousButton instanceof HTMLButtonElement) previousButton.disabled = currentPage === 0;
    if (nextButton instanceof HTMLButtonElement) nextButton.disabled = currentPage >= pageCount - 1;
    if (seasonFilter instanceof HTMLElement) seasonFilter.hidden = selectedType !== '1';
    updateSortControls();
  };

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => {
      selectedType = tab.dataset.type ?? '1';
      currentPage = 0;
      tabs.forEach((item) => {
        const selected = item === tab;
        item.setAttribute('aria-selected', String(selected));
        item.tabIndex = selected ? 0 : -1;
      });
      if (panel instanceof HTMLElement) panel.setAttribute('aria-labelledby', tab.id);
      updateList();
    });

    tab.addEventListener('keydown', (event) => {
      let nextIndex = index;
      if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
      else if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
      else if (event.key === 'Home') nextIndex = 0;
      else if (event.key === 'End') nextIndex = tabs.length - 1;
      else return;

      event.preventDefault();
      tabs[nextIndex].focus();
      tabs[nextIndex].click();
    });
  });

  if (search instanceof HTMLInputElement) {
    search.addEventListener('input', () => {
      currentPage = 0;
      updateList();
    });
  }
  if (seasonSelect instanceof HTMLSelectElement) {
    seasonSelect.addEventListener('change', () => {
      currentPage = 0;
      updateList();
    });
  }
  sortButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const sortState = getSortState();
      if (button.dataset.hymnalSort === 'number') {
        sortState.order = 'number';
      } else if (button.dataset.hymnalSort === 'title') {
        if (sortState.order === 'title') {
          sortState.titleDirection = sortState.titleDirection === 'asc' ? 'desc' : 'asc';
        } else {
          sortState.order = 'title';
        }
      }
      currentPage = 0;
      updateList();
    });
  });
  previousButton?.addEventListener('click', () => {
    if (currentPage === 0) return;
    currentPage -= 1;
    updateList();
  });
  nextButton?.addEventListener('click', () => {
    currentPage += 1;
    updateList();
  });
  updateList();
})();
