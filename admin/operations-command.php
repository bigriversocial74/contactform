<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.operations_command');
$page_title = 'Operations Command | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-ops-command-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-ops-command.css','/assets/css/admin-ops-incidents.css'];
$page_scripts = ['/assets/js/admin-ops-command.js'];
$adminActive = 'operations-command';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-ops-command-shell" data-ops-command>
      <header class="mg-ops-command-hero">
        <div>
          <a class="mg-ops-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Mission control</span>
          <h1>Operations command center</h1>
          <p>Unified visibility for queue health, automation, incident mode, SLA status, workload balance, notification urgency, reporting, and critical work across Microgifter admin operations.</p>
        </div>
        <div class="mg-ops-hero-actions">
          <span>Command score <strong>10/10</strong></span>
          <button class="mg-btn mg-btn-soft" type="button" data-ops-run>Run automation</button>
          <button class="mg-btn mg-btn-ghost" type="button" data-ops-refresh disabled>Refresh</button>
        </div>
      </header>

      <section class="mg-ops-status" data-ops-status role="status" aria-live="polite">Loading command center…</section>
      <section class="mg-ops-grid" data-ops-summary></section>
      <section class="mg-ops-actions" data-ops-actions></section>

      <div class="mg-ops-columns">
        <section class="mg-ops-panel">
          <header><h2>Critical work rail</h2><p>Breached, overdue, escalated, aged, incomplete, and follow-up required work.</p></header>
          <div data-ops-critical></div>
        </section>
        <section class="mg-ops-panel">
          <header><h2>Workload balance</h2><p>Active queue work by admin and risk level.</p></header>
          <div data-ops-workload></div>
        </section>
      </div>

      <div class="mg-ops-columns">
        <section class="mg-ops-panel">
          <header><h2>Automation health</h2><p>Last automation run, counts, and recommended next run.</p></header>
          <div data-ops-automation></div>
        </section>
        <section class="mg-ops-panel">
          <header><h2>Resolution reporting</h2><p>Resolution time, SLA breach rate, reopen rate, outcomes, playbooks, and aging.</p></header>
          <div data-ops-reporting></div>
        </section>
      </div>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
