<?php
require_once __DIR__ . '/includes/app.php';
if (!mg_mfa_pending_login()) {
    header('Location: /signin.php', true, 302);
    exit;
}
$page_title = 'Verify sign in | Microgifter';
$page_section = 'core';
$header_mode = 'public';
require __DIR__ . '/includes/header.php';
?>
<section class="mg-auth-shell" aria-labelledby="mfa-title">
  <aside class="mg-auth-aside">
    <span class="mg-badge">Secure sign in</span>
    <h1 id="mfa-title">Confirm it is really you.</h1>
    <p>Enter the six-digit code from your authenticator app. A one-time recovery code also works.</p>
    <div class="mg-auth-value-grid" aria-label="Account security protections">
      <span><strong>Short lived</strong><small>This sign-in challenge expires automatically.</small></span>
      <span><strong>One time</strong><small>Authenticator counters and recovery codes cannot be replayed.</small></span>
      <span><strong>Revocable</strong><small>Completed sessions remain visible in account security.</small></span>
    </div>
  </aside>
  <form class="mg-auth-card" method="post" action="/api/auth/mfa/verify.php" data-auth-form="mfa">
    <?= mg_csrf_field() ?>
    <span class="mg-auth-kicker">Multi-factor authentication</span>
    <h2>Enter security code</h2>
    <p class="mg-auth-card-intro">Use your authenticator app or one saved recovery code.</p>
    <div class="mg-form-status" data-auth-status role="status" aria-live="polite"></div>
    <label>Authenticator or recovery code<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required autofocus></label>
    <button class="mg-btn mg-btn-primary" type="submit">Verify and sign in</button>
    <div class="mg-auth-switch-row"><p><a href="/signin.php">Restart sign in</a></p></div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
