<?php
declare(strict_types=1);
?>
<section class="mg-agent-execution-page" data-merchant-agent-execution>
  <header class="mg-agent-execution-hero">
    <div>
      <span class="mg-eyebrow">Reviewed agent execution</span>
      <h1>Agent Execution Center</h1>
      <p>Run merchant-reviewed agent actions, draft customer messages, create follow-up tasks, skip duplicates, retry failures, and keep every result in the automation history.</p>
    </div>
    <div class="mg-agent-execution-actions">
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-analytics.php">Outcome Analytics</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-messages.php">Message Outbox</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-approvals.php">Review Queue</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-monitor.php">Agent Monitor</a>
      <button class="mg-btn mg-btn-primary" type="button" data-agent-execution-refresh>Refresh center</button>
    </div>
  </header>

  <section class="mg-agent-execution-kpis" aria-label="Agent execution summary">
    <article><strong data-execution-total>—</strong><span>Total items</span></article>
    <article><strong data-execution-approved>—</strong><span>Approved, not executed</span></article>
    <article><strong data-execution-completed>—</strong><span>Completed</span></article>
    <article><strong data-execution-failed>—</strong><span>Failed</span></article>
    <article><strong data-execution-skipped>—</strong><span>Skipped</span></article>
  </section>

  <section class="mg-agent-execution-grid">
    <article class="mg-app-panel mg-agent-execution-panel">
      <div class="mg-app-panel-head">
        <div>
          <h2>Execution Filters</h2>
          <p>Focus on approved work, completed results, failures, skipped items, or active execution.</p>
        </div>
        <a class="mg-btn mg-btn-secondary" href="/merchant-agent-messages.php">Review drafts</a>
      </div>
      <div class="mg-agent-execution-filters" data-agent-execution-filters>
        <button type="button" class="is-active" data-execution-filter="all">All</button>
        <button type="button" data-execution-filter="approved_not_executed">Approved</button>
        <button type="button" data-execution-filter="executing">Executing</button>
        <button type="button" data-execution-filter="completed">Completed</button>
        <button type="button" data-execution-filter="failed">Failed</button>
        <button type="button" data-execution-filter="skipped">Skipped</button>
      </div>
    </article>

    <article class="mg-app-panel mg-agent-execution-panel">
      <div class="mg-app-panel-head">
        <div>
          <h2>Safe Execution Rules</h2>
          <p>Execution starts only after merchant review and records every started, completed, failed, skipped, and message draft event.</p>
        </div>
      </div>
      <div class="mg-agent-execution-model">
        <span>Reviewed first</span>
        <span>Execute once</span>
        <span>Log result</span>
        <span>Retry failures</span>
      </div>
    </article>
  </section>

  <article class="mg-app-panel mg-agent-execution-panel">
    <div class="mg-app-panel-head">
      <div>
        <h2>Execution Items</h2>
        <p>Each item includes the reviewed action, guardrail applied, current state, latest result, and available safe next actions.</p>
      </div>
      <div class="mg-crm-tab-actions">
        <a class="mg-btn mg-btn-secondary" href="/merchant-agent-analytics.php">Open outcome analytics</a>
        <a class="mg-btn mg-btn-secondary" href="/merchant-agent-messages.php">Open message outbox</a>
        <p class="mg-form-status" data-agent-execution-status></p>
      </div>
    </div>
    <div class="mg-agent-execution-list" data-agent-execution-list>
      <div class="mg-empty-state"><strong>Loading execution center…</strong></div>
    </div>
  </article>
</section>
