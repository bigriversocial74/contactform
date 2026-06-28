<?php
declare(strict_types=1);
?>
<section class="mg-agent-qa-page" data-merchant-agent-qa>
  <header class="mg-agent-qa-hero">
    <div>
      <span class="mg-eyebrow">Merchant agent QA console</span>
      <h1>Agent System Health Check</h1>
      <p>Verify Claude, Stage 19C tables, policy, memory, review queue, execution layer, notification digest, demo permissions, and latest agent errors before production testing.</p>
    </div>
    <div class="mg-agent-qa-actions">
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-chat.php">Agent Chat</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-approvals.php">Review Queue</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-execution.php">Execution Center</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-automation.php">Controls</a>
      <button class="mg-btn mg-btn-primary" type="button" data-agent-qa-refresh>Run health check</button>
    </div>
  </header>

  <section class="mg-agent-qa-score" data-agent-qa-score>
    <article><strong>—</strong><span>Health score</span></article>
    <article><strong>—</strong><span>Passed</span></article>
    <article><strong>—</strong><span>Warnings</span></article>
    <article><strong>—</strong><span>Failures</span></article>
  </section>

  <section class="mg-agent-qa-grid">
    <article class="mg-app-panel mg-agent-qa-panel">
      <div class="mg-app-panel-head">
        <div><h2>Health checks</h2><p>Core dependencies needed for the merchant agent workflow.</p></div>
        <p class="mg-form-status" data-agent-qa-status></p>
      </div>
      <div class="mg-agent-qa-checks" data-agent-qa-checks>
        <div class="mg-empty-state"><strong>Loading health checks…</strong></div>
      </div>
    </article>

    <article class="mg-app-panel mg-agent-qa-panel">
      <div class="mg-app-panel-head">
        <div><h2>Workflow counts</h2><p>Live counters from chat, review, execution, and digest events.</p></div>
      </div>
      <div class="mg-agent-qa-counts" data-agent-qa-counts>
        <div class="mg-empty-state"><strong>Loading counters…</strong></div>
      </div>
      <div class="mg-agent-qa-links" data-agent-qa-links></div>
    </article>
  </section>

  <article class="mg-app-panel mg-agent-qa-panel">
    <div class="mg-app-panel-head">
      <div><h2>Latest agent error</h2><p>Most recent failed/error event for this merchant workspace.</p></div>
    </div>
    <div class="mg-agent-qa-error" data-agent-qa-error>
      <div class="mg-empty-state"><strong>No error loaded yet.</strong></div>
    </div>
  </article>

  <article class="mg-app-panel mg-agent-qa-panel">
    <div class="mg-app-panel-head">
      <div><h2>Manual QA script</h2><p>Run this sequence after every merge before calling the agent workflow production-ready.</p></div>
    </div>
    <ol class="mg-agent-qa-script">
      <li>Open <strong>Automation Controls</strong>, save policy, and confirm audit events update.</li>
      <li>Open <strong>Agent Chat</strong>, generate a recommendation, and save one preference.</li>
      <li>Send a card to the <strong>Review Queue</strong>, approve it, and confirm adapter execution.</li>
      <li>Open <strong>Execution Center</strong>, verify result card and open-result link.</li>
      <li>Open <strong>Notifications</strong>, confirm digest item, mark read, and archive.</li>
      <li>Return here and confirm the health score remains clean.</li>
    </ol>
  </article>
</section>
