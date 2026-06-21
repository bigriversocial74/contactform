<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'My Feed | Microgifter';
$page_section = 'feed';
$header_mode = 'account';
$page_styles = ['/assets/css/social-feed.css','/assets/css/newsfeed.css'];
$page_scripts = ['/assets/js/newsfeed.js'];
$page_manifest = [
    'id' => 'newfeed',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-social-feed-page mg-newsfeed-page',
    'onboarding' => ['enabled' => false, 'page' => 'newfeed', 'sections' => []],
];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-feed-shell" data-newsfeed>
  <header class="mg-feed-hero">
    <div class="mg-container mg-feed-hero-grid">
      <div>
        <span class="mg-kicker">Following feed</span>
        <h1>My Feed</h1>
        <p>Latest posts from profiles you follow. No public discovery filler, no owner posts unless you follow that profile.</p>
      </div>
      <div class="mg-feed-hero-actions">
        <a class="mg-btn mg-btn-primary" href="/discover.php">Find profiles to follow</a>
        <a class="mg-btn mg-btn-ghost" href="/feed.php">Open community feed</a>
      </div>
    </div>
  </header>

  <div class="mg-container mg-feed-layout">
    <aside class="mg-feed-sidebar">
      <nav class="mg-feed-tabs" aria-label="Feed views">
        <a class="is-active" href="/newfeed.php">Following</a>
        <a href="/feed.php">Discover</a>
        <a href="/feed.php#my-posts">My posts</a>
      </nav>
      <div class="mg-feed-sidebar-note">
        <strong>Only following</strong>
        <p>This feed is limited to posts from users you actively follow.</p>
      </div>
    </aside>

    <div class="mg-feed-main">
      <section class="mg-feed-toolbar" aria-labelledby="mg-newsfeed-view-title">
        <div>
          <span class="mg-kicker">Your network</span>
          <h2 id="mg-newsfeed-view-title">Latest from people you follow</h2>
          <p>Posts are ordered newest first and respect mute, block, visibility, follower, and subscriber access rules.</p>
        </div>
      </section>

      <div class="mg-feed-status" data-newsfeed-status role="status" aria-live="polite"></div>
      <section class="mg-feed-loading" data-newsfeed-loading aria-busy="true">
        <?php for ($i=0; $i<3; $i++): ?><article class="mg-feed-card is-skeleton" aria-hidden="true"></article><?php endfor; ?>
      </section>
      <section class="mg-feed-message mg-hidden" data-newsfeed-empty>
        <h2>No following posts yet.</h2>
        <p>Follow merchant, creator, or customer profiles to build your personal feed.</p>
        <a class="mg-btn mg-btn-primary" href="/discover.php">Discover profiles</a>
      </section>
      <section class="mg-feed-message mg-hidden" data-newsfeed-error role="alert">
        <h2>Unable to load your feed.</h2>
        <p data-newsfeed-error-message>Please try again.</p>
        <button class="mg-btn mg-btn-primary" type="button" data-newsfeed-retry>Try again</button>
      </section>

      <section class="mg-feed-list mg-hidden" data-newsfeed-list aria-label="Following feed posts"></section>
      <div class="mg-feed-pagination mg-hidden" data-newsfeed-pagination>
        <button class="mg-btn mg-btn-soft" type="button" data-newsfeed-more>Load more posts</button>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
