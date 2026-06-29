<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Sign Up | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'signup',
]);
?>
<section class="labs-hero">
  <div>
    <span class="labs-eyebrow">Visual account flow</span>
    <h1 class="labs-h1">Create your Training Lab workspace.</h1>
    <p class="labs-copy">Stage 1 keeps this form visual only. Future auth should submit through the main Microgifter account system.</p>
  </div>
  <form class="labs-card" action="#" method="post">
    <label>Name<br><input type="text" placeholder="Your name"></label><br><br>
    <label>Email<br><input type="email" placeholder="you@example.com"></label><br><br>
    <label>Workspace type<br><select><option>Team</option><option>Organization</option><option>Enterprise</option></select></label><br><br>
    <button class="labs-btn labs-btn-primary" type="button">Create Visual Account</button>
  </form>
</section>
<?php labs_page_end(['section' => 'public']); ?>
