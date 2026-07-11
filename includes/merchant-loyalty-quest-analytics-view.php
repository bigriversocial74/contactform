<?php
declare(strict_types=1);
?>
<section class="mg-merchant-heading mg-lqa-heading">
  <div>
    <span class="mg-eyebrow">Loyalty Quest intelligence</span>
    <h1>See where quests start, stall, complete, and convert.</h1>
    <p>Measure participant movement from campaign contact through verified completion, Microgifter Inbox delivery, claim, and redemption without exposing personal proof data.</p>
  </div>
  <div class="mg-lqa-actions">
    <a class="mg-btn mg-btn-secondary" href="/merchant-loyalty-quest-delivery.php">Delivery operations</a>
    <button class="mg-btn mg-btn-secondary" type="button" data-lqa-export="json">Export JSON</button>
    <button class="mg-btn mg-btn-primary" type="button" data-lqa-export="csv">Export CSV</button>
  </div>
</section>

<section class="mg-lqa-toolbar" aria-label="Loyalty Quest report filters">
  <label>Report period
    <select data-lqa-days>
      <option value="7">Last 7 days</option>
      <option value="30" selected>Last 30 days</option>
      <option value="90">Last 90 days</option>
      <option value="180">Last 180 days</option>
      <option value="365">Last 365 days</option>
    </select>
  </label>
  <label>Loyalty Quest
    <select data-lqa-campaign><option value="">All Loyalty Quests</option></select>
  </label>
  <button class="mg-btn mg-btn-primary" type="button" data-lqa-apply>Apply report</button>
  <span class="mg-status-badge" data-lqa-status>Loading analytics</span>
</section>

<div class="mg-lqa-kpis" data-lqa-kpis aria-live="polite"></div>

<div class="mg-lqa-grid">
  <section class="mg-app-panel mg-lqa-span-2">
    <div class="mg-app-panel-head"><div><span class="mg-eyebrow">Conversion path</span><h2>Quest funnel</h2><p>Campaign contacts through completed redemption.</p></div></div>
    <div class="mg-app-panel-body"><div class="mg-lqa-funnel" data-lqa-funnel></div></div>
  </section>

  <section class="mg-app-panel mg-lqa-span-2">
    <div class="mg-app-panel-head"><div><span class="mg-eyebrow">Daily activity</span><h2>Lifecycle trend</h2><p>Starts, verified completions, Inbox delivery, claims, and redemptions by day.</p></div></div>
    <div class="mg-app-panel-body"><div class="mg-lqa-chart-wrap"><svg class="mg-lqa-chart" viewBox="0 0 960 280" role="img" aria-label="Loyalty Quest lifecycle trend" data-lqa-chart></svg></div><div class="mg-lqa-legend" data-lqa-legend></div></div>
  </section>

  <section class="mg-app-panel">
    <div class="mg-app-panel-head"><div><span class="mg-eyebrow">Evidence quality</span><h2>Verification methods</h2><p>Approval rates by QR, location, purchase, receipt, staff, event, referral, and social proof.</p></div></div>
    <div class="mg-app-panel-body"><div class="mg-lqa-list" data-lqa-verification></div></div>
  </section>

  <section class="mg-app-panel">
    <div class="mg-app-panel-head"><div><span class="mg-eyebrow">Acquisition</span><h2>Participation sources</h2><p>Starts and completions by public page, website embed, QR, or other recorded source.</p></div></div>
    <div class="mg-app-panel-body"><div class="mg-lqa-list" data-lqa-sources></div></div>
  </section>

  <section class="mg-app-panel">
    <div class="mg-app-panel-head"><div><span class="mg-eyebrow">Transactional delivery</span><h2>Notification delivery</h2><p>Queued, delivered, failed, and suppressed Loyalty Quest messages.</p></div></div>
    <div class="mg-app-panel-body"><div class="mg-lqa-delivery" data-lqa-delivery></div></div>
  </section>

  <section class="mg-app-panel">
    <div class="mg-app-panel-head"><div><span class="mg-eyebrow">Cycle speed</span><h2>Time to outcome</h2><p>Average time to complete, review proof, and redeem an Inbox reward.</p></div></div>
    <div class="mg-app-panel-body"><div class="mg-lqa-time-grid" data-lqa-time></div></div>
  </section>

  <section class="mg-app-panel mg-lqa-span-2">
    <div class="mg-app-panel-head"><div><span class="mg-eyebrow">Campaign comparison</span><h2>Loyalty Quest performance</h2><p>Aggregate merchant-owned campaign results. No participant names, emails, proof content, claim codes, or precise coordinates are included.</p></div><span class="mg-lqa-privacy">Aggregate only</span></div>
    <div class="mg-app-panel-body mg-lqa-table-wrap"><table class="mg-lqa-table"><thead><tr><th>Quest</th><th>Participants</th><th>Completed</th><th>Completion</th><th>Inbox</th><th>Claimed</th><th>Redeemed</th><th>Redemption</th><th>Redeemed value</th><th>Delivery</th></tr></thead><tbody data-lqa-campaigns></tbody></table></div>
  </section>
</div>
