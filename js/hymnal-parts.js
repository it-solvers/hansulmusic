(() => {
  // 찬송가/찬양곡 목록 화면의 필터, 정렬, 페이지 이동을 관리합니다.
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
    // 탭마다 마지막으로 사용한 정렬 방식을 따로 기억합니다.
    // 처음 선택한 분류라면 기본 정렬 상태를 만들어 저장합니다.
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
      // 번호 정렬 버튼은 페이지에서 지정한 이름을 우선 사용합니다.
      if (button.dataset.hymnalSort === 'number') {
        button.textContent = button.dataset.numberLabel ?? '장번호순';
      }
      // 제목 정렬 버튼에는 현재 오름차순/내림차순 방향을 표시합니다.
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
    // 현재 탭, 절기 분류, 검색어를 모두 적용한 뒤 정렬합니다.
    const query = search instanceof HTMLInputElement ? search.value.trim().toLocaleLowerCase() : '';
    const season = seasonSelect instanceof HTMLSelectElement ? seasonSelect.value : '';
    const matches = cards.filter((card) => card.dataset.type === selectedType
      && (selectedType !== '1' || season === '' || card.dataset.section === season)
      && (query === '' || card.textContent.toLocaleLowerCase().includes(query)));
    const sortState = getSortState();
    matches.sort((first, second) => {
      // 절기별 목록은 먼저 절기 순서를 유지하고 그 안에서 제목을 정렬합니다.
      if (selectedType === '1' && panel?.hasAttribute('data-seasonal-title-sort')) {
        const sectionComparison = Number(first.dataset.sectionOrder) - Number(second.dataset.sectionOrder);
        // 서로 다른 절기이면 절기 순서만으로 위치를 정합니다.
        if (sectionComparison !== 0) return sectionComparison;
        const titleComparison = titleCollator.compare(first.dataset.title ?? '', second.dataset.title ?? '');
        // 같은 절기 안에서는 선택한 제목 정렬 방향을 적용합니다.
        return sortState.order === 'title' && sortState.titleDirection === 'desc'
          ? -titleComparison
          : titleComparison;
      }
      // 제목순 선택 시 한글·숫자 제목을 자연스러운 순서로 비교합니다.
      if (sortState.order === 'title') {
        const titleComparison = titleCollator.compare(first.dataset.title ?? '', second.dataset.title ?? '');
        // 제목이 다를 때만 정렬 결과를 반환하고, 같으면 다음 비교를 이어갑니다.
        if (titleComparison !== 0) {
          return sortState.titleDirection === 'asc' ? titleComparison : -titleComparison;
        }
      } else {
        const firstNumber = Number(first.dataset.number);
        const secondNumber = Number(second.dataset.number);
        const firstHasNumber = first.dataset.number !== '';
        const secondHasNumber = second.dataset.number !== '';
        // 번호가 있는 항목을 번호가 없는 항목보다 앞에 둡니다.
        if (firstHasNumber !== secondHasNumber) return firstHasNumber ? -1 : 1;
        // 두 항목 모두 번호가 있고 번호가 다르면 작은 번호를 앞에 둡니다.
        if (firstHasNumber && firstNumber !== secondNumber) return firstNumber - secondNumber;
      }
      return 0;
    });
    matches.forEach((card, index) => {
      card.style.order = String(index);
    });
    // 전체 일치 항목 수로 페이지를 계산하고 현재 페이지에 해당하는 카드만 표시합니다.
    const pageCount = Math.max(1, Math.ceil(matches.length / pageSize));
    currentPage = Math.min(currentPage, pageCount - 1);
    const start = currentPage * pageSize;
    const visibleCards = new Set(matches.slice(start, start + pageSize));
    cards.forEach((card) => {
      card.hidden = !visibleCards.has(card);
    });
    // 아래 요소는 페이지마다 선택적으로 존재하므로, 실제 요소가 있을 때만 갱신합니다.
    if (emptyMessage instanceof HTMLElement) emptyMessage.hidden = matches.length !== 0;
    if (pageStatus instanceof HTMLElement) {
      pageStatus.textContent = `${currentPage + 1} / ${pageCount} 페이지`;
    }
    // 첫 페이지이면 이전 버튼을, 마지막 페이지이면 다음 버튼을 비활성화합니다.
    if (previousButton instanceof HTMLButtonElement) previousButton.disabled = currentPage === 0;
    if (nextButton instanceof HTMLButtonElement) nextButton.disabled = currentPage >= pageCount - 1;
    // 절기 필터는 찬송가 분류에서만 표시합니다.
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
      // 접근성 패널이 존재할 때 선택된 탭과 연결합니다.
      if (panel instanceof HTMLElement) panel.setAttribute('aria-labelledby', tab.id);
      updateList();
    });

    tab.addEventListener('keydown', (event) => {
      // 좌우 화살표와 Home/End 키로 탭을 바꿉니다.
      // 지원하지 않는 키에는 별도 동작을 가로채지 않습니다.
      let nextIndex = index;
      if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length; // 다음 탭으로 이동합니다.
      else if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length; // 이전 탭으로 이동합니다.
      else if (event.key === 'Home') nextIndex = 0; // 첫 번째 탭으로 이동합니다.
      else if (event.key === 'End') nextIndex = tabs.length - 1; // 마지막 탭으로 이동합니다.
      else return; // 다른 키 입력은 가로채지 않습니다.

      event.preventDefault();
      tabs[nextIndex].focus();
      tabs[nextIndex].click();
    });
  });

  // 검색 입력란이 있는 페이지에서만 검색 이벤트를 연결합니다.
  if (search instanceof HTMLInputElement) {
    search.addEventListener('input', () => {
      currentPage = 0;
      updateList();
    });
  }
  // 절기 선택 메뉴가 있는 페이지에서만 분류 변경 이벤트를 연결합니다.
  if (seasonSelect instanceof HTMLSelectElement) {
    seasonSelect.addEventListener('change', () => {
      currentPage = 0;
      updateList();
    });
  }
  sortButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const sortState = getSortState();
      // 번호순 버튼은 기본 정렬로 돌아갑니다.
      if (button.dataset.hymnalSort === 'number') {
        sortState.order = 'number';
      // 제목순 버튼은 처음 선택 시 오름차순, 다시 누르면 방향을 뒤집습니다.
      } else if (button.dataset.hymnalSort === 'title') {
        // 이미 제목순이면 현재 정렬 방향을 반전합니다.
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
    // 첫 페이지에서는 이전 페이지가 없으므로 아무 동작도 하지 않습니다.
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
