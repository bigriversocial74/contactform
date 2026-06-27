<?php
declare(strict_types=1);
?>
<section class="mg-agent-approvals-page" data-merchant-agent-approvals>
  <header class="mg-agent-approvals-hero">
    <div>
      <span class="mg-eyebrow">Controlled agent-assisted execution</span>
      <h1>Agent Approval Queue</h1>
      <p>Review agent-recommended actions before execution. Approve, reject, defer, or convert recommendations into follow-up tasks.</p>
    </div>
    <div class="mg-agent-approvals-actions">
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-execution.php">Execution Center</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-monitor.php">Agent Monitor</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-automation.php">Automation Controls</a>
      <button class="mg-btn mg-btn-primary" type="button" data-agent-approval-refresh>Refresh queue</button>
    </div>
  </header>

  <section class="mg-agent-approvals-kpis" aria-label="Agent approval summary">
    <article><strong data-approval-total>—</strong><span>Total items</span></article>
    <article><strong data-approval-pending>—</strong><span>Pending</span></article>
    <article><strong data-approval-approved>—</strong><span>Approved</span></article>
    <article><strong data-approval-deferred>—</strong><span>Deferred</span></article>
    <article><strong data-approval-tasks>—</strong><span>Tasks created</span></article>
  </section>

  <section class="mg-agent-approvals-grid">
    <article class="mg-app-panel mg-agent-approvals-panel">
      <div class="mg-app-panel-head">
        <div>
          <h2>Review Filters</h2>
          <p>Focus on pending decisions, completed decisions, or risk level.</p>
        </div>
        <a class="mg-btn mg-btn-secondary" href="/merchant-agent-execution.php">Run approved</a>
      </div>
      <div class="mg-agent-approval-filters" data-agent-approval-filters>
        <button type="button" class="is-active" data-approval-filter="all">All</button>
        <button type="button" data-approval-filter="pending">Pending</button>
        <button type="button" data-approval-filter="approved">Approved</button>
        <button type="button" data-approval-filter="rejected">Rejected</button>
        <button type="button" data-approval-filter="deferred">Deferred</button>
        <button type="button" data-approval-filter="task_created">Task created</button>
        <button type="button" data-approval-filter="high">High risk</button>
        <button type="button" data-approval-filter="medium">Medium risk</button>
        <button type="button" data-approval-filter="low">Low risk</button>
      </div>
    </article>

    <article class="mg-app-panel mg-agent-approvals-panel">
      <div class="mg-app-panel-head">
        <div>
          <h2>Decision Model</h2>
          <p>Every item is merchant-scoped, guardrail-aware, and stored in automation history.</p>
        </div>
      </div>
      <div class="mg-agent-approval-model">
        <span>Approve</span>
        <span>Reject</span>
        <span>Defer / snooze</span>
        <span>Convert to follow-up task</span>
      </div>
    </article>
  </section>

  <article class="mg-app-panel mg-agent-approvals-panel">
    <div class="mg-app-panel-head">
      <div>
        <h2>Approval Items</h2>
        <p>Each item shows why the agent recommends it, customer/campaign source, guardrail applied, expected action, risk level, and approval requirement.</p>
      </div>
      <div class="mg-crm-tab-actions">
        <a class="mg-btn mg-btn-secondary" href="/merchant-agent-execution.php">Open execution center</a>
        <p class="mg-form-status" data-agent-approval-status></p>
      </div>
    </div>
    <div class="mg-agent-approval-list" data-agent-approval-list>
      <div class="mg-empty-state"><strong>Loading approval queue…</strong></div>
    </div>
  </article>
</section>
