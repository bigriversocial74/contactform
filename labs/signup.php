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
    <div class="labs-timeline">
      <div class="labs-step"><span class="labs-mini-icon">1</span><strong>Choose workspace type</strong><span class="labs-pill">Visual</span></div>
      <div class="labs-step"><span class="labs-mini-icon">2</span><strong>Preview account setup</strong><span class="labs-pill">No auth</span></div>
      <div class="labs-step"><span class="labs-mini-icon">3</span><strong>Open demo dashboard</strong><span class="labs-pill">Static</span></div>
    </div>
  </div>
  <form class="labs-card" action="#" method="post">
    <h2>Start workspace</h2>
    <p class="labs-muted">Visual form only. No account is created.</p>
    <label>Name<br><input type="text" placeholder="Your name"></label><br><br>
    <label>Email<br><input type="email" placeholder="you@example.com"></label><br><br>
    <label>Workspace type<br><select><option>Team</option><option>Organization</option><option>Enterprise</option></select></label><br><br>
    <a class="labs-btn labs-btn-primary" href="/app/index.php">Open Demo Workspace</a>
  </form>
</section>
<?php labs_page_end(['section' => 'public']); ?>
