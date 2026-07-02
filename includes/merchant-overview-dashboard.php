<?php
 declare(strict_types=1);
?>
<section class="mg-merchant-overview-dashboard" data-merchant-overview-dashboard>
  <section class="mg-merchant-hero">
    <div class="mg-merchant-hero-main">
      <span class="mg-eyebrow">Merchant workspace</span>
      <h1>Welcome back, <span data-merchant-name>tom</span>!</h1>
      <p>Here’s what’s happening with your merchant account today.</p>
      <div class="mg-merchant-hero-meta"><span data-merchant-company>Merchant workspace</span><span data-merchant-location>Local operating center</span><span data-merchant-local-time>Local time syncing</span></div>
    </div>
    <div class="mg-merchant-hero-cards"><article><span>Account status</span><strong data-merchant-eligibility>Checking</strong></article><article><span>Plan and Tier</span><strong data-merchant-plan-name>Current</strong><a href="/account-subscriptions.php">Manage Plan</a></article></div>
  </section>

  <div class="mg-merchant-actions-bar"><div class="mg-merchant-primary-actions"><a class="mg-btn mg-btn-primary" href="/build.php">Create Product</a><a class="mg-btn mg-btn-primary" href="/merchant-campaigns.php#campaign-create">Create Campaign</a><a class="mg-btn mg-btn-ghost" href="/merchant-crm.php">Open CRM</a><a class="mg-btn mg-btn-ghost" href="/merchant-reward-templates.php">Send Rewards</a><a class="mg-btn mg-btn-ghost" href="/merchant-distribution.php">More Actions</a></div></div>

  <nav class="mg-merchant-dashboard-tabs"><a class="is-active" href="/merchant.php">Overview</a><a href="/merchant-products.php">Commerce</a><a href="/merchant-campaigns.php">Campaigns</a><a href="/merchant-crm.php">Customers</a><a href="/merchant-claims.php">Claims</a><a href="/merchant-distribution.php">Programs</a><a href="/merchant-settings.php">Operations</a></nav>

  <section class="mg-dashboard-panel mg-dashboard-executive"><header class="mg-dashboard-panel-head"><div><h2>Executive overview</h2><p>Key performance indicators for your business.</p></div><div class="mg-dashboard-date-tools"><span data-merchant-report-window>Last 30 days</span><span>Compare: Previous 30 days</span></div></header><div class="mg-merchant-kpis mg-merchant-exec-grid" data-merchant-kpis></div></section>

  <div class="mg-merchant-analytics-grid"><section class="mg-dashboard-panel mg-dashboard-chart-card is-wide"><header class="mg-dashboard-panel-head"><div><h2>Pre-Sale Revenue Trend</h2><p>Track your pre-sale revenue over time.</p></div><div class="mg-dashboard-chart-toggle"><span>Daily</span><span class="is-active">Weekly</span><span>Monthly</span></div></header><div class="mg-dashboard-panel-body" data-merchant-revenue-chart></div></section><aside class="mg-dashboard-panel mg-dashboard-summary-card"><header><h2>Revenue summary</h2></header><div data-merchant-revenue-summary></div><a class="mg-dashboard-card-link" href="/merchant-intelligence.php">View full report</a></aside></div>

  <div class="mg-dashboard-two-column"><section class="mg-dashboard-panel"><header class="mg-dashboard-panel-head"><div><h2>Sales by category</h2><p>By product category</p></div></header><div class="mg-dashboard-panel-body" data-merchant-category-chart></div></section><section class="mg-dashboard-panel"><header class="mg-dashboard-panel-head"><div><h2>Top performing products</h2><p>By revenue and claim activity</p></div></header><div class="mg-dashboard-panel-body" data-merchant-top-products></div></section></div>

  <section class="mg-dashboard-panel mg-package-limits-panel"><header class="mg-dashboard-panel-head"><div><h2>Package usage</h2><p>Track your package limits and usage.</p></div><a class="mg-btn mg-btn-soft" href="/account-subscriptions.php">Upgrade Package</a></header><div class="mg-dashboard-panel-body"><div class="mg-package-limit-grid" data-package-limit-cards></div></div></section>

  <div class="mg-dashboard-two-column"><section class="mg-dashboard-panel"><header class="mg-dashboard-panel-head"><div><h2>Recent transactions</h2><p>Latest orders, microgifts, and rewards activity.</p></div></header><div class="mg-dashboard-panel-body" data-merchant-transactions></div></section><section class="mg-dashboard-panel"><header class="mg-dashboard-panel-head"><div><h2>Live activity feed</h2><p>Real-time activity across your workspace.</p></div></header><div class="mg-dashboard-panel-body" data-merchant-activity-feed></div></section></div>

  <div class="mg-dashboard-two-column"><section class="mg-dashboard-panel"><header class="mg-dashboard-panel-head"><div><h2>Campaign performance</h2><p>Your active campaigns at a glance.</p></div></header><div class="mg-dashboard-panel-body" data-merchant-campaign-performance></div></section><section class="mg-dashboard-panel"><header class="mg-dashboard-panel-head"><div><h2>Customer insights</h2><p>Understand your customer base.</p></div></header><div class="mg-dashboard-panel-body" data-merchant-customer-insights></div></section></div>

  <div class="mg-dashboard-three-column"><section class="mg-dashboard-panel"><header class="mg-dashboard-panel-head"><div><h2>Claims and redemptions</h2><p>Performance of claims and redemptions.</p></div></header><div class="mg-dashboard-panel-body" data-merchant-claims-summary></div></section><section class="mg-dashboard-panel"><header class="mg-dashboard-panel-head"><div><h2>Customer engagement</h2><p>How customers interact with your brand.</p></div></header><div class="mg-dashboard-panel-body" data-merchant-engagement-summary></div></section><section class="mg-dashboard-panel"><header class="mg-dashboard-panel-head"><div><h2>Workspace readiness</h2><p>Complete setup steps to maximize your success.</p></div></header><div class="mg-dashboard-panel-body" data-merchant-readiness></div></section></div>

  <div class="mg-dashboard-three-column"><section class="mg-dashboard-panel"><header class="mg-dashboard-panel-head"><div><h2>Operations status</h2><p>Health of your key systems.</p></div></header><div class="mg-dashboard-panel-body" data-merchant-operations-status></div></section><section class="mg-dashboard-panel"><header class="mg-dashboard-panel-head"><div><h2>Quick actions</h2><p>Common tasks to run your business.</p></div></header><div class="mg-dashboard-panel-body" data-merchant-quick-actions></div></section><section class="mg-dashboard-panel"><header class="mg-dashboard-panel-head"><div><h2>Need help?</h2><p>Resources and support.</p></div></header><div class="mg-dashboard-panel-body" data-merchant-help-center></div></section></div>

  <div hidden><div data-merchant-step-list></div><div data-payment-readiness></div></div>
</section>
