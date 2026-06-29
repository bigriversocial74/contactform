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
  </div>
  <form class="labs-card" action="#" method="post">
    <label>Email<br><input type="email" placeholder="you@example.com"></label><br><br>
    <label>Password<br><input type="password" placeholder="••••••••"></label><br><br>
    <a class="labs-btn labs-btn-primary" href="/app/index.php">Open Demo Dashboard</a>
  </form>
</section>
<?php labs_page_end(['section' => 'public']); ?>
