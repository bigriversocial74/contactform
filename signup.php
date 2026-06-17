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
    <h1>Create an account to save, publish, and unlock the inbox.</h1>
    <p>Guest mode lets you test. An account lets Microgifter connect drafts, profile details, and future merchant tools.</p>
  </aside>
  <form class="mg-auth-card" method="post" action="/api/auth/register.php" data-auth-form="signup" data-success-redirect="/inbox.php">
    <?= mg_csrf_field() ?>
    <h2>Create account</h2>
    <div class="mg-form-status" data-auth-status role="status" aria-live="polite"></div>
    <label>Full name<input type="text" name="full_name" autocomplete="name" required></label>
    <label>Email<input type="email" name="email" autocomplete="email" required></label>
    <label>Password<input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
    <button class="mg-btn mg-btn-primary" type="submit">Create account</button>
    <p>Already have an account? <a href="/signin.php">Sign in</a></p>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>