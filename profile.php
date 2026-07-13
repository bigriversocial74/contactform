<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
$slugIsValid = $slug !== ''
    && strlen($slug) <= 120
    && preg_match('/^[a-z0-9](?:[a-z0-9-]{0,118}[a-z0-9])?$/', $slug) === 1;
$preview = (string) ($_GET['preview'] ?? '') === '1';

if ($preview) {
    header('X-Robots-Tag: noindex, nofollow');
}

$page_title = 'Merchant profile | Microgifter';
$page_section = 'profile';
$header_mode = 'public';
$page_styles = [
    '/assets/css/public-profile.css',
    '/assets/css/public-profile-storefront.css',
    '/assets/css/public-profile-engagement.css',
    '/assets/css/public-profile-investment.css',
    '/assets/css/public-profile-polish.css',
    '/assets/css/public-profile-realtime.css',
    '/assets/css/public-profile-content-first.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/public-profile-runtime.js',
    '/assets/js/public-profile.js',
    '/assets/js/public-profile-storefront.js',
    '/assets/js/public-profile-engagement.js',
    '/assets/js/public-profile-investment.js?v=2.0.0',
    '/assets/js/public-profile-posts-fix.js',
    '/assets/js/profile-story-action-dock.js',
];
$page_manifest = [
    'id' => 'public-profile',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-public-profile-page mg-investment-profile-page mg-profile-light-theme mg-profile-no-footer mg-profile-content-first',
    'public_header' => ['presentation' => false, 'search' => false],
    'onboarding' => ['enabled' => false, 'page' => 'profile', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<section
  class="mg-public-profile-shell mg-invest-profile-shell"
  data-public-profile-page
  data-profile-slug="<?= mg_e($slugIsValid ? $slug : '') ?>"
  data-profile-preview="<?= $preview ? '1' : '0' ?>"
  aria-busy="true"
>
  <div class="mg-profile-loading" data-profile-loading>
    <div class="mg-invest-shell">
      <div class="mg-profile-content-loading" aria-hidden="true">
        <span></span><span></span><span></span>
      </div>
    </div>
  </div>

  <div class="mg-profile-error mg-hidden" data-profile-error role="alert">
    <div class="mg-invest-shell">
      <div class="mg-invest-card mg-profile-empty">
        <span class="mg-invest-overline">Profile unavailable</span>
        <h1 data-profile-error-title>Profile not found</h1>
        <p data-profile-error-message>This profile may be private, still in draft, suspended, blocked, or using a different address.</p>
        <button class="mg-invest-btn is-gold" type="button" data-profile-retry>Try again</button>
      </div>
    </div>
  </div>

  <div class="mg-profile-content mg-hidden" data-profile-content>
    <div class="mg-profile-preview-banner mg-hidden" data-profile-preview-banner role="status">
      <div class="mg-invest-shell">
        <strong>Owner preview</strong>
        <span>This profile is visible because you are signed in as its owner.</span>
        <a href="/account.php">Edit profile</a>
      </div>
    </div>

    <section class="mg-invest-cover-card" aria-label="Profile cover">
      <div class="mg-invest-cover" data-profile-cover aria-hidden="true"></div>
    </section>

    <div class="mg-invest-shell mg-profile-content-shell">
      <section class="mg-profile-hero-card" aria-label="Merchant profile summary">
        <div class="mg-profile-hero-identity">
          <div class="mg-invest-avatar" data-profile-avatar-wrap>
            <img class="mg-hidden" data-profile-avatar alt="">
            <span data-profile-avatar-fallback>M</span>
            <b aria-hidden="true">✓</b>
          </div>

          <div class="mg-invest-identity-copy">
            <div class="mg-invest-title-line">
              <h1 data-profile-name data-invest-field="display_name">Microgifter Merchant</h1>
              <span class="mg-profile-merchant-badge">Merchant <b aria-hidden="true">✓</b></span>
            </div>
            <p class="mg-invest-handle mg-hidden" data-profile-headline data-invest-field="tagline"></p>
            <p class="mg-invest-bio" data-profile-biography>This profile has not added a biography yet.</p>

            <div class="mg-invest-meta-row">
              <div data-profile-meta></div>
              <a class="mg-invest-link mg-hidden" data-profile-website target="_blank" rel="noopener noreferrer">Website</a>
            </div>
            <div class="mg-profile-status-row" data-profile-status-row></div>

            <div class="mg-profile-image-tools" data-profile-image-tools>
              <a href="/account.php">Replace profile image</a>
              <button type="button" data-profile-avatar-delete>Delete profile image</button>
            </div>
          </div>
        </div>

        <aside class="mg-profile-hero-actions" aria-label="Profile actions">
          <div class="mg-invest-actions">
            <button class="mg-invest-btn is-gold mg-hidden mg-profile-follow-action" type="button" data-profile-follow>Follow</button>
            <button class="mg-invest-btn mg-profile-message-action" type="button" data-profile-message>Message</button>
            <button class="mg-invest-btn mg-profile-share-action" type="button" data-profile-share>Share</button>
            <a class="mg-invest-btn mg-hidden mg-profile-edit-action" data-profile-edit href="/account.php">Edit</a>
            <div class="mg-public-link-list mg-hidden" data-profile-links-section><div data-profile-links></div></div>
          </div>
          <div class="mg-profile-action-status" data-profile-follow-status role="status" aria-live="polite"></div>
          <div class="mg-profile-action-status" data-profile-button-status role="status" aria-live="polite"></div>
        </aside>
      </section>

      <div class="mg-profile-data-bridge" aria-hidden="true">
        <span data-profile-followers>0</span>
        <span data-profile-supporters>0</span>
        <span data-profile-products>0</span>
      </div>

      <nav class="mg-invest-tabs" aria-label="Profile content tabs">
        <button type="button" class="is-active" data-invest-tab="overview">Overview</button>
        <button type="button" data-invest-tab="products">Products</button>
        <button type="button" data-invest-tab="stories">Stories</button>
        <button type="button" data-invest-tab="posts">Posts</button>
        <button type="button" data-invest-tab="campaigns">Campaigns</button>
      </nav>

      <div class="mg-invest-content-grid">
        <div class="mg-invest-main-column">
          <section class="mg-invest-tab-panel is-active" data-invest-panel="overview">
            <div class="mg-invest-overview-grid">
              <article class="mg-invest-card mg-profile-products-card">
                <div class="mg-invest-section-head">
                  <div>
                    <span class="mg-profile-section-kicker">Shop local</span>
                    <h2>Featured Experiences</h2>
                  </div>
                  <a class="mg-invest-link" href="/discover.php">View all products <span aria-hidden="true">→</span></a>
                </div>
                <div class="mg-profile-product-grid mg-invest-product-grid" data-profile-products-grid></div>
                <div class="mg-invest-empty-state mg-hidden" data-profile-products-empty>No featured experiences are available yet.</div>
                <div class="mg-profile-load-more mg-hidden" data-product-pagination><button class="mg-invest-btn" type="button" data-products-load-more>Load more products</button></div>
              </article>

              <article class="mg-invest-card mg-profile-campaigns-card">
                <div class="mg-invest-section-head">
                  <div>
                    <span class="mg-profile-section-kicker">Ways to participate</span>
                    <h2>Active Campaigns</h2>
                  </div>
                  <a class="mg-invest-link" href="/campaign.php">View all campaigns <span aria-hidden="true">→</span></a>
                </div>
                <div class="mg-invest-campaign-list" data-invest-campaigns-list></div>
                <div class="mg-invest-empty-state mg-hidden" data-invest-campaigns-empty>No active campaigns are available yet.</div>
              </article>
            </div>
          </section>

          <section class="mg-invest-tab-panel" data-invest-panel="products" hidden>
            <article class="mg-invest-card">
              <div class="mg-invest-section-head">
                <div><span class="mg-profile-section-kicker">Storefront</span><h2>All Experiences</h2></div>
                <a class="mg-invest-link" href="/discover.php">Open marketplace <span aria-hidden="true">→</span></a>
              </div>
              <div class="mg-profile-product-grid" data-profile-products-grid-clone></div>
            </article>
          </section>

          <section class="mg-invest-tab-panel" data-invest-panel="stories" hidden>
            <article class="mg-invest-card mg-profile-simple-panel">
              <div class="mg-invest-section-head"><div><span class="mg-profile-section-kicker">Highlights</span><h2>Stories</h2></div></div>
              <div class="mg-invest-empty-state">No stories are available yet.</div>
            </article>
          </section>

          <section class="mg-invest-tab-panel" data-invest-panel="posts" data-profile-posts-section hidden>
            <article class="mg-invest-card">
              <div class="mg-invest-section-head"><div><span class="mg-profile-section-kicker">Updates</span><h2>Latest Posts</h2></div></div>
              <div class="mg-profile-post-list mg-invest-post-grid" data-profile-posts-list></div>
              <div class="mg-invest-empty-state mg-hidden" data-profile-posts-empty>No posts are available yet.</div>
              <div class="mg-profile-load-more mg-hidden" data-post-pagination><button class="mg-invest-btn" type="button" data-posts-load-more>Load more posts</button></div>
            </article>
          </section>

          <section class="mg-invest-tab-panel" data-invest-panel="campaigns" hidden>
            <article class="mg-invest-card">
              <div class="mg-invest-section-head"><div><span class="mg-profile-section-kicker">Participate</span><h2>Campaigns</h2></div></div>
              <div class="mg-invest-campaign-list mg-profile-campaign-list-full" data-invest-campaigns-list-full></div>
              <div class="mg-invest-empty-state mg-hidden" data-invest-campaigns-empty-full>No campaigns are available yet.</div>
            </article>
          </section>

          <section class="mg-invest-tab-panel mg-hidden" data-profile-storefront-section data-invest-panel="storefront" hidden></section>
          <section class="mg-invest-tab-panel mg-hidden" data-profile-support-section data-invest-panel="support" hidden>
            <div class="mg-profile-plan-grid" data-profile-plans-grid></div>
            <div class="mg-invest-empty-state mg-hidden" data-profile-plans-empty>No memberships are available yet.</div>
            <div class="mg-profile-load-more mg-hidden" data-plan-pagination><button class="mg-invest-btn" type="button" data-plans-load-more>Load more memberships</button></div>
          </section>
          <div data-profile-sections></div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>