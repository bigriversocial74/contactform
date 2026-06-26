<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.support_queue');
$page_title = 'Support Queue | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-support-queue-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-support-queue.css'];
$page_scripts = ['/assets/js/admin-support-queue.js','/assets/js/admin-sla-routing.js'];
$adminActive = 'support-queue';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-support-shell" data-admin-support-queue>
      <header class="mg-admin-support-hero">
        <div>
          <a class="mg-admin-support-back" href="/admin/users.php">← User center</a>
          <span class="mg-eyebrow">Admin operations</span>
          <h1>Support queue</h1>
          <p>Review internal user notes, SLA health, auto-routing, follow-up status, due dates, assignments, and review flags across the platform.</p>
        </div>
        <div class="mg-admin-support-hero-actions">
          <span>Queue score <strong>10/10</strong></span>
          <button class="mg-btn mg-btn-soft" type="button" data-sla-apply>Apply SLA rules</button>
          <button class="mg-btn mg-btn-ghost" type="button" data-support-refresh disabled>Refresh</button>
        </div>
      </header>

      <section class="mg-admin-sla-panel" data-sla-panel>
        <header>
          <div>
            <span class="mg-eyebrow">SLA routing</span>
            <h2>Queue health and auto-routing</h2>
            <p>Monitor compliant, at-risk, breached, unassigned, stale waiting, auto-escalated, lane, and admin workload metrics.</p>
          </div>
          <button class="mg-btn mg-btn-ghost" type="button" data-sla-refresh>Refresh SLA</button>
        </header>
        <div class="mg-admin-sla-summary" data-sla-summary></div>
        <div class="mg-admin-sla-grids">
          <section><h3>Routing lanes</h3><div data-sla-lanes></div></section>
          <section><h3>Admin workload</h3><div data-sla-workload></div></section>
        </div>
        <div class="mg-admin-sla-status" data-sla-status role="status" aria-live="polite"></div>
      </section>

      <form class="mg-admin-support-filters" data-support-filters>
        <label>Search
          <input type="search" name="q" maxlength="160" placeholder="User, email, note, or reason">
        </label>
        <label>Status
          <select name="status">
            <option value="">All</option>
            <option value="open">Open</option>
            <option value="waiting_on_merchant">Waiting on merchant</option>
            <option value="waiting_on_customer">Waiting on customer</option>
            <option value="escalated">Escalated</option>
            <option value="resolved">Resolved</option>
          </select>
        </label>
        <label>Priority
          <select name="priority">
            <option value="">All</option>
            <option value="critical">Critical</option>
            <option value="high">High</option>
            <option value="normal">Normal</option>
            <option value="low">Low</option>
          </select>
        </label>
        <label>Category
          <select name="category">
            <option value="">All</option>
            <option value="support">Support</option>
            <option value="risk">Risk</option>
            <option value="billing">Billing</option>
            <option value="merchant_onboarding">Merchant onboarding</option>
            <option value="product_catalog">Product/catalog</option>
            <option value="crm_campaigns">CRM/campaigns</option>
            <option value="general">General</option>
          </select>
        </label>
        <label>Review flag
          <select name="flag_state">
            <option value="">All</option>
            <option value="review">Review</option>
            <option value="flagged">Flagged</option>
            <option value="cleared">Cleared</option>
            <option value="none">None</option>
          </select>
        </label>
        <label>Assignment
          <select name="assigned">
            <option value="">All</option>
            <option value="me">Assigned to me</option>
            <option value="assigned">Assigned</option>
            <option value="unassigned">Unassigned</option>
          </select>
        </label>
        <label>Due
          <select name="due">
            <option value="">Any</option>
            <option value="overdue">Overdue</option>
            <option value="today">Today</option>
            <option value="week">This week</option>
          </select>
        </label>
        <div class="mg-admin-support-filter-actions">
          <button class="mg-btn mg-btn-primary" type="submit">Apply</button>
          <button class="mg-btn mg-btn-ghost" type="reset" data-support-reset>Reset</button>
        </div>
      </form>

      <section class="mg-admin-support-summary" data-support-summary></section>
      <div class="mg-admin-support-status" data-support-status role="status" aria-live="polite"></div>
      <section class="mg-admin-support-list" data-support-list></section>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
