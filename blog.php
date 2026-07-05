<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/blog/blog-functions.php';
require_once __DIR__ . '/includes/blog/blog-settings.php';
require_once __DIR__ . '/includes/blog/blog-showcase.php';

$pdo = mg_db();
$schema = mg_blog_schema_ready($pdo);
$settings = mg_blog_get_settings($pdo);
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(9, min(24, (int) ($settings['posts_per_page'] ?? 9)));
$offset = ($page - 1) * $limit;
$categories = [];
$posts = [];
$totalPosts = 0;
$totalPages = 1;
$showcaseMode = 'published';

if ($schema['ready']) {
    $categories = mg_blog_categories($pdo, true);
    $showcase = mg_blog_showcase_posts($pdo, ['limit' => $limit, 'offset' => $offset]);
    $posts = $showcase['posts'];
    $totalPosts = (int) $showcase['total'];
    $showcaseMode = (string) $showcase['mode'];
    $totalPages = max(1, (int) ceil($totalPosts / $limit));
}

function mg_blog_page_url(int $page): string
{
    return '/blog.php' . ($page > 1 ? '?page=' . $page : '');
}

$page_title = $settings['blog_title'] . ' | Local Commerce, Loyalty CRM & Pre Sale Revenue';
$page_section = 'blog';
$header_mode = 'public';
$page_body_class = 'mg-blog-page';
$page_styles = ['/assets/css/blog.css', '/assets/css/blog-launch.css', '/assets/css/blog-db-polish.css'];
$page_meta = [
    'description' => $settings['blog_description'],
    'canonical' => 'https://microgifter.com/blog.php',
    'og_title' => $settings['blog_title'],
    'og_description' => $settings['blog_description'],
    'og_image' => $settings['default_social_image'],
];
$page_manifest = [
    'id' => 'blog',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'description' => $page_meta['description'],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Explore', 'href' => '/discover.php'],
            ['label' => 'Blog', 'href' => '/blog.php'],
            ['label' => 'Pricing', 'href' => '/pricing.php'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'blog', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<main class="mg-blog-main">
  <section class="mg-blog-wrap mg-blog-topbar" aria-labelledby="mgBlogTitle">
    <div>
      <span class="mg-blog-eyebrow">Microgifter Content Studio</span>
      <h1 id="mgBlogTitle">Latest articles</h1>
    </div>
    <a class="mg-blog-topbar-action" href="/learn-more.php">Book a demo</a>
  </section>

  <?php if (!$schema['ready']): ?>
    <section class="mg-blog-wrap mg-blog-empty-panel">
      <span class="mg-blog-eyebrow">Blog setup required</span>
      <h2>The public blog is ready for the SQL migration.</h2>
      <p>Run <code>database/microgifter_blog_module.sql</code> to create the blog tables, categories, tags, and admin permissions.</p>
    </section>
  <?php else: ?>
    <section class="mg-blog-wrap mg-blog-layout">
      <div class="mg-blog-content-column">
        <?php if ($showcaseMode === 'seed_drafts'): ?>
          <div class="mg-blog-data-note"><span>Showing database seed posts from Content Studio. Publish posts in admin when ready for launch.</span></div>
        <?php endif; ?>

        <?php if ($posts): ?>
          <div class="mg-blog-grid">
            <?php foreach ($posts as $index => $post): ?>
              <?php $tone = ['gift', 'chat', 'growth', 'megaphone', 'dashboard', 'folder', 'mail', 'map', 'bag'][$index % 9]; ?>
              <article class="mg-blog-card">
                <a class="mg-blog-card-media is-<?= mg_e($tone) ?>" href="<?= mg_e(mg_blog_public_post_url($post)) ?>" style="<?= !empty($post['featured_image']) ? 'background-image:url(' . mg_e((string) $post['featured_image']) . ')' : '' ?>">
                  <?php if (empty($post['featured_image'])): ?><span><?= mg_e(mb_substr((string) ($post['category_name'] ?? 'MG'), 0, 1)) ?></span><?php endif; ?>
                </a>
                <div class="mg-blog-card-body">
                  <div class="mg-blog-card-top">
                    <?php if (!empty($post['category_name'])): ?><a href="<?= mg_e(mg_blog_public_category_url(['slug' => (string) $post['category_slug']])) ?>"><?= mg_e((string) $post['category_name']) ?></a><?php endif; ?>
                  </div>
                  <h2><a href="<?= mg_e(mg_blog_public_post_url($post)) ?>"><?= mg_e((string) $post['title']) ?></a></h2>
                  <p><?= mg_e((string) $post['excerpt']) ?></p>
                  <div class="mg-blog-meta">
                    <span><?= $showcaseMode === 'seed_drafts' ? 'Draft sample' : mg_e(mg_blog_format_date($post['published_at'] ?? null)) ?></span>
                    <span><?= mg_blog_reading_time((string) $post['body']) ?> min read</span>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="mg-blog-empty-panel">
            <h3>No blog posts found.</h3>
            <p>Create or publish posts from Content Studio to populate this page.</p>
          </div>
        <?php endif; ?>

        <?php if ($posts && $totalPages > 1): ?>
          <nav class="mg-blog-pagination" aria-label="Blog pagination">
            <?php if ($page > 1): ?><a href="<?= mg_e(mg_blog_page_url($page - 1)) ?>">← Newer</a><?php endif; ?>
            <span>Page <?= (int) $page ?> of <?= (int) $totalPages ?></span>
            <?php if ($page < $totalPages): ?><a href="<?= mg_e(mg_blog_page_url($page + 1)) ?>">Older →</a><?php endif; ?>
          </nav>
        <?php endif; ?>
      </div>

      <aside class="mg-blog-sidebar" aria-label="Blog sidebar">
        <section class="mg-blog-category-panel">
          <h2>Categories</h2>
          <div class="mg-blog-category-list">
            <?php foreach ($categories as $category): ?>
              <a href="<?= mg_e(mg_blog_public_category_url($category)) ?>"><span><?= mg_e((string) $category['name']) ?></span><b aria-hidden="true">›</b></a>
            <?php endforeach; ?>
          </div>
        </section>
        <section class="mg-blog-sidebar-cta">
          <span class="mg-blog-cta-icon" aria-hidden="true">✦</span>
          <h2>Turn future demand into present-day revenue.</h2>
          <p>Pre-sell products, reward loyalty, and track claims from one connected commerce platform.</p>
          <a href="/learn-more.php">Book a demo <span aria-hidden="true">→</span></a>
        </section>
      </aside>
    </section>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
