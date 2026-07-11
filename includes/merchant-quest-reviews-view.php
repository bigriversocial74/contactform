<?php
declare(strict_types=1);
?>
<section class="mg-merchant-heading mg-quest-review-heading">
  <div>
    <span class="mg-eyebrow">Loyalty Quest Operations</span>
    <h1>Review participant evidence.</h1>
    <p>Approve verified customer actions, reject incomplete proof with a clear reason, and issue eligible rewards through the Microgifter wallet lifecycle.</p>
  </div>
  <div class="mg-heading-actions">
    <a class="mg-btn mg-btn-soft" href="/merchant-loyalty-quests.php">Manage quests</a>
    <button class="mg-btn mg-btn-primary" type="button" data-quest-review-refresh>Refresh queue</button>
  </div>
</section>

<section class="mg-campaign-kpis mg-quest-review-kpis" aria-label="Quest evidence metrics">
  <article><span>Needs review</span><strong data-review-kpi-submitted>—</strong><small>Waiting for a decision</small></article>
  <article><span>Approved</span><strong data-review-kpi-verified>—</strong><small>Verified evidence</small></article>
  <article><span>Rejected</span><strong data-review-kpi-rejected>—</strong><small>Participant can resubmit</small></article>
  <article><span>All evidence</span><strong data-review-kpi-all>—</strong><small>Merchant-owned records</small></article>
</section>

<section class="mg-app-panel mg-quest-review-command" data-quest-review-workspace>
  <div class="mg-app-panel-head mg-quest-review-panel-head">
    <div>
      <span class="mg-eyebrow">Evidence Queue</span>
      <h2>Participant submissions</h2>
      <p>Every result is merchant-scoped. Decisions are recorded in the campaign event and audit history.</p>
    </div>
  </div>
  <div class="mg-app-panel-body">
    <form class="mg-quest-review-toolbar" data-quest-review-filters role="search">
      <label><span>Search</span><input name="q" type="search" maxlength="180" placeholder="Participant, quest, or reference"></label>
      <label><span>Status</span><select name="status" data-review-status-filter><option value="submitted">Needs review</option><option value="verified">Approved</option><option value="rejected">Rejected</option><option value="all">All evidence</option></select></label>
      <label><span>Loyalty Quest</span><select name="campaign_id" data-review-campaign-filter><option value="">All Loyalty Quests</option></select></label>
      <button class="mg-btn mg-btn-primary" type="submit">Apply filters</button>
      <button class="mg-btn mg-btn-ghost" type="button" data-review-clear>Clear</button>
    </form>
    <div class="mg-form-status" data-quest-review-status role="status" aria-live="polite">Loading evidence queue…</div>
    <div class="mg-quest-review-list" data-quest-review-list></div>
  </div>
</section>

<dialog class="mg-quest-review-dialog" data-quest-review-dialog>
  <form method="dialog" class="mg-quest-review-dialog-card">
    <button class="mg-quest-review-dialog-close" value="cancel" aria-label="Close evidence details">×</button>
    <div data-quest-review-detail></div>
  </form>
</dialog>
