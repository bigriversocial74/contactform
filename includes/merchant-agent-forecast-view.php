<?php
declare(strict_types=1);
?>
<section class="mg-agent-forecast-page" data-merchant-agent-forecast>
  <header class="mg-agent-forecast-hero">
    <div>
      <span class="mg-eyebrow">Forward-looking agent commerce</span>
      <h1>Agent ROI Forecasting</h1>
      <p>Forecast expected agent-influenced claims, redemption value, message-to-claim projection, follow-up conversion, playbook ROI, campaign ROI, and 30/60/90 day PSR impact.</p>
    </div>
    <div class="mg-agent-forecast-actions">
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-growth-plan.php">Growth Planner</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-roi.php">ROI Attribution</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-analytics.php">Outcome Analytics</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-claims.php">Claims</a>
      <button class="mg-btn mg-btn-primary" type="button" data-agent-forecast-refresh>Refresh forecast</button>
    </div>
  </header>

  <section class="mg-agent-forecast-controls" aria-label="Forecast controls">
    <div><label>Scenario</label><select data-forecast-scenario><option value="conservative">Conservative</option><option value="base" selected>Base</option><option value="aggressive">Aggressive</option></select></div>
    <div><label>Historical window</label><select data-forecast-days><option value="30">30 days</option><option value="90" selected>90 days</option><option value="180">180 days</option><option value="365">365 days</option></select></div>
    <div><label>Claim lift multiplier</label><input type="number" min="0.1" max="5" step="0.1" value="1" data-forecast-claim-lift></div>
    <div><label>Avg. redemption override</label><input type="number" min="0" step="1" placeholder="Use actual avg" data-forecast-avg-value></div>
  </section>

  <section class="mg-agent-forecast-kpis" aria-label="Agent forecast summary">
    <article><strong data-fc-claims>—</strong><span>90d claims forecast</span></article>
    <article><strong data-fc-revenue>—</strong><span>90d redemption value</span></article>
    <article><strong data-fc-psr>—</strong><span>90d PSR impact</span></article>
    <article><strong data-fc-msg-rate>—</strong><span>Message to claim</span></article>
    <article><strong data-fc-follow-rate>—</strong><span>Follow-up to claim</span></article>
    <article><strong data-fc-avg>—</strong><span>Avg redemption value</span></article>
  </section>

  <section class="mg-agent-forecast-grid">
    <article class="mg-app-panel mg-agent-forecast-panel">
      <div class="mg-app-panel-head"><div><h2>30 / 60 / 90 Day Forecast</h2><p>Expected agent-influenced claims, redemption value, and PSR impact by projection window.</p></div><a class="mg-btn mg-btn-secondary" href="/merchant-agent-growth-plan.php">Plan actions</a></div>
      <div class="mg-agent-forecast-periods" data-forecast-periods><div class="mg-empty-state"><strong>Loading forecast windows…</strong></div></div>
    </article>
    <article class="mg-app-panel mg-agent-forecast-panel">
      <div class="mg-app-panel-head"><div><h2>Forecast Inputs</h2><p>Scenario model and actual attribution source state used to generate the forecast.</p></div></div>
      <div class="mg-agent-forecast-inputs" data-forecast-inputs></div>
      <div class="mg-agent-forecast-links"><a href="/merchant-agent-growth-plan.php">Growth Planner</a><a href="/merchant-agent-roi.php">ROI Attribution</a><a href="/merchant-agent-analytics.php">Analytics</a><a href="/merchant-customer.php?tab=timeline">Customer timeline</a><a href="/merchant-agent-messages.php">Messages</a><a href="/merchant-claims.php">Claims</a></div>
    </article>
  </section>

  <section class="mg-agent-forecast-grid is-wide">
    <article class="mg-app-panel mg-agent-forecast-panel"><div class="mg-app-panel-head"><div><h2>Playbook ROI Forecast</h2><p>Forecasted 90-day value by agent playbook.</p></div></div><div class="mg-agent-forecast-table" data-forecast-playbooks></div></article>
    <article class="mg-app-panel mg-agent-forecast-panel"><div class="mg-app-panel-head"><div><h2>Campaign ROI Forecast</h2><p>Forecasted 90-day value by campaign.</p></div></div><div class="mg-agent-forecast-table" data-forecast-campaigns></div></article>
  </section>

  <section class="mg-agent-forecast-grid is-wide">
    <article class="mg-app-panel mg-agent-forecast-panel"><div class="mg-app-panel-head"><div><h2>Customer Forecast</h2><p>Projected value for customers already touched by agent workflow.</p></div></div><div class="mg-agent-forecast-table" data-forecast-customers></div></article>
    <article class="mg-app-panel mg-agent-forecast-panel"><div class="mg-app-panel-head"><div><h2>Historical Daily Attribution</h2><p>Actual attributed daily redemption value feeding the forecast.</p></div></div><div class="mg-agent-forecast-trend" data-forecast-daily></div></article>
  </section>

  <article class="mg-app-panel mg-agent-forecast-panel">
    <div class="mg-app-panel-head"><div><h2>Forecast Status</h2><p>Forecasts are model estimates based on existing ROI attribution and merchant-controlled agent events.</p></div><div class="mg-crm-tab-actions"><a class="mg-btn mg-btn-secondary" href="/merchant-agent-growth-plan.php">Open growth planner</a><p class="mg-form-status" data-agent-forecast-status></p></div></div>
  </article>
</section>
