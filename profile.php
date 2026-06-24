<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$slug = strtolower(trim((string)($_GET['slug'] ?? '')));
$slugIsValid = $slug !== '' && strlen($slug) <= 120 && preg_match('/^[a-z0-9](?:[a-z0-9-]{0,118}[a-z0-9])?$/', $slug) === 1;
$preview = (string)($_GET['preview'] ?? '') === '1';
if ($preview) header('X-Robots-Tag: noindex, nofollow');

$page_title = 'Merchant profile | Microgifter';
$page_section = 'profile';
$header_mode = 'public';
$page_styles = [
    '/assets/css/public-profile.css',
    '/assets/css/public-profile-storefront.css',
    '/assets/css/public-profile-engagement.css',
    '/assets/css/public-profile-investment.css',
];
$page_scripts = [
    '/assets/js/public-profile-runtime.js',
    '/assets/js/public-profile.js',
    '/assets/js/public-profile-storefront.js',
    '/assets/js/public-profile-engagement.js',
    '/assets/js/public-profile-investment.js',
];
$page_manifest = [
    'id' => 'public-profile',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-public-profile-page mg-investment-profile-page',
    'public_header' => [
        'presentation' => false,
        'search' => true,
        'links' => [
            ['label' => 'Explore', 'href' => '/discover.php'],
            ['label' => 'Drops', 'href' => '/discover.php'],
            ['label' => 'Portfolio', 'href' => '/account.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'profile', 'sections' => []],
];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-public-profile-shell mg-invest-profile-shell" data-public-profile-page data-profile-slug="<?= mg_e($slugIsValid ? $slug : '') ?>" data-profile-preview="<?= $preview ? '1' : '0' ?>" aria-busy="true">
  <div class="mg-profile-loading" data-profile-loading>
    <div class="mg-invest-wrap"><div class="mg-invest-skeleton-dashboard"><span></span><span></span><span></span></div></div>
  </div>

  <div class="mg-profile-error mg-hidden" data-profile-error role="alert">
    <div class="mg-invest-wrap"><div class="mg-invest-panel mg-profile-empty"><span class="mg-invest-eyebrow">Profile unavailable</span><h1 data-profile-error-title>Profile not found</h1><p data-profile-error-message>This profile may be private, still in draft, suspended, blocked, or using a different address.</p><button class="mg-invest-btn mg-invest-btn-gold" type="button" data-profile-retry>Try again</button></div></div>
  </div>

  <div class="mg-profile-content mg-hidden" data-profile-content>
    <div class="mg-profile-preview-banner mg-hidden" data-profile-preview-banner role="status"><div class="mg-invest-wrap"><strong>Owner preview</strong><span>This profile is visible because you are signed in as its owner.</span><a href="/account.php">Edit profile</a></div></div>

    <div class="mg-invest-wrap mg-invest-browser-frame">
      <section class="mg-invest-cover-stage" aria-label="Profile cover">
        <div class="mg-invest-cover" data-profile-cover aria-hidden="true"></div>
        <div class="mg-invest-cover-glow" aria-hidden="true"></div>
      </section>

      <section class="mg-invest-profile-top" aria-label="Merchant investment summary">
        <div class="mg-invest-profile-left">
          <div class="mg-invest-avatar" data-profile-avatar-wrap><img class="mg-hidden" data-profile-avatar alt=""><span data-profile-avatar-fallback>C</span><b aria-hidden="true">✓</b></div>
          <div class="mg-invest-identity">
            <div class="mg-invest-title-row"><h1 data-profile-name data-invest-field="display_name">Curate Hospitality</h1><span class="mg-invest-verified">Verified Merchant</span></div>
            <p class="mg-invest-handle" data-profile-headline data-invest-field="tagline">@curatehospitality</p>
            <p class="mg-profile-biography mg-invest-bio" data-profile-biography>We partner with top local venues to create tokenized experiences with real-world value and future demand.</p>
            <div class="mg-invest-meta-row"><div class="mg-profile-meta" data-profile-meta></div><a class="mg-invest-meta-link mg-hidden" data-profile-website target="_blank" rel="noopener noreferrer">Website</a><div class="mg-public-link-list mg-hidden" data-profile-links-section><div data-profile-links></div></div></div>
            <div class="mg-profile-status-row" data-profile-status-row></div>
          </div>
        </div>
        <div class="mg-invest-action-cluster"><button class="mg-invest-btn mg-invest-btn-gold mg-hidden" type="button" data-profile-follow>+ Follow</button><button class="mg-invest-btn mg-invest-btn-dark" type="button">Message</button><button class="mg-invest-btn mg-invest-btn-dark" type="button">Share</button><button class="mg-invest-btn mg-invest-btn-dark" type="button">Save</button><a class="mg-invest-btn mg-invest-btn-dark mg-hidden" data-profile-edit href="/account.php">Edit</a></div>
        <div class="mg-profile-action-status" data-profile-follow-status role="status" aria-live="polite"></div>
      </section>

      <section class="mg-invest-metrics-band" aria-label="Social and market performance">
        <div class="mg-invest-metric-group"><span>Social</span><dl><div><dt>Followers</dt><dd data-profile-followers>12.4K</dd></div><div><dt>Supporters</dt><dd data-profile-supporters>1.2K</dd></div><div><dt>Posts</dt><dd data-invest-posts>156</dd></div><div><dt>Reach</dt><dd data-invest-reach>3.8M</dd></div><div><dt>Engagement</dt><dd data-invest-engagement>4.9%</dd></div></dl></div>
        <div class="mg-invest-metric-group"><span>Market and Performance</span><dl><div><dt>Active Drops</dt><dd data-profile-products data-invest-active-drops>24</dd></div><div><dt>Market Value</dt><dd data-invest-field="demand_value">$1.28M</dd></div><div><dt>Floor Price</dt><dd data-invest-field="floor_price">0.84 MGFTR</dd></div><div><dt>Volume 30D</dt><dd data-invest-field="volume_30d">$342K</dd></div><div><dt>Redemption Rate</dt><dd data-invest-field="redemption_rate">92%</dd></div></dl></div>
      </section>

      <section class="mg-invest-dashboard-grid" aria-label="Profile market charts">
        <article class="mg-invest-chart-card"><div class="mg-invest-card-head"><span>Market Value</span><strong><span data-invest-field="demand_value">$1.28M</span> <em>▲ 18.6% 30D</em></strong></div><svg viewBox="0 0 330 150" role="img" aria-label="Market value chart"><path class="mg-chart-fill" d="M10 118 L35 88 L62 96 L86 78 L110 72 L136 101 L162 55 L190 68 L214 42 L242 38 L270 26 L305 21 L305 142 L10 142 Z"></path><path class="mg-chart-line" d="M10 118 L35 88 L62 96 L86 78 L110 72 L136 101 L162 55 L190 68 L214 42 L242 38 L270 26 L305 21"></path></svg><div class="mg-chart-labels"><span>Apr 20</span><span>Apr 27</span><span>May 4</span><span>May 18</span></div></article>
        <article class="mg-invest-demand-card"><div class="mg-invest-card-head"><span>Demand Score</span><strong><span data-invest-field="demand_score">87</span> <em>High Demand</em></strong></div><div class="mg-demand-meter"><svg viewBox="0 0 120 120"><circle cx="60" cy="60" r="45"></circle><circle cx="60" cy="60" r="45" class="meter"></circle><path d="M32 68 C42 38 46 88 55 58 C63 31 70 84 79 52 C86 30 94 70 99 58"></path></svg></div><dl class="mg-demand-factors"><div><dt>Scarcity</dt><dd>92</dd></div><div><dt>Redemptions</dt><dd>88</dd></div><div><dt>Velocity</dt><dd>81</dd></div><div><dt>Social Buzz</dt><dd>85</dd></div></dl></article>
      </section>

      <nav class="mg-invest-tabbar" aria-label="Profile sections"><a href="#overview">Overview</a><a href="#products">Products</a><a href="#posts">Posts</a><a href="#campaigns">Campaigns</a><a href="#analytics">Analytics</a></nav>

      <div class="mg-invest-layout-grid">
        <div class="mg-invest-main-column">
          <section class="mg-invest-panel" id="overview"><div class="mg-invest-section-head"><h2>Valuation Formula</h2><span>Predictive Revenue Signal</span></div><div class="mg-valuation-formula"><strong>PRS = Presold Demand + Wallet Intent + Redemption Velocity + Social Proof</strong><p data-invest-overview>This merchant profile acts like a market page for future local demand. It turns products, campaigns, followers, and redemptions into visible demand signals.</p></div></section>
          <section class="mg-invest-panel mg-hidden" data-profile-storefront-section id="storefront"><div class="mg-invest-section-head"><h2 data-storefront-name>Storefront</h2><a class="mg-invest-link mg-hidden" data-storefront-link>Visit storefront</a></div><p data-storefront-headline data-storefront-tagline></p><img class="mg-hidden" data-storefront-logo alt=""><span data-storefront-logo-fallback>S</span><span data-storefront-status>Live</span><div class="mg-profile-storefront-cover" data-storefront-cover></div><div class="mg-profile-storefront-description" data-storefront-description></div></section>
          <section class="mg-invest-panel" id="products"><div class="mg-invest-section-head"><h2>Featured Experiences</h2><a class="mg-invest-link" href="/discover.php">View all products</a></div><div class="mg-profile-product-grid mg-invest-product-grid" data-profile-products-grid></div><div class="mg-profile-empty-inline mg-hidden" data-profile-products-empty>No published products yet.</div><div class="mg-profile-load-more mg-hidden" data-product-pagination><button class="mg-invest-btn mg-invest-btn-dark" type="button" data-products-load-more>Load more products</button></div></section>
          <section class="mg-invest-panel mg-hidden" data-profile-posts-section id="posts"><div class="mg-invest-section-head"><h2>Latest Posts</h2><a class="mg-invest-link" href="#posts">View all posts</a></div><div class="mg-profile-post-list mg-invest-post-grid" data-profile-posts-list></div><div class="mg-profile-empty-inline mg-hidden" data-profile-posts-empty>No visible posts.</div><div class="mg-profile-load-more mg-hidden" data-post-pagination><button class="mg-invest-btn mg-invest-btn-dark" type="button" data-posts-load-more>Load more posts</button></div></section>
          <section class="mg-invest-panel" id="campaigns"><div class="mg-invest-section-head"><h2>Active Campaigns</h2><a class="mg-invest-link" href="/campaign.php">View all campaigns</a></div><div class="mg-invest-campaign-list" data-invest-campaigns-list><article><b>Mother's Day Brunch Drop</b><span>LIVE</span><p>Celebrate with a special tokenized brunch.</p><progress value="72" max="100"></progress></article><article><b>Summer Experiences Series</b><span>UPCOMING</span><p>A curated series of premium drops.</p><progress value="38" max="100"></progress></article><article><b>Loyalty Holders Airdrop</b><span>LIVE</span><p>Exclusive airdrop for top holders.</p><progress value="58" max="100"></progress></article></div></section>
          <section class="mg-invest-panel mg-hidden" data-profile-support-section id="support"><h2>Support this profile</h2><div class="mg-profile-plan-grid" data-profile-plans-grid></div><div class="mg-profile-empty-inline mg-hidden" data-profile-plans-empty>No active memberships.</div><div class="mg-profile-load-more mg-hidden" data-plan-pagination><button type="button" data-plans-load-more>Load more memberships</button></div><aside class="mg-profile-tip-card mg-hidden" data-profile-tip-card></aside></section>
          <section class="mg-invest-panel" id="analytics"><div class="mg-invest-section-head"><h2>Analytics</h2><span>Demand movement</span></div><div class="mg-invest-analytics-grid"><div><strong>+18.6%</strong><span>Market Growth</span></div><div><strong>92%</strong><span>Redemption Rate</span></div><div><strong>$342K</strong><span>30D Volume</span></div><div><strong>0.84</strong><span>Floor MGFTR</span></div></div></section>
          <div data-profile-sections></div>
        </div>
        <aside class="mg-invest-sidebar"><article class="mg-invest-card"><div class="mg-invest-card-head"><span>Portfolio Snapshot</span><a href="/account.php">View portfolio</a></div><strong>3.46 MGFTR</strong><small>$2,919.32 USD</small><svg viewBox="0 0 260 70"><path d="M4 50 L30 49 L56 42 L82 45 L108 34 L134 40 L160 37 L186 46 L212 48 L236 38 L258 40" class="mg-chart-line"></path></svg></article><article class="mg-invest-card"><div class="mg-invest-card-head"><span>Recent Activity</span><a href="#posts">View all</a></div><div class="mg-invest-activity-list" data-invest-activity-list><p>Chef's Table Access <b>+0.48 MGFTR</b></p><p>Weekend Brunch for Two <b>-0.65 MGFTR</b></p><p>Coffee for Two <b>+0.22 MGFTR</b></p></div></article><article class="mg-invest-card"><div class="mg-invest-card-head"><span>Top Trending Experiences</span><a href="#products">View all</a></div><ol class="mg-invest-trending" data-invest-trending-list><li>Sunset Rooftop Pass <b>▲ 24%</b></li><li>Chef's Table Access <b>▲ 18%</b></li><li>Weekend Brunch for Two <b>▲ 15%</b></li></ol></article></aside>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
