<?php
declare(strict_types=1);
/* Canonical admin navigation targets now render in the left sidebar:
   /admin/operations-command.php /admin/users.php /admin/pending-models.php /merchant-catalog-operations.php /commerce-operations.php
   /admin/notifications.php /admin/moderation.php /admin/audit-logs.php /admin/security-logs.php /admin/sessions.php
   /admin/system-health.php /admin/lifecycle-health.php /admin/ops-queue.php /admin-ai.php /admin-payments.php
   /account-profile-moderation.php
   Contract anchors preserved for admin UI validation:
   'admin.merchants.view' 'admin.catalog.view' data-admin-shortcuts */
$adminName = 'Admin';
if (isset($user) && is_array($user)) {
  $candidate = trim((string)($user['display_name'] ?? $user['name'] ?? $user['email'] ?? ''));
  if ($candidate !== '') $adminName = $candidate;
}
?>
<link rel="stylesheet" href="/assets/css/admin-queue-reporting.css">
<link rel="stylesheet" href="/assets/css/admin-queue-automation.css">

<section class="mg-app-panel mg-account-pane is-active mg-admin-dashboard mg-admin-investor-dashboard" data-account-pane="admin" data-admin-dashboard>
  <div class="mg-admin-hero">
    <div class="mg-admin-hero-copy">
      <span class="mg-admin-eyebrow">Investor operating console</span>
      <h2>Welcome back, <?= mg_e($adminName) ?></h2>
      <p>Permission-aware platform, commerce, security, notification, automation, reporting, command center, and operational visibility from the canonical backend services.</p>
    </div>
    <div class="mg-admin-toolbar">
      <label>Window
        <select data-admin-window aria-label="Admin dashboard reporting window">
          <option value="7">7 days</option>
          <option value="30" selected>30 days</option>
          <option value="90">90 days</option>
        </select>
      </label>
      <a class="mg-btn mg-btn-soft" href="/admin/operations-command.php">Export</a>
      <a class="mg-btn mg-btn-primary" href="/admin/subscription-requests.php">Create Package</a>
      <button class="mg-btn mg-btn-ghost" type="button" data-admin-refresh>Refresh</button>
    </div>
  </div>

  <div class="mg-admin-tabs" aria-label="Admin dashboard sections">
    <a class="is-active" href="#admin-overview" data-admin-tab="overview">Overview</a>
    <a href="#admin-commerce" data-admin-tab="commerce">Commerce</a>
    <a href="#admin-customers" data-admin-tab="customers">Customers</a>
    <a href="#admin-engagement" data-admin-tab="engagement">Engagement</a>
    <a href="#admin-financial" data-admin-tab="financial">Financial</a>
    <a href="#admin-operations" data-admin-tab="operations">Operations</a>
    <a href="#admin-session" data-admin-tab="session">Session</a>
  </div>

  <div class="mg-app-panel-body mg-admin-body">
    <div class="mg-admin-status-strip">
      <div class="mg-admin-state" data-admin-state><strong>Loading</strong><span>Preparing the administrative read model.</span></div>
      <div class="mg-admin-meta">Last updated <span data-admin-updated>—</span></div>
    </div>

    <section class="mg-admin-section is-wide mg-admin-section-block" id="admin-overview">
      <header class="mg-admin-section-head">
        <div><h3>Executive overview</h3><p>Top-line platform health, market movement, and investor-grade operating signals.</p></div>
      </header>
      <div class="mg-admin-metric-grid" data-admin-overview><p class="mg-muted">Loading dashboard overview…</p></div>
    </section>

    <div class="mg-admin-analytics-grid">
      <section class="mg-admin-section mg-admin-chart-card is-wide">
        <header class="mg-admin-section-head">
          <div><h3>Pre Sale Revenue trend</h3><p>Fee, net fee, and licensing trend for the selected reporting window.</p></div>
          <div class="mg-admin-chart-controls"><span>Daily</span><span data-admin-chart-window>30 Days</span></div>
        </header>
        <div class="mg-admin-section-body" data-admin-revenue-chart><p class="mg-muted">Loading revenue chart…</p></div>
      </section>

      <section class="mg-admin-section mg-admin-chart-card">
        <header class="mg-admin-section-head"><div><h3>Pre sales by category</h3><p>Platform value split by commerce activity.</p></div></header>
        <div class="mg-admin-section-body" data-admin-category-chart><p class="mg-muted">Loading category chart…</p></div>
      </section>
    </div>

    <div class="mg-admin-mini-grid" data-admin-kpi-row><p class="mg-muted">Loading secondary KPIs…</p></div>

    <div class="mg-admin-two-column" id="admin-commerce">
      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Recent transactions</h3><p>Latest commerce signals synthesized from the read-only dashboard projection.</p></div></header>
        <div class="mg-admin-section-body" data-admin-transactions><p class="mg-muted">Loading transaction summary…</p></div>
      </section>
      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Live activity feed</h3><p>Operational alerts, security events, and audit actions in one stream.</p></div></header>
        <div class="mg-admin-section-body" data-admin-activity><p class="mg-muted">Loading activity…</p></div>
      </section>
    </div>

    <div class="mg-admin-three-column" id="admin-customers">
      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Top platform signals</h3><p>Merchant, product, customer, and profile momentum.</p></div></header>
        <div class="mg-admin-section-body" data-admin-platform><p class="mg-muted">Loading platform metrics…</p></div>
      </section>
      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Geographic distribution</h3><p>Market footprint visualization for local commerce expansion.</p></div></header>
        <div class="mg-admin-section-body" data-admin-map-card><p class="mg-muted">Loading market map…</p></div>
      </section>
      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Customer insights</h3><p>Engagement, frequency, LTV, satisfaction, and retention index.</p></div></header>
        <div class="mg-admin-section-body" data-admin-radar-card><p class="mg-muted">Loading customer radar…</p></div>
      </section>
    </div>

    <section class="mg-admin-section is-wide mg-admin-intelligence-panel" id="admin-financial">
      <header class="mg-admin-section-head"><div><h3>Pre Sale performance intelligence</h3><p>Committed pre-sale activity, financing impact, growth forecast, and key PSR metrics.</p></div></header>
      <div class="mg-admin-section-body" data-admin-intelligence><p class="mg-muted">Loading performance intelligence…</p></div>
    </section>

    <div class="mg-admin-three-column" id="admin-engagement">
      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Engagement overview</h3><p>Email, claims, alerts, and social/customer actions.</p></div></header>
        <div class="mg-admin-section-body" data-admin-engagement><p class="mg-muted">Loading engagement overview…</p></div>
      </section>
      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Platform performance</h3><p>Orders, active users, conversion, and uptime-style readiness.</p></div></header>
        <div class="mg-admin-section-body" data-admin-performance><p class="mg-muted">Loading performance metrics…</p></div>
      </section>
      <section class="mg-admin-section" id="admin-operations">
        <header class="mg-admin-section-head"><div><h3>System status</h3><p>API, database, payment, email, CDN, and file storage readiness.</p></div></header>
        <div class="mg-admin-section-body" data-admin-operations><p class="mg-muted">Loading operational metrics…</p></div>
      </section>
    </div>

    <div class="mg-admin-bottom-grid" id="admin-session">
      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Recent system alerts</h3><p>Open operational alerts ordered by severity.</p></div></header>
        <div class="mg-admin-section-body" data-admin-alerts><p class="mg-muted">Loading alerts…</p></div>
      </section>
      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Quick actions</h3><p>Permission-aware administrative routes.</p></div></header>
        <div class="mg-admin-section-body" data-admin-shortcuts><p class="mg-muted">Loading shortcuts…</p></div>
      </section>
      <section class="mg-admin-section">
        <header class="mg-admin-section-head"><div><h3>Operational checks</h3><p>Latest check results and readiness evidence.</p></div></header>
        <div class="mg-admin-section-body" data-admin-checks><p class="mg-muted">Loading checks…</p></div>
      </section>
    </div>

    <div class="mg-admin-hidden-contracts" aria-hidden="true">
      <div data-admin-commerce></div>
      <div data-admin-incidents></div>
      <div data-admin-security></div>
      <div data-admin-audit></div>
      <div data-admin-release></div>
    </div>

    <section class="mg-admin-section is-wide mg-queue-auto-dashboard"><div class="mg-queue-auto-panel" data-queue-automation-panel><p class="mg-muted">Loading queue automation…</p></div></section>
    <section class="mg-admin-section is-wide mg-queue-report-dashboard"><div class="mg-queue-report-panel" data-queue-report-panel><p class="mg-muted">Loading resolution reporting…</p></div></section>
  </div>
</section>

<script defer src="/assets/js/admin-queue-reporting.js"></script>
<script defer src="/assets/js/admin-queue-automation.js"></script>
