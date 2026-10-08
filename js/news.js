(() => {
  document.querySelectorAll('[data-book-carousel]').forEach((carousel) => {
    const image = carousel.querySelector('.news-book-image');

    if (!(image instanceof HTMLImageElement)) return;

    let covers;
    try {
      covers = JSON.parse(image.dataset.covers || '[]');
    } catch {
      return;
    }
    if (!Array.isArray(covers) || covers.length < 2
      || !covers.every((cover) => typeof cover === 'string')) {
      return;
    }

    let index = Number(image.dataset.coverIndex) || 0;
    let timer;
    const rotateCover = () => {
      index = (index + 1) % covers.length;
      image.style.opacity = '0';
      window.setTimeout(() => {
        image.src = covers[index];
        image.alt = `관현악법의 역사 책 표지 ${index + 1}`;
        image.dataset.coverIndex = String(index);
        image.style.opacity = '1';
      }, 350);
    };

    const startRotation = () => {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || timer) return;
      timer = window.setInterval(rotateCover, 4000);
    };
    const stopRotation = () => {
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
