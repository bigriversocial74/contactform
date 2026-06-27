<?php
declare(strict_types=1);
?>
<section class="mg-agent-roi-page" data-merchant-agent-roi>
  <header class="mg-agent-roi-hero">
    <div>
      <span class="mg-eyebrow">Agent commerce attribution</span>
      <h1>Agent ROI Attribution</h1>
      <p>Connect agent workflow activity to claims, redemptions, influenced revenue, message-to-claim performance, follow-up conversion, and PSR impact estimates.</p>
    </div>
    <div class="mg-agent-roi-actions">
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-analytics.php">Outcome Analytics</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-customer.php?tab=timeline">Customer Timeline</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-claims.php">Claims</a>
      <button class="mg-btn mg-btn-primary" type="button" data-agent-roi-refresh>Refresh ROI</button>
    </div>
  </header>

  <section class="mg-agent-roi-kpis" aria-label="Agent ROI summary">
    <article><strong data-roi-customers>—</strong><span>Agent-touched customers</span></article>
    <article><strong data-roi-claims>—</strong><span>Influenced claims</span></article>
    <article><strong data-roi-revenue>—</strong><span>Revenue influenced</span></article>
    <article><strong data-roi-message-rate>—</strong><span>Message to claim</span></article>
    <article><strong data-roi-followup-rate>—</strong><span>Follow-up to claim</span></article>
    <article><strong data-roi-psr>—</strong><span>PSR impact estimate</span></article>
  </section>

  <section class="mg-agent-roi-grid">
    <article class="mg-app-panel mg-agent-roi-panel">
      <div class="mg-app-panel-head"><div><h2>Attribution Funnel</h2><p>Agent touchpoints matched to redemption value for the selected time window.</p></div></div>
      <div class="mg-agent-roi-funnel" data-agent-roi-funnel><div class="mg-empty-state"><strong>Loading ROI funnel…</strong></div></div>
    </article>
    <article class="mg-app-panel mg-agent-roi-panel">
      <div class="mg-app-panel-head"><div><h2>Time Window</h2><p>Adjust ROI attribution without changing the underlying event log.</p></div></div>
      <div class="mg-agent-roi-window" data-agent-roi-window><button type="button" data-roi-days="30">30 days</button><button type="button" class="is-active" data-roi-days="90">90 days</button><button type="button" data-roi-days="180">180 days</button><button type="button" data-roi-days="365">365 days</button></div>
      <div class="mg-agent-roi-links"><a href="/merchant-agent-analytics.php">Analytics</a><a href="/merchant-agent-messages.php">Messages</a><a href="/merchant-agent-execution.php">Execution</a><a href="/merchant-followups.php">Follow-ups</a><a href="/merchant-claims.php">Claims</a></div>
      <div class="mg-agent-roi-sources" data-roi-sources></div>
    </article>
  </section>

  <section class="mg-agent-roi-grid is-wide">
    <article class="mg-app-panel mg-agent-roi-panel"><div class="mg-app-panel-head"><div><h2>ROI by Playbook</h2><p>Which agent playbooks influenced claims and redemption value.</p></div></div><div class="mg-agent-roi-table" data-roi-playbooks></div></article>
    <article class="mg-app-panel mg-agent-roi-panel"><div class="mg-app-panel-head"><div><h2>ROI by Campaign</h2><p>Campaign-level attribution from agent workflow to claim activity.</p></div></div><div class="mg-agent-roi-table" data-roi-campaigns></div></article>
  </section>

  <section class="mg-agent-roi-grid is-wide">
    <article class="mg-app-panel mg-agent-roi-panel"><div class="mg-app-panel-head"><div><h2>ROI by Customer</h2><p>Customers with agent-touched redemption activity.</p></div></div><div class="mg-agent-roi-table" data-roi-customers-table></div></article>
    <article class="mg-app-panel mg-agent-roi-panel"><div class="mg-app-panel-head"><div><h2>Daily Influenced Revenue</h2><p>Attributed redemption value by day.</p></div></div><div class="mg-agent-roi-trend" data-roi-daily></div></article>
  </section>

  <article class="mg-app-panel mg-agent-roi-panel">
    <div class="mg-app-panel-head"><div><h2>Recent Agent-Influenced Redemptions</h2><p>Latest redemption value linked to agent touchpoints.</p></div><p class="mg-form-status" data-agent-roi-status></p></div>
    <div class="mg-agent-roi-events" data-roi-recent><div class="mg-empty-state"><strong>Loading attributed redemptions…</strong></div></div>
  </article>
</section>
