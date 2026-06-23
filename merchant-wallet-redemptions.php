<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Wallet Redemptions | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css'];
$page_scripts = ['/assets/js/stage12-redemptions.js'];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-merchant-main" data-stage12-redemptions>
  <section class="mg-merchant-heading"><div><span class="mg-eyebrow">Merchant redemption</span><h1>Redeem wallet rewards</h1><p>Validate a claimed wallet item and close the campaign loop from issued to redeemed.</p></div><div class="mg-heading-actions"><a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Campaigns</a></div></section>
  <section class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>Redeem wallet item</h2><p>Enter the wallet item ID shown by the customer.</p></div></div><div class="mg-app-panel-body"><form class="mg-merchant-form" data-redemption-form><label>Wallet item ID<input name="wallet_item_id" required placeholder="Wallet item UUID"></label><label>Location code<input name="location_code" placeholder="Optional location code"></label><div class="mg-form-status" data-redemption-status>Ready to redeem a claimed wallet item.</div><button class="mg-btn mg-btn-primary" type="submit">Redeem reward</button></form></div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
