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
$page_styles = ['/assets/css/public-app-header.css','/assets/css/social-feed.css','/assets/css/social-feed-upload.css','/assets/css/feed-centered-layout.css','/assets/css/store-presence-feed.css','/assets/css/avatar-anchor-consent.css'];
$page_scripts = ['/assets/js/social-feed.js','/assets/js/social-feed-sidebar-tabs.js','/assets/js/social-feed-upload.js','/assets/js/store-presence-feed.js','/assets/js/avatar-anchor-consent.js'];
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
      <div class="mg-container mg-feed-layout">
        <div class="mg-feed-main">
          <nav class="mg-feed-tabs mg-feed-tabs-proxy" aria-label="Feed views" hidden>
            <button type="button" class="<?= $feedView === 'discover' ? 'is-active' : '' ?>" data-feed-tab="discover">Discover</button>
            <button type="button" class="<?= $feedView === 'following' ? 'is-active' : '' ?>" data-feed-tab="following">Following</button>
            <button type="button" class="<?= $feedView === 'mine' ? 'is-active' : '' ?>" data-feed-tab="mine">My posts</button>
          </nav>

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

          <label class="mg-feed-owner-filter mg-hidden" data-owner-filter-wrap>Post status
            <select data-owner-filter>
              <option value="">All posts</option>
              <option value="draft">Drafts</option>
              <option value="published">Published</option>
              <option value="archived">Archived</option>
              <option value="retired">Retired</option>
            </select>
          </label>

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
      </div>
    </section>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
