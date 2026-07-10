<?php
declare(strict_types=1);
?>
<section class="mg-merchant-heading mg-loyalty-quest-heading">
  <div>
    <span class="mg-eyebrow">Loyalty Quest Campaigns</span>
    <h1>Manage every quest from one workspace.</h1>
    <p>Create, schedule, publish, pause, duplicate, complete, archive, and measure merchant-owned Loyalty Quests.</p>
  </div>
  <div class="mg-heading-actions">
    <a class="mg-btn mg-btn-primary" href="/merchant-campaigns.php#campaign-create" data-create-loyalty-quest>Create Loyalty Quest</a>
    <button class="mg-btn mg-btn-soft" type="button" data-loyalty-quest-refresh>Refresh</button>
  </div>
</section>

<section class="mg-campaign-kpis" aria-label="Loyalty Quest metrics">
  <article><span>Total quests</span><strong data-lq-kpi-total>—</strong><small>All merchant-owned quests</small></article>
  <article><span>Active</span><strong data-lq-kpi-active>—</strong><small><span data-lq-kpi-scheduled>—</span> scheduled</small></article>
  <article><span>Participants</span><strong data-lq-kpi-participants>—</strong><small>Unique campaign contacts</small></article>
  <article><span>Rewards issued</span><strong data-lq-kpi-issued>—</strong><small>Wallet items created</small></article>
  <article><span>Redeemed</span><strong data-lq-kpi-redeemed>—</strong><small>Completed reward redemptions</small></article>
</section>

<section class="mg-app-panel mg-lq-command" data-loyalty-quest-management>
  <div class="mg-app-panel-head">
    <div>
      <span class="mg-eyebrow">Quest Portfolio</span>
      <h2>Campaign lifecycle</h2>
      <p>Filter by lifecycle state, inspect participant activity, and apply guarded merchant-scoped actions.</p>
    </div>
    <div class="mg-heading-actions">
      <label class="mg-lq-filter">Status
        <select data-loyalty-quest-status-filter>
          <option value="all">All quests</option>
          <option value="draft">Draft</option>
          <option value="scheduled">Scheduled</option>
          <option value="active">Active</option>
          <option value="paused">Paused</option>
          <option value="ended">Completed</option>
          <option value="archived">Archived</option>
        </select>
      </label>
    </div>
  </div>
  <div class="mg-app-panel-body">
    <div class="mg-form-status" data-loyalty-quest-status role="status" aria-live="polite"></div>
    <div class="mg-lq-list" data-loyalty-quest-list>
      <div class="mg-empty-state"><p>Loading Loyalty Quests…</p></div>
    </div>
  </div>
</section>

<dialog class="mg-lq-dialog" data-loyalty-quest-dialog>
  <form method="dialog" class="mg-lq-dialog-card">
    <button class="mg-lq-dialog-close" value="cancel" aria-label="Close">×</button>
    <div data-loyalty-quest-detail></div>
  </form>
</dialog>
