(() => {
  // 공용 영상 팝업의 필수 요소를 확인합니다. 페이지에 팝업이 없거나
  // 마크업이 완전하지 않으면 이벤트를 연결하지 않고 스크립트를 종료합니다.
  const dialog = document.querySelector('.concert-player');
  const frame = dialog?.querySelector('.concert-player-frame');
  const closeButton = dialog?.querySelector('.concert-player-close');

  // 필수 팝업 요소가 하나라도 없으면 안전하게 초기화를 중단합니다.
  if (!(dialog instanceof HTMLDialogElement)
    || !(frame instanceof HTMLElement)
    || !(closeButton instanceof HTMLButtonElement)) {
    return;
  }

  const playVideo = (videoId, title, startAt = 0) => {
    // 잘못된 ID가 iframe 주소에 들어가는 것을 막고, 개인정보 보호용
    // YouTube 플레이어를 생성합니다. startAt이 있으면 해당 시점부터 재생합니다.
    // ID 형식이 맞지 않으면 재생을 시도하지 않습니다.
    if (!/^[A-Za-z0-9_-]{11}$/.test(videoId)) return;

    const iframe = document.createElement('iframe');
    const startParameter = startAt > 0 ? `&start=${startAt}` : '';
    iframe.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(videoId)}?autoplay=1${startParameter}`;
    iframe.title = title;
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen';
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    frame.replaceChildren(iframe);
    dialog.showModal();
    closeButton.focus();
  };

  // 썸네일 버튼은 data 속성의 영상 ID와 제목을 사용해 팝업을 엽니다.
  document.querySelectorAll('.concert-video-frame[data-video-id]').forEach((button) => {
    // 버튼이 아닌 요소에는 클릭 이벤트를 연결하지 않습니다.
    if (!(button instanceof HTMLButtonElement)) return;

    button.addEventListener('click', () => {
      const videoId = button.dataset.videoId;
      // 영상 ID가 비어 있으면 팝업을 열 수 없으므로 종료합니다.
      if (!videoId) return;
      playVideo(videoId, button.dataset.videoTitle || 'YouTube video player');
    });
  });

  // 뉴스 영상 링크는 YouTube 주소에서 ID와 선택적 시작 시각을 읽습니다.
  // 지원하지 않는 주소 형식은 원래 링크 동작을 그대로 둡니다.
  document.querySelectorAll('.news-work-link, [data-youtube-popup]').forEach((link) => {
    // 실제 링크 요소인지 확인한 뒤 URL을 처리합니다.
    if (!(link instanceof HTMLAnchorElement)) return;

    link.addEventListener('click', (event) => {
      let videoUrl;
      try {
        videoUrl = new URL(link.href);
      } catch {
        return;
      }
      // HTTPS 링크만 팝업 재생에 사용하고, 그 외 주소는 기본 링크 동작에 맡깁니다.
      if (videoUrl.protocol !== 'https:') return;

      let videoId = '';
      const host = videoUrl.hostname.toLowerCase();
      // 단축 주소는 경로에서 ID를, 일반 YouTube 주소는 v 매개변수에서 ID를 가져옵니다.
      if (host === 'youtu.be') {
        videoId = videoUrl.pathname.split('/').filter(Boolean)[0] || '';
      } else if (['youtube.com', 'www.youtube.com', 'm.youtube.com'].includes(host)) {
        videoId = videoUrl.searchParams.get('v') || '';
      } else {
        // YouTube가 아닌 호스트는 팝업으로 열지 않습니다.
        return;
      }

      const start = videoUrl.searchParams.get('t') || videoUrl.searchParams.get('start') || '0';
      // YouTube 시작 시각은 초 단위 숫자 또는 1h2m3s 형식일 수 있으므로,
      // iframe이 받는 초 단위 값으로 통일합니다.
      const durationParts = start.match(/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/);
      const startAt = /^\d+$/.test(start)
        ? Number(start)
        : durationParts && durationParts[0] !== ''
          ? Number(durationParts[1] || 0) * 3600
            + Number(durationParts[2] || 0) * 60
            + Number(durationParts[3] || 0)
          : 0;
      event.preventDefault();
      playVideo(videoId, link.getAttribute('aria-label') || 'YouTube work video', startAt);
    });
  });

  const closePlayer = () => {
    // 팝업을 닫을 때 iframe도 제거해 백그라운드 영상 재생을 중단합니다.
    frame.replaceChildren();
    dialog.close();
  };

  // 닫기 버튼, 팝업 바깥 클릭, 다이얼로그의 기본 닫힘 처리를 모두 정리합니다.
  closeButton.addEventListener('click', closePlayer);
  dialog.addEventListener('click', (event) => {
    // 다이얼로그 바깥 영역을 눌렀을 때만 닫고, 영상 내부 클릭은 유지합니다.
    if (event.target === dialog) closePlayer();
  });
  dialog.addEventListener('close', () => frame.replaceChildren());
})();
