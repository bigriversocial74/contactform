<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$admin_user=mg_require_admin_page_any(['commerce.manage','admin']);
$page_title='Bundle Settlement Reviews | Microgifter';
$page_section='admin';
$page_styles=['/assets/css/bundle-settlement-review-v8.css'];
$page_scripts=['/assets/js/bundle-settlement-review-v8.js'];
require dirname(__DIR__) . '/includes/header.php';
?>
<main class="mg-settlement-review" data-settlement-review-page>
  <header><div><span>Product Bundles · Phase 8</span><h1>Settlement review queue</h1><p>Approve, hold, block, or mark eligible component settlements release-ready. Stripe transfers remain disabled.</p></div><strong>Transfers disabled</strong></header>
  <section class="mg-review-toolbar"><button type="button" data-refresh>Refresh queue</button><span data-review-summary>Loading…</span></section>
  <section class="mg-review-list" data-review-list aria-live="polite" aria-busy="true"></section>
</main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>