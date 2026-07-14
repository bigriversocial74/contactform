<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user=mg_require_admin_page_permission('admin.loyalty_quests');
$page_title='Loyalty Quest Operations | Microgifter Admin';
$page_section='account';
$header_mode='account';
$page_body_class='mg-admin-loyalty-quests-page';
$page_styles=['/assets/css/admin-shell.css','/assets/css/admin-loyalty-quests.css'];
$page_scripts=['/assets/js/admin-loyalty-quests.js'];
$adminActive='loyalty-quests';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <main class="mg-admin-main mg-admin-content-page" data-admin-loyalty-quests>
      <section class="mg-admin-page-head mg-lqo-head">
        <div>
          <span class="mg-eyebrow">Campaign operations</span>
          <h1>Loyalty Quest Command Center</h1>
          <p>Pause unsafe campaigns, route evidence backlogs, recover failed delivery jobs, and inspect Inbox reward outcomes without bypassing merchant review or PPPM ownership.</p>
        </div>
        <div class="mg-lqo-head-actions">
          <a class="mg-btn mg-btn-secondary" href="/admin/operations-command.php">Operations Command</a>
          <button class="mg-btn mg-btn-primary" type="button" data-lqo-refresh>Refresh operations</button>
        </div>
      </section>

      <section class="mg-lqo-authority" aria-label="Administrative authority boundary">
        <strong>Authority boundary</strong>
        <span>Admins may pause, resume, or end quests; nudge merchant review; and retry delivery failures.</span>
        <span>Admins cannot approve evidence, issue rewards, alter claim ownership, or redeem PPPM items here.</span>
      </section>

      <div class="mg-lqo-kpis" data-lqo-kpis aria-live="polite"></div>

      <section class="mg-app-panel mg-lqo-filter-panel">
        <div class="mg-app-panel-body mg-lqo-filters">
          <label>Status
            <select data-lqo-status>
              <option value="all">All statuses</option>
              <option value="active">Active</option>
              <option value="paused">Paused</option>
              <option value="scheduled">Scheduled</option>
              <option value="draft">Draft</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
              <option value="archived">Archived</option>
            </select>
          </label>
          <label>Search
            <input type="search" data-lqo-search maxlength="180" placeholder="Quest, merchant, or campaign ID">
          </label>
          <button class="mg-btn mg-btn-secondary" type="button" data-lqo-apply>Apply filters</button>
          <span class="mg-status-badge" data-lqo-status-text>Loading</span>
        </div>
      </section>

      <nav class="mg-lqo-tabs" aria-label="Loyalty Quest operations sections" data-lqo-tabs>
        <button type="button" class="is-active" data-tab="campaigns">Campaigns <span data-count="campaigns">0</span></button>
        <button type="button" data-tab="evidence">Review backlog <span data-count="evidence">0</span></button>
        <button type="button" data-tab="deliveries">Delivery recovery <span data-count="deliveries">0</span></button>
        <button type="button" data-tab="activity">Admin activity <span data-count="activity">0</span></button>
      </nav>

      <section class="mg-app-panel mg-lqo-panel" data-panel="campaigns">
        <div class="mg-app-panel-head"><div><h2>Loyalty Quest campaigns</h2><p>Cross-merchant operational health, participation, review backlog, Inbox delivery, redemption, and message failures.</p></div></div>
        <div class="mg-app-panel-body"><div class="mg-lqo-campaigns" data-lqo-campaigns aria-live="polite"></div></div>
      </section>

      <section class="mg-app-panel mg-lqo-panel" data-panel="evidence" hidden>
        <div class="mg-app-panel-head"><div><h2>Merchant review backlog</h2><p>Submitted evidence is shown without proof content or participant PII. Admins may remind the merchant but cannot approve or reject it.</p></div></div>
        <div class="mg-app-panel-body"><div class="mg-lqo-list" data-lqo-evidence aria-live="polite"></div></div>
      </section>

      <section class="mg-app-panel mg-lqo-panel" data-panel="deliveries" hidden>
        <div class="mg-app-panel-head"><div><h2>Delivery recovery</h2><p>Failed, dead-letter, and processing jobs stuck longer than fifteen minutes. Recipient email addresses are masked.</p></div></div>
        <div class="mg-app-panel-body"><div class="mg-lqo-list" data-lqo-deliveries aria-live="polite"></div></div>
      </section>

      <section class="mg-app-panel mg-lqo-panel" data-panel="activity" hidden>
        <div class="mg-app-panel-head"><div><h2>Recent admin actions</h2><p>Campaign events generated by admin pause, resume, end, review-nudge, and delivery-retry actions.</p></div></div>
        <div class="mg-app-panel-body"><div class="mg-lqo-list" data-lqo-activity aria-live="polite"></div></div>
      </section>

      <dialog class="mg-lqo-dialog" data-lqo-dialog aria-labelledby="lqo-dialog-title">
        <form method="dialog" data-lqo-action-form>
          <div class="mg-lqo-dialog-head">
            <div><span class="mg-eyebrow">Audited operation</span><h2 id="lqo-dialog-title" data-lqo-dialog-title>Confirm action</h2></div>
            <button type="button" class="mg-icon-btn" data-lqo-close aria-label="Close dialog">×</button>
          </div>
          <p data-lqo-dialog-description>Describe why this operation is required.</p>
          <input type="hidden" name="action">
          <input type="hidden" name="campaign_id">
          <input type="hidden" name="evidence_id">
          <input type="hidden" name="job_id">
          <label>Operator reason
            <textarea name="reason" minlength="12" maxlength="1000" required placeholder="Explain the operational reason and expected outcome."></textarea>
          </label>
          <div class="mg-form-status" data-lqo-dialog-status role="status"></div>
          <div class="mg-lqo-dialog-actions">
            <button class="mg-btn mg-btn-secondary" type="button" data-lqo-close>Cancel</button>
            <button class="mg-btn mg-btn-primary" type="submit" data-lqo-confirm>Confirm operation</button>
          </div>
        </form>
      </dialog>
    </main>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>