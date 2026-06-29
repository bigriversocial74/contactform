<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Blog | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'blog',
]);
$posts = [
    ['Proof-based training rewards', 'How proof, action receipts, and rewards can help teams build consistency.'],
    ['Why streaks matter', 'A simple look at how visible progress helps people keep going.'],
    ['From task to reward', 'How Training Lab connects completed actions to reward progress.'],
];
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Training Lab Blog</span>
    <h1>Ideas for proof-based engagement.</h1>
    <p class="labs-copy">Static article cards for Stage 1. A full publishing system is not included yet.</p>
  </div>
</section>
<section class="labs-grid">
  <?php foreach ($posts as $post): ?>
    <article class="labs-card">
      <span class="labs-pill">Article</span>
      <h2><?php echo htmlspecialchars($post[0], ENT_QUOTES, 'UTF-8'); ?></h2>
      <p class="labs-muted"><?php echo htmlspecialchars($post[1], ENT_QUOTES, 'UTF-8'); ?></p>
      <a class="labs-btn" href="/blog-article.php">Read Article</a>
    </article>
  <?php endforeach; ?>
</section>
<?php labs_page_end(['section' => 'public']); ?>
