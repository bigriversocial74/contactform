<?php
require_once __DIR__ . '/includes/app.php';
$page_title = 'Sign in | Microgifter';
$page_section = 'core';
$header_mode = 'public';
require __DIR__ . '/includes/header.php';
?>
<section class="mg-auth-shell" aria-labelledby="signin-title">
  <aside class="mg-auth-aside">
    <span class="mg-badge">Welcome back</span>
    <h1 id="signin-title">Keep every local customer connection moving.</h1>
    <p>Sign in to open your wallet, manage customer activity, continue campaigns, and follow gifts and rewards from send through claim and redemption.</p>
    <div class="mg-auth-value-grid" aria-label="Microgifter account benefits">
      <span><strong>Wallet</strong><small>Keep gifts, rewards, claims, and saved activity together.</small></span>
      <span><strong>Customers</strong><small>See relationship signals and follow-up opportunities.</small></span>
      <span><strong>Campaigns</strong><small>Continue creating measurable local value.</small></span>
    </div>
  </aside>
  <form class="mg-auth-card" method="post" action="/api/auth/login.php" data-auth-form="signin" data-success-redirect="/agent.php">
    <?= mg_csrf_field() ?>
    <span class="mg-auth-kicker">Account access</span>
    <h2>Sign in</h2>
    <p class="mg-auth-card-intro">Access your Microgifter workspace and connected local activity.</p>
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
