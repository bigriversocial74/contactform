<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Pricing | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'pricing',
]);
$plans = [
    ['Free', '$0', 'forever', 'Try one proof-based campaign.', 'Get Started Free', ['1 active campaign', '10 participants', 'Basic proof states']],
    ['Team', '$9', 'user / month', 'Run team challenges and track consistency.', 'Start Team Plan', ['Unlimited campaigns', '100 participants', 'Streaks and milestones']],
    ['Organization', '$19', 'user / month', 'Advanced reward rules and review queues.', 'Start Organization Plan', ['Unlimited participants', 'Admin review queue', 'Custom branding']],
    ['Enterprise', 'Custom', '', 'Custom programs, permissions, and support.', 'Contact Sales', ['SSO later', 'Custom reporting', 'Dedicated support']],
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
    <article class="labs-card labs-price-card<?php echo $plan[0] === 'Organization' ? ' is-featured' : ''; ?>">
      <h2><?php echo htmlspecialchars($plan[0], ENT_QUOTES, 'UTF-8'); ?></h2>
      <div class="labs-price"><?php echo htmlspecialchars($plan[1], ENT_QUOTES, 'UTF-8'); ?></div>
      <p class="labs-muted"><?php echo htmlspecialchars($plan[2], ENT_QUOTES, 'UTF-8'); ?></p>
      <p class="labs-muted"><?php echo htmlspecialchars($plan[3], ENT_QUOTES, 'UTF-8'); ?></p>
      <ul class="labs-feature-list">
        <?php foreach ($plan[5] as $feature): ?>
          <li><?php echo htmlspecialchars($feature, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
      <a class="labs-btn labs-btn-primary" href="/cart.php" style="margin-top:auto"><?php echo htmlspecialchars($plan[4], ENT_QUOTES, 'UTF-8'); ?></a>
    </article>
  <?php endforeach; ?>
</section>
<section class="labs-section-band">
  <span class="labs-eyebrow">Every plan includes</span>
  <h2>Proof uploads, action receipts, progress tracking, and reward rules.</h2>
  <p class="labs-copy">Stage 1 shows the commercial flow visually. Real billing, subscriptions, and plan enforcement come later only after approval.</p>
</section>
<?php labs_page_end(['section' => 'public']); ?>
