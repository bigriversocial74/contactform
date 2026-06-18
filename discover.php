<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Discover local vouchers and profiles | Microgifter';
$page_section = 'discover';
$header_mode = 'public';
$page_styles = ['/assets/css/profile-discovery.css'];
$page_scripts = ['/assets/js/profile-discovery.js','/assets/js/product-discovery.js'];
$page_manifest = [
    'id' => 'discover',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-discovery-page',
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Home', 'href' => '/index.php'],
            ['label' => 'Discover', 'href' => '/discover.php'],
            ['label' => 'Learn More', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'discover', 'sections' => []],
];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-discovery-shell" data-profile-discovery>
  <section class="mg-discovery-hero">
    <div class="mg-container">
      <span class="mg-kicker">Local discovery</span>
      <h1>Find local vouchers, merchants, creators, and gifting profiles.</h1>
      <p>Search published voucher products by title, category, merchant, city, region, or active merchant location.</p>
      <form class="mg-discovery-search" data-discovery-form role="search">
        <label class="mg-discovery-query">Search
          <input type="search" name="q" maxlength="100" autocomplete="off" placeholder="Voucher, merchant, profile, or location">
        </label>
        <label>Profile type
          <select name="type">
            <option value="">All profile types</option>
            <option value="customer">Customer</option>
            <option value="creator">Creator</option>
            <option value="merchant">Merchant</option>
            <option value="marketing_affiliate">Marketing affiliate</option>
          </select>
        </label>
        <label>Location
          <input type="search" name="location" maxlength="100" placeholder="City, region, ZIP, or store">
        </label>
        <label>Category
          <input type="search" name="category" maxlength="60" placeholder="Voucher category">
        </label>
        <button class="mg-btn mg-btn-primary" type="submit">Search</button>
        <button class="mg-btn mg-btn-ghost" type="reset" data-discovery-reset>Reset</button>
      </form>
    </div>
  </section>

  <div class="mg-container mg-discovery-content">
    <div class="mg-discovery-status" data-discovery-status role="status" aria-live="polite"></div>

    <section class="mg-discovery-state" data-discovery-loading aria-busy="true">
      <div class="mg-discovery-card-grid">
        <?php for ($i = 0; $i < 6; $i++): ?><article class="mg-discovery-card is-skeleton" aria-hidden="true"></article><?php endfor; ?>
      </div>
    </section>

    <section class="mg-discovery-state mg-hidden" data-discovery-error role="alert">
      <div class="mg-panel mg-discovery-message">
        <h2>Search is temporarily unavailable.</h2>
        <p data-discovery-error-message>We could not load discovery.</p>
        <button class="mg-btn mg-btn-primary" type="button" data-discovery-retry>Try again</button>
      </div>
    </section>

    <section class="mg-discovery-state mg-hidden" data-discovery-empty>
      <div class="mg-panel mg-discovery-message">
        <h2>No public vouchers or profiles are available yet.</h2>
        <p>Published merchant products and profiles will appear here when they become discoverable.</p>
      </div>
    </section>

    <section class="mg-discovery-state mg-hidden" data-discovery-no-results>
      <div class="mg-panel mg-discovery-message">
        <h2>No matching results.</h2>
        <p>Try a broader voucher, merchant, location, category, or profile search.</p>
      </div>
    </section>

    <div class="mg-hidden" data-discovery-content>
      <section class="mg-discovery-section mg-hidden" data-product-results-section>
        <div class="mg-discovery-heading"><div><span class="mg-kicker">Available nearby</span><h2>Local vouchers</h2></div><p>Published products available at active merchant locations.</p></div>
        <div class="mg-discovery-card-grid" data-product-results-grid></div>
      </section>
      <section class="mg-discovery-section mg-hidden" data-featured-section>
        <div class="mg-discovery-heading"><div><span class="mg-kicker">Curated</span><h2>Featured profiles</h2></div><p>Deterministic selections based on public storefront, product, audience, and activity signals.</p></div>
        <div class="mg-discovery-card-grid" data-featured-grid></div>
      </section>
      <section class="mg-discovery-section mg-hidden" data-storefront-section>
        <div class="mg-discovery-heading"><div><span class="mg-kicker">Published commerce</span><h2>Profiles with storefronts</h2></div></div>
        <div class="mg-discovery-card-grid" data-storefront-grid></div>
      </section>
      <section class="mg-discovery-section mg-hidden" data-recent-section>
        <div class="mg-discovery-heading"><div><span class="mg-kicker">Recent activity</span><h2>Recently active profiles</h2></div></div>
        <div class="mg-discovery-card-grid" data-recent-grid></div>
      </section>
      <section class="mg-discovery-section" aria-labelledby="discovery-results-title">
        <div class="mg-discovery-heading"><div><span class="mg-kicker">Organic results</span><h2 id="discovery-results-title">Profiles</h2></div><p data-results-summary></p></div>
        <div class="mg-discovery-card-grid" data-results-grid></div>
        <div class="mg-discovery-more mg-hidden" data-discovery-pagination>
          <button class="mg-btn mg-btn-soft" type="button" data-discovery-more>Load more profiles</button>
        </div>
      </section>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
