<?php
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/pricing-packages.php';

$availablePlans = [];
foreach (mg_public_pricing_packages() as $package) {
    $id = strtolower(trim((string) ($package['id'] ?? '')));
    if ($id !== '') $availablePlans[$id] = $package;
}

$selectedPlan = strtolower(trim((string) ($_GET['plan'] ?? '')));
if (!isset($availablePlans[$selectedPlan])) $selectedPlan = '';

$accountType = strtolower(trim((string) ($_GET['type'] ?? ($selectedPlan !== '' ? 'merchant' : 'customer'))));
if (!in_array($accountType, ['customer', 'merchant'], true)) $accountType = 'customer';
if ($selectedPlan !== '') $accountType = 'merchant';
if ($accountType === 'merchant' && $selectedPlan === '') $selectedPlan = 'starter';

$isMerchant = $accountType === 'merchant';
$selectedPlanName = $selectedPlan !== '' ? (string) ($availablePlans[$selectedPlan]['name'] ?? ucfirst($selectedPlan)) : '';
$page_title = ($isMerchant ? 'Create merchant account' : 'Create account') . ' | Microgifter';
$page_section = 'core';
$header_mode = 'public';
$page_styles = ['/assets/css/auth-password-fields.css'];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-auth-shell" aria-labelledby="signup-title">
  <aside class="mg-auth-aside">
    <span class="mg-badge"><?= $isMerchant ? 'Merchant account setup' : 'Start free' ?></span>
    <h1 id="signup-title"><?= $isMerchant ? 'Create your account, then activate the right merchant package.' : 'Create your wallet for local gifts, rewards, and experiences.' ?></h1>
    <p><?= $isMerchant ? 'Every account begins with the Free Wallet. After email verification, continue to secure checkout for ' . mg_e($selectedPlanName) . ' and unlock merchant tools only after confirmed activation.' : 'Keep local gifts, rewards, claims, and saved experiences connected to one secure Microgifter account.' ?></p>
    <div class="mg-auth-value-grid" aria-label="Microgifter account benefits">
      <span><strong><?= $isMerchant ? 'Account' : 'Wallet' ?></strong><small><?= $isMerchant ? 'Create one secure identity before activating merchant access.' : 'Keep gifts and local rewards organized in one place.' ?></small></span>
      <span><strong><?= $isMerchant ? 'Package' : 'Discover' ?></strong><small><?= $isMerchant ? mg_e($selectedPlanName) . ' is selected for the next checkout step.' : 'Explore offers and local experiences worth sharing.' ?></small></span>
      <span><strong><?= $isMerchant ? 'Activation' : 'Share' ?></strong><small><?= $isMerchant ? 'Merchant permissions and workspace capacity activate after verified payment or an admin grant.' : 'Send, claim, save, and regift local value.' ?></small></span>
    </div>
  </aside>
  <form class="mg-auth-card" method="post" action="/api/auth/register.php" data-auth-form="signup" data-success-redirect="<?= $isMerchant ? '/account-subscriptions.php' : '/inbox.php' ?>">
    <?= mg_csrf_field() ?>
    <input type="hidden" name="account_type" value="<?= mg_e($accountType) ?>">
    <input type="hidden" name="selected_plan" value="<?= mg_e($selectedPlan) ?>">
    <span class="mg-auth-kicker"><?= $isMerchant ? 'New merchant account' : 'New account' ?></span>
    <h2><?= $isMerchant ? 'Create account for ' . mg_e($selectedPlanName) : 'Create account' ?></h2>
    <p class="mg-auth-card-intro"><?= $isMerchant ? 'Your account is created as a Free Wallet first. Merchant access is granted only after checkout completes or an administrator applies a complimentary subscription.' : 'Create a free account now. Merchant packages can be added later from your subscription workspace.' ?></p>
    <div class="mg-form-status" data-auth-status role="status" aria-live="polite"></div>
    <label>Full name<input type="text" name="full_name" autocomplete="name" required></label>
    <?php if($isMerchant): ?><label>Business name<input type="text" name="business_name" autocomplete="organization" required maxlength="180"></label><?php endif; ?>
    <label>Email<input type="email" name="email" autocomplete="email" required></label>

    <div class="mg-auth-field">
      <label for="signup-password">Password</label>
      <div class="mg-password-field">
        <input id="signup-password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="4096" aria-describedby="signup-password-help" required>
        <button class="mg-password-toggle" type="button" data-password-toggle data-password-target="signup-password" aria-label="Show password" aria-pressed="false" title="Show password">
          <svg class="mg-password-icon mg-password-icon-show" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
          <svg class="mg-password-icon mg-password-icon-hide" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3l18 18M10.6 6.15A10.9 10.9 0 0 1 12 6c6 0 9.5 6 9.5 6a15 15 0 0 1-2.1 2.7M6.2 6.2C3.85 7.75 2.5 12 2.5 12s3.5 6 9.5 6c1.5 0 2.85-.38 4.05-.95M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
        </button>
      </div>
      <small class="mg-field-hint" id="signup-password-help">Use at least 12 characters.</small>
    </div>

    <div class="mg-auth-field">
      <label for="signup-password-confirmation">Confirm password</label>
      <div class="mg-password-field">
        <input id="signup-password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="4096" aria-describedby="signup-password-confirmation-help" required>
        <button class="mg-password-toggle" type="button" data-password-toggle data-password-target="signup-password-confirmation" aria-label="Show confirmed password" aria-pressed="false" title="Show confirmed password">
          <svg class="mg-password-icon mg-password-icon-show" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
          <svg class="mg-password-icon mg-password-icon-hide" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3l18 18M10.6 6.15A10.9 10.9 0 0 1 12 6c6 0 9.5 6 9.5 6a15 15 0 0 1-2.1 2.7M6.2 6.2C3.85 7.75 2.5 12 2.5 12s3.5 6 9.5 6c1.5 0 2.85-.38 4.05-.95M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
        </button>
      </div>
      <small class="mg-field-hint" id="signup-password-confirmation-help">Enter the same password again.</small>
    </div>

    <button class="mg-btn mg-btn-primary" type="submit"><?= $isMerchant ? 'Create account and continue' : 'Create account' ?></button>
    <div class="mg-auth-switch-row">
      <p>Already have an account? <a href="/signin.php?return=<?= rawurlencode($isMerchant ? '/account-subscriptions.php?plan=' . $selectedPlan . '&source=signup' : '/inbox.php') ?>">Sign in</a></p>
      <p><a href="/signup.php?type=<?= $isMerchant ? 'customer' : 'merchant' ?>"><?= $isMerchant ? 'Create a customer account instead' : 'Create a merchant account' ?></a></p>
    </div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
