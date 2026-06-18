<?php
declare(strict_types=1);
?>
<section class="mg-app-panel mg-account-pane is-active mg-system-health" data-account-pane="system_health" data-system-health>
  <div class="mg-app-panel-head mg-section-head">
    <div>
      <span class="mg-eyebrow">Platform operations</span>
      <h2>System health</h2>
      <p>Storage, uploaded media, notification delivery, schema readiness, and recent operational errors.</p>
    </div>
    <div class="mg-system-health-toolbar">
      <span data-system-health-updated>Not checked yet</span>
      <button class="mg-btn mg-btn-ghost" type="button" data-system-health-refresh>Refresh</button>
    </div>
  </div>
  <div class="mg-app-panel-body">
    <div class="mg-system-health-banner" data-system-health-banner>
      <span class="mg-system-health-light" aria-hidden="true"></span>
      <div><strong>Loading system health</strong><p>Reading bounded operational metrics.</p></div>
    </div>

    <div class="mg-system-health-summary" data-system-health-summary></div>

    <div class="mg-system-health-grid">
      <section class="mg-system-health-card" data-health-section="storage">
        <header><div><h3>Persistent media storage</h3><p>Active provider, protected path, write state, and available capacity.</p></div><span data-health-tone="storage">—</span></header>
        <div data-health-storage><p class="mg-muted">Loading storage status…</p></div>
      </section>

      <section class="mg-system-health-card" data-health-section="media">
        <header><div><h3>Feed media</h3><p>Uploaded files, attachment state, storage use, and old unattached uploads.</p></div><span data-health-tone="media">—</span></header>
        <div data-health-media><p class="mg-muted">Loading media metrics…</p></div>
      </section>

      <section class="mg-system-health-card" data-health-section="notifications">
        <header><div><h3>Notification delivery</h3><p>Queued, overdue, successful, suppressed, and failed delivery jobs.</p></div><span data-health-tone="notifications">—</span></header>
        <div data-health-notifications><p class="mg-muted">Loading notification health…</p></div>
      </section>

      <section class="mg-system-health-card" data-health-section="schema">
        <header><div><h3>Database readiness</h3><p>Latest required schema update and recorded migration state.</p></div><span data-health-tone="schema">—</span></header>
        <div data-health-schema><p class="mg-muted">Loading schema status…</p></div>
      </section>
    </div>

    <section class="mg-system-health-actions" data-health-actions hidden>
      <header><div><h3>Recovery actions</h3><p>Bounded actions are permission-gated and recorded in the audit log.</p></div></header>
      <div class="mg-system-health-action-grid">
        <article><strong>Verify storage</strong><p>Run a write, read, and removal probe against the persistent media directory.</p><button class="mg-btn mg-btn-soft" type="button" data-health-action="verify_storage">Run verification</button></article>
        <article><strong>Retry failed notifications</strong><p>Return up to 100 eligible failed delivery jobs to the queue.</p><button class="mg-btn mg-btn-soft" type="button" data-health-action="retry_notifications">Retry eligible jobs</button></article>
        <article data-health-archive-action hidden><strong>Archive old unattached media</strong><p>Archive a bounded batch of uploads older than 24 hours that are not linked to a post.</p><button class="mg-btn mg-btn-soft" type="button" data-health-action="archive_media">Review and archive</button></article>
      </div>
      <div class="mg-system-health-action-result" data-health-action-result hidden></div>
    </section>

    <div class="mg-system-health-lists">
      <section class="mg-system-health-card">
        <header><div><h3>Failed notifications</h3><p>Most recent delivery failures with bounded error details.</p></div></header>
        <div data-health-notification-failures><p class="mg-muted">Loading failures…</p></div>
      </section>
      <section class="mg-system-health-card">
        <header><div><h3>Recent system errors</h3><p>Latest error and critical security-log events.</p></div></header>
        <div data-health-errors><p class="mg-muted">Loading errors…</p></div>
      </section>
      <section class="mg-system-health-card">
        <header><div><h3>Recent health checks</h3><p>Results recorded by administrator-run verification actions.</p></div></header>
        <div data-health-checks><p class="mg-muted">Loading checks…</p></div>
      </section>
    </div>
  </div>
</section>
