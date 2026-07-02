<?php
 declare(strict_types=1);
?>
<section class="mg-merchant-overview-dashboard" data-merchant-overview-dashboard>
  <section class="mg-merchant-hero">
    <div class="mg-merchant-hero-main">
      <span class="mg-eyebrow">Merchant workspace</span>
      <h1>Welcome back, <span data-merchant-name>tom</span>!</h1>
      <p>Here’s what’s happening with your merchant account today.</p>
    </div>
    <div class="mg-merchant-hero-cards"><article><span>Account status</span><strong data-merchant-eligibility>Checking</strong></article><article><span>Plan and Tier</span><strong data-merchant-plan-name>Current</strong></article></div>
  </section>
  <div class="mg-merchant-actions-bar"><a class="mg-btn mg-btn-primary" href="/build.php">Create Product</a><a class="mg-btn mg-btn-primary" href="/merchant-campaigns.php">Create Campaign</a><a class="mg-btn mg-btn-ghost" href="/merchant-crm.php">Open CRM</a></div>
  <nav class="mg-merchant-dashboard-tabs"><a class="is-active" href="/merchant.php">Overview</a><a href="/merchant-products.php">Commerce</a><a href="/merchant-campaigns.php">Campaigns</a><a href="/merchant-crm.php">Customers</a><a href="/merchant-claims.php">Claims</a><a href="/merchant-distribution.php">Programs</a><a href="/merchant-settings.php">Operations</a></nav>
  <section class="mg-dashboard-panel"><header class="mg-dashboard-panel-head"><div><h2>Executive overview</h2><p>Key performance indicators for your business.</p></div></header><div class="mg-merchant-kpis mg-merchant-exec-grid" data-merchant-kpis></div></section>
  <div class="mg-merchant-analytics-grid"><section class="mg-dashboard-panel is-wide"><header class="mg-dashboard-panel-head"><div><h2>Pre-Sale Revenue Trend</h2><p>Track your pre-sale revenue over time.</p></div></header><div data-merchant-revenue-chart></div></section><aside class="mg-dashboard-panel"><h2>Revenue summary</h2><div data-merchant-revenue-summary></div></aside></div>
  <div class="mg-dashboard-two-column"><section class="mg-dashboard-panel"><h2>Sales by category</h2><div data-merchant-category-chart></div></section><section class="mg-dashboard-panel"><h2>Top performing products</h2><div data-merchant-top-products></div></section></div>
  <section class="mg-dashboard-panel mg-package-limits-panel"><header class="mg-dashboard-panel-head"><div><h2>Package usage</h2><p>Track your package limits and usage.</p></div><a class="mg-btn mg-btn-soft" href="/account-subscriptions.php">Upgrade Package</a></header><div class="mg-package-limit-grid" data-package-limit-cards></div></section>
  <div class="mg-dashboard-two-column"><section class="mg-dashboard-panel"><h2>Recent transactions</h2><div data-merchant-transactions></div></section><section class="mg-dashboard-panel"><h2>Live activity feed</h2><div data-merchant-activity-feed></div></section></div>
  <div class="mg-dashboard-three-column"><section class="mg-dashboard-panel"><h2>Claims and redemptions</h2><div data-merchant-claims-summary></div></section><section class="mg-dashboard-panel"><h2>Workspace readiness</h2><div data-merchant-readiness></div></section><section class="mg-dashboard-panel"><h2>Quick actions</h2><div data-merchant-quick-actions></div></section></div>
  <div hidden><div data-merchant-step-list></div><div data-payment-readiness></div></div>
</section>
