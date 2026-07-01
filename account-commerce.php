<?php
declare(strict_types=1);
require_once __DIR__.'/includes/app.php';
$user=mg_current_user();
if(!$user){header('Location: /signin.php?return=%2Faccount-commerce.php');exit;}
$page_title='Commerce Account | Microgifter';
$page_section='agent';
$header_mode='agent';
$agent_tab='commerce';
$can_merchant_nav=true;
$can_create_microgift=true;
$page_styles=['/assets/css/agent-workspace-layout.css','/assets/css/checkout.css','/assets/css/account-commerce.css','/assets/css/account-commerce-fixes.css'];
$page_scripts=['/assets/js/account-commerce.js','/assets/js/account-orders.js'];
require __DIR__.'/includes/header.php';
?>
<section class="mg-app-shell mg-account-commerce-shell" data-account-commerce-overview>
  <?php require __DIR__.'/includes/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-account-shell">
    <header class="mg-account-hero">
      <div>
        <span class="mg-account-eyebrow">Customer account</span>
        <h1>Commerce center</h1>
        <p>Track purchases, items, gifts, claims, redemptions, orders, and receipts.</p>
      </div>
      <a class="mg-btn mg-btn-primary" href="/cart.php">Open cart</a>
    </header>
    <nav class="mg-account-nav" aria-label="Commerce account navigation"><a href="#overview">Overview</a><a href="#orders">Orders</a><a href="#items">Items</a><a href="#gifts">Gifts</a><a href="#claims">Claims</a></nav>
    <section id="overview"><div class="mg-account-stats" data-account-summary>Loading summary…</div><div class="mg-account-panel"><div class="mg-account-list" data-account-recent>Loading recent activity…</div></div></section>
    <section id="orders" class="mg-account-panel" data-account-orders><div class="mg-account-heading"><div><span class="mg-account-eyebrow">Commerce history</span><h2>Orders and receipts</h2></div></div><div data-account-orders-list>Loading orders…</div></section>
    <section id="items" class="mg-account-panel" data-account-items><div class="mg-account-heading"><div><span class="mg-account-eyebrow">Permanent items</span><h2>PPPM items</h2></div></div><div class="mg-account-tabs"><button class="is-active" data-items-scope="purchased">Purchased</button><button data-items-scope="owned">Owned</button><button data-items-scope="sent">Sent</button><button data-items-scope="received">Received</button><button data-items-scope="redeemed">Redeemed</button></div><div class="mg-account-list" data-account-items-list>Loading items…</div></section>
    <section id="gifts" class="mg-account-panel" data-account-gifts><div class="mg-account-heading"><div><span class="mg-account-eyebrow">Gift activity</span><h2>Sent and received gifts</h2></div></div><div class="mg-account-tabs"><button class="is-active" data-gifts-scope="received">Received</button><button data-gifts-scope="sent">Sent</button></div><div class="mg-account-list" data-account-gifts-list>Loading gifts…</div></section>
    <section id="claims" class="mg-account-panel" data-account-claims><div class="mg-account-filter-row"><div><span class="mg-account-eyebrow">Claims and redemption</span><h2>Claim history</h2></div><select data-claims-status><option value="all">All statuses</option><option value="pending">Pending</option><option value="verified">Verified</option><option value="redeemed">Redeemed</option><option value="locked">Locked</option><option value="expired">Expired</option></select></div><div class="mg-account-list" data-account-claims-list>Loading claims…</div></section>
  </main>
</section>
<?php require __DIR__.'/includes/footer.php'; ?>
