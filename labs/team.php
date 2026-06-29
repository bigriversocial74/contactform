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
    <span class="labs-eyebrow">Team</span>
    <h1>Built for teams that need action, proof, and follow-through.</h1>
    <p class="labs-copy">Training Lab supports organizations, managers, reviewers, and participants with a simple proof-based workflow.</p>
  </div>
</section>
<section class="labs-grid">
  <article class="labs-card"><span class="labs-mini-icon">O</span><h2>Organization admin</h2><p class="labs-muted">Creates campaigns, assigns participants, and monitors program progress.</p></article>
  <article class="labs-card"><span class="labs-mini-icon">R</span><h2>Reviewer</h2><p class="labs-muted">Reviews submitted proof and manages visual approval states.</p></article>
  <article class="labs-card"><span class="labs-mini-icon">P</span><h2>Participant</h2><p class="labs-muted">Completes actions, submits proof, tracks streaks, and views reward progress.</p></article>
</section>
<?php labs_page_end(['section' => 'public']); ?>
