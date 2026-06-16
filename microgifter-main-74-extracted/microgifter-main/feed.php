<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Feed | Microgifter';
$page_section = 'feed';
$header_mode = 'public';
$page_styles = ['/assets/css/social-feed.css'];
$page_scripts = ['/assets/js/social-feed.js'];
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
      <section class="mg-feed-composer mg-hidden" data-post-composer aria-labelledby="mg-feed-composer-title">
        <div class="mg-feed-section-heading">
          <div>
            <span class="mg-kicker">Post composer</span>
            <h2 id="mg-feed-composer-title" data-composer-title>Create a post</h2>
          </div>
          <button class="mg-btn mg-btn-ghost" type="button" data-composer-close>Close</button>
        </div>
        <form data-post-form>
          <input type="hidden" name="post_id">
          <label>Headline
            <input type="text" name="headline" maxlength="240" placeholder="What is happening?">
          </label>
          <label>Post body
            <textarea name="body" rows="6" maxlength="10000" placeholder="Share an update, story, offer, or local gifting idea."></textarea>
          </label>
          <div class="mg-feed-form-grid">
            <label>Visibility
              <select name="visibility">
                <option value="public">Public</option>
                <option value="unlisted">Unlisted</option>
                <option value="followers">Followers</option>
                <option value="subscribers">Subscribers</option>
                <option value="premium">Premium members</option>
                <option value="private">Private draft</option>
              </select>
            </label>
            <label>Post type
              <select name="post_type">
                <option value="simple">Text update</option>
                <option value="image">Image</option>
                <option value="audio">Audio</option>
                <option value="video">Video</option>
                <option value="greeting_card">Greeting card</option>
                <option value="multimedia_card">Multimedia card</option>
                <option value="collab">Collaboration</option>
              </select>
            </label>
          </div>
          <details class="mg-feed-attachments">
            <summary>Attachments and access</summary>
            <div class="mg-feed-form-grid">
              <label>Product public ID
                <input type="text" name="product_id" maxlength="80" placeholder="Optional product UUID">
              </label>
              <label>Microgift public ID
                <input type="text" name="microgift_id" maxlength="80" placeholder="Optional Microgift UUID">
              </label>
              <label>Subscription plan public ID
                <input type="text" name="subscription_plan_id" maxlength="80" placeholder="Required for subscriber visibility">
              </label>
              <label>Link URL
                <input type="url" name="link_url" maxlength="500" placeholder="https://example.com">
              </label>
            </div>
            <label>Media URLs
              <textarea name="media_urls" rows="4" placeholder="One image, audio, video, or link URL per line. Maximum 8."></textarea>
            </label>
          </details>
          <div class="mg-feed-composer-actions">
            <button class="mg-btn mg-btn-soft" type="button" data-post-save-draft>Save draft</button>
            <button class="mg-btn mg-btn-primary" type="submit" data-post-publish>Publish</button>
            <button class="mg-btn mg-btn-ghost mg-hidden" type="button" data-post-cancel-edit>Cancel edit</button>
          </div>
          <div class="mg-feed-action-status" data-composer-status role="status" aria-live="polite"></div>
        </form>
      </section>

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
