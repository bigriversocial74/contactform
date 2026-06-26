<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.notifications');
$page_title = 'Notifications | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-notifications-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-notifications.css'];
$page_scripts = ['/assets/js/admin-notifications.js'];
$adminActive = 'notifications';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-notifications-shell" data-admin-notifications>
      <header class="mg-admin-notifications-hero">
        <div>
          <a class="mg-admin-notifications-back" href="/admin/support-queue.php">← Follow-up queue</a>
          <span class="mg-eyebrow">Admin operations</span>
          <h1>Notification center</h1>
          <p>Manage queue alerts, automation summaries, incident updates, incident analytics, prevention work, SLA breaches, auto-routing, auto-escalation, playbook usage, timeline activity, case comments, checklist completion, overdue reminders, review flags, assignments, reopen events, and digest records.</p>
        </div>
        <div class="mg-admin-notifications-actions">
          <span>Center score <strong>10/10</strong></span>
          <button class="mg-btn mg-btn-soft" type="button" data-notification-mark-all>Mark all read</button>
          <button class="mg-btn mg-btn-ghost" type="button" data-notification-refresh disabled>Refresh</button>
        </div>
      </header>

      <form class="mg-admin-notifications-filters" data-notification-filters>
        <label>Search
          <input type="search" name="q" maxlength="160" placeholder="Title, message, user, or email">
        </label>
        <label>Type
          <select name="type">
            <option value="">All</option>
            <option value="assigned">Assigned</option>
            <option value="overdue">Overdue</option>
            <option value="due_soon">Due soon</option>
            <option value="escalated">Escalated</option>
            <option value="reopened">Reopened</option>
            <option value="review_flag">Review flag</option>
            <option value="digest">Digest</option>
            <option value="auto_routed">Auto routed</option>
            <option value="sla_breach">SLA breach</option>
            <option value="auto_escalated">Auto escalated</option>
            <option value="workload_balance">Workload balance</option>
            <option value="playbook_applied">Playbook applied</option>
            <option value="template_used">Template used</option>
            <option value="checklist_completed">Checklist completed</option>
            <option value="case_comment">Case comment</option>
            <option value="case_comment_pinned">Pinned case comment</option>
            <option value="timeline_viewed">Timeline viewed</option>
            <option value="automation_summary">Automation summary</option>
            <option value="automation_failed">Automation failed</option>
            <option value="quality_review">Quality review</option>
            <option value="incident_declared">Incident declared</option>
            <option value="incident_updated">Incident updated</option>
            <option value="incident_resolved">Incident resolved</option>
            <option value="incident_review_required">Incident review required</option>
            <option value="incident_review_completed">Incident review completed</option>
            <option value="incident_review_followup_due">Incident review follow-up due</option>
            <option value="repeat_incident_detected">Repeat incident detected</option>
            <option value="prevention_task_overdue">Prevention task overdue</option>
            <option value="incident_trend_worsening">Incident trend worsening</option>
          </select>
        </label>
        <label>Severity
          <select name="severity">
            <option value="">All</option>
            <option value="critical">Critical</option>
            <option value="warning">Warning</option>
            <option value="info">Info</option>
          </select>
        </label>
        <label>State
          <select name="unread">
            <option value="">All</option>
            <option value="1">Unread only</option>
          </select>
        </label>
        <div class="mg-admin-notifications-filter-actions">
          <button class="mg-btn mg-btn-primary" type="submit">Apply</button>
          <button class="mg-btn mg-btn-ghost" type="reset" data-notification-reset>Reset</button>
        </div>
      </form>

      <section class="mg-admin-notifications-summary" data-notification-summary></section>
      <div class="mg-admin-notifications-status" data-notification-status role="status" aria-live="polite"></div>
      <section class="mg-admin-notifications-list" data-notification-list></section>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
