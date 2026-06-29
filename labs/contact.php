<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Contact | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'contact',
]);
?>
<section class="labs-hero">
  <div>
    <span class="labs-eyebrow">Contact</span>
    <h1 class="labs-h1">Talk about Training Lab.</h1>
    <p class="labs-copy">This contact page is a visual shell only. The form does not submit messages in Stage 1.</p>
    <div class="labs-section-band" style="margin:28px 0 0">
      <span class="labs-eyebrow">Good fit</span>
      <h2>Teams, programs, challenges, and proof-based participation.</h2>
      <p class="labs-copy">Use this page later for pilots, organization interest, and customer support routing.</p>
    </div>
  </div>
  <form class="labs-card" action="#" method="post">
    <h2>Contact form preview</h2>
    <p class="labs-muted">Visual only. No message is sent.</p>
    <label>Name<br><input type="text" placeholder="Your name"></label><br><br>
    <label>Email<br><input type="email" placeholder="you@example.com"></label><br><br>
    <label>Program notes<br><textarea rows="5" placeholder="Tell us about your training program."></textarea></label><br><br>
    <button class="labs-btn labs-btn-primary" type="button">Visual Contact Button</button>
  </form>
</section>
<?php labs_page_end(['section' => 'public']); ?>
