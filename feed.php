<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$feedView = trim((string) ($_GET['view'] ?? 'discover'));
$feedView = in_array($feedView, ['discover', 'following', 'mine'], true) ? $feedView : 'discover';

$page_title = 'Feed | Microgifter';
$page_section = 'feed';
$header_mode = 'agent';
$agent_tab = 'feed-' . $feedView;
$suppress_footer = true;
$page_styles = ['/assets/css/public-app-header.css','/assets/css/social-feed.css','/assets/css/social-feed-upload.css','/assets/css/feed-centered-layout.css','/assets/css/store-presence-feed.css','/assets/css/avatar-anchor-consent.css','/assets/css/sponsored-campaign-card.css','/assets/css/microgifter-stories.css'];
$page_scripts = ['/assets/js/social-feed.js','/assets/js/social-feed-sidebar-tabs.js','/assets/js/social-feed-post-polish.js','/assets/js/social-feed-upload.js','/assets/js/store-presence-feed.js','/assets/js/avatar-anchor-consent.js','/assets/js/sponsored-campaign-card.js','/assets/js/microgifter-stories.js'];
$page_manifest = [
    'id' => 'feed',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-social-feed-page',
    'footer' => false,
    'public_header' => [
        'presentation' => false,
        'search' => true,
        'auth_aware' => true,
        'links' => [],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'feed', 'sections' => []],
];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-feed-app-shell" data-social-feed data-initial-feed-view="<?= mg_e($feedView) ?>">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>

  <div class="mg-app-workspace mg-feed-workspace">
    <section class="mg-feed-shell">
      <div class="mg-container mg-feed-layout has-sponsored-sidebar">
        <div class="mg-feed-main">
          <nav class="mg-feed-tabs mg-feed-tabs-proxy" aria-label="Feed views" hidden>
            <button type="button" class="<?= $feedView === 'discover' ? 'is-active' : '' ?>" data-feed-tab="discover">Discover</button>
            <button type="button" class="<?= $feedView === 'following' ? 'is-active' : '' ?>" data-feed-tab="following">Following</button>
            <button type="button" class="<?= $feedView === 'mine' ? 'is-active' : '' ?>" data-feed-tab="mine">My posts</button>
          </nav>

          <div class="mg-hidden" data-owner-filter-wrap hidden aria-hidden="true">
            <label>Status
              <select data-owner-filter aria-label="Filter my posts by status">
                <option value="">All posts</option>
                <option value="draft">Drafts</option>
                <option value="published">Published</option>
                <option value="archived">Archived</option>
              </select>
            </label>
          </div>

          <section class="mg-feed-stories-shell" data-feed-stories aria-label="Stories">
            <div class="mg-feed-stories-header">
              <div>
                <span>Stories</span>
                <h2>24-hour local updates</h2>
              </div>
              <div class="mg-stories-status" data-stories-status role="status" aria-live="polite"></div>
            </div>
            <div class="mg-feed-stories-tray" data-stories-tray aria-label="Story cards"></div>
          </section>

          <?php
          $post_composer_id_suffix = 'feed';
          $post_composer_hidden = true;
          require __DIR__ . '/includes/social-feed-composer.php';
          ?>

          <div class="mg-hidden">
            <span data-feed-kicker>Public discovery</span>
            <span data-feed-title>Discover posts</span>
            <span data-feed-description>Public and unlisted posts from active profiles.</span>
          </div>

          <div class="mg-feed-status" data-feed-status role="status" aria-live="polite"></div>
          <section class="mg-feed-loading" data-feed-loading aria-busy="true">
            <?php for ($i=0; $i<3; $i++): ?><article class="mg-feed-card is-skeleton" aria-hidden="true"></article><?php endfor; ?>
          </section>
          <section class="mg-feed-message mg-hidden" data-feed-signin>
            <h2>Sign in to use this feed.</h2>
            <p>Following, publishing, saving, commenting, and owner post management require an account.</p>
            <a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a>
          </section>
          <section class="mg-feed-message mg-hidden" data-feed-empty>
            <h2>No posts yet.</h2>
            <p data-feed-empty-message>Published posts will appear here.</p>
          </section>
          <section class="mg-feed-message mg-hidden" data-feed-error role="alert">
            <h2>Unable to load the feed.</h2>
            <p data-feed-error-message>Please try again.</p>
            <button class="mg-btn mg-btn-primary" type="button" data-feed-retry>Try again</button>
          </section>

          <section class="mg-feed-list mg-hidden" data-feed-list aria-label="Social posts"></section>
          <div class="mg-feed-pagination mg-hidden" data-feed-pagination>
            <button class="mg-btn mg-btn-soft" type="button" data-feed-more>Load more posts</button>
          </div>
        </div>

        <aside class="mg-feed-ad-sidebar" aria-label="Sponsored local opportunities">
          <section class="mg-sponsored-placement" data-mg-ad-placement="sidebar_sponsored_card" data-mg-ad-limit="1"></section>
        </aside>
      </div>
    </section>
  </div>

  <div class="mg-story-modal" data-story-modal hidden>
    <div class="mg-story-modal-backdrop" data-story-modal-close></div>
    <section class="mg-story-modal-panel" role="dialog" aria-modal="true" aria-labelledby="mg-story-modal-title">
      <header class="mg-story-modal-head">
        <div>
          <span>Feed Story</span>
          <h2 id="mg-story-modal-title">Create a 24-hour story</h2>
          <p>Share one image or video clip. Videos must be 30 seconds or less.</p>
        </div>
        <button type="button" data-story-modal-close aria-label="Close story creator">×</button>
      </header>
      <form class="mg-story-form" data-story-form>
        <label class="mg-story-upload-card">
          <input type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime" data-story-media-input>
          <span data-story-upload-preview><span>Choose image or video</span></span>
          <strong>Upload story media</strong>
          <small>JPG, PNG, WebP, MP4, WebM, or MOV. Video clips max at 30 seconds.</small>
        </label>
        <div class="mg-story-upload-status" data-story-upload-status role="status" aria-live="polite"></div>
        <label>Caption <span>Optional</span>
          <textarea name="caption" rows="3" maxlength="280" placeholder="Add a short update, offer teaser, or local moment."></textarea>
        </label>
        <div class="mg-story-merchant-options" data-story-merchant-options hidden>
          <label>Attach Product or Campaign
            <select name="linked_target">
              <option value="none:">No product or campaign</option>
            </select>
          </label>
          <small>Rewards are distributed through campaigns, so direct reward attachment is excluded.</small>
        </div>
        <label>CTA label <span>Optional</span>
          <input name="cta_label" maxlength="80" placeholder="Auto-fills for products and campaigns">
        </label>
        <footer class="mg-story-modal-actions">
          <button class="mg-btn mg-btn-ghost" type="button" data-story-modal-close>Cancel</button>
          <button class="mg-btn mg-btn-primary" type="submit" data-story-publish>Publish Story</button>
        </footer>
      </form>
    </section>
  </div>
  <div class="mg-story-viewer" data-story-viewer hidden></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
