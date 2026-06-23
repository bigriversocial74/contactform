<?php
require_once __DIR__ . '/includes/app.php';
$page_title = 'Sign in | Microgifter';
$page_section = 'core';
$header_mode = 'public';
require __DIR__ . '/includes/header.php';
?>
<section class="mg-auth-shell">
  <aside class="mg-auth-aside">
    <span class="mg-badge">Welcome back</span>
    <h1>Sign in to manage rewards, profiles, and local demand.</h1>
    <p>Use your Microgifter account to open your inbox, manage merchant tools, publish profile updates, and track wallet-ready experiences.</p>
    <div class="mg-auth-value-grid" aria-label="Microgifter account benefits">
      <span><strong>Inbox</strong><small>Track sends, claims, and customer activity.</small></span>
      <span><strong>Profile</strong><small>Open your public profile and storefront tools.</small></span>
      <span><strong>Market</strong><small>Follow demand signals across local experiences.</small></span>
    </div>
  </aside>
  <form class="mg-auth-card" method="post" action="/api/auth/login.php" data-auth-form="signin" data-success-redirect="/inbox.php">
    <?= mg_csrf_field() ?>
    <span class="mg-auth-kicker">Account access</span>
    <h2>Sign in</h2>
    <p class="mg-auth-card-intro">Access your Microgifter workspace and saved local reward activity.</p>
    <div class="mg-form-status" data-auth-status role="status" aria-live="polite"></div>
    <label>Email<input type="email" name="email" autocomplete="email" required></label>
    <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
    <button class="mg-btn mg-btn-primary" type="submit">Sign in</button>
    <div class="mg-auth-switch-row">
      <p><a href="/forgot-password.php">Forgot password?</a></p>
      <p>New here? <a href="/signup.php">Create an account</a></p>
    </div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
