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
];
$page_scripts = [
    '/assets/js/public-profile-runtime.js',
    '/assets/js/public-profile.js',
    '/assets/js/public-profile-storefront.js',
    '/assets/js/public-profile-engagement.js',
    '/assets/js/public-profile-investment.js',
];
$page_manifest = [
    'id' => 'profile',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-public-profile-page mg-investment-profile-page',
    'public_header' => [
        'presentation' => false,
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
<header class="mg-invest-sticky-header" aria-label="Microgifter market navigation">
  <nav class="mg-invest-sticky-nav">
    <a class="mg-invest-sticky-logo mg-brand brand" href="/index.php" aria-label="Microgifter home">
      <span>Microgifter</span>
    </a>

    <div class="mg-invest-header-ticker" role="region" aria-label="Sample experience market ticker">
      <div class="mg-invest-ticker-label">Experience Market</div>
      <div class="mg-invest-ticker-track">
        <div class="mg-invest-ticker-marquee">
          <div class="mg-invest-ticker-row" data-invest-ticker-row>
            <span class="mg-invest-ticker-item"><strong>MGFTR</strong><span>$0.842</span><em class="is-up">▲ 3.21%</em></span>
            <span class="mg-invest-ticker-item"><strong>COF2</strong><span>0.22 MGFTR</span><em class="is-up">▲ 4.2%</em></span>
            <span class="mg-invest-ticker-item"><strong>BRNCH</strong><span>0.65 MGFTR</span><em class="is-up">▲ 8.7%</em></span>
            <span class="mg-invest-ticker-item"><strong>CHEF</strong><span>1.25 MGFTR</span><em class="is-up">▲ 12.4%</em></span>
            <span class="mg-invest-ticker-item"><strong>SHOW</strong><span>0.75 MGFTR</span><em class="is-up">▲ 6.1%</em></span>
            <span class="mg-invest-ticker-item"><strong>TACO</strong><span>0.55 MGFTR</span><em class="is-down">▼ 1.8%</em></span>
            <span class="mg-invest-ticker-item"><strong>VIPX</strong><span>2.25 MGFTR</span><em class="is-up">▲ 15.9%</em></span>
          </div>
          <div class="mg-invest-ticker-row" aria-hidden="true">
            <span class="mg-invest-ticker-item"><strong>MGFTR</strong><span>$0.842</span><em class="is-up">▲ 3.21%</em></span>
            <span class="mg-invest-ticker-item"><strong>COF2</strong><span>0.22 MGFTR</span><em class="is-up">▲ 4.2%</em></span>
            <span class="mg-invest-ticker-item"><strong>BRNCH</strong><span>0.65 MGFTR</span><em class="is-up">▲ 8.7%</em></span>
            <span class="mg-invest-ticker-item"><strong>CHEF</strong><span>1.25 MGFTR</span><em class="is-up">▲ 12.4%</em></span>
            <span class="mg-invest-ticker-item"><strong>SHOW</strong><span>0.75 MGFTR</span><em class="is-up">▲ 6.1%</em></span>
            <span class="mg-invest-ticker-item"><strong>TACO</strong><span>0.55 MGFTR</span><em class="is-down">▼ 1.8%</em></span>
            <span class="mg-invest-ticker-item"><strong>VIPX</strong><span>2.25 MGFTR</span><em class="is-up">▲ 15.9%</em></span>
          </div>
        </div>
      </div>
    </div>

    <div class="mg-invest-sticky-actions">
      <a href="/discover.php">Explore</a>
      <a href="/campaign.php">Campaigns</a>
      <a href="/merchant.php">Merchant</a>
      <a class="mg-invest-sticky-icon" href="/notifications.php" aria-label="Notifications">●</a>
      <a class="mg-invest-sticky-account" href="/account.php">Account</a>
    </div>
  </nav>
</header>

<section
  class="mg-public-profile-shell mg-invest-profile-shell"
  data-public-profile-page
  data-profile-slug="<?= mg_e($slugIsValid ? $slug : '') ?>"
  data-profile-preview="<?= $preview ? '1' : '0' ?>"
  aria-busy="true"
>
  <div class="mg-profile-loading" data-profile-loading>
    <div class="mg-invest-loading-cover"></div>
    <div class="mg-invest-wrap">
      <div class="mg-invest-skeleton-row">
        <div class="mg-invest-skeleton-avatar"></div>
        <div class="mg-invest-skeleton-copy">
          <span></span>
          <span></span>
          <span></span>
        </div>
      </div>
      <div class="mg-invest-skeleton-grid">
        <span></span><span></span><span></span><span></span>
      </div>
    </div>
  </div>

  <div class="mg-profile-error mg-hidden" data-profile-error role="alert">
    <div class="mg-invest-wrap">
      <div class="mg-invest-panel mg-profile-empty">
        <span class="mg-invest-eyebrow">Profile unavailable</span>
        <h1 data-profile-error-title>Profile not found</h1>
        <p data-profile-error-message>This profile may be private, still in draft, suspended, or using a different address.</p>
        <div class="mg-invest-actions">
          <button class="mg-invest-btn mg-invest-btn-gold" type="button" data-profile-retry>Try again</button>
          <a class="mg-invest-btn mg-invest-btn-dark" href="/index.php">Go home</a>
        </div>
      </div>
    </div>
  </div>

  <div class="mg-profile-content mg-hidden" data-profile-content>
    <div class="mg-profile-preview-banner mg-hidden" data-profile-preview-banner role="status">
      <div class="mg-invest-wrap">
        <strong>Owner preview</strong>
        <span>This private or draft profile is visible only because you are signed in as its owner.</span>
        <a href="/account.php">Edit profile</a>
      </div>
    </div>

    <div class="mg-invest-wrap mg-invest-browser-frame">
      <section class="mg-invest-hero">
        <div class="mg-invest-cover" data-profile-cover aria-hidden="true"></div>

        <div class="mg-cover-adjust-panel mg-hidden" data-cover-adjust-panel>
          <div class="mg-cover-adjust-head">
            <strong>Adjust cover image</strong>
            <span>Move the image crop for this profile header.</span>
          </div>
          <div class="mg-cover-adjust-controls">
            <label>
              <span>Horizontal</span>
              <input type="range" min="0" max="100" step="1" value="50" data-cover-position-x>
            </label>
            <label>
              <span>Vertical</span>
              <input type="range" min="0" max="100" step="1" value="50" data-cover-position-y>
            </label>
          </div>
          <div class="mg-cover-adjust-actions">
            <button class="mg-invest-btn mg-invest-btn-dark" type="button" data-cover-adjust-reset>Reset</button>
            <button class="mg-invest-btn mg-invest-btn-gold" type="button" data-cover-adjust-save>Save cover position</button>
          </div>
          <div class="mg-profile-action-status" data-cover-adjust-status role="status" aria-live="polite"></div>
        </div>
      </section>

      <section class="mg-invest-profile-top" aria-label="Merchant investment profile summary">
        <div class="mg-invest-profile-left">
          <div class="mg-invest-avatar" data-profile-avatar-wrap>
            <img class="mg-hidden" data-profile-avatar alt="">
            <span data-profile-avatar-fallback aria-hidden="true">M</span>
          </div>

          <div class="mg-invest-identity">
            <div class="mg-invest-title-row">
              <h1 data-profile-name>Microgifter merchant</h1>
              <span class="mg-invest-verified" aria-label="Verified merchant">✓</span>
              <span class="mg-invest-status-pill">Verified Merchant</span>
            </div>
            <p class="mg-invest-handle" data-profile-headline>@microgiftermerchant</p>
            <p class="mg-profile-biography mg-invest-bio" data-profile-biography>
              Tokenized local experiences with real-world value and future demand.
            </p>
            <div class="mg-invest-meta-row">
              <div class="mg-profile-meta" data-profile-meta></div>
              <a class="mg-invest-meta-link mg-hidden" data-profile-website target="_blank" rel="noopener noreferrer">Website</a>
              <div class="mg-public-link-list mg-invest-inline-links mg-hidden" data-profile-links-section>
                <span class="mg-invest-meta-label">Links</span>
                <div data-profile-links></div>
              </div>
            </div>
            <div class="mg-profile-status-row mg-invest-status-row" data-profile-status-row></div>
          </div>
        </div>

        <div class="mg-invest-action-cluster">
          <button class="mg-invest-btn mg-invest-btn-gold mg-hidden" type="button" data-profile-follow>+ Follow</button>
          <button class="mg-invest-btn mg-invest-btn-dark" type="button">Message</button>
          <button class="mg-invest-btn mg-invest-btn-dark" type="button">Share</button>
          <button class="mg-invest-btn mg-invest-btn-dark" type="button">Save</button>
          <button class="mg-invest-btn mg-invest-btn-dark mg-hidden" type="button" data-cover-adjust-toggle>Adjust cover</button>
          <a class="mg-invest-btn mg-invest-btn-dark mg-hidden" data-profile-edit href="/account.php">Edit</a>
        </div>
        <div class="mg-profile-action-status" data-profile-follow-status role="status" aria-live="polite"></div>
      </section>

      <section class="mg-invest-summary-grid" aria-label="Profile metrics">
        <article class="mg-invest-stats-panel">
          <div class="mg-invest-stat-group">
            <span class="mg-invest-stat-label">Social</span>
            <dl class="mg-invest-social-stats">
              <div><dt>Followers</dt><dd data-profile-followers>0</dd></div>
              <div><dt>Supporters</dt><dd data-profile-supporters>0</dd></div>
              <div><dt>Following</dt><dd data-invest-following>—</dd></div>
              <div><dt>Posts</dt><dd data-invest-posts>—</dd></div>
              <div><dt>Reach</dt><dd data-invest-reach>—</dd></div>
              <div><dt>Engagement</dt><dd data-invest-engagement>—</dd></div>
            </dl>
          </div>
          <div class="mg-invest-stat-group">
            <span class="mg-invest-stat-label">Market &amp; Performance</span>
            <dl class="mg-invest-market-stats">
              <div><dt>Active Drops</dt><dd data-profile-products data-invest-active-drops>0</dd></div>
              <div><dt>Market Value</dt><dd data-invest-market-value>—</dd></div>
              <div><dt>Floor Price</dt><dd data-invest-floor-price>—</dd></div>
              <div><dt>Volume (30D)</dt><dd data-invest-volume-30d>—</dd></div>
              <div><dt>Redemption Rate</dt><dd data-invest-redemption-rate>—</dd></div>
            </dl>
          </div>
        </article>

        <article class="mg-invest-chart-card" aria-label="Demand chart">
          <div class="mg-invest-chart-head">
            <div>
              <span class="mg-invest-eyebrow">Demand Signal</span>
              <h2>Market movement</h2>
            </div>
            <strong data-invest-demand-score>82</strong>
          </div>
          <svg class="mg-invest-chart" viewBox="0 0 640 220" aria-hidden="true">
            <defs>
              <linearGradient id="mgInvestLine" x1="0" x2="1" y1="0" y2="0">
                <stop offset="0" stop-color="#7c5cff"/>
                <stop offset="1" stop-color="#d9a735"/>
              </linearGradient>
              <linearGradient id="mgInvestArea" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0" stop-color="rgba(217,167,53,.32)"/>
                <stop offset="1" stop-color="rgba(217,167,53,0)"/>
              </linearGradient>
            </defs>
            <path d="M20 180 L90 162 L150 150 L215 132 L280 140 L345 110 L410 122 L485 82 L560 96 L620 54 L620 210 L20 210 Z" fill="url(#mgInvestArea)"/>
            <path d="M20 180 L90 162 L150 150 L215 132 L280 140 L345 110 L410 122 L485 82 L560 96 L620 54" fill="none" stroke="url(#mgInvestLine)" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
            <g fill="#d9a735"><circle cx="90" cy="162" r="5"/><circle cx="215" cy="132" r="5"/><circle cx="345" cy="110" r="5"/><circle cx="485" cy="82" r="5"/><circle cx="620" cy="54" r="5"/></g>
          </svg>
        </article>
      </section>

      <nav class="mg-invest-tabbar" aria-label="Profile sections">
        <a href="#overview">Overview</a>
        <a href="#products">Products</a>
        <a href="#posts">Posts</a>
        <a href="#campaigns">Campaigns</a>
        <a href="#analytics">Analytics</a>
      </nav>

      <div class="mg-invest-layout-grid">
        <main class="mg-invest-main-column">
          <section class="mg-invest-panel" id="overview" aria-labelledby="mg-invest-overview-title">
            <div class="mg-invest-section-head">
              <div>
                <span class="mg-invest-eyebrow">Merchant Story</span>
                <h2 id="mg-invest-overview-title">Experience economy profile</h2>
              </div>
            </div>
            <p data-invest-overview>
              This merchant profile acts like a micro-market page for future local demand. Customers can discover limited experiences, follow updates, react to posts, and support the merchant through products, subscriptions, or tips.
            </p>
          </section>

          <section class="mg-invest-panel mg-hidden" data-profile-storefront-section id="storefront" aria-labelledby="mg-profile-storefront-title">
            <div class="mg-invest-section-head">
              <div>
                <span class="mg-invest-eyebrow">Storefront</span>
                <h2 id="mg-profile-storefront-title" data-storefront-name>Storefront</h2>
                <p class="mg-profile-section-intro" data-storefront-tagline></p>
              </div>
              <span class="mg-invest-link" data-storefront-status>Live</span>
            </div>
            <div class="mg-profile-storefront-cover mg-invest-storefront-cover" data-storefront-cover aria-hidden="true"></div>
            <div class="mg-profile-storefront-description" data-storefront-description></div>
          </section>

          <section class="mg-invest-panel" id="products" aria-labelledby="mg-profile-products-title">
            <div class="mg-invest-section-head">
              <div>
                <span class="mg-invest-eyebrow">Tokenized Drops</span>
                <h2 id="mg-profile-products-title">Limited experiences</h2>
                <p class="mg-profile-section-intro">Wallet-ready products, memberships, and demand campaigns connected to this merchant.</p>
              </div>
              <span class="mg-invest-link">View all products →</span>
            </div>
            <div class="mg-profile-product-grid mg-invest-product-grid" data-profile-products-grid></div>
            <div class="mg-profile-empty-inline mg-hidden" data-profile-products-empty>
              <strong>No published products yet.</strong>
              <span>This storefront is live, but it does not currently have public products.</span>
            </div>
            <div class="mg-profile-load-more mg-hidden" data-product-pagination>
              <button class="mg-invest-btn mg-invest-btn-dark" type="button" data-products-load-more>Load more products</button>
            </div>
          </section>

          <section class="mg-invest-split-row">
            <section class="mg-invest-panel mg-hidden" data-profile-posts-section id="posts" aria-labelledby="mg-profile-posts-title">
              <div class="mg-invest-section-head">
                <div>
                  <span class="mg-invest-eyebrow">Latest Posts</span>
                  <h2 id="mg-profile-posts-title">Latest posts</h2>
                  <p class="mg-profile-section-intro">Updates, drops, and social activity available to your account.</p>
                </div>
                <span class="mg-invest-link">View all posts →</span>
              </div>
              <div class="mg-profile-post-list mg-invest-post-grid" data-profile-posts-list></div>
              <div class="mg-profile-empty-inline mg-hidden" data-profile-posts-empty>
                <strong>No visible posts.</strong>
                <span>This profile has not published an update for your current access level.</span>
              </div>
              <div class="mg-profile-load-more mg-hidden" data-post-pagination>
                <button class="mg-invest-btn mg-invest-btn-dark" type="button" data-posts-load-more>Load more posts</button>
              </div>
            </section>

            <section class="mg-invest-panel" id="campaigns" aria-labelledby="mg-invest-campaigns-title">
              <div class="mg-invest-section-head">
                <div>
                  <span class="mg-invest-eyebrow">Campaigns</span>
                  <h2 id="mg-invest-campaigns-title">Active campaigns</h2>
                </div>
                <span class="mg-invest-link">View all campaigns →</span>
              </div>
              <div class="mg-invest-campaign-list" data-invest-campaigns-list>
                <div class="mg-invest-empty-small">Loading campaigns…</div>
              </div>
            </section>
          </section>

          <section class="mg-invest-panel mg-hidden" data-profile-support-section aria-labelledby="mg-profile-support-title">
            <div class="mg-invest-section-head">
              <div>
                <span class="mg-invest-eyebrow">Support</span>
                <h2 id="mg-profile-support-title">Support this profile</h2>
                <p class="mg-profile-section-intro">Choose a recurring membership or send a wallet- or card-funded one-time tip.</p>
              </div>
            </div>

            <div class="mg-profile-support-grid">
              <div class="mg-profile-support-column">
                <h3>Memberships</h3>
                <div class="mg-profile-plan-grid" data-profile-plans-grid></div>
                <div class="mg-profile-empty-inline mg-hidden" data-profile-plans-empty>
                  <strong>No active memberships.</strong>
                  <span>This profile is not currently offering a recurring plan.</span>
                </div>
                <div class="mg-profile-load-more mg-hidden" data-plan-pagination>
                  <button class="mg-invest-btn mg-invest-btn-dark" type="button" data-plans-load-more>Load more memberships</button>
                </div>
              </div>

              <aside class="mg-profile-tip-card mg-hidden" data-profile-tip-card>
                <h3>Send a tip</h3>
                <p>Choose your Microgifter wallet or a card payment. Card tips post only after server-side provider confirmation.</p>
                <div class="mg-profile-tip-amounts" aria-label="Suggested tip amounts">
                  <button type="button" data-tip-amount="5">$5</button>
                  <button type="button" data-tip-amount="10">$10</button>
                  <button type="button" data-tip-amount="25">$25</button>
                </div>
                <form class="mg-profile-tip-form" data-profile-tip-form>
                  <label>Tip amount
                    <input type="number" name="tip_amount" min="1" max="1000" step="1" inputmode="decimal" placeholder="10" required>
                  </label>
                  <label>Payment method
                    <select name="tip_funding" data-tip-funding>
                      <option value="wallet">Microgifter wallet</option>
                      <option value="stripe">Card</option>
                    </select>
                  </label>
                  <button class="mg-invest-btn mg-invest-btn-gold" type="submit" data-profile-tip-submit>Send tip</button>
                  <div class="mg-profile-tip-confirmation mg-hidden" data-profile-tip-confirmation>
                    <strong>Card authorization required</strong>
                    <span>Complete the secure payment authorization, then confirm the tip.</span>
                    <button class="mg-invest-btn mg-invest-btn-dark" type="button" data-profile-tip-confirm>Confirm card tip</button>
                  </div>
                  <div class="mg-profile-action-status" data-profile-tip-status role="status" aria-live="polite"></div>
                </form>
              </aside>
            </div>
          </section>

          <section class="mg-invest-panel" id="analytics" aria-labelledby="mg-invest-analytics-title">
            <div class="mg-invest-section-head">
              <div>
                <span class="mg-invest-eyebrow">Analytics</span>
                <h2 id="mg-invest-analytics-title">Market movement</h2>
                <p>Sample merchant-facing analytics for tokenized experiences. Investor account data will use a separate dataset next.</p>
              </div>
            </div>
            <div class="mg-invest-analytics-grid">
              <div><strong data-invest-demand-growth>—</strong><span>Demand growth</span></div>
              <div><strong data-invest-analytics-redemption>—</strong><span>Redemption rate</span></div>
              <div><strong data-invest-analytics-volume>—</strong><span>30D volume</span></div>
              <div><strong data-invest-analytics-floor>—</strong><span>Floor price</span></div>
            </div>
          </section>

          <div data-profile-sections></div>
        </main>

        <aside class="mg-invest-sidebar" aria-label="Investment profile sidebar">
          <article class="mg-invest-card">
            <div class="mg-invest-card-head">
              <span>Portfolio Snapshot</span>
              <a href="/account.php">View portfolio →</a>
            </div>
            <div class="mg-invest-holdings">
              <div>
                <span>Holdings</span>
                <strong data-invest-holdings>—</strong>
                <small data-invest-holdings-usd>—</small>
              </div>
              <div>
                <span>Unrealized P/L</span>
                <strong class="is-up" data-invest-pl-percent>—</strong>
                <small class="is-up" data-invest-pl-usd>—</small>
              </div>
            </div>
            <svg class="mg-invest-mini-line" viewBox="0 0 300 70" aria-hidden="true" data-invest-portfolio-chart>
              <path d="M4 50 L34 48 L62 40 L90 44 L118 32 L146 37 L174 29 L202 34 L230 28 L258 31 L296 22" fill="none" stroke="#d9a735" stroke-width="3" stroke-linecap="round"/>
            </svg>
          </article>

          <article class="mg-invest-card">
            <div class="mg-invest-card-head">
              <span>Recent Activity</span>
              <a href="#posts">View all →</a>
            </div>
            <div class="mg-invest-activity-list" data-invest-activity-list><div class="mg-invest-empty-small">Loading activity…</div></div>
          </article>

          <article class="mg-invest-card">
            <div class="mg-invest-card-head">
              <span>Top Trending Experiences</span>
              <a href="#products">View all →</a>
            </div>
            <ol class="mg-invest-trending" data-invest-trending-list><li class="mg-invest-empty-small">Loading trending experiences…</li></ol>
          </article>

          <section class="mg-invest-panel mg-profile-cta">
            <span class="mg-invest-eyebrow">Powered by Microgifter</span>
            <h2>Create tokenized experiences.</h2>
            <p>Pre-sell future demand with limited, wallet-ready local experiences.</p>
            <div class="mg-invest-actions">
              <a class="mg-invest-btn mg-invest-btn-gold" href="/signup.php">Create your profile</a>
              <a class="mg-invest-btn mg-invest-btn-dark" href="/learn-more.php">Learn more</a>
            </div>
          </section>
        </aside>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>