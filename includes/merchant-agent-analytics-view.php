<?php
declare(strict_types=1);
?>
<section class="mg-agent-analytics-page" data-merchant-agent-analytics>
  <header class="mg-agent-analytics-hero">
    <div>
      <span class="mg-eyebrow">Agent outcome intelligence</span>
      <h1>Agent Outcome Analytics</h1>
      <p>Measure recommendations, merchant decisions, execution results, message conversion, follow-up conversion, and customer reach across the controlled agent workflow.</p>
    </div>
    <div class="mg-agent-analytics-actions">
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-monitor.php">Agent Monitor</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-approvals.php">Review Queue</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-execution.php">Execution Center</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-messages.php">Message Outbox</a>
      <button class="mg-btn mg-btn-primary" type="button" data-agent-analytics-refresh>Refresh analytics</button>
    </div>
  </header>

  <section class="mg-agent-analytics-kpis" aria-label="Agent analytics summary">
    <article><strong data-aa-recommendations>—</strong><span>Recommendations</span></article>
    <article><strong data-aa-approval-rate>—</strong><span>Approval rate</span></article>
    <article><strong data-aa-execution-rate>—</strong><span>Execution completion</span></article>
    <article><strong data-aa-draft-rate>—</strong><span>Draft to send</span></article>
    <article><strong data-aa-customers>—</strong><span>Customers touched</span></article>
    <article><strong data-aa-risk-rate>—</strong><span>Failed / skipped</span></article>
  </section>

  <section class="mg-agent-analytics-grid">
    <article class="mg-app-panel mg-agent-analytics-panel">
      <div class="mg-app-panel-head">
        <div><h2>Outcome Funnel</h2><p>Shows the path from recommendation to merchant approval, execution, customer message, and follow-up task.</p></div>
      </div>
      <div class="mg-agent-analytics-funnel" data-agent-analytics-funnel>
        <div class="mg-empty-state"><strong>Loading outcome funnel…</strong></div>
      </div>
    </article>

    <article class="mg-app-panel mg-agent-analytics-panel">
      <div class="mg-app-panel-head">
        <div><h2>Time Window</h2><p>Adjust the analytics window without changing the underlying event log.</p></div>
      </div>
      <div class="mg-agent-analytics-window" data-agent-analytics-window>
        <button type="button" data-aa-days="30">30 days</button>
        <button type="button" class="is-active" data-aa-days="90">90 days</button>
        <button type="button" data-aa-days="180">180 days</button>
        <button type="button" data-aa-days="365">365 days</button>
      </div>
      <div class="mg-agent-analytics-links">
        <a href="/merchant-agent-monitor.php">Monitor</a>
        <a href="/merchant-agent-approvals.php">Review</a>
        <a href="/merchant-agent-execution.php">Execute</a>
        <a href="/merchant-agent-messages.php">Messages</a>
        <a href="/merchant-customer.php?tab=timeline">Customer timeline</a>
      </div>
    </article>
  </section>

  <section class="mg-agent-analytics-grid is-wide">
    <article class="mg-app-panel mg-agent-analytics-panel">
      <div class="mg-app-panel-head"><div><h2>By Playbook</h2><p>Which agent playbooks generate decisions and completed outcomes.</p></div></div>
      <div class="mg-agent-analytics-table" data-aa-playbooks></div>
    </article>
    <article class="mg-app-panel mg-agent-analytics-panel">
      <div class="mg-app-panel-head"><div><h2>By Customer</h2><p>Customers touched by recommendations, approvals, executions, messages, and follow-ups.</p></div></div>
      <div class="mg-agent-analytics-table" data-aa-customers></div>
    </article>
  </section>

  <section class="mg-agent-analytics-grid is-wide">
    <article class="mg-app-panel mg-agent-analytics-panel">
      <div class="mg-app-panel-head"><div><h2>By Event Type</h2><p>Raw workflow distribution across the agent event stream.</p></div></div>
      <div class="mg-agent-analytics-table" data-aa-events></div>
    </article>
    <article class="mg-app-panel mg-agent-analytics-panel">
      <div class="mg-app-panel-head"><div><h2>Daily Trend</h2><p>Recent agent activity by day across recommendations, approvals, executions, messages, and follow-ups.</p></div></div>
      <div class="mg-agent-analytics-trend" data-aa-daily></div>
    </article>
  </section>

  <article class="mg-app-panel mg-agent-analytics-panel">
    <div class="mg-app-panel-head">
      <div><h2>Recent Outcome Events</h2><p>Latest agent workflow events with links back to the right controlled workflow surface.</p></div>
      <p class="mg-form-status" data-agent-analytics-status></p>
    </div>
    <div class="mg-agent-analytics-events" data-aa-recent>
      <div class="mg-empty-state"><strong>Loading recent agent outcome events…</strong></div>
    </div>
  </article>
</section>
