<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Sign In | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'signin',
]);
?>
<section class="labs-hero">
  <div>
    <span class="labs-eyebrow">Microgifter account system later</span>
    <h1 class="labs-h1">Welcome back to Training Lab.</h1>
    <p class="labs-copy">This is a visual sign-in shell. Stage 1 does not create a separate account system.</p>
    <div class="labs-section-band" style="margin:28px 0 0">
      <span class="labs-eyebrow">Auth rule</span>
      <h2>Main Microgifter account system remains the source of truth.</h2>
      <p class="labs-copy">This page is only a visual placeholder until auth integration is approved.</p>
    </div>
  </div>
  <form class="labs-card" action="#" method="post">
    <h2>Sign in preview</h2>
    <p class="labs-muted">Visual only. No session is created.</p>
    <label>Email<br><input type="email" placeholder="you@example.com"></label><br><br>
    <label>Password<br><input type="password" placeholder="••••••••"></label><br><br>
    <a class="labs-btn labs-btn-primary" href="/app/index.php">Open Demo Dashboard</a>
  </form>
</section>
<?php labs_page_end(['section' => 'public']); ?>
