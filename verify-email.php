<?php
require_once __DIR__ . '/includes/app.php';
$page_title = 'Verify email | Microgifter';
$page_section = 'core';
$header_mode = 'public';
$token = trim((string) ($_GET['token'] ?? ''));
$pending = isset($_GET['pending']);
$current = mg_current_user();
require __DIR__ . '/includes/header.php';
?>
<section class="mg-auth-shell" aria-labelledby="verify-title">
  <aside class="mg-auth-aside">
    <span class="mg-badge">Email verification</span>
    <h1 id="verify-title">Verify your email and activate your workspace.</h1>
    <p>Email verification protects account ownership before wallet activity, merchant tools, customer communication, and value-bearing actions are enabled.</p>
    <div class="mg-auth-note"><strong>Account protection</strong><span>Verification links are single-use and expire automatically.</span></div>
  </aside>

  <?php if ($token !== ''): ?>
    <form class="mg-auth-card" method="post" action="/api/auth/email/verify.php" data-auth-form="verify-email" novalidate>
      <?= mg_csrf_field() ?>
      <input type="hidden" name="token" value="<?= mg_e($token) ?>">
      <span class="mg-auth-kicker">Account activation</span>
      <h2>Verify email</h2>
      <p class="mg-form-intro">Confirm this email address to finish securing your Microgifter account.</p>
      <div class="mg-form-status" data-auth-status aria-live="polite"></div>
      <button class="mg-btn mg-btn-primary" type="submit">Verify email</button>
      <p class="mg-auth-links"><a href="/signin.php">Back to sign in</a></p>
    </form>
  <?php else: ?>
    <form class="mg-auth-card" method="post" action="/api/auth/email/resend.php" data-auth-form="resend-verification" novalidate>
      <?= mg_csrf_field() ?>
      <span class="mg-auth-kicker">Verification required</span>
      <h2>Check your inbox</h2>
      <p class="mg-form-intro"><?= $pending ? 'Your account is ready, but the email address must be verified before protected features are enabled.' : 'Request a new verification link below.' ?></p>
      <div class="mg-form-status" data-auth-status aria-live="polite"></div>
      <label>Email<input type="email" name="email" autocomplete="email" value="<?= mg_e((string) ($current['email'] ?? '')) ?>" required></label>
      <button class="mg-btn mg-btn-primary" type="submit">Send verification link</button>
      <p class="mg-auth-links"><a href="/signin.php">Back to sign in</a></p>
    </form>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
