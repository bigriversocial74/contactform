<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$user = mg_current_user();
if (!$user) { header('Location: /signin.php?next=' . rawurlencode('/bundle-settlements.php')); exit; }
if (function_exists('mg_user_has_merchant_access') && !mg_user_has_merchant_access($user)) { http_response_code(403); }
$page_title='Bundle Settlements | Microgifter';
$page_section='account';
$page_styles=['/assets/css/bundle-settlements-v7.css'];
$page_scripts=['/assets/js/bundle-settlements-v7.js'];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-bundle-settlement-page" data-bundle-settlements>
  <header class="mg-bundle-settlement-hero">
    <div><span>Product Bundles · Phase 7</span><h1>Settlement accounting</h1><p>Review bundle earnings, platform fees, payable balances, and payout readiness. Transfers remain disabled until release controls are approved.</p></div>
    <button class="mg-btn mg-btn-primary" type="button" data-settlement-reconcile>Reconcile paid components</button>
  </header>
  <div class="mg-bundle-settlement-notice" role="status">Accounting only — no Stripe transfer or merchant payout is executed from this page.</div>
  <section class="mg-bundle-settlement-totals" data-settlement-totals aria-busy="true"></section>
  <section class="mg-bundle-settlement-ledger">
    <div class="mg-bundle-settlement-head"><div><span>Component ledger</span><h2>Merchant earnings</h2></div><div data-settlement-status role="status" aria-live="polite"></div></div>
    <div data-settlement-list><div class="mg-bundle-settlement-empty">Loading settlement accounting…</div></div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
