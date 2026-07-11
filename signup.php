<?php
require_once __DIR__ . '/includes/app.php';
$accountType = strtolower(trim((string)($_GET['type'] ?? 'customer'));
if (!in_array($accountType, ['customer','merchant'], true)) $accountType = 'customer';
$isMerchant = $accountType === 'merchant';
$page_title = ($isMerchant ? 'Create merchant account' : 'Create account') . ' | Microgifter';
$page_section = 'core';
$header_mode = 'public';
require __DIR__ . '/includes/header.php';
?>
<section class="mg-auth-shell" aria-labelledby="signup-title">
  <aside class="mg-auth-aside">
    <span class="mg-badge"><?= $isMerchant ? 'Merchant growth workspace' : 'Start free' ?></span>
    <h1 id="signup-title"><?= $isMerchant ? 'Build the account behind stronger customer relationships.' : 'Create your wallet for local gifts, rewards, and experiences.' ?></h1>
    <p><?= $isMerchant ? 'Set up your business, connect customer activity, launch rewards, and turn social gifting into measurable local growth.' : 'Keep local gifts, rewards, claims, and saved experiences connected to one secure Microgifter account.' ?></p>
    <div class="mg-auth-value-grid" aria-label="Microgifter account benefits">
      <span><strong><?= $isMerchant ? 'Business' : 'Wallet' ?></strong><small><?= $isMerchant ? 'Create your merchant identity and primary location.' : 'Keep gifts and local rewards organized in one place.' ?></small></span>
      <span><strong><?= $isMerchant ? 'Customers' : 'Discover' ?></strong><small><?= $isMerchant ? 'Connect claims, visits, campaigns, and follow-up.' : 'Explore offers and local experiences worth sharing.' ?></small></span>
      <span><strong><?= $isMerchant ? 'Growth' : 'Share' ?></strong><small><?= $isMerchant ? 'Launch campaigns, rewards, and measurable commerce.' : 'Send, claim, save, and regift local value.' ?></small></span>
    </div>
  </aside>
  <form class="mg-auth-card" method="post" action="/api/auth/register.php" data-auth-form="signup" data-success-redirect="<?= $isMerchant ? '/merchant-onboarding.php' : '/inbox.php' ?>">
    <?= mg_csrf_field() ?>
    <input type="hidden" name="account_type" value="<?= mg_e($accountType) ?>">
    <span class="mg-auth-kicker"><?= $isMerchant ? 'New merchant' : 'New account' ?></span>
    <h2><?= $isMerchant ? 'Create merchant account' : 'Create account' ?></h2>
    <p class="mg-auth-card-intro"><?= $isMerchant ? 'Start with your business identity and continue into the guided merchant setup.' : 'Create a free account now. Merchant tools can be added later from your workspace.' ?></p>
    <div class="mg-form-status" data-auth-status role="status" aria-live="polite"></div>
    <label>Full name<input type="text" name="full_name" autocomplete="name" required></label>
    <?php if($isMerchant): ?><label>Business name<input type="text" name="business_name" autocomplete="organization" required maxlength="180"></label><?php endif; ?>
    <label>Email<input type="email" name="email" autocomplete="email" required></label>
    <label>Password<input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
    <button class="mg-btn mg-btn-primary" type="submit"><?= $isMerchant ? 'Create merchant workspace' : 'Create account' ?></button>
    <div class="mg-auth-switch-row">
      <p>Already have an account? <a href="/signin.php?return=<?= rawurlencode($isMerchant ? '/merchant-onboarding.php' : '/inbox.php') ?>">Sign in</a></p>
      <p><a href="/signup.php?type=<?= $isMerchant ? 'customer' : 'merchant' ?>"><?= $isMerchant ? 'Create a customer account instead' : 'Create a merchant account' ?></a></p>
    </div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
