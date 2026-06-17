<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Committed Demand Intelligence | Microgifter';
$page_section = 'intelligence';
$header_mode = mg_current_user() ? 'agent' : 'public';
$page_styles = ['/assets/css/intelligence.css','/assets/css/committed-demand.css'];
$page_scripts = ['/assets/js/intelligence.js'];
$page_manifest = [
    'id'=>'intelligence','title'=>$page_title,'section'=>$page_section,'header_mode'=>$header_mode,
    'styles'=>$page_styles,'scripts'=>$page_scripts,'body_class'=>'mg-demand-intelligence-page',
    'public_header'=>[
        'presentation'=>false,
        'links'=>[
            ['label'=>'Home','href'=>'/index.php'],
            ['label'=>'Discover','href'=>'/discover.php'],
            ['label'=>'Feed','href'=>'/feed.php'],
            ['label'=>'Learn More','href'=>'/learn-more.php'],
        ],
    ],
    'onboarding'=>['enabled'=>false,'page'=>'intelligence','sections'=>[]],
];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-intelligence" data-intelligence-dashboard>
  <header class="mg-intelligence-header">
    <div>
      <span class="mg-intelligence-eyebrow">Stage 15 demand intelligence</span>
      <h1>See prepaid demand before it becomes a visit.</h1>
      <p>Committed demand comes from purchased Microgifts. Forecasts and agent recommendations are labeled separately and never authorize spending or merchant actions.</p>
    </div>
    <a class="mg-btn mg-btn-ghost" href="/commitments.php">Customer commitment view</a>
  </header>
  <section class="mg-intelligence-definitions" aria-label="Demand definitions">
    <article><strong>Committed</strong><span>Prepaid Microgifts that remain outstanding.</span></article>
    <article><strong>Realized</strong><span>Canonical Microgift redemptions completed.</span></article>
    <article><strong>Forecast</strong><span>Statistical projection; not prepaid demand.</span></article>
    <article><strong>Recommendation</strong><span>Operational suggestion requiring Stage 16 policy and approval.</span></article>
  </section>
  <section class="mg-intelligence-filters" aria-label="Demand filters">
    <label>Horizon<select data-demand-horizon><option value="7">Next 7 days</option><option value="30" selected>Next 30 days</option><option value="60">Next 60 days</option><option value="90">Next 90 days</option><option value="180">Next 180 days</option><option value="365">Next year</option></select></label>
    <label>Location<select data-demand-location><option value="">All locations</option></select></label>
    <label>Product<select data-demand-product><option value="">All products</option></select></label>
    <label>Privacy cohort<select data-demand-cohort><option value="5">Minimum 5 purchasers</option><option value="10">Minimum 10 purchasers</option><option value="20">Minimum 20 purchasers</option></select></label>
    <button class="mg-btn mg-btn-primary" type="button" data-demand-refresh>Refresh</button>
  </section>
  <div class="mg-intelligence-status" data-demand-status role="status" aria-live="polite"></div>
  <section class="mg-intelligence-loading" data-demand-loading aria-busy="true"><?php for ($i=0;$i<6;$i++): ?><article class="mg-intelligence-skeleton" aria-hidden="true"></article><?php endfor; ?></section>
  <section class="mg-intelligence-message mg-hidden" data-demand-error role="alert"><h2>Unable to load committed demand.</h2><p data-demand-error-message>Please try again.</p><button class="mg-btn mg-btn-primary" type="button" data-demand-retry>Try again</button></section>
  <section class="mg-intelligence-message mg-hidden" data-demand-signin><h2>Merchant access is required.</h2><p>Sign in with an account that can view demand intelligence.</p><a class="mg-btn mg-btn-primary" href="/signin.php?return=%2Fintelligence.php">Sign in</a></section>
  <div class="mg-hidden" data-demand-content>
    <section class="mg-intelligence-kpis" data-demand-kpis></section>
    <div class="mg-intelligence-grid">
      <article class="mg-intelligence-panel mg-intelligence-panel-wide"><div class="mg-panel-head"><div><span>Prepaid timeline</span><h2>Committed and realized value</h2></div><p data-demand-window></p></div><div class="mg-demand-chart" data-demand-chart role="img" aria-label="Committed demand trend"></div><p class="mg-privacy-note" data-demand-privacy></p></article>
      <article class="mg-intelligence-panel"><div class="mg-panel-head"><div><span>Lifecycle</span><h2>Where gifts are now</h2></div></div><div class="mg-lifecycle-grid" data-demand-lifecycle></div></article>
      <article class="mg-intelligence-panel mg-intelligence-panel-wide"><div class="mg-panel-head"><div><span>Products</span><h2>Prepaid product opportunity</h2></div></div><div class="mg-table-wrap"><table><thead><tr><th>Product</th><th>Commitments</th><th>Committed</th><th>Realized</th><th>Claimed</th><th>Redeemed</th></tr></thead><tbody data-demand-products></tbody></table></div></article>
      <article class="mg-intelligence-panel"><div class="mg-panel-head"><div><span>Locations</span><h2>Upcoming value</h2></div></div><div class="mg-location-list" data-demand-locations></div></article>
      <article class="mg-intelligence-panel mg-intelligence-panel-wide"><div class="mg-panel-head"><div><span>Signals</span><h2>Recommendations and approvals</h2></div></div><div class="mg-signal-list" data-demand-signals></div></article>
      <article class="mg-intelligence-panel"><div class="mg-panel-head"><div><span>Snapshot</span><h2>Forecast context</h2></div></div><div data-demand-snapshot></div><button class="mg-btn mg-btn-soft" type="button" data-run-forecast>Run canonical 30-day forecast</button></article>
      <article class="mg-intelligence-panel"><div class="mg-panel-head"><div><span>Exports</span><h2>Privacy-safe export</h2></div></div><form data-export-form><label>Format<select name="format"><option value="csv">CSV</option><option value="json">JSON</option></select></label><label>Privacy<select name="privacy_mode"><option value="aggregate">Aggregate</option><option value="k_anonymous">K-anonymous</option></select></label><button class="mg-btn mg-btn-primary" type="submit">Queue export</button></form><div class="mg-export-status" data-export-status role="status"></div></article>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
