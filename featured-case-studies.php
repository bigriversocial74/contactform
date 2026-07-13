<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Featured Case Studies | Microgifter';
$page_section = 'case-studies';
$header_mode = 'agent';
$page_styles = ['/assets/css/featured-case-studies.css?v=1.0.0'];
$page_scripts = ['/assets/js/featured-case-studies.js?v=1.0.0'];
$page_manifest = [
    'id' => 'featured-case-studies',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-featured-case-studies-page',
    'onboarding' => ['enabled' => false, 'page' => 'featured-case-studies', 'sections' => []],
];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-app-shell mg-case-index-shell" data-case-study-index>
  <aside class="mg-app-sidebar" hidden></aside>
  <section class="mg-case-index-main">
    <div class="mg-case-index-inner">
      <section class="mg-case-index-hero">
        <div class="mg-case-index-copy">
          <span class="mg-case-eyebrow">Case studies</span>
          <h1>Real businesses.<br>Real results.</h1>
          <p>Explore how merchants use Microgifter to launch campaigns, grow customer loyalty, and drive measurable sales.</p>
          <div class="mg-case-hero-stats">
            <article><span class="mg-case-stat-icon">▣</span><div><strong data-system-businesses>0</strong><small>Businesses</small></div></article>
            <article><span class="mg-case-stat-icon">⌁</span><div><strong data-system-campaigns>0</strong><small>Campaigns</small></div></article>
            <article><span class="mg-case-stat-icon">☆</span><div><strong data-system-products>0</strong><small>Products</small></div></article>
          </div>
        </div>

        <div class="mg-case-analytics-card" aria-label="Microgifter system performance">
          <header>
            <div><small>Total sales</small><strong data-system-sales>$0</strong><span data-system-sales-change>Last 30 days</span></div>
            <span class="mg-case-live-pill">Live system data</span>
          </header>
          <div class="mg-case-line-chart" data-sales-chart role="img" aria-label="System sales over the last 30 days"></div>
          <div class="mg-case-chart-grid">
            <article>
              <div class="mg-case-chart-head"><div><small>Campaign activity</small><strong data-campaign-total>0 total</strong></div></div>
              <div class="mg-case-donut-layout"><div class="mg-case-donut" data-campaign-donut></div><div class="mg-case-legend" data-campaign-legend></div></div>
            </article>
            <article>
              <div class="mg-case-chart-head"><div><small>Redemptions</small><strong data-redemption-total>0 total</strong></div><span>30 days</span></div>
              <div class="mg-case-bar-chart" data-redemption-chart role="img" aria-label="System redemptions over the last 30 days"></div>
            </article>
          </div>
          <div class="mg-case-data-note" data-analytics-note>Loading system totals…</div>
        </div>
      </section>

      <section class="mg-case-directory" aria-labelledby="case-directory-title">
        <div class="mg-case-directory-head">
          <div><span class="mg-case-eyebrow">Featured merchants</span><h2 id="case-directory-title">Stories from across the network</h2></div>
          <a href="/discover.php">Explore all merchants →</a>
        </div>
        <div class="mg-case-filters">
          <label><span class="sr-only">Search case studies</span><input type="search" data-case-search placeholder="Search case studies…" maxlength="100"></label>
          <select data-case-category aria-label="Filter by category"><option value="">All categories</option><option value="restaurant">Restaurants</option><option value="bar">Bars & nightlife</option><option value="coffee">Coffee shops</option><option value="event">Events & venues</option><option value="fitness">Fitness & wellness</option><option value="retail">Retail</option><option value="service">Local services</option></select>
          <select data-case-sort aria-label="Sort case studies"><option value="trending">Featured</option><option value="newest">Newest</option><option value="active">Most active</option></select>
        </div>
        <div class="mg-case-state" data-case-loading>Loading featured case studies…</div>
        <div class="mg-case-state mg-hidden" data-case-error><strong>Unable to load case studies.</strong><button type="button" data-case-retry>Try again</button></div>
        <div class="mg-case-grid mg-hidden" data-case-grid></div>
        <div class="mg-case-state mg-hidden" data-case-empty>No published merchant case studies match these filters.</div>
      </section>

      <section class="mg-case-cta">
        <div><span class="mg-case-stat-icon">★</span><div><strong>Have a success story to share?</strong><p>Partner with Microgifter and become a future featured case study.</p></div></div>
        <a href="/learn-more.php">Work with us</a>
      </section>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>