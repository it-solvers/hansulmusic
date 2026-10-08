(() => {
  // 표지 이미지 여러 장이 등록된 뉴스 카드만 회전 처리합니다.
  document.querySelectorAll('[data-book-carousel]').forEach((carousel) => {
    const image = carousel.querySelector('.news-book-image');

    // 회전할 이미지 요소가 없으면 이 카드의 처리를 건너뜁니다.
    if (!(image instanceof HTMLImageElement)) return;

    let covers;
    try {
      covers = JSON.parse(image.dataset.covers || '[]');
    } catch {
      // 이미지 목록이 올바른 JSON이 아니면 회전할 수 없으므로 건너뜁니다.
      return;
    }
    // 두 장 이상의 문자열 이미지 주소가 있을 때만 회전 기능을 활성화합니다.
    if (!Array.isArray(covers) || covers.length < 2
      || !covers.every((cover) => typeof cover === 'string')) {
      return;
    }

    let index = Number(image.dataset.coverIndex) || 0;
    let timer;
    const rotateCover = () => {
      index = (index + 1) % covers.length;
      // 이미지가 바뀌는 동안 흐리게 전환해 깜박임을 줄입니다.
      image.style.opacity = '0';
      window.setTimeout(() => {
        image.src = covers[index];
        image.alt = `관현악법의 역사 책 표지 ${index + 1}`;
        image.dataset.coverIndex = String(index);
        image.style.opacity = '1';
      }, 350);
    };

    const startRotation = () => {
      // 사용자가 움직임 감소를 요청했거나 이미 실행 중이면 자동 회전하지 않습니다.
      // 접근성 설정을 따르고 중복 타이머 생성을 막습니다.
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || timer) return;
      timer = window.setInterval(rotateCover, 4000);
    };
    const stopRotation = () => {
      // 마우스나 키보드로 카드를 살펴보는 동안 회전을 멈춥니다.
      window.clearInterval(timer);
      timer = undefined;
    };

    carousel.addEventListener('mouseenter', stopRotation);
    carousel.addEventListener('mouseleave', startRotation);
    carousel.addEventListener('focusin', stopRotation);
    carousel.addEventListener('focusout', startRotation);
    startRotation();
  });
})();
