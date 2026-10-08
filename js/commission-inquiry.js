(() => {
  const form = document.querySelector('[data-commission-form]');
  if (!(form instanceof HTMLFormElement)) return;

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;

    const formData = new FormData(form);
    const name = String(formData.get('name') || '').trim();
    const email = String(formData.get('email') || '').trim();
    const phone = String(formData.get('phone') || '').trim();
    const subject = String(formData.get('subject') || '').trim();
    const message = String(formData.get('message') || '').trim();
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
