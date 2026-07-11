<?php
require_once __DIR__ . '/includes/app.php';
$page_title = 'Forgot password | Microgifter';
$page_section = 'core';
$header_mode = 'public';
require __DIR__ . '/includes/header.php';
?>
<section class="mg-auth-shell" aria-labelledby="forgot-title">
  <aside class="mg-auth-aside">
    <span class="mg-badge">Account recovery</span>
    <h1 id="forgot-title">Get back to your Microgifter workspace.</h1>
    <p>Enter the email connected to your account. Microgifter will prepare a secure, time-limited reset request when the account is eligible.</p>
    <div class="mg-auth-note"><strong>Privacy protected</strong><span>This page always returns a generic confirmation and never reveals whether an email address is registered.</span></div>
  </aside>
  <form class="mg-auth-card" method="post" action="/api/auth/password/forgot.php" data-auth-form="forgot-password" novalidate>
    <?= mg_csrf_field() ?>
    <span class="mg-auth-kicker">Secure recovery</span>
    <h2>Forgot password</h2>
    <p class="mg-form-intro">Enter your account email to prepare a password reset link.</p>
    <label>Email address<input type="email" name="email" autocomplete="email" inputmode="email" required></label>
    <div class="mg-form-status" data-auth-status aria-live="polite"></div>
    <button class="mg-btn mg-btn-primary" type="submit">Send reset link</button>
    <p class="mg-auth-links"><a href="/signin.php">Back to sign in</a></p>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
