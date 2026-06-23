<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'My Wallet | Microgifter';
$page_section = 'wallet';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css'];
$page_scripts = ['/assets/js/stage12-wallet.js'];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-merchant-main" data-stage12-wallet>
  <section class="mg-merchant-heading"><div><span class="mg-eyebrow">Microgifter wallet</span><h1>My local rewards</h1><p>Claim, hold, and redeem wallet-ready rewards from campaigns, QR drops, contests, and agent-discovered offers.</p></div></section>
  <section class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>Wallet items</h2><p>Issued, claimed, and redeemed local value objects.</p></div></div><div class="mg-app-panel-body"><div class="mg-product-list" data-wallet-list></div><div class="mg-form-status" data-wallet-status></div></div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
