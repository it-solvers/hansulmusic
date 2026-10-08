(() => {
  // 문의 양식이 있는 홈 화면에서만 메일 작성 동작을 연결합니다.
  const form = document.querySelector('[data-commission-form]');
  // 문의 양식이 없거나 폼 요소가 아니면 제출 동작을 연결하지 않습니다.
  if (!(form instanceof HTMLFormElement)) return;

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    // 브라우저 기본 유효성 검사를 통과한 경우에만 메일 내용을 구성합니다.
    // 필수 항목이나 이메일 형식이 잘못되면 작성 화면을 열지 않습니다.
    if (!form.reportValidity()) return;

    const formData = new FormData(form);
    const name = String(formData.get('name') || '').trim();
    const email = String(formData.get('email') || '').trim();
    const phone = String(formData.get('phone') || '').trim();
    const subject = String(formData.get('subject') || '').trim();
    const message = String(formData.get('message') || '').trim();
    // 입력 내용을 메일 본문으로 조합해 사용자의 메일 앱을 엽니다.
    const body = [
      `이름 / Name: ${name}`,
      `이메일 / Email: ${email}`,
      `연락처 / Mobile: ${phone || '-'}`,
      '',
      message,
    ].join('\n');
    const mailto = new URL('mailto:sangeun@hansulmusic.com');
    mailto.searchParams.set('subject', `[한설뮤직 의뢰] ${subject}`);
    mailto.searchParams.set('body', body);
    window.location.href = mailto.toString();
  });
})();
