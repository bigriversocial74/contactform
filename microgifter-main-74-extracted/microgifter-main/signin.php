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
    <h1>Sign in to save gifts and manage your workspace.</h1>
    <p>Use your Microgifter account to unlock saved drafts, inbox tools, and permission-based workspace features.</p>
  </aside>
  <form class="mg-auth-card" method="post" action="/api/auth/login.php" data-auth-form="signin" data-success-redirect="/inbox.php">
    <?= mg_csrf_field() ?>
    <h2>Sign in</h2>
    <div class="mg-form-status" data-auth-status role="status" aria-live="polite"></div>
    <label>Email<input type="email" name="email" autocomplete="email" required></label>
    <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
    <button class="mg-btn mg-btn-primary" type="submit">Sign in</button>
    <p><a href="/forgot-password.php">Forgot password?</a></p>
    <p>New here? <a href="/signup.php">Create an account</a></p>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>