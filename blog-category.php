<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/blog/blog-functions.php';

$pdo = mg_db();
$schema = mg_blog_schema_ready($pdo);
$slug = mg_blog_slugify((string) ($_GET['slug'] ?? ''), '');
$category = $schema['ready'] && $slug !== '' ? mg_blog_category_by_slug($pdo, $slug) : null;
if (!$category) {
    http_response_code(404);
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 9;
$offset = ($page - 1) * $limit;
$posts = [];
$totalPosts = 0;
$totalPages = 1;
$categories = $schema['ready'] ? mg_blog_categories($pdo, true) : [];

if ($category) {
    $filters = [
        'category_slug' => (string) $category['slug'],
        'limit' => $limit,
        'offset' => $offset,
    ];
    $posts = mg_blog_list_public_posts($pdo, $filters);
    $totalPosts = mg_blog_count_public_posts($pdo, ['category_slug' => (string) $category['slug']]);
    $totalPages = max(1, (int) ceil($totalPosts / $limit));
}

function mg_blog_category_page_url(string $slug, int $page): string
{
    $query = ['slug' => $slug];
    if ($page > 1) {
        $query['page'] = $page;
    }
    return '/blog-category.php?' . http_build_query($query);
}

$page_title = $category ? (string) $category['name'] . ' | Microgifter Blog' : 'Blog Category Not Found | Microgifter';
$page_section = 'blog';
$header_mode = 'public';
$page_body_class = 'mg-blog-page';
$page_styles = ['/assets/css/blog.css', '/assets/css/blog-launch.css'];
$page_meta = [
    'description' => $category ? ((string) ($category['description'] ?: 'Microgifter articles in ' . $category['name'] . '.')) : 'Microgifter blog category not found.',
    'canonical' => 'https://microgifter.com/blog-category.php?slug=' . rawurlencode($slug),
    'og_title' => $category ? (string) $category['name'] . ' | Microgifter Blog' : 'Microgifter Blog',
    'og_description' => $category ? ((string) ($category['description'] ?: 'Microgifter category articles.')) : 'Microgifter blog category not found.',
    'robots' => $category ? '' : 'noindex',
];
$page_manifest = [
    'id' => 'blog-category',
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
    'onboarding' => ['enabled' => false, 'page' => 'blog-category', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<main class="mg-blog-main">
  <?php if (!$category): ?>
    <section class="mg-blog-wrap mg-blog-empty-panel">
      <span class="mg-blog-eyebrow">Category not found</span>
      <h1>This blog category is not available.</h1>
      <p>The category may be inactive or the URL may have changed.</p>
      <a class="mg-blog-primary-link" href="/blog.php">Back to blog</a>
    </section>
  <?php else: ?>
    <section class="mg-blog-wrap mg-blog-topbar mg-blog-category-topbar" aria-labelledby="mgBlogCategoryTitle">
      <div>
        <a class="mg-blog-back" href="/blog.php">← All articles</a>
        <span class="mg-blog-eyebrow">Blog category</span>
        <h1 id="mgBlogCategoryTitle"><?= mg_e((string) $category['name']) ?></h1>
        <?php if (!empty($category['description'])): ?><p><?= mg_e((string) $category['description']) ?></p><?php endif; ?>
      </div>
      <span class="mg-blog-category-count"><?= (int) $totalPosts ?> article<?= $totalPosts === 1 ? '' : 's' ?></span>
    </section>

    <section class="mg-blog-wrap mg-blog-layout">
      <div class="mg-blog-content-column">
        <?php if (!$posts): ?>
          <div class="mg-blog-empty-panel">
            <h3>No published posts in this category yet.</h3>
            <p>Publish posts in Content Studio to populate this category page.</p>
          </div>
        <?php else: ?>
          <div class="mg-blog-grid">
            <?php foreach ($posts as $index => $post): ?>
              <?php $tone = ['gift', 'chat', 'growth', 'megaphone', 'dashboard', 'folder', 'mail', 'map', 'bag'][$index % 9]; ?>
              <article class="mg-blog-card">
                <a class="mg-blog-card-media is-<?= mg_e($tone) ?>" href="<?= mg_e(mg_blog_public_post_url($post)) ?>" style="<?= !empty($post['featured_image']) ? 'background-image:url(' . mg_e((string) $post['featured_image']) . ')' : '' ?>">
                  <?php if (empty($post['featured_image'])): ?><span><?= mg_e(mb_substr((string) ($post['category_name'] ?? 'MG'), 0, 1)) ?></span><?php endif; ?>
                </a>
                <div class="mg-blog-card-body">
                  <div class="mg-blog-card-top"><span><?= mg_e((string) ($post['category_name'] ?? $category['name'])) ?></span></div>
                  <h2><a href="<?= mg_e(mg_blog_public_post_url($post)) ?>"><?= mg_e((string) $post['title']) ?></a></h2>
                  <p><?= mg_e((string) $post['excerpt']) ?></p>
                  <div class="mg-blog-meta">
                    <span><?= mg_e(mg_blog_format_date($post['published_at'] ?? null)) ?></span>
                    <span><?= mg_blog_reading_time((string) $post['body']) ?> min read</span>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <?php if ($totalPages > 1): ?>
            <nav class="mg-blog-pagination" aria-label="Category pagination">
              <?php if ($page > 1): ?><a href="<?= mg_e(mg_blog_category_page_url((string) $category['slug'], $page - 1)) ?>">← Newer</a><?php endif; ?>
              <span>Page <?= (int) $page ?> of <?= (int) $totalPages ?></span>
              <?php if ($page < $totalPages): ?><a href="<?= mg_e(mg_blog_category_page_url((string) $category['slug'], $page + 1)) ?>">Older →</a><?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <aside class="mg-blog-sidebar" aria-label="Blog sidebar">
        <section class="mg-blog-category-panel">
          <h2>All categories</h2>
          <div class="mg-blog-category-list">
            <?php foreach ($categories as $item): ?>
              <a class="<?= (string) $item['slug'] === (string) $category['slug'] ? 'is-active' : '' ?>" href="<?= mg_e(mg_blog_public_category_url($item)) ?>"><span><?= mg_e((string) $item['name']) ?></span><b aria-hidden="true">›</b></a>
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