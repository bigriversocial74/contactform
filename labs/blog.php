<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Blog | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'blog',
]);
$posts = [
    ['Proof-based training programs', 'How proof, action receipts, and progress states can help teams build consistency.', 'Product'],
    ['Why streaks matter', 'A simple look at how visible progress helps people keep going.', 'Behavior'],
    ['From task to progress', 'How Training Lab connects completed actions to a clear participant journey.', 'Workflow'],
];
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Training Lab Blog</span>
    <h1>Ideas for proof-based engagement.</h1>
    <p class="labs-copy">Static article cards for Stage 1. A full publishing system is not included yet.</p>
  </div>
</section>
<section class="labs-section-band">
  <span class="labs-eyebrow">Editorial direction</span>
  <h2>Short, practical writing for teams running action-based programs.</h2>
  <p class="labs-copy">The blog shell is designed for product education, customer examples, and simple workflow explainers.</p>
</section>
<section class="labs-grid">
  <?php foreach ($posts as $post): ?>
    <article class="labs-card labs-price-card">
      <span class="labs-pill"><?php echo htmlspecialchars($post[2], ENT_QUOTES, 'UTF-8'); ?></span>
      <h2><?php echo htmlspecialchars($post[0], ENT_QUOTES, 'UTF-8'); ?></h2>
      <p class="labs-muted"><?php echo htmlspecialchars($post[1], ENT_QUOTES, 'UTF-8'); ?></p>
      <a class="labs-btn" href="/blog-article.php" style="margin-top:auto">Read Article</a>
    </article>
  <?php endforeach; ?>
</section>
<?php labs_page_end(['section' => 'public']); ?>
