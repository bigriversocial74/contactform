<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Pricing | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'pricing',
]);
$plans = [
    ['Free', '$0', 'forever', 'Try one proof-based campaign.', 'Get Started Free'],
    ['Team', '$9', 'user / month', 'Run team challenges and track consistency.', 'Start Team Plan'],
    ['Organization', '$19', 'user / month', 'Advanced reward rules and admin review.', 'Start Organization Plan'],
    ['Enterprise', 'Custom', '', 'Custom programs, permissions, and support.', 'Contact Sales'],
];
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Pricing concept</span>
    <h1>Simple pricing for action-based training rewards.</h1>
    <p class="labs-copy">Launch proof-based training, track consistency, and run reward campaigns that keep people engaged.</p>
  </div>
</section>
<section class="labs-grid">
  <?php foreach ($plans as $plan): ?>
    <article class="labs-card">
      <h2><?php echo htmlspecialchars($plan[0], ENT_QUOTES, 'UTF-8'); ?></h2>
      <p><strong style="font-size:2.2rem"><?php echo htmlspecialchars($plan[1], ENT_QUOTES, 'UTF-8'); ?></strong> <span class="labs-muted"><?php echo htmlspecialchars($plan[2], ENT_QUOTES, 'UTF-8'); ?></span></p>
      <p class="labs-muted"><?php echo htmlspecialchars($plan[3], ENT_QUOTES, 'UTF-8'); ?></p>
      <a class="labs-btn labs-btn-primary" href="/cart.php"><?php echo htmlspecialchars($plan[4], ENT_QUOTES, 'UTF-8'); ?></a>
    </article>
  <?php endforeach; ?>
</section>
<?php labs_page_end(['section' => 'public']); ?>
