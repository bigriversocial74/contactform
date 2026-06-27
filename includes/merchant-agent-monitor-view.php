<?php
declare(strict_types=1);
?>
<section class="mg-agent-monitor-page" data-merchant-agent-monitor>
  <header class="mg-agent-monitor-hero">
    <div>
      <span class="mg-eyebrow">Agentic management monitor</span>
      <h1>Agent Activity Monitor</h1>
      <p>Watch playbook triggers, recommended actions, guardrails, approval gates, and daily action usage before agents are allowed to act.</p>
    </div>
    <div class="mg-agent-monitor-actions">
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-approvals.php">Approval Queue</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-automation.php">Automation Controls</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-crm.php?tab=retention">Retention Playbooks</a>
      <button class="mg-btn mg-btn-primary" type="button" data-agent-monitor-refresh>Refresh monitor</button>
    </div>
  </header>

  <section class="mg-agent-monitor-kpis" aria-label="Agent monitor summary">
    <article><strong data-agent-total>—</strong><span>Total activity</span></article>
    <article><strong data-agent-approval>—</strong><span>Needs approval</span></article>
    <article><strong data-agent-ready>—</strong><span>Ready to run</span></article>
    <article><strong data-agent-blocked>—</strong><span>Blocked</span></article>
    <article><strong data-agent-tasks>—</strong><span>Created tasks</span></article>
  </section>

  <section class="mg-agent-monitor-grid">
    <article class="mg-app-panel mg-agent-monitor-panel">
      <div class="mg-app-panel-head">
        <div>
          <h2>Agent Status Panels</h2>
          <p>Each playbook shows what the agent can monitor, recommend, create, or why it is blocked.</p>
        </div>
        <a class="mg-btn mg-btn-secondary" href="/merchant-agent-approvals.php">Review approvals</a>
      </div>
      <div class="mg-agent-status-grid" data-agent-statuses>
        <div class="mg-empty-state"><strong>Loading agent statuses…</strong></div>
      </div>
    </article>

    <article class="mg-app-panel mg-agent-monitor-panel">
      <div class="mg-app-panel-head">
        <div>
          <h2>Monitor Filters</h2>
          <p>Focus the activity stream by approval state, execution readiness, blocks, or completed task creation.</p>
        </div>
      </div>
      <div class="mg-agent-filter-list" data-agent-filters>
        <button type="button" data-agent-filter="all" class="is-active">All</button>
        <button type="button" data-agent-filter="needs_approval">Needs approval</button>
        <button type="button" data-agent-filter="ready_to_run">Ready to run</button>
        <button type="button" data-agent-filter="blocked">Blocked</button>
        <button type="button" data-agent-filter="created_task">Created task</button>
        <button type="button" data-agent-filter="recommendation_only">Recommendation only</button>
      </div>
    </article>
  </section>

  <article class="mg-app-panel mg-agent-monitor-panel">
    <div class="mg-app-panel-head">
      <div>
        <h2>Agent-readable Activity</h2>
        <p>Every item includes why the action exists, customer/campaign source, guardrail applied, and whether merchant approval is required.</p>
      </div>
      <div class="mg-crm-tab-actions">
        <a class="mg-btn mg-btn-secondary" href="/merchant-agent-approvals.php">Open approval queue</a>
        <p class="mg-form-status" data-agent-monitor-status></p>
      </div>
    </div>
    <div class="mg-agent-activity-list" data-agent-activity>
      <div class="mg-empty-state"><strong>Loading agent activity…</strong></div>
    </div>
  </article>
</section>
