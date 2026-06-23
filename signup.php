<?php
require_once __DIR__ . '/includes/app.php';
$page_title = 'Create account | Microgifter';
$page_section = 'core';
$header_mode = 'public';
require __DIR__ . '/includes/header.php';
?>
<section class="mg-auth-shell">
  <aside class="mg-auth-aside">
    <span class="mg-badge">Start free</span>
    <h1>Create your Microgifter account and start building local demand.</h1>
    <p>Save rewards, publish profile details, prepare merchant tools, and connect future campaigns to a single account workspace.</p>
    <div class="mg-auth-value-grid" aria-label="Microgifter account benefits">
      <span><strong>Save</strong><small>Keep gifts, drafts, and local reward activity connected.</small></span>
      <span><strong>Publish</strong><small>Prepare a profile and storefront for public discovery.</small></span>
      <span><strong>Launch</strong><small>Turn experiences into measurable demand campaigns.</small></span>
    </div>
  </aside>
  <form class="mg-auth-card" method="post" action="/api/auth/register.php" data-auth-form="signup" data-success-redirect="/inbox.php">
    <?= mg_csrf_field() ?>
    <span class="mg-auth-kicker">New workspace</span>
    <h2>Create account</h2>
    <p class="mg-auth-card-intro">Start with a free account. Merchant and campaign tools can be activated from your workspace.</p>
    <div class="mg-form-status" data-auth-status role="status" aria-live="polite"></div>
    <label>Full name<input type="text" name="full_name" autocomplete="name" required></label>
    <label>Email<input type="email" name="email" autocomplete="email" required></label>
    <label>Password<input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
    <button class="mg-btn mg-btn-primary" type="submit">Create account</button>
    <div class="mg-auth-switch-row">
      <p>Already have an account? <a href="/signin.php">Sign in</a></p>
    </div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
