<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$user = mg_require_auth('/signin.php');
$page_title = 'Stamp Purchase Receipt | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/stamp-ledger.css','/assets/css/merchant-stamps-ledger.css'];
$page_scripts = ['/assets/js/stamp-receipt.js?v=20260707-stamp-merchant-receipts'];
$purchase_id = trim((string)($_GET['purchase'] ?? $_GET['purchase_id'] ?? ''));
$mg_package_context = is_array($mg_package_context ?? null) ? $mg_package_context : mg_user_package_context(null, $user);
$canMerchantAccess = (bool)($can_merchant_nav ?? !empty($mg_package_context['merchant_access']));
$appSidebarVariant = $canMerchantAccess ? 'merchant' : 'utility';
$appSidebarLabel = $canMerchantAccess ? 'Merchant' : 'Workspace';
$appSidebarActive = $canMerchantAccess ? 'stamps' : 'subscriptions';
$appSidebarCompact = true;
$appSidebarNav = $canMerchantAccess ? [
  'overview' => ['section'=>'Overview','label'=>'Overview','detail'=>'Workspace health','href'=>'/merchant.php','visible'=>true],
  'campaigns' => ['section'=>'Engage','label'=>'Campaigns','detail'=>'Forms, contests, QR drops','href'=>'/merchant-campaigns.php','visible'=>true],
  'merchant_crm' => ['label'=>'Merchant CRM','detail'=>'Customers and campaign history','href'=>'/merchant-crm.php','visible'=>true],
  'stamps' => ['section'=>'Finance','label'=>'Stamp Ledger','detail'=>'Sends and balance','href'=>'/merchant-stamps.php','visible'=>true,'active'=>true],
  'payments' => ['label'=>'Payments','detail'=>'Checkout and reconciliation','href'=>'/merchant-payments.php','visible'=>true],
  'settings' => ['section'=>'Manage','label'=>'Settings','detail'=>'Business configuration','href'=>'/merchant-settings.php','visible'=>true],
] : [
  'inbox' => ['section'=>'Workspace','label'=>'Inbox','detail'=>'Gift inbox','href'=>'/inbox.php','visible'=>true],
  'subscriptions' => ['section'=>'Merchant','label'=>'Upgrade','detail'=>'Unlock merchant tools','href'=>'/pricing.php','visible'=>true,'active'=>true],
];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-merchant-app" data-sidebar-contract="mg-app-sidebar">
  <?php require __DIR__ . '/includes/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main">
    <section class="mg-stamp-ledger-workspace" data-stamp-receipt-page data-purchase-id="<?= mg_e($purchase_id) ?>">
      <section class="mg-app-panel mg-stamp-ledger-panel">
        <div class="mg-app-panel-head mg-stamp-ledger-panel-head">
          <div>
            <a class="mg-admin-package-back" href="/merchant-stamps.php#stamp-purchase-history">← Back to purchase history</a>
            <span class="mg-eyebrow">Merchant receipt</span>
            <h1>Stamp purchase receipt</h1>
            <p>Owner-scoped proof of Stamp bundle purchase, payment state, provider reference, and ledger credit status.</p>
          </div>
          <div class="mg-heading-actions"><button class="mg-btn mg-btn-primary" type="button" data-print-stamp-receipt>Print / Save PDF</button><a class="mg-btn mg-btn-soft" href="/merchant-stamps.php#stamp-purchase-history">Purchase history</a></div>
        </div>
        <div class="mg-app-panel-body" data-stamp-receipt-content>
          <div class="mg-empty-state">Loading Stamp receipt…</div>
        </div>
      </section>
    </section>
  </main>
</section>
<style>
@media print{
  .mg-app-sidebar,.mg-site-header,.mg-heading-actions,.mg-admin-package-back{display:none!important}
  .mg-app-shell,.mg-app-workspace,.mg-merchant-main{display:block!important;margin:0!important;padding:0!important;width:100%!important}
  .mg-app-panel{box-shadow:none!important;border:0!important}
  body{background:#fff!important}
}
</style>
<?php require __DIR__ . '/includes/footer.php'; ?>
