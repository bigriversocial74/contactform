<?php
require_once __DIR__ . '/includes/app.php';
$page_title = 'Verify email | Microgifter';
$page_section = 'core';
$token = trim((string) ($_GET['token'] ?? ''));
require __DIR__ . '/includes/header.php';
?>
<section class="mg-auth-shell">
  <aside class="mg-auth-aside">
    <span class="mg-badge">Email verification</span>
    <h1>Verify your account email.</h1>
    <p>Email verification protects account ownership and prepares your account for future merchant, inbox, and publishing tools.</p>
    <div class="mg-auth-note">
      <strong>Account protection</strong>
      <span>Verification tokens are one-time use and expire automatically.</span>
    </div>
  </aside>

  <form class="mg-auth-card" method="post" action="/api/auth/email/verify.php" data-auth-form="verify-email" novalidate>
    <?= mg_csrf_field() ?>
    <input type="hidden" name="token" value="<?= mg_e($token) ?>">
    <h2>Verify email</h2>
    <?php if ($token === ''): ?>
      <div class="mg-alert mg-alert-warning">This page is missing a verification token. Use the verification link from your email or request a new one from your account page.</div>
    <?php else: ?>
      <p class="mg-form-intro">Click the button below to verify this account email.</p>
    <?php endif; ?>
    <div class="mg-form-status" data-auth-status aria-live="polite"></div>
    <button class="mg-btn mg-btn-primary" type="submit" <?= $token === '' ? 'disabled' : '' ?>>Verify email</button>
    <p class="mg-auth-links"><a href="/account.php">Go to account</a> · <a href="/signin.php">Back to sign in</a></p>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>