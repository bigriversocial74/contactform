<?php
require_once __DIR__ . '/../includes/labs-layout.php';
labs_page_start([
    'title' => 'Admin Campaigns | Training Lab by Microgifter',
    'section' => 'admin',
    'active' => 'admin-campaigns',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Campaign management</span>
    <h1>Campaigns</h1>
    <p class="labs-copy">Static campaign management table for Stage 1.</p>
  </div>
  <button class="labs-btn labs-btn-primary" type="button">Create Visual Campaign</button>
</section>
<section class="labs-card">
  <table class="labs-table">
    <thead><tr><th>Campaign</th><th>Participants</th><th>Status</th><th>Queue</th></tr></thead>
    <tbody>
      <tr><td>5-Day Movement Challenge</td><td>86</td><td><span class="labs-pill">Active</span></td><td>12 items</td></tr>
      <tr><td>Customer Service Basics</td><td>44</td><td><span class="labs-pill">Draft</span></td><td>0 items</td></tr>
      <tr><td>Safety Readiness</td><td>118</td><td><span class="labs-pill">Active</span></td><td>19 items</td></tr>
    </tbody>
  </table>
</section>
<?php labs_page_end(['section' => 'admin']); ?>
