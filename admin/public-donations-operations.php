<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_key('admin.settings');
$page_title = 'Public Donations Operations | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-public-donations-page';
$page_styles = [
    '/assets/css/admin-shell.css',
    '/assets/css/admin-public-donations-operations.css?v=20260724-v1',
];
$page_scripts = [
    '/assets/js/admin-public-donations-operations.js?v=20260724-v1',
];
$adminActive = 'public-donations-operations';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <main class="mg-pdo" data-public-donations-operations data-csrf="<?= mg_e(mg_csrf_token()) ?>">
      <header class="mg-pdo-hero">
        <div>
          <a class="mg-pdo-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Public Donations · Production operations</span>
          <h1>Rollout, integrity, and repair control</h1>
          <p>Verify deployment readiness, control feature exposure, reconcile canonical reward lifecycles, and preserve immutable operational receipts from one protected workspace.</p>
        </div>
        <div class="mg-pdo-hero-actions">
          <span>Updated <strong data-pdo-updated>—</strong></span>
          <button class="mg-btn mg-btn-ghost" type="button" data-pdo-refresh>Refresh</button>
        </div>
      </header>

      <section class="mg-pdo-stats" aria-label="Public Donations operational summary">
        <article><span>Campaigns</span><strong data-pdo-stat="campaigns">—</strong><small><b data-pdo-stat="active_campaigns">—</b> active</small></article>
        <article><span>Net allocated</span><strong data-pdo-stat="net_allocated">—</strong><small><b data-pdo-stat="recalled">—</b> recalled</small></article>
        <article><span>Assignments</span><strong data-pdo-stat="assignments">—</strong><small>Active or paused</small></article>
        <article><span>Drift receipts</span><strong data-pdo-stat="receipts_with_drift">—</strong><small><b data-pdo-stat="receipts">—</b> total receipts</small></article>
      </section>

      <div class="mg-pdo-grid mg-pdo-grid-top">
        <section class="mg-pdo-panel mg-pdo-readiness-panel">
          <header>
            <div><span class="mg-eyebrow">Deployment gate</span><h2>Readiness</h2></div>
            <span class="mg-pdo-status" data-pdo-readiness-status>Checking…</span>
          </header>
          <div class="mg-pdo-checks" data-pdo-readiness></div>
          <div class="mg-pdo-disclosure">
            <strong>Operational boundary</strong>
            <p>Public Donations are merchant-funded promotional rewards. They are not cash donations or tax-deductible charitable contributions. Repairs never invent attribution or ownership.</p>
          </div>
        </section>

        <section class="mg-pdo-panel mg-pdo-rollout-panel">
          <header>
            <div><span class="mg-eyebrow">Feature exposure</span><h2>Rollout control</h2></div>
            <span class="mg-pdo-source" data-pdo-rollout-source>—</span>
          </header>
          <form data-pdo-rollout-form>
            <label>Feature state
              <select name="feature_state" data-pdo-feature-state>
                <option value="disabled">Disabled</option>
                <option value="admin_only">Admin only</option>
                <option value="selected_merchants">Selected merchants</option>
                <option value="enabled">Enabled for all merchants</option>
              </select>
            </label>

            <div class="mg-pdo-selected-block" data-pdo-selected-block>
              <div class="mg-pdo-field-heading">
                <div><strong>Selected merchants</strong><span>Required only for selected-merchants rollout.</span></div>
                <button class="mg-btn mg-btn-soft" type="button" data-pdo-clear-merchants>Clear</button>
              </div>
              <div class="mg-pdo-selected-list" data-pdo-selected-merchants></div>
              <label>Find merchant
                <div class="mg-pdo-search-row">
                  <input type="search" maxlength="100" placeholder="Merchant name, email, or user ID" data-pdo-merchant-query>
                  <button class="mg-btn mg-btn-soft" type="button" data-pdo-search-merchants>Search</button>
                </div>
              </label>
              <div class="mg-pdo-search-results" data-pdo-merchant-results></div>
            </div>

            <label>Required action reason
              <textarea name="reason" rows="3" maxlength="240" required placeholder="Explain why this rollout state is changing."></textarea>
            </label>
            <label>Typed confirmation
              <input name="confirmation" type="text" maxlength="80" required autocomplete="off" placeholder="UPDATE PUBLIC DONATIONS ROLLOUT">
            </label>
            <div class="mg-pdo-notice" data-pdo-rollout-notice role="status" aria-live="polite"></div>
            <div class="mg-pdo-form-actions">
              <button class="mg-btn mg-btn-ghost" type="button" data-pdo-environment>Return to environment config</button>
              <button class="mg-btn mg-btn-primary" type="submit">Apply rollout</button>
            </div>
          </form>
          <div class="mg-pdo-environment" data-pdo-environment-summary></div>
        </section>
      </div>

      <section class="mg-pdo-panel mg-pdo-reconcile-panel">
        <header>
          <div>
            <span class="mg-eyebrow">Canonical lifecycle integrity</span>
            <h2>Reconciliation</h2>
            <p>Dry-run is the default. Deterministic repair is limited to counters, batch totals, recalled visibility, and stale assignments. Missing links and ownership disagreements remain report-only.</p>
          </div>
          <span class="mg-pdo-repair-permission" data-pdo-repair-permission>Checking repair permission…</span>
        </header>

        <form class="mg-pdo-reconcile-form" data-pdo-reconcile-form>
          <div class="mg-pdo-form-grid">
            <label>Merchant user ID
              <input name="merchant_id" type="number" min="1" step="1" required placeholder="42">
            </label>
            <label>Campaign ID or slug <small>Optional</small>
              <input name="campaign" type="text" maxlength="190" placeholder="public UUID or slug">
            </label>
            <label>Operation ID <small>Optional</small>
              <input name="operation" type="text" maxlength="190" placeholder="operation UUID">
            </label>
            <label>Scan limit
              <input name="limit" type="number" min="1" max="1000" value="100" required>
            </label>
          </div>

          <fieldset class="mg-pdo-mode-fieldset">
            <legend>Execution mode</legend>
            <label class="mg-pdo-choice"><input type="radio" name="execution_mode" value="dry_run" checked> <span><strong>Dry run</strong><small>Detect and report only.</small></span></label>
            <label class="mg-pdo-choice"><input type="radio" name="execution_mode" value="repair"> <span><strong>Safe repair</strong><small>Apply only selected deterministic repairs.</small></span></label>
          </fieldset>

          <fieldset class="mg-pdo-repair-modes" data-pdo-repair-modes disabled>
            <legend>Repair modes</legend>
            <label><input type="checkbox" name="repair_modes[]" value="counters"> Campaign and reward-template counters</label>
            <label><input type="checkbox" name="repair_modes[]" value="batch_totals"> Batch recall totals and status</label>
            <label><input type="checkbox" name="repair_modes[]" value="recalled_visibility"> Recalled Wallet, PPPM, Microgift, and Inbox visibility</label>
            <label><input type="checkbox" name="repair_modes[]" value="assignments"> Assignments after Community-role removal</label>
          </fieldset>

          <div class="mg-pdo-form-grid mg-pdo-reconcile-confirmation">
            <label>Required action reason
              <textarea name="reason" rows="3" maxlength="240" required placeholder="Explain why this reconciliation is being run."></textarea>
            </label>
            <label data-pdo-repair-confirmation-wrap hidden>Typed repair confirmation
              <input name="confirmation" type="text" maxlength="80" autocomplete="off" placeholder="REPAIR PUBLIC DONATIONS">
            </label>
          </div>
          <div class="mg-pdo-notice" data-pdo-reconcile-notice role="status" aria-live="polite"></div>
          <div class="mg-pdo-form-actions">
            <button class="mg-btn mg-btn-primary" type="submit" data-pdo-reconcile-submit>Run dry reconciliation</button>
          </div>
        </form>

        <div class="mg-pdo-result mg-hidden" data-pdo-result>
          <header>
            <div><span class="mg-eyebrow">Latest receipt</span><h3 data-pdo-result-title>Reconciliation result</h3></div>
            <code data-pdo-result-checksum></code>
          </header>
          <div class="mg-pdo-result-metrics" data-pdo-result-metrics></div>
          <div class="mg-pdo-issue-groups" data-pdo-issue-groups></div>
        </div>
      </section>

      <div class="mg-pdo-grid mg-pdo-grid-bottom">
        <section class="mg-pdo-panel">
          <header><div><span class="mg-eyebrow">Immutable evidence</span><h2>Reconciliation receipts</h2></div></header>
          <div class="mg-pdo-list" data-pdo-receipts></div>
        </section>
        <section class="mg-pdo-panel">
          <header><div><span class="mg-eyebrow">Lifecycle activity</span><h2>Recent operations</h2></div></header>
          <div class="mg-pdo-list" data-pdo-operations></div>
        </section>
      </div>

      <div class="mg-pdo-state" data-pdo-loading>
        <strong>Loading Public Donations operations</strong>
        <span>Reading rollout state, schema readiness, lifecycle activity, and reconciliation receipts.</span>
      </div>
      <div class="mg-pdo-state mg-hidden" data-pdo-error role="alert">
        <strong>Unable to load Public Donations operations</strong>
        <span data-pdo-error-message>The operations workspace could not be loaded.</span>
        <button class="mg-btn mg-btn-soft" type="button" data-pdo-retry>Try again</button>
      </div>
    </main>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
