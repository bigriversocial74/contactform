<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.admin_agent');
$page_title = 'Main Admin Agent | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-main-admin-agent-page';
$page_styles = [
    '/assets/css/admin-shell.css',
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/personal-gifting-agent.css',
    '/assets/css/merchant-agent-chat.css',
    '/assets/css/admin-agent.css?v=1.0.0',
    '/assets/css/admin-agent-phase2.css?v=2.0.0',
    '/assets/css/admin-agent-phase3.css?v=3.0.0',
    '/assets/css/admin-agent-phase4.css?v=4.0.0',
    '/assets/css/admin-agent-phase5.css?v=5.0.0',
    '/assets/css/admin-agent-phase6.css?v=6.0.0',
];
$page_scripts = [
    '/assets/js/admin-agent-phase2.js?v=2.0.0',
    '/assets/js/admin-agent-phase3.js?v=3.0.0',
    '/assets/js/admin-agent-phase4.js?v=4.0.0',
    '/assets/js/admin-agent-phase5.js?v=5.0.0',
    '/assets/js/admin-agent-phase6.js?v=6.0.0',
];
$adminActive = 'admin-agent';
$csrfToken = mg_csrf_token();
$displayName = mg_user_display_name();

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app mg-admin-agent-app" data-admin-agent data-csrf-token="<?= mg_e($csrfToken) ?>" data-api-endpoint="/api/admin/admin-agent-phase6-router.php" data-phase6-api-endpoint="/api/admin/admin-agent-phase6.php" data-stream-endpoint="/api/admin/admin-agent-phase6-stream.php" data-phase6-upload-endpoint="/api/admin/admin-agent-phase6-evidence-upload.php" data-phase5-api-endpoint="/api/admin/admin-agent-phase5.php" data-phase5-stream-endpoint="/api/admin/admin-agent-phase5-stream.php" data-phase4-api-endpoint="/api/admin/admin-agent-phase4.php" data-phase4-stream-endpoint="/api/admin/admin-agent-phase4-stream.php" data-phase3-api-endpoint="/api/admin/admin-agent-phase3.php" data-phase3-stream-endpoint="/api/admin/admin-agent-phase3-stream.php" data-phase2-api-endpoint="/api/admin/admin-agent-phase2.php" data-phase2-stream-endpoint="/api/admin/admin-agent-phase2-stream.php">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>

  <div class="mg-app-workspace mg-admin-workspace mg-admin-agent-workspace">
    <main class="mg-admin-agent-main">
      <section class="mg-admin-agent-topbar">
        <div>
          <span class="mg-admin-agent-eyebrow">Final operational readiness · Phase 6</span>
          <h1>Main Admin Agent</h1>
          <p>Complete the setup from this page: run the full analysis, upload validator evidence, verify scheduled monitoring, review continuity alerts, and generate the final readiness package.</p>
        </div>
        <div class="mg-admin-agent-topbar-actions">
          <span class="mg-admin-agent-live"><i></i><strong data-admin-agent-live-label>Connecting</strong></span>
          <button class="mg-btn mg-btn-primary" type="button" data-admin-agent-final-readiness>Run final readiness check</button>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-phase6-settings>Phase 6 settings</button>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-continuity-brief>Send continuity brief</button>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-readiness-export>Generate readiness export</button>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-retention-preview-button>Retention preview</button>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-drill-create>Plan recovery drill</button>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-change-risk>Evaluate change risk</button>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-maintenance-create>Maintenance window</button>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-release>Evaluate release</button>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-brief>Brief delivery</button>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-summary>Executive summary</button>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-deployment>Record deployment</button>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-scan>Run full analysis</button>
          <button class="mg-btn mg-btn-ghost" type="button" data-admin-agent-controls aria-expanded="false">Agent controls</button>
        </div>
      </section>

      <section class="mg-admin-agent-schema" data-admin-agent-schema hidden></section>
      <section class="mg-admin-agent-schema is-phase2" data-admin-agent-phase2-schema hidden></section>
      <section class="mg-admin-agent-schema is-phase3" data-admin-agent-phase3-schema hidden></section>
      <section class="mg-admin-agent-schema is-phase4" data-admin-agent-phase4-schema hidden></section>
      <section class="mg-admin-agent-schema is-phase5" data-admin-agent-phase5-schema hidden></section>
      <section class="mg-admin-agent-schema is-phase6" data-admin-agent-phase6-schema hidden></section>

      <section class="mg-admin-agent-overview" data-admin-agent-overview aria-label="Current platform status">
        <article class="mg-admin-agent-score is-loading">
          <span>System health</span><strong>—</strong><small>Loading monitor state</small>
        </article>
      </section>

      <section class="mg-admin-agent-intelligence-strip" data-admin-agent-intelligence-strip aria-label="Phase 2 intelligence status"></section>
      <section class="mg-admin-agent-phase3-strip" data-admin-agent-phase3-strip aria-label="Phase 3 operational status"></section>
      <section class="mg-admin-agent-phase4-strip" data-admin-agent-phase4-strip aria-label="Phase 4 reliability governance status"></section>
      <section class="mg-admin-agent-phase5-strip" data-admin-agent-phase5-strip aria-label="Phase 5 recovery assurance status"></section>
      <section class="mg-admin-agent-phase6-strip" data-admin-agent-phase6-strip aria-label="Phase 6 production readiness status"></section>

      <section class="mg-admin-agent-phase6-panel" aria-label="Phase 6 setup">
        <div class="mg-admin-agent-phase6-panel-head">
          <div>
            <h2>Finish setup without the command line</h2>
            <p data-admin-agent-phase6-settings-summary>Loading Phase 6 setup…</p>
          </div>
          <div class="mg-admin-agent-readiness-score" data-admin-agent-readiness-score>0/100</div>
        </div>
        <div class="mg-admin-agent-phase6-setup">
          <div>
            <div class="mg-admin-agent-phase6-actions">
              <button class="mg-btn mg-btn-primary" type="button" data-admin-agent-final-readiness>Run the complete readiness check</button>
              <div class="mg-admin-agent-phase6-upload">
                <input type="file" accept="application/json,.json" data-phase6-evidence-file aria-label="Choose validator JSON evidence">
                <button class="mg-btn mg-btn-soft" type="button" data-phase6-evidence-upload>Upload validator JSON</button>
              </div>
            </div>
            <p class="mg-help-text">The upload reads the validator JSON, records only its verification metadata, and recalculates readiness. Backup contents and credentials are never uploaded.</p>
          </div>
          <div data-admin-agent-phase6-scheduler></div>
        </div>
      </section>

      <section class="mg-personal-agent-view mg-personal-agent-chat-view mg-admin-agent-chat-view">
        <div class="mg-personal-agent-chat-stream mg-admin-agent-chat-stream">
          <article class="mg-personal-agent-message is-assistant is-intro mg-admin-agent-intro">
            <div class="mg-admin-agent-intro-head">
              <div>
                <span class="mg-personal-agent-message-label mg-admin-agent-kicker">Main Admin Agent</span>
                <h2 class="mg-personal-agent-intro-greeting">Final readiness is available, <?= mg_e($displayName) ?>.</h2>
              </div>
              <span class="mg-admin-agent-systematic">Database-first · No AI credits</span>
            </div>
            <p>Ask what remains before production readiness, whether automatic monitoring is active, which recovery drills are due, what continuity alerts need attention, or whether the evidence package is ready to export.</p>
            <div class="mg-admin-agent-quick-prompts" aria-label="Main Admin Agent quick reports">
              <button type="button" data-admin-agent-prompt="Overview">Overview</button>
              <button type="button" data-admin-agent-prompt="What changed?">What changed?</button>
              <button type="button" data-admin-agent-prompt="Final production readiness report">Final readiness</button>
              <button type="button" data-admin-agent-prompt="Automatic scheduler status">Scheduler</button>
              <button type="button" data-admin-agent-prompt="Continuity alert report">Continuity alerts</button>
              <button type="button" data-admin-agent-prompt="Recovery drill calendar">Drill calendar</button>
              <button type="button" data-admin-agent-prompt="Evidence attestation history">Attestations</button>
              <button type="button" data-admin-agent-prompt="Readiness export history">Readiness exports</button>
              <button type="button" data-admin-agent-prompt="Retention preview">Retention</button>
              <button type="button" data-admin-agent-prompt="Recovery objective report">Recovery objectives</button>
              <button type="button" data-admin-agent-prompt="Backup evidence report">Backup evidence</button>
              <button type="button" data-admin-agent-prompt="Restore drill report">Restore drills</button>
              <button type="button" data-admin-agent-prompt="Recovery plan report">Recovery plans</button>
              <button type="button" data-admin-agent-prompt="Business continuity scorecards">Continuity</button>
              <button type="button" data-admin-agent-prompt="Recovery gap report">Recovery gaps</button>
              <button type="button" data-admin-agent-prompt="Maintenance window report">Maintenance</button>
              <button type="button" data-admin-agent-prompt="Deployment change risk report">Change risk</button>
              <button type="button" data-admin-agent-prompt="Historical reliability scorecards">Reliability</button>
              <button type="button" data-admin-agent-prompt="Capacity forecast report">Capacity</button>
              <button type="button" data-admin-agent-prompt="Incident learning and postmortem report">Learning</button>
              <button type="button" data-admin-agent-prompt="Prevention follow-up report">Prevention</button>
              <button type="button" data-admin-agent-prompt="Service topology report">Service map</button>
              <button type="button" data-admin-agent-prompt="SLO and error budget report">SLO budgets</button>
              <button type="button" data-admin-agent-prompt="Incident workspace report">Incident rooms</button>
              <button type="button" data-admin-agent-prompt="Root cause timeline">Cause analysis</button>
              <button type="button" data-admin-agent-prompt="Release readiness gate">Release gate</button>
              <button type="button" data-admin-agent-prompt="Scheduled brief delivery">Brief delivery</button>
              <button type="button" data-admin-agent-prompt="Cross-system correlations">Correlations</button>
              <button type="button" data-admin-agent-prompt="Anomaly report">Anomalies</button>
              <button type="button" data-admin-agent-prompt="Deployment impact report">Deploy impact</button>
              <button type="button" data-admin-agent-prompt="Escalation report">Escalations</button>
              <button type="button" data-admin-agent-prompt="Executive summary">Executive summary</button>
              <button type="button" data-admin-agent-prompt="Controlled remediation report">Remediation</button>
            </div>
          </article>

          <div class="mg-agent-chat-feed mg-admin-agent-feed" data-admin-agent-feed aria-live="polite">
            <div class="mg-agent-chat-empty">
              <div class="mg-agent-chat-empty-icon" aria-hidden="true">✦</div>
              <strong>Loading Main Admin Agent…</strong>
              <p>Final readiness, scheduler health, continuity alerts, drill schedules, attestations, exports, recovery evidence, and live findings will appear here.</p>
            </div>
          </div>
        </div>
      </section>

      <p class="mg-form-status mg-admin-agent-status" data-admin-agent-status role="status" aria-live="polite"></p>
    </main>

    <form class="mg-merchant-agent-composer mg-admin-agent-composer" data-admin-agent-form>
      <div class="mg-merchant-agent-composer-context">
        <span><kbd>A</kbd> Admin command mode · <span data-admin-agent-context>All systems · Final readiness · Review-gated execution</span></span>
        <span>Manual operation works without a scheduler; automatic monitoring requires one hosting control-panel setup</span>
      </div>
      <div class="mg-merchant-agent-composer-row">
        <div class="mg-agent-chat-tool-wrap">
          <button class="mg-agent-chat-tool" type="button" aria-label="Add system report command" aria-expanded="false" data-admin-agent-context-toggle>+</button>
          <div class="mg-agent-context-menu" data-admin-agent-context-menu hidden>
            <button type="button" data-admin-agent-prompt="Show the final production readiness checklist">Final readiness</button>
            <button type="button" data-admin-agent-prompt="Show automatic scheduler status">Scheduler status</button>
            <button type="button" data-admin-agent-prompt="Show active continuity alerts">Continuity alerts</button>
            <button type="button" data-admin-agent-prompt="Show the recovery drill calendar">Drill calendar</button>
            <button type="button" data-admin-agent-prompt="Show evidence attestations">Attestations</button>
            <button type="button" data-admin-agent-prompt="Show readiness export history">Readiness exports</button>
            <button type="button" data-admin-agent-prompt="Show retention preview">Retention preview</button>
            <button type="button" data-admin-agent-prompt="Show recovery objectives and RTO RPO targets">Recovery objectives</button>
            <button type="button" data-admin-agent-prompt="Show backup and restore validation evidence">Backup evidence</button>
            <button type="button" data-admin-agent-prompt="Show recovery drill readiness">Restore drills</button>
            <button type="button" data-admin-agent-prompt="Show dependency-aware recovery plans">Recovery plans</button>
            <button type="button" data-admin-agent-prompt="Show business continuity scorecards">Continuity scores</button>
            <button type="button" data-admin-agent-prompt="Show active recovery readiness gaps">Recovery gaps</button>
            <button type="button" data-admin-agent-prompt="Show planned and active maintenance windows">Maintenance windows</button>
            <button type="button" data-admin-agent-prompt="Evaluate current deployment change risk">Change risk</button>
            <button type="button" data-admin-agent-prompt="Show 7 30 and 90 day reliability scorecards">Reliability scorecards</button>
            <button type="button" data-admin-agent-prompt="Show deterministic capacity forecasts">Capacity forecasts</button>
            <button type="button" data-admin-agent-prompt="Show incident learning drafts">Incident learning</button>
            <button type="button" data-admin-agent-prompt="Show prevention follow-up proposals">Prevention follow-ups</button>
            <button type="button" data-admin-agent-prompt="Show service topology and dependencies">Service topology</button>
            <button type="button" data-admin-agent-prompt="Show SLO burn rates and error budgets">SLO and error budgets</button>
            <button type="button" data-admin-agent-prompt="Show active incident workspaces">Incident workspaces</button>
            <button type="button" data-admin-agent-prompt="Show ranked root cause candidates">Cause candidates</button>
            <button type="button" data-admin-agent-prompt="Is production safe to deploy?">Release readiness</button>
            <button type="button" data-admin-agent-prompt="Review scheduled brief delivery">Scheduled briefs</button>
            <button type="button" data-admin-agent-prompt="Show active anomalies">Learned anomalies</button>
            <button type="button" data-admin-agent-prompt="Show cross-system correlations">Correlated incidents</button>
            <button type="button" data-admin-agent-prompt="Review approved remediation actions">Remediation queue</button>
          </div>
        </div>
        <textarea data-admin-agent-textarea name="message" rows="1" maxlength="4000" placeholder="Ask what remains before production readiness, whether monitoring is active, or what needs attention…" aria-label="Message the Main Admin Agent" required></textarea>
        <button class="mg-agent-chat-send" type="submit" data-admin-agent-send aria-label="Send message" disabled>↑</button>
      </div>
    </form>
  </div>

  <div class="mg-agent-chat-drawer-backdrop" data-admin-agent-drawer-close hidden></div>
  <aside class="mg-agent-chat-right mg-admin-agent-drawer" id="admin-agent-drawer" aria-label="Main Admin Agent controls" data-admin-agent-drawer aria-hidden="true">
    <div class="mg-agent-drawer-head">
      <div><strong>Main Admin Agent controls</strong><small>Phase 6 final readiness</small></div>
      <button type="button" aria-label="Close Main Admin Agent controls" data-admin-agent-drawer-close>×</button>
    </div>

    <section class="mg-agent-context-card mg-agent-compact-rail">
      <div class="mg-agent-rail-row mg-agent-thread-actions">
        <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-new-thread>New chat</button>
        <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-refresh>Refresh</button>
      </div>
      <div class="mg-agent-chat-fields">
        <label>Saved system chat
          <select data-admin-agent-thread-select><option value="">Current system chat</option></select>
        </label>
        <label>System domain
          <select data-admin-agent-domain>
            <option value="">All systems</option>
            <option value="security">Security</option>
            <option value="operations">Operations</option>
            <option value="support">Support &amp; SLA</option>
            <option value="automation">Automation</option>
            <option value="notifications">Notifications</option>
            <option value="database">Database</option>
            <option value="commerce">Commerce</option>
            <option value="claims">Claims &amp; redemption</option>
            <option value="campaigns">Campaigns</option>
            <option value="storefront">Storefront</option>
            <option value="deployment">Deployment</option>
            <option value="ai_accounting">AI accounting</option>
            <option value="governance">Governance</option>
            <option value="intelligence">Intelligence</option>
            <option value="continuity">Continuity</option>
          </select>
        </label>
      </div>
    </section>

    <section class="mg-admin-agent-rail-section is-phase6"><header><strong>Final readiness checks</strong><span data-admin-agent-readiness-count>0</span></header><div data-admin-agent-readiness-checks class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase6"><header><strong>Continuity alerts</strong><span data-admin-agent-continuity-alert-count>0</span></header><div data-admin-agent-continuity-alerts class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase6"><header><strong>Drill calendar</strong><span data-admin-agent-drill-schedule-count>0</span></header><div data-admin-agent-drill-schedules class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase6"><header><strong>Evidence attestations</strong><span data-admin-agent-attestation-count>0</span></header><div data-admin-agent-attestations class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase6"><header><strong>Continuity briefs</strong><span data-admin-agent-continuity-brief-count>0</span></header><div data-admin-agent-continuity-briefs class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase6"><header><strong>Readiness exports</strong><span data-admin-agent-readiness-export-count>0</span></header><div data-admin-agent-readiness-exports class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase6"><header><strong>Retention preview</strong><span>Safe</span></header><div data-admin-agent-retention-preview class="mg-admin-agent-rail-list"></div></section>

    <section class="mg-admin-agent-rail-section is-phase5"><header><strong>Recovery objectives</strong><span data-admin-agent-recovery-objective-count>0</span></header><div data-admin-agent-recovery-objectives class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase5"><header><strong>Backup evidence</strong><span data-admin-agent-backup-evidence-count>0</span></header><div data-admin-agent-backup-evidence class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase5"><header><strong>Restore drills</strong><span data-admin-agent-restore-drill-count>0</span></header><div data-admin-agent-restore-drills class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase5"><header><strong>Recovery plans</strong><span data-admin-agent-recovery-plan-count>0</span></header><div data-admin-agent-recovery-plans class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase5"><header><strong>Continuity scorecards</strong><span data-admin-agent-continuity-count>0</span></header><div data-admin-agent-continuity-scorecards class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase5"><header><strong>Recovery gaps</strong><span data-admin-agent-recovery-gap-count>0</span></header><div data-admin-agent-recovery-gaps class="mg-admin-agent-rail-list"></div></section>

    <section class="mg-admin-agent-rail-section is-phase4"><header><strong>Maintenance windows</strong><span data-admin-agent-maintenance-count>0</span></header><div data-admin-agent-maintenance class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase4"><header><strong>Deployment change risk</strong><span data-admin-agent-change-risk-count>0</span></header><div data-admin-agent-change-risks class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase4"><header><strong>Reliability scorecards</strong><span data-admin-agent-reliability-count>0</span></header><div data-admin-agent-reliability class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase4"><header><strong>Capacity forecasts</strong><span data-admin-agent-capacity-count>0</span></header><div data-admin-agent-capacity class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase4"><header><strong>Incident learning</strong><span data-admin-agent-learning-count>0</span></header><div data-admin-agent-learning class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section is-phase4"><header><strong>Prevention follow-ups</strong><span data-admin-agent-prevention-count>0</span></header><div data-admin-agent-prevention class="mg-admin-agent-rail-list"></div></section>

    <section class="mg-admin-agent-rail-section"><header><strong>Service topology</strong><span data-admin-agent-service-count>0</span></header><div data-admin-agent-services class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section"><header><strong>SLO &amp; error budgets</strong><span data-admin-agent-slo-count>0</span></header><div data-admin-agent-slos class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section"><header><strong>Incident workspaces</strong><span data-admin-agent-incident-count>0</span></header><div data-admin-agent-incidents class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section"><header><strong>Release readiness</strong><span data-admin-agent-release-count>0</span></header><div data-admin-agent-release-gates class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section"><header><strong>Scheduled briefs</strong><span data-admin-agent-brief-count>0</span></header><div data-admin-agent-briefs class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section"><header><strong>Correlated incidents</strong><span data-admin-agent-correlation-count>0</span></header><div data-admin-agent-correlations class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section"><header><strong>Learned anomalies</strong><span data-admin-agent-anomaly-count>0</span></header><div data-admin-agent-anomalies class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section"><header><strong>Escalations</strong><span data-admin-agent-escalation-count>0</span></header><div data-admin-agent-escalations class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section"><header><strong>Deployment timeline</strong><span data-admin-agent-deployment-count>0</span></header><div data-admin-agent-deployments class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section"><header><strong>Controlled remediation</strong><span data-admin-agent-review-count>0</span></header><div data-admin-agent-remediation class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section"><header><strong>Monitor registry</strong><span data-admin-agent-monitor-count>0</span></header><div data-admin-agent-monitors class="mg-admin-agent-rail-list"></div></section>
    <section class="mg-admin-agent-rail-section"><header><strong>Active findings</strong><span data-admin-agent-finding-count>0</span></header><div data-admin-agent-findings class="mg-admin-agent-rail-list"></div></section>
  </aside>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
