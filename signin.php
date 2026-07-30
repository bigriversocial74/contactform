<?php
require_once __DIR__ . '/includes/app.php';

$requestedReturn = trim((string)($_GET['return'] ?? ''));
$inviteToken = strtolower(trim((string)($_GET['invite'] ?? '')));
$invitePrefill = null;
if (preg_match('/^[a-f0-9]{64}$/', $inviteToken)) {
    require_once __DIR__ . '/includes/investment/investor-invitations.php';
    $invitePrefill = mg_investment_invitation_prefill(mg_db(), $inviteToken);
    if ($requestedReturn === '') $requestedReturn = '/investor-invitation.php?token=' . rawurlencode($inviteToken);
}
$returnPath = $requestedReturn !== '' ? mg_safe_return_path($requestedReturn) : '/agent.php';

$page_title = 'Sign in | Microgifter';
$page_section = 'core';
$header_mode = 'public';
require __DIR__ . '/includes/header.php';
?>
<section class="mg-auth-shell" aria-labelledby="signin-title">
  <aside class="mg-auth-aside">
    <span class="mg-badge"><?= $invitePrefill ? 'Investor invitation' : 'Welcome back' ?></span>
    <h1 id="signin-title"><?= $invitePrefill ? 'Sign in with the invited email to continue Investor onboarding.' : 'Keep every local customer connection moving.' ?></h1>
    <p><?= $invitePrefill ? 'Your invitation remains subject to professional onboarding and Super Admin approval. Signing in does not grant Investor Portal or Data Room access.' : 'Sign in to open your wallet, manage customer activity, continue campaigns, and follow gifts and rewards from send through claim and redemption.' ?></p>
    <div class="mg-auth-value-grid" aria-label="Microgifter account benefits">
      <span><strong><?= $invitePrefill ? 'Identity' : 'Wallet' ?></strong><small><?= $invitePrefill ? 'Use the exact invited email address.' : 'Keep gifts, rewards, claims, and saved activity together.' ?></small></span>
      <span><strong><?= $invitePrefill ? 'Onboarding' : 'Customers' ?></strong><small><?= $invitePrefill ? 'Complete professional information and disclosures.' : 'See relationship signals and follow-up opportunities.' ?></small></span>
      <span><strong><?= $invitePrefill ? 'Approval' : 'Campaigns' ?></strong><small><?= $invitePrefill ? 'Super Admin approval remains required.' : 'Continue creating measurable local value.' ?></small></span>
    </div>
  </aside>
  <form class="mg-auth-card" method="post" action="/api/auth/login.php" data-auth-form="signin" data-success-redirect="<?= mg_e($returnPath) ?>">
    <?= mg_csrf_field() ?>
    <input type="hidden" name="return" value="<?= mg_e($returnPath) ?>">
    <span class="mg-auth-kicker">Account access</span>
    <h2>Sign in</h2>
    <p class="mg-auth-card-intro"><?= $invitePrefill ? 'Continue with the email address bound to this Investor invitation.' : 'Access your Microgifter workspace and connected local activity.' ?></p>
    <div class="mg-form-status" data-auth-status role="status" aria-live="polite"></div>
    <label>Email<input type="email" name="email" autocomplete="email" required value="<?= mg_e((string)($invitePrefill['email'] ?? '')) ?>"<?= $invitePrefill ? ' readonly' : '' ?>></label>
    <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
    <button class="mg-btn mg-btn-primary" type="submit">Sign in</button>
    <div class="mg-auth-switch-row">
      <p><a href="/forgot-password.php">Forgot password?</a></p>
      <p>New here? <a href="/signup.php?type=customer&amp;return=<?= rawurlencode($returnPath) ?><?= $invitePrefill ? '&amp;invite=' . rawurlencode($inviteToken) : '' ?>">Create an account</a></p>
    </div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
