<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user=mg_require_admin_page_permission('admin.loyalty_quest_integrity');
$page_title='Loyalty Quest Integrity | Microgifter Admin';
$page_section='account';
$header_mode='account';
$page_body_class='mg-admin-loyalty-quest-integrity-page';
$page_styles=['/assets/css/admin-shell.css','/assets/css/admin-loyalty-quest-integrity.css'];
$page_scripts=['/assets/js/admin-loyalty-quest-integrity.js'];
$adminActive='loyalty-quest-integrity';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <main class="mg-admin-main mg-admin-content-page" data-admin-loyalty-quest-integrity>
      <section class="mg-admin-page-head mg-lqi-head">
        <div>
          <span class="mg-eyebrow">Trust and integrity</span>
          <h1>Loyalty Quest Integrity Center</h1>
          <p>Investigate coordinated account activity, duplicate proof, impossible travel, rapid completion, code velocity, and reward farming without exposing raw IP addresses, device tokens, proof content, or claim secrets.</p>
        </div>
        <div class="mg-lqi-head-actions">
          <a class="mg-btn mg-btn-secondary" href="/admin/loyalty-quests.php">Quest operations</a>
          <button class="mg-btn mg-btn-primary" type="button" data-lqi-refresh>Refresh integrity</button>
        </div>
      </section>

      <section class="mg-lqi-authority" aria-label="Integrity authority boundary">
        <strong>Human review remains authoritative</strong>
        <span>Integrity scoring routes suspicious activity to review. It does not automatically revoke rewards or alter PPPM ownership.</span>
        <span>Confirmed signals can block a pending evidence approval until an administrator clears the signal.</span>
      </section>

      <div class="mg-lqi-kpis" data-lqi-kpis aria-live="polite"></div>

      <section class="mg-app-panel mg-lqi-filter-panel">
        <div class="mg-app-panel-body mg-lqi-filters">
          <label>Status
            <select data-lqi-status>
              <option value="open">Open</option>
              <option value="confirmed">Confirmed</option>
              <option value="acknowledged">Acknowledged</option>
              <option value="cleared">Cleared</option>
              <option value="all">All statuses</option>
            </select>
          </label>
          <label>Severity
            <select data-lqi-severity>
              <option value="all">All severities</option>
              <option value="critical">Critical</option>
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </select>
          </label>
          <label>Loyalty Quest
            <select data-lqi-campaign><option value="">All Loyalty Quests</option></select>
          </label>
          <label>Search
            <input type="search" data-lqi-search maxlength="180" placeholder="Signal, quest, merchant, or signal ID">
          </label>
          <button class="mg-btn mg-btn-secondary" type="button" data-lqi-apply>Apply filters</button>
          <span class="mg-status-badge" data-lqi-status-text>Loading</span>
        </div>
      </section>

      <section class="mg-app-panel">
        <div class="mg-app-panel-head"><div><h2>Integrity signals</h2><p>Signals contain aggregate operational context only. Raw fingerprints, IP addresses, device tokens, and participant proof are never displayed.</p></div></div>
        <div class="mg-app-panel-body"><div class="mg-lqi-signal-list" data-lqi-signals aria-live="polite"></div></div>
      </section>

      <dialog class="mg-lqi-dialog" data-lqi-dialog aria-labelledby="lqi-dialog-title">
        <form method="dialog" data-lqi-form>
          <div class="mg-lqi-dialog-head">
            <div><span class="mg-eyebrow">Audited integrity decision</span><h2 id="lqi-dialog-title" data-lqi-dialog-title>Resolve integrity signal</h2></div>
            <button type="button" class="mg-icon-btn" data-lqi-close aria-label="Close dialog">×</button>
          </div>
          <p data-lqi-dialog-description>Record the evidence-independent reason for this decision.</p>
          <input type="hidden" name="signal_id">
          <input type="hidden" name="resolution">
          <label>Administrator reason
            <textarea name="reason" minlength="12" maxlength="1000" required placeholder="Describe the integrity finding and expected operational outcome."></textarea>
          </label>
          <div class="mg-form-status" data-lqi-dialog-status role="status"></div>
          <div class="mg-lqi-dialog-actions">
            <button class="mg-btn mg-btn-secondary" type="button" data-lqi-close>Cancel</button>
            <button class="mg-btn mg-btn-primary" type="submit" data-lqi-confirm>Confirm decision</button>
          </div>
        </form>
      </dialog>
    </main>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>