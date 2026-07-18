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
];
$page_scripts = ['/assets/js/admin-agent.js?v=1.0.0'];
$adminActive = 'admin-agent';
$csrfToken = mg_csrf_token();
$displayName = mg_user_display_name();

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app mg-admin-agent-app" data-admin-agent data-csrf-token="<?= mg_e($csrfToken) ?>">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>

  <div class="mg-app-workspace mg-admin-workspace mg-admin-agent-workspace">
    <main class="mg-admin-agent-main">
      <section class="mg-admin-agent-topbar">
        <div>
          <span class="mg-admin-agent-eyebrow">Protected system observer</span>
          <h1>Main Admin Agent</h1>
          <p>Unified system monitoring, normalized events, durable findings, and review-gated recovery planning.</p>
        </div>
        <div class="mg-admin-agent-topbar-actions">
          <span class="mg-admin-agent-live"><i></i><strong data-admin-agent-live-label>Connecting</strong></span>
          <button class="mg-btn mg-btn-soft" type="button" data-admin-agent-scan>Run system scan</button>
          <button class="mg-btn mg-btn-ghost" type="button" data-admin-agent-controls aria-expanded="false">Agent controls</button>
        </div>
      </section>

      <section class="mg-admin-agent-schema" data-admin-agent-schema hidden></section>

      <section class="mg-admin-agent-overview" data-admin-agent-overview aria-label="Current platform status">
        <article class="mg-admin-agent-score is-loading">
          <span>System health</span><strong>—</strong><small>Loading monitor state</small>
        </article>
      </section>

      <section class="mg-personal-agent-view mg-personal-agent-chat-view mg-admin-agent-chat-view">
        <div class="mg-personal-agent-chat-stream mg-admin-agent-chat-stream">
          <article class="mg-personal-agent-message is-assistant is-intro mg-admin-agent-intro">
            <div class="mg-admin-agent-intro-head">
              <div>
                <span class="mg-personal-agent-message-label mg-admin-agent-kicker">Main Admin Agent</span>
                <h2 class="mg-personal-agent-intro-greeting">System control is ready, <?= mg_e($displayName) ?>.</h2>
              </div>
              <span class="mg-admin-agent-systematic">Database-first · No AI credits</span>
            </div>
            <p>Ask what changed, what is failing, which systems need attention, or request a focused security, operations, migration, notification, or AI-accounting report. All remediation remains review-gated.</p>
            <div class="mg-admin-agent-quick-prompts" aria-label="Main Admin Agent quick reports">
              <button type="button" data-admin-agent-prompt="Overview">Overview</button>
              <button type="button" data-admin-agent-prompt="What changed?">What changed?</button>
              <button type="button" data-admin-agent-prompt="Active findings">Active findings</button>
              <button type="button" data-admin-agent-prompt="Security report">Security</button>
              <button type="button" data-admin-agent-prompt="Operations report">Operations</button>
              <button type="button" data-admin-agent-prompt="AI credit accounting">AI accounting</button>
              <button type="button" data-admin-agent-prompt="Migration report">Migrations</button>
            </div>
          </article>

          <div class="mg-agent-chat-feed mg-admin-agent-feed" data-admin-agent-feed aria-live="polite">
            <div class="mg-agent-chat-empty">
              <div class="mg-agent-chat-empty-icon" aria-hidden="true">✦</div>
              <strong>Loading Main Admin Agent…</strong>
              <p>System reports, normalized events, findings, and review-ready recommendations will appear here.</p>
            </div>
          </div>
        </div>
      </section>

      <p class="mg-form-status mg-admin-agent-status" data-admin-agent-status role="status" aria-live="polite"></p>
    </main>

    <form class="mg-merchant-agent-composer mg-admin-agent-composer" data-admin-agent-form>
      <div class="mg-merchant-agent-composer-context">
        <span><kbd>A</kbd> Admin system mode · <span data-admin-agent-context>All systems · Live findings · Advisory only</span></span>
        <span>Financial and destructive actions always require review</span>
      </div>
      <div class="mg-merchant-agent-composer-row">
        <div class="mg-agent-chat-tool-wrap">
          <button class="mg-agent-chat-tool" type="button" aria-label="Add system report command" aria-expanded="false" data-admin-agent-context-toggle>+</button>
          <div class="mg-agent-context-menu" data-admin-agent-context-menu hidden>
            <button type="button" data-admin-agent-prompt="What changed since the last scan?">What changed</button>
            <button type="button" data-admin-agent-prompt="Show critical and high findings">Critical findings</button>
            <button type="button" data-admin-agent-prompt="Review security events">Security events</button>
            <button type="button" data-admin-agent-prompt="Review operations incidents and SLA risk">Operations and SLA</button>
            <button type="button" data-admin-agent-prompt="Review AI credit accounting incidents">AI accounting</button>
            <button type="button" data-admin-agent-prompt="Review database migrations">Migration readiness</button>
          </div>
        </div>
        <textarea data-admin-agent-textarea name="message" rows="1" maxlength="4000" placeholder="Ask the Main Admin Agent about system health, incidents, changes, or risks…" aria-label="Message the Main Admin Agent" required></textarea>
        <button class="mg-agent-chat-send" type="submit" data-admin-agent-send aria-label="Send message" disabled>↑</button>
      </div>
    </form>
  </div>

  <div class="mg-agent-chat-drawer-backdrop" data-admin-agent-drawer-close hidden></div>
  <aside class="mg-agent-chat-right mg-admin-agent-drawer" id="admin-agent-drawer" aria-label="Main Admin Agent controls" data-admin-agent-drawer aria-hidden="true">
    <div class="mg-agent-drawer-head">
      <div><strong>Main Admin Agent controls</strong><small>Live system observer</small></div>
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
            <option value="ai_accounting">AI accounting</option>
            <option value="governance">Governance</option>
          </select>
        </label>
      </div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>Monitor registry</strong><span data-admin-agent-monitor-count>0</span></header>
      <div data-admin-agent-monitors class="mg-admin-agent-rail-list"></div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>Active findings</strong><span data-admin-agent-finding-count>0</span></header>
      <div data-admin-agent-findings class="mg-admin-agent-rail-list"></div>
    </section>

    <section class="mg-admin-agent-rail-section">
      <header><strong>Action review queue</strong><span data-admin-agent-review-count>0</span></header>
      <div data-admin-agent-reviews class="mg-admin-agent-rail-list"></div>
    </section>
  </aside>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
