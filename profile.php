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
];
$page_scripts = [
    '/assets/js/public-profile-runtime.js',
    '/assets/js/public-profile.js',
    '/assets/js/public-profile-storefront.js',
    '/assets/js/public-profile-engagement.js',
    '/assets/js/public-profile-investment.js',
    '/assets/js/public-profile-posts-fix.js',
];
$page_manifest = [
    'id' => 'public-profile',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-public-profile-page mg-investment-profile-page mg-profile-light-theme mg-profile-no-footer',
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
      <div class="mg-invest-loading-grid" aria-hidden="true"><span></span><span></span><span></span></div>
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

    <div class="mg-invest-shell">
      <div class="mg-invest-top-grid">
        <div class="mg-invest-top-left">
          <section class="mg-invest-head-row" aria-label="Merchant profile summary">
            <div class="mg-invest-identity-row">
              <div class="mg-invest-avatar" data-profile-avatar-wrap>
                <img class="mg-hidden" data-profile-avatar alt="">
                <span data-profile-avatar-fallback>M</span>
                <b aria-hidden="true">✓</b>
              </div>
              <div class="mg-invest-identity-copy">
                <div class="mg-invest-title-line">
                  <h1 data-profile-name data-invest-field="display_name">Microgifter Merchant</h1>
                  <span class="mg-invest-verified">Verified Merchant</span>
                </div>
                <p class="mg-invest-handle mg-hidden" data-profile-headline data-invest-field="tagline"></p>
                <p class="mg-invest-bio" data-profile-biography>This profile has not added a biography yet.</p>
                <div class="mg-invest-meta-row">
                  <div data-profile-meta></div>
                  <a class="mg-invest-link mg-hidden" data-profile-website target="_blank" rel="noopener noreferrer">Website</a>
                  <div class="mg-public-link-list mg-hidden" data-profile-links-section><div data-profile-links></div></div>
                </div>
                <div class="mg-profile-status-row" data-profile-status-row></div>
                <div class="mg-profile-image-tools" data-profile-image-tools>
                  <a href="/account.php">Replace profile image</a>
                  <button type="button" data-profile-avatar-delete>Delete profile image</button>
                </div>
              </div>
            </div>
          </section>

          <section class="mg-invest-stat-board" aria-label="Profile real-time statistics">
            <div class="mg-invest-stat-group">
              <span>Social</span>
              <dl>
                <div><dt>Followers</dt><dd data-profile-followers>0</dd></div>
                <div><dt>Supporters</dt><dd data-profile-supporters>0</dd></div>
                <div><dt>Follower Momentum</dt><dd data-invest-field="follower_momentum">0</dd></div>
                <div><dt>Posts</dt><dd data-invest-field="posts_total">0</dd></div>
                <div><dt>Interactions</dt><dd data-invest-field="post_interactions">0</dd></div>
                <div><dt>Engagement</dt><dd data-invest-field="engagement_rate">0%</dd></div>
              </dl>
            </div>
            <div class="mg-invest-stat-group">
              <span>Market and Distribution</span>
              <dl>
                <div><dt>Ticker</dt><dd data-invest-field="ticker_symbol">MGFT</dd></div>
                <div><dt>Ticker Value</dt><dd data-invest-field="ticker_value">$0</dd></div>
                <div><dt>Merchant Score</dt><dd data-invest-field="merchant_score">0</dd></div>
                <div><dt>Rating</dt><dd data-invest-field="rating">No data</dd></div>
                <div><dt>Funnel Quality</dt><dd data-invest-field="campaign_funnel_quality">0</dd></div>
                <div><dt>Risk Adjustment</dt><dd data-invest-field="risk_adjustment">$0</dd></div>
                <div><dt>Active Drops</dt><dd data-profile-products data-invest-field="active_drops">0</dd></div>
                <div><dt>Campaigns</dt><dd data-invest-field="active_campaigns">0</dd></div>
                <div><dt>Conversions</dt><dd data-invest-field="campaign_conversions">0</dd></div>
                <div><dt>Distribution</dt><dd data-invest-field="distribution_channels">0</dd></div>
                <div><dt>Stamp Inventory</dt><dd data-invest-field="stamp_inventory">0</dd></div>
                <div><dt>Stamp Spend 30D</dt><dd data-invest-field="stamp_spend_30d">0</dd></div>
                <div><dt>Demand Value</dt><dd data-invest-field="demand_value">$0</dd></div>
                <div><dt>Floor Price</dt><dd data-invest-field="floor_price">$0</dd></div>
                <div><dt>Volume 30D</dt><dd data-invest-field="volume_30d">$0</dd></div>
                <div><dt>Redemption Rate</dt><dd data-invest-field="redemption_rate">0%</dd></div>
              </dl>
            </div>
          </section>
        </div>

        <div class="mg-invest-top-right">
          <div class="mg-invest-actions">
            <button class="mg-invest-btn is-gold mg-hidden" type="button" data-profile-follow>Follow</button>
            <button class="mg-invest-btn" type="button" data-profile-message>Message</button>
            <button class="mg-invest-btn" type="button" data-profile-share>Share</button>
            <button class="mg-invest-btn" type="button" data-profile-save>Save</button>
            <a class="mg-invest-btn mg-hidden" data-profile-edit href="/account.php">Edit</a>
          </div>
          <div class="mg-profile-action-status" data-profile-follow-status role="status" aria-live="polite"></div>
          <div class="mg-profile-action-status" data-profile-button-status role="status" aria-live="polite"></div>

          <section class="mg-invest-chart-row" aria-label="Market charts">
            <article class="mg-invest-card mg-market-card">
              <div class="mg-invest-card-head">
                <span>Ticker Value</span>
                <strong><span data-invest-field="ticker_value">$0</span><em data-invest-field="market_growth_30d">No trend</em></strong>
              </div>
              <div class="mg-invest-empty-state mg-hidden" data-invest-chart-empty>No data available.</div>
              <svg viewBox="0 0 330 150" role="img" aria-label="30-day demand value chart" data-invest-market-chart>
                <path class="mg-chart-fill" data-invest-chart-fill d=""></path>
                <path class="mg-chart-line" data-invest-chart-line d=""></path>
              </svg>
              <div class="mg-chart-labels" data-invest-chart-labels></div>
            </article>

            <article class="mg-invest-card mg-demand-card">
              <div class="mg-invest-card-head">
                <span>Merchant Score</span>
                <strong><span data-invest-field="merchant_score">0</span><em data-invest-field="rating">No data</em></strong>
              </div>
              <div class="mg-invest-empty-state mg-hidden" data-invest-demand-empty>No data available.</div>
              <div class="mg-demand-meter" data-invest-demand-meter>
                <svg viewBox="0 0 120 120" aria-hidden="true">
                  <circle cx="60" cy="60" r="45"></circle>
                  <circle class="meter" cx="60" cy="60" r="45" data-invest-demand-ring></circle>
                  <path d="M32 68 C42 38 46 88 55 58 C63 31 70 84 79 52 C86 30 94 70 99 58"></path>
                </svg>
              </div>
              <dl class="mg-demand-factors">
                <div><dt>Products</dt><dd data-invest-factor="products">0</dd></div>
                <div><dt>Campaigns</dt><dd data-invest-factor="campaigns">0</dd></div>
                <div><dt>Conversions</dt><dd data-invest-factor="conversions">0</dd></div>
                <div><dt>Funnel</dt><dd data-invest-factor="funnel">0</dd></div>
                <div><dt>Redemptions</dt><dd data-invest-factor="redemptions">0</dd></div>
                <div><dt>Engagement</dt><dd data-invest-factor="engagement">0</dd></div>
                <div><dt>Distribution</dt><dd data-invest-factor="distribution">0</dd></div>
                <div><dt>Stamps</dt><dd data-invest-factor="stamps">0</dd></div>
                <div><dt>Followers</dt><dd data-invest-factor="followers">0</dd></div>
                <div><dt>Momentum</dt><dd data-invest-factor="momentum">0</dd></div>
                <div><dt>Risk</dt><dd data-invest-factor="risk">0</dd></div>
              </dl>
            </article>
          </section>
        </div>
      </div>

      <nav class="mg-invest-tabs" aria-label="Profile content tabs">
        <button type="button" class="is-active" data-invest-tab="overview">Overview</button>
        <button type="button" data-invest-tab="products">Products</button>
        <button type="button" data-invest-tab="posts">Posts</button>
        <button type="button" data-invest-tab="campaigns">Campaigns</button>
        <button type="button" data-invest-tab="analytics">Analytics</button>
      </nav>

      <div class="mg-invest-content-grid">
        <div class="mg-invest-main-column">
          <section class="mg-invest-tab-panel is-active" data-invest-panel="overview">
            <div class="mg-invest-overview-grid">
              <article class="mg-invest-card">
                <div class="mg-invest-section-head">
                  <h2>Featured Experiences</h2>
                  <a class="mg-invest-link" href="/discover.php">View all products</a>
                </div>
                <div class="mg-profile-product-grid mg-invest-product-grid" data-profile-products-grid></div>
                <div class="mg-invest-empty-state mg-hidden" data-profile-products-empty>No data available.</div>
                <div class="mg-profile-load-more mg-hidden" data-product-pagination><button class="mg-invest-btn" type="button" data-products-load-more>Load more products</button></div>
              </article>

              <article class="mg-invest-card">
                <div class="mg-invest-section-head">
                  <h2>Active Campaigns</h2>
                  <a class="mg-invest-link" href="/campaign.php">Manage campaigns</a>
                </div>
                <div class="mg-invest-campaign-list" data-invest-campaigns-list></div>
                <div class="mg-invest-empty-state mg-hidden" data-invest-campaigns-empty>No data available.</div>
              </article>
            </div>
          </section>

          <section class="mg-invest-tab-panel" data-invest-panel="products" hidden>
            <article class="mg-invest-card">
              <div class="mg-invest-section-head">
                <h2>All Experiences</h2>
                <a class="mg-invest-link" href="/discover.php">Open marketplace</a>
              </div>
              <div class="mg-profile-product-grid" data-profile-products-grid-clone></div>
            </article>
          </section>

          <section class="mg-invest-tab-panel" data-invest-panel="posts" data-profile-posts-section hidden>
            <article class="mg-invest-card">
              <div class="mg-invest-section-head">
                <h2>Latest Posts</h2>
                <span>Updates from this profile</span>
              </div>
              <div class="mg-profile-post-list mg-invest-post-grid" data-profile-posts-list></div>
              <div class="mg-invest-empty-state mg-hidden" data-profile-posts-empty>No data available.</div>
              <div class="mg-profile-load-more mg-hidden" data-post-pagination><button class="mg-invest-btn" type="button" data-posts-load-more>Load more posts</button></div>
            </article>
          </section>

          <section class="mg-invest-tab-panel" data-invest-panel="campaigns" hidden>
            <article class="mg-invest-card">
              <div class="mg-invest-section-head">
                <h2>Campaigns</h2>
                <span>Live reward and CRM activity</span>
              </div>
              <div class="mg-invest-campaign-list" data-invest-campaigns-list-full></div>
              <div class="mg-invest-empty-state mg-hidden" data-invest-campaigns-empty-full>No data available.</div>
            </article>
          </section>

          <section class="mg-invest-tab-panel" data-invest-panel="analytics" hidden>
            <article class="mg-invest-card">
              <div class="mg-invest-section-head">
                <h2>Analytics</h2>
                <span>Calculated from real profile, product, campaign, wallet, distribution, stamp, follower, and post data</span>
              </div>
              <div class="mg-invest-analytics-grid" data-invest-analytics-grid></div>
              <div class="mg-invest-empty-state mg-hidden" data-invest-analytics-empty>No data available.</div>
              <div class="mg-invest-formula-list" data-invest-formula-list></div>
            </article>
          </section>

          <section class="mg-invest-tab-panel mg-hidden" data-profile-storefront-section data-invest-panel="storefront" hidden></section>
          <section class="mg-invest-tab-panel mg-hidden" data-profile-support-section data-invest-panel="support" hidden>
            <div class="mg-profile-plan-grid" data-profile-plans-grid></div>
            <div class="mg-invest-empty-state mg-hidden" data-profile-plans-empty>No data available.</div>
            <div class="mg-profile-load-more mg-hidden" data-plan-pagination><button class="mg-invest-btn" type="button" data-plans-load-more>Load more memberships</button></div>
          </section>
          <div data-profile-sections></div>
        </div>

        <aside class="mg-invest-sidebar">
          <article class="mg-invest-card">
            <div class="mg-invest-card-head"><span>Portfolio Snapshot</span><a href="/account.php">View portfolio</a></div>
            <strong data-invest-portfolio-value>No data</strong>
            <small data-invest-portfolio-subtitle>No data available.</small>
            <div class="mg-invest-empty-state mg-hidden" data-invest-portfolio-empty>No data available.</div>
          </article>

          <article class="mg-invest-card">
            <div class="mg-invest-card-head"><span>Recent Activity</span><a href="/campaign.php">View all</a></div>
            <div class="mg-invest-activity-list" data-invest-activity-list></div>
            <div class="mg-invest-empty-state mg-hidden" data-invest-activity-empty>No data available.</div>
          </article>

          <article class="mg-invest-card">
            <div class="mg-invest-card-head"><span>Top Trending Experiences</span><a href="/discover.php">View all</a></div>
            <ol class="mg-invest-trending" data-invest-trending-list></ol>
            <div class="mg-invest-empty-state mg-hidden" data-invest-trending-empty>No data available.</div>
          </article>
        </aside>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
