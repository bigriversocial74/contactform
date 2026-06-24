<?php
declare(strict_types=1);
/* Canonical admin navigation targets now render in the left sidebar:
   /admin/users.php /admin/pending-models.php /merchant-catalog-operations.php /commerce-operations.php
   /admin/audit-logs.php /admin/security-logs.php /admin/sessions.php /admin/system-health.php /admin/lifecycle-health.php
   /admin/ops-queue.php /admin-ai.php /admin-payments.php /account-profile-moderation.php */
?>
<section class="mg-app-panel mg-account-pane is-active mg-admin-dashboard" data-account-pane="admin" data-admin-dashboard>
  <div class="mg-app-panel-head mg-section-head">
    <div>
      <h2>Admin control center</h2>
      <p>Permission-aware platform, commerce, security, and operational visibility from the canonical backend services.</p>
    </div>
    <div class="mg-admin-toolbar">
      <label>Window
        <select data-admin-window aria-label="Admin dashboard reporting window">
          <option value="7">7 days</option>
          <option value="30" selected>30 days</option>
          <option value="90">90 days</option>
        </select>
      </label>
      <button class="mg-btn mg-btn-ghost" type="button" data-admin-refresh>Refresh</button>
    </div>
  </div>
  <div class="mg-app-panel-body">
    <div class="mg-admin-state" data-admin-state><strong>Loading</strong><span>Preparing the administrative read model.</span></div>
    <div class="mg-admin-meta">Last updated <span data-admin-updated>—</span></div>
    <div class="mg-admin-metric-grid" data-admin-overview><p class="mg-muted">Loading dashboard overview…</p></div>

    <div class="mg-admin-section-grid">
      <section class="mg-admin-section is-wide">
        <header class="mg-admin-section-head"><div><h3>Platform foundation</h3><p>Users, profiles, model approvals, storefronts, products, and posts.</p></div></header>
        <div class="mg-admin-section-body" data-admin-platform><p class="mg-muted">Loading platform metrics…</p></div>
      </section>

      <section class="mg-admin-section is-wide">
        <header class="mg-admin-section-head"><div><h3>Commerce and lifecycle</h3><p>Paid orders, fulfillment, subscriptions, tips, claims, and redemption.</p></div></header>
        <div class="mg-admin-section-body" data-admin-commerce><p class="mg-muted">Loading commerce metrics…</p></div>
      </section>

      <section class="mg-admin-section is-wide">
        <header class="mg-admin-section-head"><div><h3>Operations</h3><p>Alerts, incidents, security warnings, sessions, orchestration, and checks.</p></div></header>
        <div class="mg-admin-section-body" data-admin-operations><p class="mg-muted">Loading operational metrics…</p></div>
      </section>

      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Current release</h3><p>Latest recorded deployment state.</p></div></header>
        <div class="mg-admin-section-body" data-admin-release><p class="mg-muted">Loading release…</p></div>
      </section>

      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Operational alerts</h3><p>Open and acknowledged alerts ordered by severity.</p></div></header>
        <div class="mg-admin-section-body" data-admin-alerts><p class="mg-muted">Loading alerts…</p></div>
      </section>

      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Open incidents</h3><p>Active incident response records.</p></div></header>
        <div class="mg-admin-section-body" data-admin-incidents><p class="mg-muted">Loading incidents…</p></div>
      </section>

      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Operational checks</h3><p>Latest check results and readiness evidence.</p></div></header>
        <div class="mg-admin-section-body" data-admin-checks><p class="mg-muted">Loading checks…</p></div>
      </section>

      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Security events</h3><p>Recent warning, error, and critical events.</p></div></header>
        <div class="mg-admin-section-body" data-admin-security><p class="mg-muted">Loading security events…</p></div>
      </section>

      <section class="mg-admin-section is-wide">
        <header class="mg-admin-section-head"><div><h3>Administrative activity</h3><p>Recent audit activity without exposing raw metadata.</p></div></header>
        <div class="mg-admin-section-body" data-admin-audit><p class="mg-muted">Loading audit activity…</p></div>
      </section>
    </div>
  </div>
</section>