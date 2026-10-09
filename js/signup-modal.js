(() => {
  // 로그인·가입 모달과 수신 동의 모달에 필요한 화면 요소를 가져옵니다.
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

  // 필수 로그인·회원가입 요소가 빠졌으면 모달 전체를 초기화하지 않습니다.
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
  // 수신 동의 모달도 이 기능의 일부이므로 요소 누락 시 초기화를 중단합니다.
  if (!(consentDialog instanceof HTMLDialogElement)
    || !(consentMessage instanceof HTMLElement)
    || !(consentStatus instanceof HTMLElement)
    || !(closeConsentButton instanceof HTMLButtonElement)
    || !(withdrawConsentButton instanceof HTMLButtonElement)
    || !(grantConsentButton instanceof HTMLButtonElement)) {
    return;
  }

  const isEnglish = document.documentElement.lang.toLowerCase().startsWith('en');
  const authEndpoint = new URL(
    loginForm.getAttribute('action') || 'auth.php',
    document.baseURI,
  );

  let csrfToken = '';
  let authenticated = false;
  let marketingConsent = false;

  // 요청 결과와 로그인 여부를 화면에 반영하는 공통 상태 함수입니다.
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
      ? (isEnglish ? `Hello, ${name}` : `Hello, ${name}님`)
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
      ? (isEnglish ? 'Enter your account information.' : '회원가입 정보를 입력해 주세요.')
      : (isEnglish ? 'Enter your email address and password.' : '이메일 주소와 비밀번호를 입력해 주세요.');
    setStatus('');
  };

  const loadAuthState = async () => {
    // 서버 세션과 CSRF 토큰을 읽어 로그인 버튼과 양식에 반영합니다.
    const response = await fetch(authEndpoint, {
      headers: { Accept: 'application/json' },
    });
    const result = await response.json();
    // 서버 오류나 누락된 CSRF 토큰이 있으면 인증 상태를 신뢰하지 않습니다.
    if (!response.ok || typeof result.csrfToken !== 'string') {
      throw new Error(result.message || (isEnglish
        ? 'Unable to load sign-in information.'
        : '로그인 정보를 불러오지 못했습니다.'));
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
    // 제출 버튼이 없으면 중복 제출 방지 처리를 할 수 없어 요청을 보내지 않습니다.
    if (!(submitButton instanceof HTMLButtonElement)) return;

    submitButton.disabled = true;
    setStatus('');
    try {
      // 로그인 또는 회원가입을 처리한 뒤 최신 인증 상태를 화면에 반영합니다.
      const response = await fetch(form.getAttribute('action') || 'register.php', {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' },
      });
      const result = await response.json();
      // 서버가 요청을 거부하면 응답 메시지를 보여주고 성공 처리를 중단합니다.
      if (!response.ok) {
        setStatus(result.message || '요청을 처리할 수 없습니다.');
        return;
      }

      // 서버가 새 CSRF 토큰을 돌려준 경우 두 양식 모두 최신 값으로 교체합니다.
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

      // 로그인 성공은 모달을 닫고, 회원가입 성공은 환영 패널을 보여줍니다.
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
        intro.textContent = isEnglish
          ? 'Your account has been created and you are now signed in.'
          : '회원가입과 동시에 자동으로 로그인되었습니다.';
        successGreeting.textContent = memberName
          ? (isEnglish ? `Welcome, ${memberName}!` : `${memberName}님, 환영합니다!`)
          : (isEnglish ? 'Welcome!' : '환영합니다!');
        successPanel.hidden = false;
        setStatus('');
      }
    } catch {
      setStatus(isEnglish
        ? 'Unable to connect to the server. Please try again later.'
        : '서버에 연결하지 못했습니다. 잠시 후 다시 시도해 주세요.');
    } finally {
      submitButton.disabled = false;
    }
  };

  openButton.addEventListener('click', async () => {
    // 로그인 상태에서는 같은 버튼을 로그아웃 동작으로 사용합니다.
    if (authenticated) {
      // 이미 로그인된 경우 버튼은 로그아웃 요청으로 동작합니다.
      openButton.disabled = true;
      try {
        const formData = new FormData();
        formData.set('action', 'logout');
        formData.set('csrf_token', csrfToken);
        const response = await fetch(authEndpoint, {
          method: 'POST',
          body: formData,
          headers: { Accept: 'application/json' },
        });
        const result = await response.json();
        // 서버 오류 또는 새 CSRF 토큰 누락은 로그아웃 실패로 처리합니다.
        if (!response.ok || typeof result.csrfToken !== 'string') {
          throw new Error(result.message || (isEnglish ? 'Unable to sign out.' : '로그아웃하지 못했습니다.'));
        }
        csrfToken = result.csrfToken;
        loginForm.querySelector('[name="csrf_token"]').value = csrfToken;
        signupForm.querySelector('[name="csrf_token"]').value = csrfToken;
        setAuthenticated(false);
        // 모달이 열려 있을 때만 닫고 양식 입력값을 초기화합니다.
        if (dialog.open) dialog.close();
        loginForm.reset();
        signupForm.reset();
      } catch (error) {
        // 로그아웃 오류를 알릴 수 있도록 모달이 닫혀 있으면 다시 엽니다.
        if (!dialog.open) dialog.showModal();
        setMode('login');
        setStatus(error instanceof Error
          ? error.message
          : (isEnglish ? 'Unable to sign out.' : '로그아웃하지 못했습니다.'));
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
      setStatus(error instanceof Error
        ? error.message
        : (isEnglish ? 'Unable to load sign-in information.' : '로그인 정보를 불러오지 못했습니다.'));
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
    // 인증된 회원만 저장된 수신 동의 상태를 조회·변경할 수 있습니다.
    if (!authenticated) return;
    // 회원 인사말을 누르면 현재 이메일 수신 동의 상태를 보여줍니다.
    consentStatus.hidden = true;
    consentMessage.textContent = isEnglish
      ? (marketingConsent
        ? 'You are subscribed to email updates about new releases, events, and promotions. You can unsubscribe at any time below.'
        : 'You are not currently subscribed to email updates about new releases, events, and promotions.')
      : (marketingConsent
        ? '현재 신규 작품 출시 및 이벤트·광고 소식을 이메일로 받는 데 동의한 상태입니다. 아래에서 언제든 수신 동의를 철회할 수 있습니다.'
        : '현재 신규 작품 출시 및 이벤트·광고 소식 수신에 동의하지 않은 상태입니다.');
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
      // 동의 변경도 CSRF 토큰을 포함해 서버에 저장합니다.
      const formData = new FormData();
      formData.set('action', consent ? 'grant_marketing_consent' : 'withdraw_marketing_consent');
      formData.set('csrf_token', csrfToken);
      const response = await fetch(authEndpoint, {
        method: 'POST',
        body: formData,
        headers: { Accept: 'application/json' },
      });
      const result = await response.json();
      // HTTP 오류 또는 서버에 저장된 값이 요청과 다르면 변경 실패로 처리합니다.
      if (!response.ok || result.marketingConsent !== consent) {
        throw new Error(result.message || (isEnglish
          ? 'Unable to update your email preferences.'
          : '소식 수신 설정을 변경하지 못했습니다.'));
      }
      marketingConsent = consent;
      consentMessage.textContent = isEnglish
        ? (consent
          ? 'You are subscribed to email updates about new releases, events, and promotions. You can unsubscribe at any time.'
          : 'You have unsubscribed from email updates.')
        : (consent
          ? '신규 작품 출시 및 이벤트·광고 소식을 이메일로 받는 데 동의한 상태입니다. 원하실 때 언제든 수신 동의를 철회할 수 있습니다.'
          : '수신 동의를 철회했습니다. 신규 작품 출시 및 이벤트·광고 소식을 보내지 않습니다.');
      withdrawConsentButton.hidden = !consent;
      grantConsentButton.hidden = consent;
      consentStatus.textContent = isEnglish
        ? (consent ? 'You are now subscribed to email updates.' : 'Your subscription has been cancelled.')
        : (consent ? '소식 이메일 수신에 동의했습니다.' : '수신 동의가 철회되었습니다.');
      consentStatus.className = 'register-message register-message-success';
      consentStatus.hidden = false;
    } catch (error) {
      consentStatus.textContent = error instanceof Error
        ? error.message
        : (isEnglish ? 'Unable to update your email preferences.' : '수신 동의를 철회하지 못했습니다.');
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
