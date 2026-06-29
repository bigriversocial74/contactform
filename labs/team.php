<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Team | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'team',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Team roles</span>
    <h1>Built for teams that need action, proof, and follow-through.</h1>
    <p class="labs-copy">Training Lab supports organizations, managers, reviewers, and participants with a simple proof-based workflow.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="/signup.php">Start Team Setup</a>
</section>
<section class="labs-section-band">
  <span class="labs-eyebrow">Role map</span>
  <h2>Clear roles keep the workflow simple.</h2>
  <p class="labs-copy">Each role has a focused job in the Stage 1 shell: create the program, complete the tasks, review the evidence, and view progress.</p>
</section>
<section class="labs-grid">
  <article class="labs-card labs-price-card"><span class="labs-mini-icon">O</span><h2>Organization lead</h2><p class="labs-muted">Creates campaigns, assigns participants, and monitors program progress.</p><span class="labs-pill">Program setup</span></article>
  <article class="labs-card labs-price-card"><span class="labs-mini-icon">R</span><h2>Reviewer</h2><p class="labs-muted">Reviews submitted items and manages visual decision states.</p><span class="labs-pill">Review queue</span></article>
  <article class="labs-card labs-price-card"><span class="labs-mini-icon">P</span><h2>Participant</h2><p class="labs-muted">Completes actions, submits proof notes, tracks streaks, and views progress.</p><span class="labs-pill">App flow</span></article>
</section>
<?php labs_page_end(['section' => 'public']); ?>
