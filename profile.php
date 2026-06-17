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

$page_title = 'Public profile | Microgifter';
$page_section = 'profile';
$header_mode = 'public';
$page_styles = [
    '/assets/css/public-profile.css',
    '/assets/css/public-profile-storefront.css',
    '/assets/css/public-profile-engagement.css',
];
$page_scripts = [
    '/assets/js/public-profile-runtime.js',
    '/assets/js/public-profile.js',
    '/assets/js/public-profile-storefront.js',
    '/assets/js/public-profile-engagement.js',
];
$page_manifest = [
    'id' => 'profile',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-public-profile-page',
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Home', 'href' => '/index.php'],
            ['label' => 'Discover', 'href' => '/discover.php'],
            ['label' => 'Learn More', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'profile', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<section
  class="mg-public-profile-shell"
  data-public-profile-page
  data-profile-slug="<?= mg_e($slugIsValid ? $slug : '') ?>"
  data-profile-preview="<?= $preview ? '1' : '0' ?>"
  aria-busy="true"
>
  <div class="mg-profile-loading" data-profile-loading>
    <div class="mg-profile-cover mg-profile-skeleton"></div>
    <div class="mg-container mg-public-profile-grid">
      <aside class="mg-profile-card mg-profile-skeleton-card" aria-hidden="true">
        <div class="mg-profile-avatar mg-profile-skeleton"></div>
        <div class="mg-profile-skeleton-line is-title"></div>
        <div class="mg-profile-skeleton-line"></div>
        <div class="mg-profile-skeleton-line is-short"></div>
      </aside>
      <div class="mg-profile-main-card mg-profile-skeleton-card" aria-hidden="true">
        <div class="mg-profile-skeleton-line is-kicker"></div>
        <div class="mg-profile-skeleton-line is-heading"></div>
        <div class="mg-profile-skeleton-line"></div>
        <div class="mg-profile-skeleton-line"></div>
        <div class="mg-profile-skeleton-line is-short"></div>
      </div>
    </div>
  </div>

  <div class="mg-profile-error mg-hidden" data-profile-error role="alert">
    <div class="mg-container">
      <div class="mg-panel mg-profile-empty">
        <span class="mg-badge">Profile unavailable</span>
        <h1 class="mg-section-title" data-profile-error-title>Profile not found</h1>
        <p class="mg-muted" data-profile-error-message>This profile may be private, still in draft, suspended, or using a different address.</p>
        <div class="mg-action-row">
          <button class="mg-btn mg-btn-primary" type="button" data-profile-retry>Try again</button>
          <a class="mg-btn mg-btn-ghost" href="/index.php">Go home</a>
        </div>
      </div>
    </div>
  </div>

  <div class="mg-profile-content mg-hidden" data-profile-content>
    <div class="mg-profile-preview-banner mg-hidden" data-profile-preview-banner role="status">
      <div class="mg-container">
        <strong>Owner preview</strong>
        <span>This private or draft profile is visible only because you are signed in as its owner.</span>
        <a href="/account.php">Edit profile</a>
      </div>
    </div>

    <div class="mg-profile-cover" data-profile-cover aria-hidden="true"></div>

    <div class="mg-container mg-public-profile-grid">
      <aside class="mg-profile-card">
        <div class="mg-profile-avatar" data-profile-avatar-wrap>
          <img class="mg-hidden" data-profile-avatar alt="">
          <span data-profile-avatar-fallback aria-hidden="true">M</span>
        </div>

        <div class="mg-profile-status-row" data-profile-status-row></div>
        <h1 data-profile-name>Microgifter profile</h1>
        <p class="mg-profile-headline mg-hidden" data-profile-headline></p>

        <div class="mg-profile-meta" data-profile-meta></div>

        <dl class="mg-profile-counts" aria-label="Profile activity">
          <div><dt>Followers</dt><dd data-profile-followers>0</dd></div>
          <div><dt>Supporters</dt><dd data-profile-supporters>0</dd></div>
          <div><dt>Products</dt><dd data-profile-products>0</dd></div>
        </dl>

        <div class="mg-profile-actions">
          <button class="mg-btn mg-btn-primary mg-hidden" type="button" data-profile-follow>Follow</button>
          <a class="mg-btn mg-btn-primary mg-hidden" data-profile-website target="_blank" rel="noopener noreferrer">Visit website</a>
          <a class="mg-btn mg-btn-ghost mg-hidden" data-profile-edit href="/account.php">Edit profile</a>
        </div>
        <div class="mg-profile-action-status" data-profile-follow-status role="status" aria-live="polite"></div>
      </aside>

      <div class="mg-profile-main-card">
        <section class="mg-profile-section" aria-labelledby="mg-profile-about-title">
          <span class="mg-kicker">Microgifter profile</span>
          <h2 id="mg-profile-about-title">About</h2>
          <p class="mg-profile-biography" data-profile-biography></p>
        </section>

        <section class="mg-profile-section mg-hidden" data-profile-links-section aria-labelledby="mg-profile-links-title">
          <h2 id="mg-profile-links-title">Links</h2>
          <div class="mg-public-link-list" data-profile-links></div>
        </section>

        <div data-profile-sections></div>

        <section class="mg-profile-section mg-hidden" data-profile-storefront-section aria-labelledby="mg-profile-storefront-title">
          <div class="mg-profile-section-heading">
            <div>
              <span class="mg-kicker">Storefront</span>
              <h2 id="mg-profile-storefront-title" data-storefront-name>Published products</h2>
              <p class="mg-profile-section-intro mg-hidden" data-storefront-headline></p>
            </div>
            <a class="mg-btn mg-btn-ghost mg-hidden" data-storefront-link>Open store</a>
          </div>

          <article class="mg-storefront-card">
            <div class="mg-storefront-cover" data-storefront-cover aria-hidden="true"></div>
            <div class="mg-storefront-body">
              <div class="mg-storefront-logo" data-storefront-logo-wrap>
                <img class="mg-hidden" data-storefront-logo alt="">
                <span data-storefront-logo-fallback aria-hidden="true">S</span>
              </div>
              <p data-storefront-description></p>
            </div>
          </article>

          <div class="mg-profile-product-grid" data-profile-products-grid></div>
          <div class="mg-profile-empty-inline mg-hidden" data-profile-products-empty>
            <strong>No published products yet.</strong>
            <span>This storefront is live, but it does not currently have public products.</span>
          </div>
          <div class="mg-profile-load-more mg-hidden" data-product-pagination>
            <button class="mg-btn mg-btn-soft" type="button" data-products-load-more>Load more products</button>
          </div>
        </section>

        <section class="mg-profile-section mg-hidden" data-profile-posts-section aria-labelledby="mg-profile-posts-title">
          <div class="mg-profile-section-heading">
            <div>
              <span class="mg-kicker">Updates</span>
              <h2 id="mg-profile-posts-title">Latest posts</h2>
              <p class="mg-profile-section-intro">React and join the conversation on posts available to your account.</p>
            </div>
          </div>
          <div class="mg-profile-post-list" data-profile-posts-list></div>
          <div class="mg-profile-empty-inline mg-hidden" data-profile-posts-empty>
            <strong>No visible posts.</strong>
            <span>This profile has not published an update for your current access level.</span>
          </div>
          <div class="mg-profile-load-more mg-hidden" data-post-pagination>
            <button class="mg-btn mg-btn-soft" type="button" data-posts-load-more>Load more posts</button>
          </div>
        </section>

        <section class="mg-profile-section mg-hidden" data-profile-support-section aria-labelledby="mg-profile-support-title">
          <span class="mg-kicker">Support</span>
          <h2 id="mg-profile-support-title">Support this profile</h2>
          <p class="mg-profile-section-intro">Choose a recurring membership or send a wallet- or card-funded one-time tip.</p>

          <div class="mg-profile-support-grid">
            <div class="mg-profile-support-column">
              <h3>Memberships</h3>
              <div class="mg-profile-plan-grid" data-profile-plans-grid></div>
              <div class="mg-profile-empty-inline mg-hidden" data-profile-plans-empty>
                <strong>No active memberships.</strong>
                <span>This profile is not currently offering a recurring plan.</span>
              </div>
              <div class="mg-profile-load-more mg-hidden" data-plan-pagination>
                <button class="mg-btn mg-btn-soft" type="button" data-plans-load-more>Load more memberships</button>
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
                <button class="mg-btn mg-btn-primary" type="submit" data-profile-tip-submit>Send tip</button>
                <div class="mg-profile-tip-confirmation mg-hidden" data-profile-tip-confirmation>
                  <strong>Card authorization required</strong>
                  <span>Complete the secure payment authorization, then confirm the tip.</span>
                  <button class="mg-btn mg-btn-soft" type="button" data-profile-tip-confirm>Confirm card tip</button>
                </div>
                <div class="mg-profile-action-status" data-profile-tip-status role="status" aria-live="polite"></div>
              </form>
            </aside>
          </div>
        </section>

        <section class="mg-profile-section mg-profile-cta">
          <div>
            <span class="mg-kicker">Powered by Microgifter</span>
            <h2>Create meaningful local gifting experiences.</h2>
            <p>Pre-purchase gifts, local rewards, and agent-assisted gifting in one connected platform.</p>
          </div>
          <div class="mg-action-row">
            <a class="mg-btn mg-btn-primary" href="/signup.php">Create your profile</a>
            <a class="mg-btn mg-btn-ghost" href="/learn-more.php">Learn more</a>
          </div>
        </section>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
