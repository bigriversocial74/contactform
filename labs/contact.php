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
  </div>
  <form class="labs-card" action="#" method="post">
    <label>Name<br><input type="text" placeholder="Your name"></label><br><br>
    <label>Email<br><input type="email" placeholder="you@example.com"></label><br><br>
    <label>Program notes<br><textarea rows="5" placeholder="Tell us about your training program."></textarea></label><br><br>
    <button class="labs-btn labs-btn-primary" type="button">Visual Contact Button</button>
  </form>
</section>
<?php labs_page_end(['section' => 'public']); ?>
