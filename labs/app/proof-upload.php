<?php
require_once __DIR__ . '/../includes/labs-layout.php';
labs_page_start([
    'title' => 'Proof Upload | Training Lab by Microgifter',
    'section' => 'app',
    'active' => 'app-tasks',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Visual proof upload</span>
    <h1>Submit proof</h1>
    <p class="labs-copy">Stage 1 displays the upload interface only. Files are not stored or processed.</p>
  </div>
</section>
<section class="labs-grid">
  <form class="labs-card" style="grid-column:span 2" action="#" method="post">
    <h2>Day 5 Movement Proof</h2>
    <label>Proof note<br><textarea rows="5" placeholder="Add a short note about the completed action."></textarea></label><br><br>
    <label>Visual file picker<br><input type="file" disabled></label><br><br>
    <button class="labs-btn labs-btn-primary" type="button">Submit Visual Proof</button>
  </form>
  <aside class="labs-card"><h2>Review status</h2><p class="labs-muted">After submission, this card will show review state in a later phase.</p><span class="labs-pill">Not submitted</span></aside>
</section>
<?php labs_page_end(['section' => 'app']); ?>
