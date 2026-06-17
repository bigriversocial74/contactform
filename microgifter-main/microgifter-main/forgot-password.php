<?php
require_once __DIR__ . '/includes/app.php';
$page_title = 'Forgot password | Microgifter';
$page_section = 'core';
$header_mode = 'public';
require __DIR__ . '/includes/header.php';
?>
<section class="mg-auth-shell">
  <aside class="mg-auth-aside">
    <span class="mg-badge">Account recovery</span>
    <h1>Reset your Microgifter password securely.</h1>
    <p>Enter the email connected to your account. If the account exists, Microgifter will create a time-limited reset request.</p>
    <div class="mg-auth-note"><strong>Security note</strong><span>For privacy, this page always shows a generic confirmation and never reveals whether an email is registered.</span></div>
  </aside>
  <form class="mg-auth-card" method="post" action="/api/auth/password/forgot.php" data-auth-form="forgot-password" novalidate>
    <?= mg_csrf_field() ?>
    <h2>Forgot password</h2>
    <p class="mg-form-intro">We will prepare a reset link when email delivery is configured.</p>
    <label>Email address<input type="email" name="email" autocomplete="email" inputmode="email" required></label>
    <div class="mg-form-status" data-auth-status aria-live="polite"></div>
    <button class="mg-btn mg-btn-primary" type="submit">Send reset link</button>
    <p class="mg-auth-links"><a href="/signin.php">Back to sign in</a></p>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>