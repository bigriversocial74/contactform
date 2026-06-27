<?php
declare(strict_types=1);
?>
<section class="mg-agent-growth-page" data-merchant-agent-growth-plan>
  <header class="mg-agent-growth-hero">
    <div>
      <span class="mg-eyebrow">Forecast-to-action planning</span>
      <h1>Agent Growth Planner</h1>
      <p>Turn ROI forecasts into an actionable merchant plan with recommended playbooks, customer follow-ups, campaign repeats, message opportunities, and PSR targets.</p>
    </div>
    <div class="mg-agent-growth-actions">
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-forecast.php">ROI Forecasting</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-roi.php">ROI Attribution</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-approvals.php">Review Queue</a>
      <button class="mg-btn mg-btn-primary" type="button" data-growth-refresh>Refresh plan</button>
    </div>
  </header>

  <section class="mg-agent-growth-controls" aria-label="Growth planning controls">
    <div><label>Goal</label><select data-growth-goal><option value="claims">Claims</option><option value="revenue" selected>Revenue</option><option value="psr">PSR impact</option><option value="reactivation">Customer reactivation</option></select></div>
    <div><label>Timeframe</label><select data-growth-timeframe><option value="30">30 days</option><option value="60">60 days</option><option value="90" selected>90 days</option></select></div>
    <div><label>Risk mode</label><select data-growth-risk><option value="conservative">Conservative</option><option value="balanced" selected>Balanced</option><option value="aggressive">Aggressive</option></select></div>
    <div><label>Merchant effort</label><select data-growth-effort><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
  </section>

  <section class="mg-agent-growth-kpis" aria-label="Growth plan targets">
    <article><strong data-growth-claims>—</strong><span>Target claims</span></article>
    <article><strong data-growth-revenue>—</strong><span>Target revenue</span></article>
    <article><strong data-growth-psr>—</strong><span>Target PSR impact</span></article>
    <article><strong data-growth-messages>—</strong><span>Required messages</span></article>
    <article><strong data-growth-followups>—</strong><span>Required follow-ups</span></article>
    <article><strong data-growth-avg>—</strong><span>Avg redemption value</span></article>
  </section>

  <section class="mg-agent-growth-grid">
    <article class="mg-app-panel mg-agent-growth-panel">
      <div class="mg-app-panel-head"><div><h2>Recommended Agent Actions</h2><p>Prioritized actions to route through merchant review, message drafts, and follow-up tasks.</p></div></div>
      <div class="mg-agent-growth-actions-list" data-growth-actions-list><div class="mg-empty-state"><strong>Loading growth actions…</strong></div></div>
    </article>
    <article class="mg-app-panel mg-agent-growth-panel">
      <div class="mg-app-panel-head"><div><h2>Plan Summary</h2><p>Selected controls and forecast-to-action assumptions.</p></div></div>
      <div class="mg-agent-growth-summary" data-growth-summary></div>
      <div class="mg-agent-growth-links"><a href="/merchant-agent-forecast.php">Forecast</a><a href="/merchant-agent-roi.php">ROI</a><a href="/merchant-agent-analytics.php">Analytics</a><a href="/merchant-agent-approvals.php">Review</a><a href="/merchant-agent-messages.php">Messages</a><a href="/merchant-followups.php">Follow-ups</a></div>
    </article>
  </section>

  <section class="mg-agent-growth-grid is-wide">
    <article class="mg-app-panel mg-agent-growth-panel"><div class="mg-app-panel-head"><div><h2>Best Next Playbooks</h2><p>Playbooks to repeat or expand based on the forecast.</p></div></div><div class="mg-agent-growth-table" data-growth-playbooks></div></article>
    <article class="mg-app-panel mg-agent-growth-panel"><div class="mg-app-panel-head"><div><h2>Campaigns Worth Repeating</h2><p>Campaigns with forecasted claim, revenue, and PSR value.</p></div></div><div class="mg-agent-growth-table" data-growth-campaigns></div></article>
  </section>

  <section class="mg-agent-growth-grid is-wide">
    <article class="mg-app-panel mg-agent-growth-panel"><div class="mg-app-panel-head"><div><h2>Best Customers to Follow Up</h2><p>Agent-touched customers to route into follow-up or message draft workflows.</p></div></div><div class="mg-agent-growth-table" data-growth-customers></div></article>
    <article class="mg-app-panel mg-agent-growth-panel"><div class="mg-app-panel-head"><div><h2>Message & Follow-up Opportunities</h2><p>Estimated work required to hit the selected goal.</p></div></div><div class="mg-agent-growth-table" data-growth-opportunities></div></article>
  </section>

  <article class="mg-app-panel mg-agent-growth-panel">
    <div class="mg-app-panel-head"><div><h2>Planner Status</h2><p>Growth plans are recommendations. Merchants still review, approve, execute, and send final actions.</p></div><p class="mg-form-status" data-growth-status></p></div>
  </article>
</section>
