<?php
require_once __DIR__ . '/includes/app.php';
$accountType = strtolower(trim((string)($_GET['type'] ?? 'customer')));
if (!in_array($accountType, ['customer','merchant'], true)) $accountType = 'customer';
$isMerchant = $accountType === 'merchant';
$page_title = ($isMerchant ? 'Create merchant account' : 'Create account') . ' | Microgifter';
$page_section = 'core';
$header_mode = 'public';
require __DIR__ . '/includes/header.php';
?>
<section class="mg-auth-shell">
  <aside class="mg-auth-aside">
    <span class="mg-badge"><?= $isMerchant ? 'Merchant workspace' : 'Start free' ?></span>
    <h1><?= $isMerchant ? 'Create your Microgifter merchant account and launch Loyalty Quests.' : 'Create your Microgifter account and start building local demand.' ?></h1>
    <p><?= $isMerchant ? 'Set up your business, location, rewards, and first Loyalty Quest in one guided workspace.' : 'Save rewards, publish profile details, prepare merchant tools, and connect future campaigns to a single account workspace.' ?></p>
    <div class="mg-auth-value-grid" aria-label="Microgifter account benefits">
      <span><strong><?= $isMerchant ? 'Set up' : 'Save' ?></strong><small><?= $isMerchant ? 'Create your business profile and primary location.' : 'Keep gifts, drafts, and local reward activity connected.' ?></small></span>
      <span><strong><?= $isMerchant ? 'Reward' : 'Publish' ?></strong><small><?= $isMerchant ? 'Activate a Microgifter reward template for your campaign.' : 'Prepare a profile and storefront for public discovery.' ?></small></span>
      <span><strong>Launch</strong><small><?= $isMerchant ? 'Publish your first Loyalty Quest campaign.' : 'Turn experiences into measurable demand campaigns.' ?></small></span>
    </div>
  </aside>
  <form class="mg-auth-card" method="post" action="/api/auth/register.php" data-auth-form="signup" data-success-redirect="<?= $isMerchant ? '/merchant-onboarding.php' : '/inbox.php' ?>">
    <?= mg_csrf_field() ?>
    <input type="hidden" name="account_type" value="<?= mg_e($accountType) ?>">
    <span class="mg-auth-kicker"><?= $isMerchant ? 'New merchant' : 'New workspace' ?></span>
    <h2><?= $isMerchant ? 'Create merchant account' : 'Create account' ?></h2>
    <p class="mg-auth-card-intro"><?= $isMerchant ? 'A Microgifter merchant account is required to create and publish Loyalty Quests.' : 'Start with a free account. Merchant and campaign tools can be activated from your workspace.' ?></p>
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
