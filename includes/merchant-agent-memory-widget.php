<?php
declare(strict_types=1);
?>
<section class="mg-app-panel mg-agent-memory-panel" data-agent-memory-widget>
  <div class="mg-app-panel-head is-compact">
    <div>
      <h2>Agent Memory</h2>
      <p>Goals, preferences, feedback, and actions the agent should avoid.</p>
    </div>
    <button class="mg-btn mg-btn-soft" type="button" data-agent-memory-refresh>Refresh</button>
  </div>
  <div class="mg-app-panel-body">
    <div class="mg-agent-memory-kpis" data-agent-memory-kpis>
      <article><strong>—</strong><span>Useful</span></article>
      <article><strong>—</strong><span>Preferences</span></article>
      <article><strong>—</strong><span>Avoid</span></article>
    </div>
    <div class="mg-agent-memory-list" data-agent-memory-list>
      <div class="mg-empty-state"><strong>Loading memory…</strong></div>
    </div>
    <p class="mg-form-status" data-agent-memory-status></p>
  </div>
</section>
