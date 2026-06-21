<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Feed | Microgifter';
$page_section = 'feed';
$header_mode = 'public';
$page_styles = ['/assets/css/social-feed.css','/assets/css/social-feed-upload.css'];
$page_scripts = ['/assets/js/social-feed.js','/assets/js/social-feed-upload.js'];
$page_manifest = [
    'id' => 'feed',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-social-feed-page',
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Home', 'href' => '/index.php'],
            ['label' => 'Discover', 'href' => '/discover.php'],
            ['label' => 'Feed', 'href' => '/feed.php'],
            ['label' => 'Learn More', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'feed', 'sections' => []],
];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-feed-shell" data-social-feed>
  <header class="mg-feed-hero">
    <div class="mg-container mg-feed-hero-grid">
      <div>
        <span class="mg-kicker">Microgifter community</span>
        <h1>Publish updates and follow meaningful local gifting.</h1>
        <p>Share products, Microgifts, member-only updates, and the stories behind local gifting experiences.</p>
      </div>
      <div class="mg-feed-hero-actions">
        <button class="mg-btn mg-btn-primary" type="button" data-composer-toggle>Create a post</button>
        <a class="mg-btn mg-btn-ghost" href="/discover.php">Discover profiles</a>
      </div>
    </div>
  </header>

  <div class="mg-container mg-feed-layout">
    <aside class="mg-feed-sidebar">
      <nav class="mg-feed-tabs" aria-label="Feed views">
        <button type="button" class="is-active" data-feed-tab="discover">Discover</button>
        <button type="button" data-feed-tab="following">Following</button>
        <button type="button" data-feed-tab="mine">My posts</button>
      </nav>
      <div class="mg-feed-sidebar-note">
        <strong>Visibility matters</strong>
        <p>Public posts can appear in discovery. Followers and subscriber posts are delivered only to eligible viewers.</p>
      </div>
    </aside>

    <div class="mg-feed-main">
      <?php
      $post_composer_id_suffix = 'feed';
      $post_composer_hidden = true;
      require __DIR__ . '/includes/social-feed-composer.php';
      ?>

      <section class="mg-feed-toolbar" aria-labelledby="mg-feed-view-title">
        <div>
          <span class="mg-kicker" data-feed-kicker>Public discovery</span>
          <h2 id="mg-feed-view-title" data-feed-title>Discover posts</h2>
          <p data-feed-description>Public and unlisted posts from active profiles.</p>
        </div>
        <label class="mg-feed-owner-filter mg-hidden" data-owner-filter-wrap>Post status
          <select data-owner-filter>
            <option value="">All posts</option>
            <option value="draft">Drafts</option>
            <option value="published">Published</option>
            <option value="archived">Archived</option>
            <option value="retired">Deleted</option>
          </select>
        </label>
      </section>

      <div class="mg-feed-status" data-feed-status role="status" aria-live="polite"></div>
      <section class="mg-feed-loading" data-feed-loading aria-busy="true">
        <?php for ($i=0; $i<3; $i++): ?><article class="mg-feed-card is-skeleton" aria-hidden="true"></article><?php endfor; ?>
      </section>
      <section class="mg-feed-message mg-hidden" data-feed-signin>
        <h2>Sign in to use this feed.</h2>
        <p>Following, publishing, saving, commenting, and owner post management require an account.</p>
        <a class="mg-btn mg-btn-primary" href="/signin.php?return=%2Ffeed.php">Sign in</a>
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
<?php require __DIR__ . '/includes/footer.php'; ?>
