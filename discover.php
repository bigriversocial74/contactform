<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Discover local gifts | Microgifter';
$page_section = 'discover';
$header_mode = 'public';
$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/profile-discovery.css',
];
$page_scripts = [
    '/assets/js/profile-discovery.js',
    '/assets/js/product-discovery.js',
];
$page_manifest = [
    'id' => 'discover',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-discovery-page',
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Feed', 'href' => '/feed.php'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'discover', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<style>
.mg-discovery-shell{background:#fff}
.mg-discovery-hero{padding:92px 0 76px;border-bottom:1px solid #dbe5f1;background:linear-gradient(180deg,#fff,#f8fafc)}
.mg-discovery-hero h1{max-width:820px;margin:16px 0 0;font-size:clamp(44px,6vw,76px);line-height:.95;letter-spacing:-.07em}
.mg-discovery-hero p{max-width:720px;margin:20px 0 0;color:#64748b;font-size:18px;line-height:1.6}
.mg-discovery-search{display:grid;grid-template-columns:minmax(220px,1.4fr) repeat(3,minmax(150px,.8fr)) auto auto;gap:12px;align-items:end;margin-top:32px}
.mg-discovery-search label{display:grid;gap:6px;color:#475569;font-size:12px;font-weight:850}
.mg-discovery-search input,.mg-discovery-search select{min-height:44px;padding:0 12px;border:1px solid #cbd5e1;border-radius:12px;background:#fff}
.mg-discovery-content{padding:72px 0 110px}
.mg-discovery-section{margin-top:44px}
.mg-discovery-section:first-child{margin-top:0}
.mg-discovery-heading{display:flex;justify-content:space-between;gap:24px;align-items:end;margin-bottom:22px}
.mg-discovery-heading h2{margin:5px 0 0;font-size:clamp(30px,4vw,48px);letter-spacing:-.05em}
.mg-discovery-heading p{max-width:560px;margin:0;color:#64748b}
.mg-discovery-card-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px}
.mg-discovery-state{margin-top:24px}
.mg-discovery-message{padding:28px;text-align:center}
@media(max-width:980px){.mg-discovery-search{grid-template-columns:1fr 1fr}.mg-discovery-heading{display:block}.mg-discovery-heading p{margin-top:10px}}
@media(max-width:620px){.mg-discovery-hero{padding:64px 0}.mg-discovery-search{grid-template-columns:1fr}.mg-discovery-content{padding:56px 0 84px}}
</style>
<section class="mg-discovery-shell" data-profile-discovery data-product-discovery>
  <header class="mg-discovery-hero">
    <div class="mg-container">
      <span class="mg-kicker">Local discovery</span>
      <h1>Discover local gifts, merchants, and places.</h1>
      <p>Search public profiles and published Microgift products by category, merchant, and location.</p>
      <form class="mg-discovery-search" data-discovery-form role="search">
        <label>Search<input type="search" name="q" maxlength="100" autocomplete="off" placeholder="Gift, merchant, profile, or keyword"></label>
        <label>Profile type<select name="type"><option value="">All profile types</option><option value="customer">Customer</option><option value="creator">Creator</option><option value="merchant">Merchant</option><option value="marketing_affiliate">Marketing affiliate</option></select></label>
        <label>Location<input type="search" name="location" maxlength="100" placeholder="City, region, or ZIP"></label>
        <label>Category<input type="search" name="category" maxlength="60" placeholder="Food, fitness, events..."></label>
        <button class="mg-btn mg-btn-primary" type="submit">Search</button>
        <button class="mg-btn mg-btn-ghost" type="reset" data-discovery-reset>Reset</button>
      </form>
    </div>
  </header>

  <div class="mg-container mg-discovery-content">
    <div class="mg-discovery-status" data-discovery-status role="status" aria-live="polite"></div>

    <section class="mg-discovery-state" data-discovery-loading aria-busy="true">
      <div class="mg-discovery-card-grid">
        <?php for ($i = 0; $i < 6; $i++): ?><article class="mg-discovery-card is-skeleton" aria-hidden="true"></article><?php endfor; ?>
      </div>
    </section>

    <section class="mg-discovery-state mg-hidden" data-discovery-error role="alert">
      <div class="mg-panel mg-discovery-message"><h2>Discovery is temporarily unavailable.</h2><p data-discovery-error-message>We could not load discovery results.</p><button class="mg-btn mg-btn-primary" type="button" data-discovery-retry>Try again</button></div>
    </section>

    <section class="mg-discovery-state mg-hidden" data-discovery-empty>
      <div class="mg-panel mg-discovery-message"><h2>No featured results are available yet.</h2><p>Published profiles and products will appear here.</p></div>
    </section>

    <section class="mg-discovery-state mg-hidden" data-discovery-no-results>
      <div class="mg-panel mg-discovery-message"><h2>No matching results.</h2><p>Try a broader search term, category, or location.</p></div>
    </section>

    <div class="mg-hidden" data-discovery-content>
      <section class="mg-discovery-section" data-recent-section>
        <div class="mg-discovery-heading"><div><span class="mg-kicker">Recently featured</span><h2>Newest profiles</h2></div><p>Recently published creators, merchants, people, and storefronts.</p></div>
        <div class="mg-discovery-card-grid" data-recent-grid></div>
      </section>

      <section class="mg-discovery-section mg-hidden" data-featured-section>
        <div class="mg-discovery-heading"><div><span class="mg-kicker">Featured</span><h2>Featured profiles</h2></div></div>
        <div class="mg-discovery-card-grid" data-featured-grid></div>
      </section>

      <section class="mg-discovery-section mg-hidden" data-storefront-section>
        <div class="mg-discovery-heading"><div><span class="mg-kicker">Storefronts</span><h2>Published merchants</h2></div></div>
        <div class="mg-discovery-card-grid" data-storefront-grid></div>
      </section>

      <section class="mg-discovery-section" aria-labelledby="product-results-title">
        <div class="mg-discovery-heading"><div><span class="mg-kicker">Published Microgifts</span><h2 id="product-results-title">Products by merchant and location</h2></div><p>Product-level results tied to canonical merchant locations.</p></div>
        <div class="mg-discovery-card-grid" data-product-results-grid></div>
      </section>

      <section class="mg-discovery-section mg-hidden" aria-labelledby="discovery-results-title">
        <div class="mg-discovery-heading"><div><span class="mg-kicker">Organic results</span><h2 id="discovery-results-title">Profiles</h2></div><p data-results-summary></p></div>
        <div class="mg-discovery-card-grid" data-results-grid></div>
        <div class="mg-discovery-more mg-hidden" data-discovery-pagination><button class="mg-btn mg-btn-soft" type="button" data-discovery-more>Load more profiles</button></div>
      </section>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
