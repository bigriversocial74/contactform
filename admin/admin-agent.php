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
];
$page_scripts = [
    '/assets/js/admin-agent-phase2.js?v=2.0.0',
    '/assets/js/admin-agent-phase3.js?v=3.0.0',
];
$adminActive = 'admin-agent';
$csrfToken = mg_csrf_token();
$displayName = mg_user_display_name();

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app mg-admin-agent-app" data-admin-agent data-csrf-token="<?= mg_e($csrfToken) ?>" data-api-endpoint="/api/admin/admin-agent-phase3.php" data-stream-endpoint="/api/admin/admin-agent-phase3-stream.php">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>

  <div class="mg-app-workspace mg-admin-workspace mg-admin-agent-workspace">
    <main class="mg-admin-agent-main">
      <section class="mg-admin-agent-topbar">
        <div>
          <span class="mg-admin-agent-eyebrow">Protected system command layer · Phase 3</span>
          <h1>Main Admin Agent</h1>
          <p>Service topology, learned intelligence, SLO error budgets, incident workspaces, causal timelines, release readiness, executive briefs, and controlled remediation.</p>
        </div>
        <div class="mg-admin-agent-topbar-actions">
          <span class="mg-admin-agent-live"><i></i><strong data-admin-agent-live-label>Connecting</strong></span>
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

      <section class="mg-admin-agent-overview" data-admin-agent-overview aria-label="Current platform status">
        <article class="mg-admin-agent-score is-loading">
          <span>System health</span><strong>—</strong><small>Loading monitor state</small>
        </article>
      </section>

      <section class="mg-admin-agent-intelligence-strip" data-admin-agent-intelligence-strip aria-label="Phase 2 intelligence status"></section>
      <section class="mg-admin-agent-phase3-strip" data-admin-agent-phase3-strip aria-label="Phase 3 operational status"></section>

      <section class="mg-personal-agent-view mg-personal-agent-chat-view mg-admin-agent-chat-view">
        <div class="mg-personal-agent-chat-stream mg-admin-agent-chat-stream">
          <article class="mg-personal-agent-message is-assistant is-intro mg-admin-agent-intro">
            <div class="mg-admin-agent-intro-head">
              <div>
                <span class="mg-personal-agent-message-label mg-admin-agent-kicker">Main Admin Agent</span>
                <h2 class="mg-personal-agent-intro-greeting">System command intelligence is ready, <?= mg_e($displayName) ?>.</h2>
              </div>
              <span class="mg-admin-agent-systematic">Database-first · No AI credits</span>
            </div>
            <p>Ask which service is degraded, how fast an error budget is burning, what likely caused an incident, whether the current release is safe, or which approved response can run. Cause candidates remain evidence-ranked hypotheses, and every operational mutation remains review-gated.</p>
            <div class="mg-admin-agent-quick-prompts" aria-label="Main Admin Agent quick reports">
              <button type="button" data-admin-agent-prompt="Overview">Overview</button>
              <button type="button" data-admin-agent-prompt="What changed?">What changed?</button>
              <button type="button" data-admin-agent-prompt="Service topology report">Service map</button>
              <button type="button" data-admin-agent-prompt="SLO and error budget report">SLO budgets</button>
              <button type="button" data-admin-agent-prompt="Incident workspace report">Incident rooms</button>
              <button type="button" data-admin-agent-prompt="Root cause timeline">Cause analysis</button>
              <button type="button" data-admin-agent-prompt="Release readiness gate">Release gate</button>
              <button type="button" data-admin-agent-prompt="Scheduled brief delivery">Brief delivery</button>
              <button type="button" data-admin-agent-prompt="Cross-system correlations">Correlations</button>
              <button type="button" data-admin-agent-prompt="Anomaly report">Anomalies</button>
              <button type="button" data-admin-agent-prompt="Controlled remediation report">Remediation</button>
            </div>
          </article>

          <div class="mg-agent-chat-feed mg-admin-agent-feed" data-admin-agent-feed aria-live="polite">
            <div class="mg-agent-chat-empty">
              <div class="mg-agent-chat-empty-icon" aria-hidden="true">✦</div>
              <strong>Loading Main Admin Agent…</strong>
              <p>Service health, error budgets, incident rooms, causal evidence, release gates, escalations, and review-ready actions will appear here.</p>
            </div>
          </div>
        </div>
      </section>

      <p class="mg-form-status mg-admin-agent-status" data-admin-agent-status role="status" aria-live="polite"></p>
    </main>

    <form class="mg-merchant-agent-composer mg-admin-agent-composer" data-admin-agent-form>
      <div class="mg-merchant-agent-composer-context">
        <span><kbd>A</kbd> Admin command mode · <span data-admin-agent-context>All systems · Dependency-aware · Review-gated execution</span></span>
        <span>Incident declaration requires approval and exact typed confirmation</span>
      </div>
      <div class="mg-merchant-agent-composer-row">
        <div class="mg-agent-chat-tool-wrap">
          <button class="mg-agent-chat-tool" type="button" aria-label="Add system report command" aria-expanded="false" data-admin-agent-context-toggle>+</button>
          <div class="mg-agent-context-menu" data-admin-agent-context-menu hidden>
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
        <textarea data-admin-agent-textarea name="message" rows="1" maxlength="4000" placeholder="Ask about service health, SLOs, incidents, causes, release safety, briefs, or approved recovery…" aria-label="Message the Main Admin Agent" required></textarea>
        <button class="mg-agent-chat-send" type="submit" data-admin-agent-send aria-label="Send message" disabled>↑</button>
      </div>
    </form>
  </div>

  <div class="mg-agent-chat-drawer-backdrop" data-admin-agent-drawer-close hidden></div>
  <aside class="mg-agent-chat-right mg-admin-agent-drawer" id="admin-agent-drawer" aria-label="Main Admin Agent controls" data-admin-agent-drawer aria-hidden="true">
    <div class="mg-agent-drawer-head">
      <div><strong>Main Admin Agent controls</strong><small>Phase 3 operational command</small></div>
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
          </select>
        </label>
      </div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>Service topology</strong><span data-admin-agent-service-count>0</span></header>
      <div data-admin-agent-services class="mg-admin-agent-rail-list"></div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>SLO &amp; error budgets</strong><span data-admin-agent-slo-count>0</span></header>
      <div data-admin-agent-slos class="mg-admin-agent-rail-list"></div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>Incident workspaces</strong><span data-admin-agent-incident-count>0</span></header>
      <div data-admin-agent-incidents class="mg-admin-agent-rail-list"></div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>Release readiness</strong><span data-admin-agent-release-count>0</span></header>
      <div data-admin-agent-release-gates class="mg-admin-agent-rail-list"></div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>Scheduled briefs</strong><span data-admin-agent-brief-count>0</span></header>
      <div data-admin-agent-briefs class="mg-admin-agent-rail-list"></div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>Correlated incidents</strong><span data-admin-agent-correlation-count>0</span></header>
      <div data-admin-agent-correlations class="mg-admin-agent-rail-list"></div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>Learned anomalies</strong><span data-admin-agent-anomaly-count>0</span></header>
      <div data-admin-agent-anomalies class="mg-admin-agent-rail-list"></div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>Escalations</strong><span data-admin-agent-escalation-count>0</span></header>
      <div data-admin-agent-escalations class="mg-admin-agent-rail-list"></div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>Deployment timeline</strong><span data-admin-agent-deployment-count>0</span></header>
      <div data-admin-agent-deployments class="mg-admin-agent-rail-list"></div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>Controlled remediation</strong><span data-admin-agent-review-count>0</span></header>
      <div data-admin-agent-remediation class="mg-admin-agent-rail-list"></div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>Monitor registry</strong><span data-admin-agent-monitor-count>0</span></header>
      <div data-admin-agent-monitors class="mg-admin-agent-rail-list"></div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>Active findings</strong><span data-admin-agent-finding-count>0</span></header>
      <div data-admin-agent-findings class="mg-admin-agent-rail-list"></div>
    </section>
  </aside>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
