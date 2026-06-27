<?php
declare(strict_types=1);
?>
<section class="mg-automation-page" data-merchant-automation-page>
  <header class="mg-automation-hero">
    <div>
      <span class="mg-eyebrow">Agentic management guardrails</span>
      <h1>Automation Control Center</h1>
      <p>Control which retention playbooks can monitor, recommend, create tasks, draft messages, or execute only after merchant approval.</p>
    </div>
    <div class="mg-automation-actions">
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-monitor.php" data-automation-open-agent-monitor>Agent Monitor</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-crm.php?tab=retention" data-automation-open-retention>Retention Playbooks</a>
      <button class="mg-btn mg-btn-secondary" type="button" data-automation-refresh>Refresh</button>
      <button class="mg-btn mg-btn-primary" type="button" data-automation-save>Save guardrails</button>
    </div>
  </header>

  <section class="mg-automation-kpis" aria-label="Automation summary">
    <article><strong data-auto-total>—</strong><span>Total playbooks</span></article>
    <article><strong data-auto-enabled>—</strong><span>Enabled</span></article>
    <article><strong data-auto-approval>—</strong><span>Require approval</span></article>
    <article><strong data-auto-task>—</strong><span>Task creation</span></article>
    <article><strong data-auto-drafts>—</strong><span>Message drafts</span></article>
  </section>

  <section class="mg-automation-grid">
    <article class="mg-app-panel mg-automation-panel">
      <div class="mg-app-panel-head">
        <div>
          <h2>Automation Levels</h2>
          <p>These levels make the future agent permission model explicit before agents are allowed to manage work.</p>
        </div>
      </div>
      <div class="mg-automation-levels" data-automation-levels>
        <div class="mg-empty-state"><strong>Loading automation levels…</strong></div>
      </div>
    </article>

    <article class="mg-app-panel mg-automation-panel">
      <div class="mg-app-panel-head">
        <div>
          <h2>Agent Guardrails</h2>
          <p>Every playbook can be controlled with merchant approval, task limits, and agent-safe execution boundaries.</p>
        </div>
        <a class="mg-btn mg-btn-secondary" href="/merchant-agent-monitor.php">View monitor</a>
      </div>
      <div class="mg-automation-guardrail-map">
        <span>agent_can_monitor</span>
        <span>agent_can_recommend</span>
        <span>agent_can_create_task</span>
        <span>agent_requires_approval</span>
      </div>
    </article>
  </section>

  <article class="mg-app-panel mg-automation-panel mg-automation-rules-panel">
    <div class="mg-app-panel-head">
      <div>
        <h2>Playbook Controls</h2>
        <p>Enable, limit, and approval-gate each deterministic automation before agents are allowed to monitor or manage it.</p>
      </div>
      <p class="mg-form-status" data-automation-status></p>
    </div>
    <div class="mg-automation-table-wrap" data-automation-settings>
      <div class="mg-empty-state"><strong>Loading automation controls…</strong></div>
    </div>
  </article>

  <article class="mg-app-panel mg-automation-panel">
    <div class="mg-app-panel-head">
      <div>
        <h2>Execution History</h2>
        <p>Playbook triggers, task creation, message drafts, approvals, rejections, and settings changes.</p>
      </div>
      <div class="mg-crm-tab-actions">
        <a class="mg-btn mg-btn-secondary" href="/merchant-agent-monitor.php">Explain activity</a>
        <button class="mg-btn mg-btn-secondary" type="button" data-automation-log-refresh>Refresh log</button>
      </div>
    </div>
    <div class="mg-automation-log" data-automation-log>
      <div class="mg-empty-state"><strong>Loading automation history…</strong></div>
    </div>
  </article>
</section>
