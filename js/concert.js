(() => {
  const dialog = document.querySelector('.concert-player');
  const frame = dialog?.querySelector('.concert-player-frame');
  const closeButton = dialog?.querySelector('.concert-player-close');

  if (!(dialog instanceof HTMLDialogElement)
    || !(frame instanceof HTMLElement)
    || !(closeButton instanceof HTMLButtonElement)) {
    return;
  }

  const playVideo = (videoId, title, startAt = 0) => {
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

  document.querySelectorAll('.concert-video-frame[data-video-id]').forEach((button) => {
    if (!(button instanceof HTMLButtonElement)) return;

    button.addEventListener('click', () => {
      const videoId = button.dataset.videoId;
      if (!videoId) return;
      playVideo(videoId, button.dataset.videoTitle || 'YouTube video player');
    });
  });

  document.querySelectorAll('.news-work-link, [data-youtube-popup]').forEach((link) => {
    if (!(link instanceof HTMLAnchorElement)) return;

    link.addEventListener('click', (event) => {
      let videoUrl;
      try {
        videoUrl = new URL(link.href);
      } catch {
        return;
      }
      if (videoUrl.protocol !== 'https:') return;

      let videoId = '';
      const host = videoUrl.hostname.toLowerCase();
      if (host === 'youtu.be') {
        videoId = videoUrl.pathname.split('/').filter(Boolean)[0] || '';
      } else if (['youtube.com', 'www.youtube.com', 'm.youtube.com'].includes(host)) {
        videoId = videoUrl.searchParams.get('v') || '';
      } else {
        return;
      }

      const start = videoUrl.searchParams.get('t') || videoUrl.searchParams.get('start') || '0';
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
    frame.replaceChildren();
    dialog.close();
  };

  closeButton.addEventListener('click', closePlayer);
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) closePlayer();
  });
  dialog.addEventListener('close', () => frame.replaceChildren());
})();
