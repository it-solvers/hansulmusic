<?php
$authIsEnglish = ($authLanguage ?? '') === 'en';
$authActionBase = isset($authFormBasePath) ? rtrim($authFormBasePath, '/') : rtrim(appBasePath(), '/');
?>
<dialog class="signup-modal" aria-labelledby="auth-title">
  <section class="register-card">
    <button class="signup-close" type="button" aria-label="<?= $authIsEnglish ? 'Close sign-in window' : 'Sign in 창 닫기' ?>" data-close-auth>&times;</button>
    <p class="register-eyebrow" data-auth-eyebrow>Member access</p>
    <h2 id="auth-title" data-auth-title>Sign in</h2>
    <p class="register-intro" data-auth-intro><?= $authIsEnglish ? 'Enter your email address and password.' : '이메일 주소와 비밀번호를 입력해 주세요.' ?></p>
    <p class="register-message" role="status" aria-live="polite" data-auth-status hidden></p>
    <div class="auth-success" role="status" aria-live="polite" data-auth-success hidden>
      <h3 data-auth-success-greeting></h3>
      <p><?= $authIsEnglish ? 'Your account has been created and you are signed in.' : '회원가입과 동시에 자동으로 로그인되었습니다.<br>이제 회원 기능을 이용하실 수 있습니다.' ?></p>
      <p class="auth-success-hint"><?= $authIsEnglish ? 'Select × in the upper-right corner to close this window.' : '창을 닫으려면 오른쪽 위의 ×를 눌러 주세요.' ?></p>
    </div>
    <form method="post" action="<?= htmlspecialchars($authActionBase . '/auth.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="register-form" data-login-form>
      <input type="hidden" name="csrf_token">
      <input type="hidden" name="action" value="login">
      <label for="login-email"><?= $authIsEnglish ? 'Email address' : '이메일 주소' ?></label>
      <input id="login-email" name="email" type="email" inputmode="email" autocapitalize="off" spellcheck="false" maxlength="255" autocomplete="email" required>
      <label for="login-password"><?= $authIsEnglish ? 'Password' : '비밀번호' ?></label>
      <input id="login-password" name="password" type="password" autocomplete="current-password" required>
      <button type="submit" class="register-submit">Sign in</button>
      <button type="button" class="auth-switch" data-show-signup>Create account</button>
    </form>
    <form method="post" action="<?= htmlspecialchars($authActionBase . '/register.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="register-form" data-signup-form hidden>
      <input type="hidden" name="csrf_token">
      <label for="signup-name"><?= $authIsEnglish ? 'Name' : '이름' ?></label>
      <input id="signup-name" name="name" type="text" maxlength="100" autocomplete="name" required>
      <label for="signup-email"><?= $authIsEnglish ? 'Email address' : '이메일 주소' ?></label>
      <input id="signup-email" name="email" type="email" inputmode="email" autocapitalize="off" spellcheck="false" maxlength="255" autocomplete="email" required>
      <label for="signup-email-confirm"><?= $authIsEnglish ? 'Confirm email address' : '이메일 주소 확인' ?></label>
      <input id="signup-email-confirm" name="email_confirm" type="email" inputmode="email" autocapitalize="off" spellcheck="false" maxlength="255" autocomplete="email" required>
      <label for="signup-password"><?= $authIsEnglish ? 'Password' : '비밀번호' ?></label>
      <input id="signup-password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
      <label for="signup-password-confirm"><?= $authIsEnglish ? 'Confirm password' : '비밀번호 확인' ?></label>
      <input id="signup-password-confirm" name="password_confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
      <label class="register-consent">
        <input type="checkbox" name="marketing_consent" value="1">
        <span><?= $authIsEnglish ? 'I agree to receive email updates about new releases, events, and promotions. <strong>(Optional)</strong>' : '신규 작품 출시 및 이벤트·광고 소식을 이메일로 받는 데 동의합니다. <strong>(선택)</strong>' ?></span>
      </label>
      <button type="submit" class="register-submit">Create account</button>
      <button type="button" class="auth-switch" data-show-login>Already have an account? Sign in</button>
    </form>
    <p class="register-footnote"><?= $authIsEnglish ? 'Email verification, social sign-in, and email updates will be available in the future.' : '이메일 인증, 간편 로그인 및 소식 이메일 발송은 추후 지원 예정입니다.' ?></p>
  </section>
</dialog>
<dialog class="signup-modal consent-modal" aria-labelledby="consent-title" data-consent-modal>
  <section class="register-card">
    <button class="signup-close" type="button" aria-label="<?= $authIsEnglish ? 'Close email preferences' : '수신 동의 창 닫기' ?>" data-close-consent>&times;</button>
    <p class="register-eyebrow">Email preferences</p>
    <h2 id="consent-title"><?= $authIsEnglish ? 'Email preferences' : '소식 수신 설정' ?></h2>
    <p class="register-intro" data-consent-message></p>
    <p class="register-message" role="status" aria-live="polite" data-consent-status hidden></p>
    <button type="button" class="register-submit" data-grant-consent hidden><?= $authIsEnglish ? 'Subscribe to updates' : '수신에 동의하기' ?></button>
    <button type="button" class="register-submit" data-withdraw-consent hidden><?= $authIsEnglish ? 'Unsubscribe from updates' : '수신 동의 철회' ?></button>
  </section>
</dialog>
