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
];
$page_scripts = ['/assets/js/admin-agent-phase2.js?v=2.0.0'];
$adminActive = 'admin-agent';
$csrfToken = mg_csrf_token();
$displayName = mg_user_display_name();

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app mg-admin-agent-app" data-admin-agent data-csrf-token="<?= mg_e($csrfToken) ?>" data-api-endpoint="/api/admin/admin-agent-phase2.php" data-stream-endpoint="/api/admin/admin-agent-phase2-stream.php">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>

  <div class="mg-app-workspace mg-admin-workspace mg-admin-agent-workspace">
    <main class="mg-admin-agent-main">
      <section class="mg-admin-agent-topbar">
        <div>
          <span class="mg-admin-agent-eyebrow">Protected system observer · Phase 2</span>
          <h1>Main Admin Agent</h1>
          <p>Live monitoring, learned baselines, cross-system correlation, deployment awareness, escalation routing, executive reporting, and controlled remediation.</p>
        </div>
        <div class="mg-admin-agent-topbar-actions">
          <span class="mg-admin-agent-live"><i></i><strong data-admin-agent-live-label>Connecting</strong></span>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-summary>Executive summary</button>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-deployment>Record deployment</button>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-scan>Run full analysis</button>
          <button class="mg-btn mg-btn-ghost" type="button" data-admin-agent-controls aria-expanded="false">Agent controls</button>
        </div>
      </section>

      <section class="mg-admin-agent-schema" data-admin-agent-schema hidden></section>
      <section class="mg-admin-agent-schema is-phase2" data-admin-agent-phase2-schema hidden></section>

      <section class="mg-admin-agent-overview" data-admin-agent-overview aria-label="Current platform status">
        <article class="mg-admin-agent-score is-loading">
          <span>System health</span><strong>—</strong><small>Loading monitor state</small>
        </article>
      </section>

      <section class="mg-admin-agent-intelligence-strip" data-admin-agent-intelligence-strip aria-label="Phase 2 intelligence status"></section>

      <section class="mg-personal-agent-view mg-personal-agent-chat-view mg-admin-agent-chat-view">
        <div class="mg-personal-agent-chat-stream mg-admin-agent-chat-stream">
          <article class="mg-personal-agent-message is-assistant is-intro mg-admin-agent-intro">
            <div class="mg-admin-agent-intro-head">
              <div>
                <span class="mg-personal-agent-message-label mg-admin-agent-kicker">Main Admin Agent</span>
                <h2 class="mg-personal-agent-intro-greeting">System intelligence is ready, <?= mg_e($displayName) ?>.</h2>
              </div>
              <span class="mg-admin-agent-systematic">Database-first · No AI credits</span>
            </div>
            <p>Ask what changed, which signals are abnormal, what is correlated, whether a deployment introduced risk, what has escalated, or which approved remediation can safely run. Financial and destructive actions remain disabled.</p>
            <div class="mg-admin-agent-quick-prompts" aria-label="Main Admin Agent quick reports">
              <button type="button" data-admin-agent-prompt="Overview">Overview</button>
              <button type="button" data-admin-agent-prompt="What changed?">What changed?</button>
              <button type="button" data-admin-agent-prompt="Anomaly report">Anomalies</button>
              <button type="button" data-admin-agent-prompt="Cross-system correlations">Correlations</button>
              <button type="button" data-admin-agent-prompt="Deployment impact report">Deployment impact</button>
              <button type="button" data-admin-agent-prompt="Escalation report">Escalations</button>
              <button type="button" data-admin-agent-prompt="Executive summary">Executive summary</button>
              <button type="button" data-admin-agent-prompt="Controlled remediation report">Remediation</button>
            </div>
          </article>

          <div class="mg-agent-chat-feed mg-admin-agent-feed" data-admin-agent-feed aria-live="polite">
            <div class="mg-agent-chat-empty">
              <div class="mg-agent-chat-empty-icon" aria-hidden="true">✦</div>
              <strong>Loading Main Admin Agent…</strong>
              <p>System reports, anomalies, correlations, deployment signals, escalations, and review-ready recommendations will appear here.</p>
            </div>
          </div>
        </div>
      </section>

      <p class="mg-form-status mg-admin-agent-status" data-admin-agent-status role="status" aria-live="polite"></p>
    </main>

    <form class="mg-merchant-agent-composer mg-admin-agent-composer" data-admin-agent-form>
      <div class="mg-merchant-agent-composer-context">
        <span><kbd>A</kbd> Admin intelligence mode · <span data-admin-agent-context>All systems · Live correlations · Review-gated execution</span></span>
        <span>Approved adapters require explicit typed confirmation</span>
      </div>
      <div class="mg-merchant-agent-composer-row">
        <div class="mg-agent-chat-tool-wrap">
          <button class="mg-agent-chat-tool" type="button" aria-label="Add system report command" aria-expanded="false" data-admin-agent-context-toggle>+</button>
          <div class="mg-agent-context-menu" data-admin-agent-context-menu hidden>
            <button type="button" data-admin-agent-prompt="What changed since the last scan?">What changed</button>
            <button type="button" data-admin-agent-prompt="Show active anomalies">Learned anomalies</button>
            <button type="button" data-admin-agent-prompt="Show cross-system correlations">Correlated incidents</button>
            <button type="button" data-admin-agent-prompt="Review deployment impact">Deployment impact</button>
            <button type="button" data-admin-agent-prompt="Review escalation and SLA routing">Escalation routing</button>
            <button type="button" data-admin-agent-prompt="Generate executive summary">Executive summary</button>
            <button type="button" data-admin-agent-prompt="Review approved remediation actions">Remediation queue</button>
          </div>
        </div>
        <textarea data-admin-agent-textarea name="message" rows="1" maxlength="4000" placeholder="Ask the Main Admin Agent about health, anomalies, correlations, deployments, escalations, or approved recovery…" aria-label="Message the Main Admin Agent" required></textarea>
        <button class="mg-agent-chat-send" type="submit" data-admin-agent-send aria-label="Send message" disabled>↑</button>
      </div>
    </form>
  </div>

  <div class="mg-agent-chat-drawer-backdrop" data-admin-agent-drawer-close hidden></div>
  <aside class="mg-agent-chat-right mg-admin-agent-drawer" id="admin-agent-drawer" aria-label="Main Admin Agent controls" data-admin-agent-drawer aria-hidden="true">
    <div class="mg-agent-drawer-head">
      <div><strong>Main Admin Agent controls</strong><small>Phase 2 system intelligence</small></div>
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
            <option value="deployment">Deployment</option>
            <option value="ai_accounting">AI accounting</option>
            <option value="governance">Governance</option>
            <option value="intelligence">Intelligence</option>
          </select>
        </label>
      </div>
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
