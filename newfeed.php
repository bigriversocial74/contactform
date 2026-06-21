<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'My Feed | Microgifter';
$page_section = 'feed';
$header_mode = 'public';
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
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Discover', 'href' => '/discover.php'],
            ['label' => 'Feed', 'href' => '/feed.php'],
            ['label' => 'Learn More', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'newfeed', 'sections' => []],
];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-feed-shell" data-newsfeed>
  <div class="mg-container mg-feed-layout">
    <aside class="mg-feed-sidebar">
      <nav class="mg-feed-tabs" aria-label="Feed views">
        <a href="/feed.php">Discover</a>
        <a class="is-active" href="/newfeed.php">Following</a>
        <a href="/feed.php#my-posts">My posts</a>
      </nav>
    </aside>

    <div class="mg-feed-main">
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
