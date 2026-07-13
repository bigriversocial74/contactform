<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
$slugIsValid = $slug !== ''
    && strlen($slug) <= 120
    && preg_match('/^[a-z0-9](?:[a-z0-9-]{0,118}[a-z0-9])?$/', $slug) === 1;

$page_title = 'Case Study | Microgifter';
$page_section = 'case-studies';
$header_mode = 'public';
$page_styles = ['/assets/css/case-study.css?v=1.0.1'];
$page_scripts = [
    '/assets/js/public-case-studies-nav.js?v=1.0.0',
    '/assets/js/case-study.js?v=1.0.0',
];
$page_manifest = [
    'id' => 'case-study-detail',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-case-study-page mg-public-case-studies-page',
    'public_header' => [
        'presentation' => false,
        'search' => false,
        'links' => [
            ['label' => 'Explore', 'href' => '/discover.php'],
            ['label' => 'Case Studies', 'href' => '/featured-case-studies.php'],
            ['label' => 'Blog', 'href' => '/blog.php'],
            ['label' => 'Pricing', 'href' => '/pricing.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'case-study', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<main class="mg-case-study" data-case-study data-profile-slug="<?= mg_e($slugIsValid ? $slug : '') ?>" aria-busy="true">
  <div class="mg-case-study__shell">
    <nav class="mg-case-study__crumbs" aria-label="Breadcrumb">
      <a href="/featured-case-studies.php">Case Studies</a><span aria-hidden="true">/</span><span data-cs-crumb>Merchant Story</span>
    </nav>

    <section class="mg-cs-state" data-cs-loading>
      <div class="mg-cs-skeleton mg-cs-skeleton--hero"></div>
      <div class="mg-cs-skeleton-grid"><span></span><span></span><span></span><span></span></div>
    </section>

    <section class="mg-cs-state mg-hidden" data-cs-error role="alert">
      <div class="mg-cs-empty">
        <span>Case study unavailable</span>
        <h1 data-cs-error-title>We could not load this case study.</h1>
        <p data-cs-error-message>Check the merchant address and try again.</p>
        <button type="button" data-cs-retry>Try again</button>
      </div>
    </section>

    <div class="mg-hidden" data-cs-content>
      <article class="mg-cs-hero">
        <div class="mg-cs-cover" data-cs-cover></div>
        <div class="mg-cs-hero__body">
          <div class="mg-cs-identity">
            <div class="mg-cs-avatar" data-cs-avatar-wrap><img class="mg-hidden" data-cs-avatar alt=""><span data-cs-avatar-fallback>M</span></div>
            <div>
              <div class="mg-cs-kicker">Merchant case study</div>
              <h1 data-cs-name>Microgifter Merchant</h1>
              <div class="mg-cs-meta" data-cs-meta></div>
              <p class="mg-cs-headline" data-cs-headline></p>
              <p class="mg-cs-brief" data-cs-brief></p>
            </div>
          </div>
          <aside class="mg-cs-review" aria-label="Featured review">
            <div class="mg-cs-review__label">Featured Review</div>
            <blockquote data-cs-review>“Microgifter gave us a simpler way to launch campaigns, reward customers, and see what is working.”</blockquote>
            <div class="mg-cs-review__footer"><div><strong data-cs-review-name>Merchant Owner</strong><span data-cs-review-role>Microgifter customer</span></div><div class="mg-cs-stars" aria-label="5 out of 5 stars">★★★★★</div></div>
          </aside>
        </div>
      </article>

      <section class="mg-cs-section" aria-labelledby="performance-title">
        <div class="mg-cs-section__head"><div><span>Live business snapshot</span><h2 id="performance-title">Performance Snapshot</h2></div><span class="mg-cs-live"><i></i> Connected data</span></div>
        <div class="mg-cs-kpis">
          <article><span class="mg-cs-kpi-icon">▣</span><div><small>Total Products</small><strong data-cs-products>0</strong><em>Published products</em></div></article>
          <article><span class="mg-cs-kpi-icon">◉</span><div><small>Active Campaigns</small><strong data-cs-campaigns>0</strong><em>Current campaigns</em></div></article>
          <article><span class="mg-cs-kpi-icon">$</span><div><small>Total Sales</small><strong data-cs-sales>$0</strong><em data-cs-sales-note>Reporting period</em></div></article>
          <article><span class="mg-cs-kpi-icon">↗</span><div><small>Redemption Rate</small><strong data-cs-redemption>0%</strong><em>Claims converted</em></div></article>
          <article><span class="mg-cs-kpi-icon">+</span><div><small>Customer Growth</small><strong data-cs-growth>0</strong><em>New customers</em></div></article>
        </div>
      </section>

      <section class="mg-cs-dashboard" aria-label="Case study performance charts">
        <article class="mg-cs-card mg-cs-card--campaigns">
          <div class="mg-cs-card__head"><div><span>Campaign portfolio</span><h2>Top Campaigns</h2></div><a href="/campaign.php">View all</a></div>
          <div class="mg-cs-campaign-list" data-cs-campaign-list></div>
          <div class="mg-cs-empty-inline mg-hidden" data-cs-campaign-empty>No active campaigns are published yet.</div>
        </article>

        <article class="mg-cs-card mg-cs-card--chart">
          <div class="mg-cs-card__head"><div><span>Real-time reporting</span><h2>Sales Activity</h2></div><span class="mg-cs-live"><i></i> Live</span></div>
          <div class="mg-cs-chart-total"><strong data-cs-chart-sales>$0</strong><span>Sales tracked</span></div>
          <div class="mg-cs-chart" data-cs-sales-chart aria-label="Sales activity chart"></div>
          <div class="mg-cs-chart-legend"><span><i class="is-line"></i>Sales</span><span><i class="is-bar"></i>Orders</span></div>
        </article>

        <article class="mg-cs-card mg-cs-card--chart">
          <div class="mg-cs-card__head"><div><span>Audience response</span><h2>Campaign Activity</h2></div><span>Last 7 periods</span></div>
          <div class="mg-cs-chart-metrics"><div><strong data-cs-claims>0</strong><span>Total claims</span></div><div><strong data-cs-redemptions>0</strong><span>Redemptions</span></div></div>
          <div class="mg-cs-chart" data-cs-activity-chart aria-label="Campaign claims and redemptions chart"></div>
          <div class="mg-cs-chart-legend"><span><i class="is-claims"></i>Claims</span><span><i class="is-redemptions"></i>Redemptions</span></div>
        </article>
      </section>

      <section class="mg-cs-lower-grid">
        <article class="mg-cs-card">
          <div class="mg-cs-card__head"><div><span>The story</span><h2>Case Study Overview</h2></div></div>
          <div class="mg-cs-story-grid">
            <section><b>01</b><h3>Challenge</h3><p data-cs-challenge>The merchant needed a clearer way to promote products, increase repeat visits, and understand campaign performance.</p></section>
            <section><b>02</b><h3>Solution</h3><p data-cs-solution>Microgifter connected product promotion, gifting, rewards, claims, and customer engagement in one campaign workflow.</p></section>
            <section><b>03</b><h3>Outcomes</h3><ul data-cs-outcomes><li>More measurable customer engagement</li><li>Clearer campaign performance</li><li>Stronger repeat-purchase opportunities</li></ul></section>
          </div>
        </article>

        <article class="mg-cs-card">
          <div class="mg-cs-card__head"><div><span>Latest signals</span><h2>Recent Activity</h2></div></div>
          <div class="mg-cs-activity-list" data-cs-activity-list></div>
        </article>
      </section>

      <section class="mg-cs-products mg-cs-card">
        <div class="mg-cs-card__head"><div><span>Products powering results</span><h2>Featured Products</h2></div><a href="/discover.php">Explore products</a></div>
        <div class="mg-cs-product-grid" data-cs-product-grid></div>
        <div class="mg-cs-empty-inline mg-hidden" data-cs-product-empty>No public products are available yet.</div>
      </section>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>