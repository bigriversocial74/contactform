<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/blog/blog-functions.php';
require_once __DIR__ . '/includes/blog/blog-settings.php';
require_once __DIR__ . '/includes/blog/blog-showcase.php';

$pdo = mg_db();
$schema = mg_blog_schema_ready($pdo);
$slug = mg_blog_slugify((string) ($_GET['slug'] ?? ''), '');
$post = $schema['ready'] && $slug !== '' ? mg_blog_get_showcase_post($pdo, $slug) : null;
$isSeedPreview = $post && (string) ($post['status'] ?? '') === 'draft' && in_array((string) ($post['slug'] ?? ''), mg_blog_seed_post_slugs(), true);
if (!$post) {
    http_response_code(404);
}

$related = [];
$tags = [];
if ($post) {
    $tags = mg_blog_tag_names($pdo, (int) $post['id']);
    if (!$isSeedPreview && !empty($post['category_slug'])) {
        $related = array_values(array_filter(
            mg_blog_list_public_posts($pdo, ['category_slug' => (string) $post['category_slug'], 'limit' => 4]),
            static fn(array $candidate): bool => (int) $candidate['id'] !== (int) $post['id']
        ));
    }
}

$settings = mg_blog_get_settings($pdo);
$effectiveCta = $post ? (string) ($post['cta_type'] ?? 'none') : 'none';
if ($effectiveCta === 'none') {
    $effectiveCta = (string) $settings['default_cta'];
}

$title = $post ? (string) ($post['seo_title'] ?: $post['title']) . ' | Microgifter Blog' : 'Article Not Found | Microgifter Blog';
$description = $post ? (string) ($post['seo_description'] ?: $post['excerpt']) : 'The requested Microgifter blog article could not be found.';
$canonical = $post && !empty($post['canonical_url']) ? (string) $post['canonical_url'] : 'https://microgifter.com/blog-post.php?slug=' . rawurlencode($slug);
$shareTitle = $post ? (string) $post['title'] : 'Microgifter Blog';
$shareUrl = $canonical;

$page_title = $title;
$page_section = 'blog';
$header_mode = 'public';
$page_body_class = 'mg-blog-page mg-blog-article-page';
$page_styles = ['/assets/css/blog.css', '/assets/css/blog-share.css', '/assets/css/blog-db-polish.css'];
$page_meta = [
    'description' => $description,
    'canonical' => $canonical,
    'og_title' => $post ? (string) $post['title'] : 'Microgifter Blog',
    'og_description' => $description,
    'og_image' => $post ? (string) ($post['featured_image'] ?: $settings['default_social_image']) : (string) $settings['default_social_image'],
    'robots' => $post && !$isSeedPreview ? '' : 'noindex',
];
$page_manifest = [
    'id' => 'blog-post',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'description' => $description,
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Explore', 'href' => '/discover.php'],
            ['label' => 'Blog', 'href' => '/blog.php'],
            ['label' => 'Pricing', 'href' => '/pricing.php'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'blog-post', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<main class="mg-blog-main mg-blog-post-main">
  <?php if (!$post): ?>
    <section class="mg-blog-wrap mg-blog-empty-panel mg-blog-not-found">
      <span class="mg-blog-eyebrow">Article not found</span>
      <h1>This Microgifter article is not available.</h1>
      <p>The article may still be in draft, archived, or the URL may have changed.</p>
      <a class="mg-blog-primary-link" href="/blog.php">Back to blog</a>
    </section>
  <?php else: ?>
    <?php if ($isSeedPreview): ?>
      <section class="mg-blog-wrap mg-blog-data-note"><span>Database seed draft preview. Publish this post in Content Studio when ready for public launch.</span></section>
    <?php endif; ?>
    <article class="mg-blog-article">
      <header class="mg-blog-wrap mg-blog-post-hero">
        <div class="mg-blog-post-kicker-row">
          <a class="mg-blog-back" href="/blog.php">← Back to blog</a>
          <?php if (!empty($post['category_name'])): ?>
            <a class="mg-blog-category-pill" href="<?= mg_e(mg_blog_public_category_url(['slug' => (string) $post['category_slug']])) ?>"><?= mg_e((string) $post['category_name']) ?></a>
          <?php endif; ?>
        </div>
        <div class="mg-blog-post-hero-grid">
          <div class="mg-blog-post-title-block">
            <span class="mg-blog-eyebrow">Microgifter insight</span>
            <h1><?= mg_e((string) $post['title']) ?></h1>
            <p><?= mg_e((string) $post['excerpt']) ?></p>
            <div class="mg-blog-meta mg-blog-post-meta">
              <span><?= mg_e(mg_blog_author_name($post)) ?></span>
              <span><?= $isSeedPreview ? 'Draft sample' : mg_e(mg_blog_format_date($post['published_at'] ?? null)) ?></span>
              <span><?= mg_blog_reading_time((string) $post['body']) ?> min read</span>
            </div>
          </div>
          <div class="mg-blog-post-summary-card">
            <span>Article brief</span>
            <strong><?= mg_e((string) ($post['category_name'] ?? 'Microgifter')) ?></strong>
            <p><?= mg_e((string) $post['excerpt']) ?></p>
          </div>
        </div>
      </header>

      <?php if (!empty($post['featured_image'])): ?>
        <figure class="mg-blog-wrap mg-blog-article-image">
          <img src="<?= mg_e((string) $post['featured_image']) ?>" alt="<?= mg_e((string) ($post['featured_image_alt'] ?: $post['title'])) ?>">
        </figure>
      <?php endif; ?>

      <div class="mg-blog-wrap mg-blog-article-layout">
        <div class="mg-blog-article-card">
          <div class="mg-blog-article-body">
            <?= (string) $post['body'] ?>
            <?= mg_blog_render_cta($effectiveCta) ?>
          </div>
        </div>

        <aside class="mg-blog-article-aside" aria-label="Article tools">
          <section class="mg-blog-article-side-card">
            <h2>Article details</h2>
            <dl>
              <div><dt>Author</dt><dd><?= mg_e(mg_blog_author_name($post)) ?></dd></div>
              <div><dt>Status</dt><dd><?= $isSeedPreview ? 'Draft sample' : 'Published' ?></dd></div>
              <div><dt>Reading time</dt><dd><?= mg_blog_reading_time((string) $post['body']) ?> min</dd></div>
            </dl>
          </section>

          <section class="mg-blog-article-side-card">
            <h2>Share article</h2>
            <div class="mg-blog-share-list">
              <div class="mg-blog-share-row">
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= rawurlencode($shareUrl) ?>" target="_blank" rel="noopener">LinkedIn</a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($shareUrl) ?>" target="_blank" rel="noopener">Facebook</a>
                <a href="https://twitter.com/intent/tweet?url=<?= rawurlencode($shareUrl) ?>&text=<?= rawurlencode($shareTitle) ?>" target="_blank" rel="noopener">X/Twitter</a>
                <a href="mailto:?subject=<?= rawurlencode($shareTitle) ?>&body=<?= rawurlencode($shareUrl) ?>">Email</a>
              </div>
              <button class="mg-blog-copy-link" type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?= mg_e($shareUrl) ?>');this.textContent='Copied link';">Copy link</button>
              <p class="mg-blog-share-note"><?= mg_e($shareUrl) ?></p>
            </div>
          </section>

          <?php if ($tags): ?>
            <section class="mg-blog-article-side-card">
              <h2>Tags</h2>
              <div class="mg-blog-tag-list">
                <?php foreach ($tags as $tag): ?><span><?= mg_e($tag) ?></span><?php endforeach; ?>
              </div>
            </section>
          <?php endif; ?>

          <section class="mg-blog-sidebar-cta">
            <span class="mg-blog-cta-icon" aria-hidden="true">✦</span>
            <h2>Build merchant growth around claimable rewards.</h2>
            <p>Use campaigns, social gifting, and customer recovery flows to create measurable future demand.</p>
            <a href="/learn-more.php">Start a conversation <span aria-hidden="true">→</span></a>
          </section>
        </aside>
      </div>
    </article>

    <?php if ($related): ?>
      <section class="mg-blog-wrap mg-blog-related">
        <div class="mg-blog-related-head">
          <span class="mg-blog-eyebrow">Related reading</span>
          <h2>More from this category</h2>
        </div>
        <div class="mg-blog-grid is-related">
          <?php foreach (array_slice($related, 0, 3) as $index => $item): ?>
            <?php $tone = ['gift', 'chat', 'growth'][$index % 3]; ?>
            <article class="mg-blog-card">
              <a class="mg-blog-card-media is-<?= mg_e($tone) ?>" href="<?= mg_e(mg_blog_public_post_url($item)) ?>" style="<?= !empty($item['featured_image']) ? 'background-image:url(' . mg_e((string) $item['featured_image']) . ')' : '' ?>">
                <?php if (empty($item['featured_image'])): ?><span><?= mg_e(mb_substr((string) ($item['category_name'] ?? 'MG'), 0, 1)) ?></span><?php endif; ?>
              </a>
              <div class="mg-blog-card-body">
                <div class="mg-blog-card-top"><?= !empty($item['category_name']) ? '<span>' . mg_e((string) $item['category_name']) . '</span>' : '' ?></div>
                <h2><a href="<?= mg_e(mg_blog_public_post_url($item)) ?>"><?= mg_e((string) $item['title']) ?></a></h2>
                <p><?= mg_e((string) $item['excerpt']) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if (!$isSeedPreview): ?><script type="application/ld+json"><?= json_encode([
      '@context' => 'https://schema.org',
      '@type' => 'Article',
      'headline' => (string) $post['title'],
      'description' => (string) $post['excerpt'],
      'image' => !empty($post['featured_image']) ? 'https://microgifter.com' . (string) $post['featured_image'] : null,
      'author' => ['@type' => 'Person', 'name' => mg_blog_author_name($post)],
      'publisher' => ['@type' => 'Organization', 'name' => 'Microgifter'],
      'datePublished' => (string) ($post['published_at'] ?? $post['created_at'] ?? ''),
      'dateModified' => (string) ($post['updated_at'] ?? ''),
      'mainEntityOfPage' => $canonical,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script><?php endif; ?>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
