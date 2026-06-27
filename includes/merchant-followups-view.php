<?php
declare(strict_types=1);
?>
<section class="mg-followups-page" data-merchant-followups-page>
  <header class="mg-followups-header">
    <div>
      <span class="mg-eyebrow">Merchant CRM</span>
      <h1>Follow-up Task Center</h1>
      <p>Work today, overdue, upcoming, snoozed, and completed CRM follow-ups across all merchant customers.</p>
    </div>
    <div class="mg-heading-actions">
      <a class="mg-btn mg-btn-soft" href="/merchant-crm.php?tab=contacts">CRM Contacts</a>
      <button class="mg-btn mg-btn-soft" type="button" data-followups-refresh>Refresh</button>
    </div>
  </header>

  <section class="mg-followup-summary" data-followup-summary>
    <span><strong data-followup-count="open">—</strong> Open</span>
    <span><strong data-followup-count="overdue">—</strong> Overdue</span>
    <span><strong data-followup-count="today">—</strong> Today</span>
    <span><strong data-followup-count="upcoming">—</strong> Upcoming</span>
    <span><strong data-followup-count="snoozed">—</strong> Snoozed</span>
    <span><strong data-followup-count="completed">—</strong> Completed</span>
  </section>

  <nav class="mg-followup-filters" aria-label="Follow-up task filters">
    <button class="is-active" type="button" data-followup-filter="open">Open</button>
    <button type="button" data-followup-filter="today">Today</button>
    <button type="button" data-followup-filter="overdue">Overdue</button>
    <button type="button" data-followup-filter="upcoming">Upcoming</button>
    <button type="button" data-followup-filter="snoozed">Snoozed</button>
    <button type="button" data-followup-filter="completed">Completed</button>
    <button type="button" data-followup-filter="all">All</button>
  </nav>

  <section class="mg-followups-card">
    <div class="mg-app-panel-head">
      <div>
        <h2 data-followup-title>Open follow-ups</h2>
        <p data-followup-subtitle>Tasks are created from Merchant CRM and customer profile follow-up actions.</p>
      </div>
    </div>
    <div class="mg-followups-table-wrap" data-followup-queue>
      <div class="mg-followups-empty"><strong>Loading follow-up tasks</strong><p>Customer task activity will appear here.</p></div>
    </div>
  </section>
</section>
