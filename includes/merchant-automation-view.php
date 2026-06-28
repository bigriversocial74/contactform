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
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-messages.php" data-automation-open-agent-messages>Message Outbox</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-execution.php" data-automation-open-agent-execution>Execution Center</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-approvals.php" data-automation-open-agent-review>Review Queue</a>
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

  <article class="mg-app-panel mg-automation-panel mg-agent-autonomy-panel" data-agent-autonomy-panel>
    <div class="mg-app-panel-head">
      <div>
        <span class="mg-eyebrow">Agent autonomy</span>
        <h2>How much control can the agent have?</h2>
        <p>Main admin defines the platform ceiling. Each merchant can choose a level up to that ceiling. Execution without approval is still locked off until the ceiling explicitly allows it.</p>
      </div>
      <div class="mg-agent-autonomy-status">
        <span>Effective level</span>
        <strong data-agent-autonomy-effective>—</strong>
      </div>
    </div>
    <div class="mg-agent-autonomy-grid">
      <label>
        <span>Merchant autonomy level</span>
        <select data-agent-autonomy-level></select>
      </label>
      <label>
        <span>Platform ceiling</span>
        <input type="text" data-agent-autonomy-ceiling readonly value="Loading…">
      </label>
      <label>
        <span>Daily action budget</span>
        <input type="number" min="0" max="100" data-agent-autonomy-budget value="10">
      </label>
    </div>
    <div class="mg-agent-autonomy-switches">
      <label><input type="checkbox" data-agent-autonomy-field="allow_review_queue" checked> Allow review queue cards</label>
      <label><input type="checkbox" data-agent-autonomy-field="allow_task_creation" checked> Allow task creation</label>
      <label><input type="checkbox" data-agent-autonomy-field="allow_message_drafts" checked> Allow message drafts</label>
      <label><input type="checkbox" data-agent-autonomy-field="high_risk_requires_approval" checked disabled> High-risk actions always require approval</label>
      <label><input type="checkbox" data-agent-autonomy-field="allow_execution_without_approval" disabled> Execution without approval locked by platform ceiling</label>
    </div>
    <div class="mg-agent-autonomy-copy" data-agent-autonomy-copy>Loading autonomy guardrails…</div>
  </article>

  <?php require __DIR__ . '/merchant-agent-control-center-widget.php'; ?>

  <article class="mg-app-panel mg-automation-panel mg-automation-ai-panel">
    <div class="mg-app-panel-head">
      <div>
        <span class="mg-eyebrow">Claude Sonnet merchant agent</span>
        <h2>Merchant Account Review</h2>
        <p>Ask Claude to review rewards, campaigns, CRM, claims, reports, analytics, locations, and API opportunities. It creates reviewable recommendations only; execution still requires merchant approval.</p>
      </div>
      <div class="mg-crm-tab-actions">
        <select data-ai-plan-scope aria-label="AI planning scope">
          <option value="all">Full merchant review</option>
          <option value="campaigns">Campaigns</option>
          <option value="rewards">Rewards</option>
          <option value="crm">CRM follow-ups</option>
          <option value="claims">Claims</option>
          <option value="analytics">Analytics / reports</option>
          <option value="developer_api">Developer API</option>
        </select>
        <button class="mg-btn mg-btn-primary" type="button" data-ai-plan-run>Run Claude review</button>
      </div>
    </div>
    <div class="mg-form-grid">
      <label>
        <span>Merchant goal</span>
        <input type="text" data-ai-plan-goal placeholder="Example: increase weekday lunch redemptions without issuing value automatically">
      </label>
      <label>
        <span>Review window</span>
        <select data-ai-plan-days>
          <option value="30">Last 30 days</option>
          <option value="90" selected>Last 90 days</option>
          <option value="180">Last 180 days</option>
          <option value="365">Last 365 days</option>
        </select>
      </label>
    </div>
    <div class="mg-automation-log" data-ai-plan-output>
      <div class="mg-empty-state"><strong>No Claude review yet</strong><p>Run a review to create supervised merchant recommendations.</p></div>
    </div>
  </article>

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
        <a class="mg-btn mg-btn-secondary" href="/merchant-agent-messages.php">Message outbox</a>
        <a class="mg-btn mg-btn-secondary" href="/merchant-agent-execution.php">Execution center</a>
        <a class="mg-btn mg-btn-secondary" href="/merchant-agent-approvals.php">Review queue</a>
        <a class="mg-btn mg-btn-secondary" href="/merchant-agent-monitor.php">Explain activity</a>
        <button class="mg-btn mg-btn-secondary" type="button" data-automation-log-refresh>Refresh log</button>
      </div>
    </div>
    <div class="mg-automation-log" data-automation-log>
      <div class="mg-empty-state"><strong>Loading automation history…</strong></div>
    </div>
  </article>
</section>