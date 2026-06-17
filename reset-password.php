<?php
require_once __DIR__ . '/includes/app.php';
$page_title = 'Reset password | Microgifter';
$page_section = 'core';
$header_mode = 'public';
$token = trim((string) ($_GET['token'] ?? ''));
require __DIR__ . '/includes/header.php';
?>
<section class="mg-auth-shell">
  <aside class="mg-auth-aside">
    <span class="mg-badge">Secure reset</span>
    <h1>Choose a new password.</h1>
    <p>Use a unique password with at least 12 characters. After the reset succeeds, you will be sent back to sign in.</p>
    <div class="mg-auth-note"><strong>Password rule</strong><span>Avoid reused passwords. Use a password manager when possible.</span></div>
  </aside>
  <form class="mg-auth-card" method="post" action="/api/auth/password/reset.php" data-auth-form="reset-password" novalidate>
    <?= mg_csrf_field() ?>
    <input type="hidden" name="token" value="<?= mg_e($token) ?>">
    <h2>Reset password</h2>
    <?php if ($token === ''): ?><div class="mg-alert mg-alert-warning">This reset page is missing a token. Request a new reset link before continuing.</div><?php endif; ?>
    <label>New password<input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
    <label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
    <div class="mg-form-status" data-auth-status aria-live="polite"></div>
    <button class="mg-btn mg-btn-primary" type="submit" <?= $token === '' ? 'disabled' : '' ?>>Reset password</button>
    <p class="mg-auth-links"><a href="/forgot-password.php">Request a new reset link</a></p>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>