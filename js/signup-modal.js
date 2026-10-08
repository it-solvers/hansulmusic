(() => {
  const dialog = document.querySelector('.signup-modal');
  const loginForm = document.querySelector('[data-login-form]');
  const signupForm = document.querySelector('[data-signup-form]');
  const status = document.querySelector('[data-auth-status]');
  const title = document.querySelector('[data-auth-title]');
  const eyebrow = document.querySelector('[data-auth-eyebrow]');
  const intro = document.querySelector('[data-auth-intro]');
  const successPanel = document.querySelector('[data-auth-success]');
  const successGreeting = document.querySelector('[data-auth-success-greeting]');
  const openButton = document.querySelector('[data-open-auth]');
  const memberIdentity = document.querySelector('[data-member-identity]');
  const closeButton = document.querySelector('[data-close-auth]');
  const showSignupButton = document.querySelector('[data-show-signup]');
  const showLoginButton = document.querySelector('[data-show-login]');
  const consentDialog = document.querySelector('[data-consent-modal]');
  const consentMessage = document.querySelector('[data-consent-message]');
  const consentStatus = document.querySelector('[data-consent-status]');
  const closeConsentButton = document.querySelector('[data-close-consent]');
  const withdrawConsentButton = document.querySelector('[data-withdraw-consent]');
  const grantConsentButton = document.querySelector('[data-grant-consent]');

  if (!(dialog instanceof HTMLDialogElement)
    || !(loginForm instanceof HTMLFormElement)
    || !(signupForm instanceof HTMLFormElement)
    || !(status instanceof HTMLElement)
    || !(title instanceof HTMLElement)
    || !(eyebrow instanceof HTMLElement)
    || !(intro instanceof HTMLElement)
    || !(successPanel instanceof HTMLElement)
    || !(successGreeting instanceof HTMLElement)
    || !(openButton instanceof HTMLButtonElement)
    || !(memberIdentity instanceof HTMLButtonElement)
    || !(closeButton instanceof HTMLButtonElement)
    || !(showSignupButton instanceof HTMLButtonElement)
    || !(showLoginButton instanceof HTMLButtonElement)) {
    return;
  }
  if (!(consentDialog instanceof HTMLDialogElement)
    || !(consentMessage instanceof HTMLElement)
    || !(consentStatus instanceof HTMLElement)
    || !(closeConsentButton instanceof HTMLButtonElement)
    || !(withdrawConsentButton instanceof HTMLButtonElement)
    || !(grantConsentButton instanceof HTMLButtonElement)) {
    return;
  }

  let csrfToken = '';
  let authenticated = false;
  let marketingConsent = false;

  const setStatus = (message) => {
    status.textContent = message;
    status.className = 'register-message register-message-error';
    status.hidden = message === '';
  };

  const setAuthenticated = (isAuthenticated, name = '', email = '', hasMarketingConsent = false) => {
    authenticated = isAuthenticated;
    marketingConsent = authenticated && hasMarketingConsent;
    openButton.textContent = authenticated ? 'Sign out' : 'Sign in';
    openButton.setAttribute('aria-label', openButton.textContent);
    memberIdentity.textContent = authenticated && name
      ? `Hello, ${name}님`
      : '';
    memberIdentity.hidden = memberIdentity.textContent === '';
    memberIdentity.disabled = !authenticated;
  };

  const setMode = (mode) => {
    const signingUp = mode === 'signup';
    loginForm.hidden = signingUp;
    signupForm.hidden = !signingUp;
    successPanel.hidden = true;
    title.textContent = signingUp ? 'Create account' : 'Sign in';
    eyebrow.textContent = signingUp ? 'Create an account' : 'Member access';
    intro.textContent = signingUp
      ? '회원가입 정보를 입력해 주세요.'
      : '이메일 주소와 비밀번호를 입력해 주세요.';
    setStatus('');
  };

  const loadAuthState = async () => {
    const response = await fetch('auth.php', {
      headers: { Accept: 'application/json' },
    });
    const result = await response.json();
    if (!response.ok || typeof result.csrfToken !== 'string') {
      throw new Error(result.message || '로그인 정보를 불러오지 못했습니다.');
    }
    csrfToken = result.csrfToken;
    loginForm.querySelector('[name="csrf_token"]').value = csrfToken;
    signupForm.querySelector('[name="csrf_token"]').value = csrfToken;
    setAuthenticated(
      result.authenticated === true,
      typeof result.name === 'string' ? result.name : '',
      typeof result.email === 'string' ? result.email : '',
      result.marketingConsent === true,
    );
  };

  const handleFormSubmit = async (form) => {
    const submitButton = form.querySelector('button[type="submit"]');
    if (!(submitButton instanceof HTMLButtonElement)) return;

    submitButton.disabled = true;
    setStatus('');
    try {
      const response = await fetch(form.getAttribute('action') || 'register.php', {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' },
      });
      const result = await response.json();
      if (!response.ok) {
        setStatus(result.message || '요청을 처리할 수 없습니다.');
        return;
      }

      if (typeof result.csrfToken === 'string') {
        csrfToken = result.csrfToken;
        loginForm.querySelector('[name="csrf_token"]').value = csrfToken;
        signupForm.querySelector('[name="csrf_token"]').value = csrfToken;
      }
      setAuthenticated(
        result.authenticated === true,
        typeof result.name === 'string' ? result.name : '',
        typeof result.email === 'string' ? result.email : '',
        result.marketingConsent === true,
      );

      if (form === loginForm) {
        dialog.close();
        loginForm.reset();
      } else {
        const memberName = typeof result.name === 'string' ? result.name : '';
        signupForm.reset();
        loginForm.hidden = true;
        signupForm.hidden = true;
        title.textContent = '가입 및 로그인 완료';
        eyebrow.textContent = 'Welcome';
        intro.textContent = '회원가입과 동시에 자동으로 로그인되었습니다.';
        successGreeting.textContent = memberName
          ? `${memberName}님, 환영합니다!`
          : '환영합니다!';
        successPanel.hidden = false;
        setStatus('');
      }
    } catch {
      setStatus('서버에 연결하지 못했습니다. 잠시 후 다시 시도해 주세요.');
    } finally {
      submitButton.disabled = false;
    }
  };

  openButton.addEventListener('click', async () => {
    if (authenticated) {
      openButton.disabled = true;
      try {
        const formData = new FormData();
        formData.set('action', 'logout');
        formData.set('csrf_token', csrfToken);
        const response = await fetch('auth.php', {
          method: 'POST',
          body: formData,
          headers: { Accept: 'application/json' },
        });
        const result = await response.json();
        if (!response.ok || typeof result.csrfToken !== 'string') {
          throw new Error(result.message || '로그아웃하지 못했습니다.');
        }
        csrfToken = result.csrfToken;
        loginForm.querySelector('[name="csrf_token"]').value = csrfToken;
        signupForm.querySelector('[name="csrf_token"]').value = csrfToken;
        setAuthenticated(false);
        if (dialog.open) dialog.close();
        loginForm.reset();
        signupForm.reset();
      } catch (error) {
        if (!dialog.open) dialog.showModal();
        setMode('login');
        setStatus(error instanceof Error ? error.message : '로그아웃하지 못했습니다.');
      } finally {
        openButton.disabled = false;
      }
      return;
    }

    dialog.showModal();
    setMode('login');
    loginForm.reset();
    signupForm.reset();
    try {
      await loadAuthState();
      loginForm.querySelector('[name="email"]')?.focus();
    } catch (error) {
      setStatus(error instanceof Error ? error.message : '로그인 정보를 불러오지 못했습니다.');
    }
  });

  closeButton.addEventListener('click', () => dialog.close());
  dialog.addEventListener('cancel', (event) => {
    event.preventDefault();
  });

  showSignupButton.addEventListener('click', () => {
    signupForm.reset();
    setMode('signup');
    signupForm.querySelector('[name="name"]')?.focus();
  });
  showLoginButton.addEventListener('click', () => {
    loginForm.reset();
    setMode('login');
    loginForm.querySelector('[name="email"]')?.focus();
  });

  memberIdentity.addEventListener('click', () => {
    if (!authenticated) return;
    consentStatus.hidden = true;
    consentMessage.textContent = marketingConsent
      ? '현재 신규 작품 출시 및 이벤트·광고 소식을 이메일로 받는 데 동의한 상태입니다. 아래에서 언제든 수신 동의를 철회할 수 있습니다.'
      : '현재 신규 작품 출시 및 이벤트·광고 소식 수신에 동의하지 않은 상태입니다.';
    withdrawConsentButton.hidden = !marketingConsent;
    grantConsentButton.hidden = marketingConsent;
    consentDialog.showModal();
  });

  closeConsentButton.addEventListener('click', () => consentDialog.close());
  consentDialog.addEventListener('cancel', (event) => {
    event.preventDefault();
  });

  const updateMarketingConsent = async (consent) => {
    const button = consent ? grantConsentButton : withdrawConsentButton;
    button.disabled = true;
    consentStatus.hidden = true;
    try {
      const formData = new FormData();
      formData.set('action', consent ? 'grant_marketing_consent' : 'withdraw_marketing_consent');
      formData.set('csrf_token', csrfToken);
      const response = await fetch('auth.php', {
        method: 'POST',
        body: formData,
        headers: { Accept: 'application/json' },
      });
      const result = await response.json();
      if (!response.ok || result.marketingConsent !== consent) {
        throw new Error(result.message || '소식 수신 설정을 변경하지 못했습니다.');
      }
      marketingConsent = consent;
      consentMessage.textContent = consent
        ? '신규 작품 출시 및 이벤트·광고 소식을 이메일로 받는 데 동의한 상태입니다. 원하실 때 언제든 수신 동의를 철회할 수 있습니다.'
        : '수신 동의를 철회했습니다. 신규 작품 출시 및 이벤트·광고 소식을 보내지 않습니다.';
      withdrawConsentButton.hidden = !consent;
      grantConsentButton.hidden = consent;
      consentStatus.textContent = consent
        ? '소식 이메일 수신에 동의했습니다.'
        : '수신 동의가 철회되었습니다.';
      consentStatus.className = 'register-message register-message-success';
      consentStatus.hidden = false;
    } catch (error) {
      consentStatus.textContent = error instanceof Error
        ? error.message
        : '수신 동의를 철회하지 못했습니다.';
      consentStatus.className = 'register-message register-message-error';
      consentStatus.hidden = false;
    } finally {
      button.disabled = false;
    }
  };

  withdrawConsentButton.addEventListener('click', () => {
    void updateMarketingConsent(false);
  });
  grantConsentButton.addEventListener('click', () => {
    void updateMarketingConsent(true);
  });

  loginForm.addEventListener('submit', (event) => {
    event.preventDefault();
    void handleFormSubmit(loginForm);
  });
  signupForm.addEventListener('submit', (event) => {
    event.preventDefault();
    void handleFormSubmit(signupForm);
  });

  void loadAuthState().catch((error) => {
    setStatus(error instanceof Error ? error.message : '로그인 정보를 불러오지 못했습니다.');
  });
})();
