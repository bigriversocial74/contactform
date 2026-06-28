<?php
declare(strict_types=1);
?>
<section class="mg-agent-command-row">
  <div class="mg-agent-modes" data-agent-modes></div>
  <label class="mg-agent-demo-toggle" data-agent-demo-wrap hidden><input type="checkbox" data-agent-demo-mode> Demo data</label>
</section>
<section class="mg-agent-mission-grid">
  <section class="mg-app-panel mg-agent-health-panel">
    <div class="mg-app-panel-head is-compact"><div><h2>Health Scores</h2><p>Campaign, reward, CRM, and claims readiness.</p></div></div>
    <div class="mg-agent-health-list" data-agent-health-list></div>
  </section>
  <section class="mg-app-panel mg-agent-goals-panel">
    <div class="mg-app-panel-head is-compact"><div><h2>Merchant Goals</h2><p>Persistent goals used to guide agent responses.</p></div></div>
    <form class="mg-agent-goals-form" data-agent-goals-form>
      <input name="primary_goal" placeholder="Primary goal">
      <input name="secondary_goal" placeholder="Secondary goal">
      <input name="focus" placeholder="Current focus">
      <input name="tone" placeholder="Tone">
      <input name="budget" placeholder="Budget">
      <button class="mg-btn mg-btn-primary" type="submit">Save goals</button>
    </form>
  </section>
</section>
<section class="mg-agent-mission-grid">
  <section class="mg-app-panel mg-agent-package-panel">
    <div class="mg-app-panel-head is-compact"><div><h2>Daily Agent Briefing</h2><p>Generate a daily operating brief with priorities and opportunities.</p></div></div>
    <button class="mg-btn mg-btn-secondary" type="button" data-agent-daily-brief>Generate daily briefing</button>
  </section>
  <section class="mg-app-panel mg-agent-package-panel">
    <div class="mg-app-panel-head is-compact"><div><h2>One-Click Draft Package</h2><p>Create a campaign, reward, and CRM package for review.</p></div></div>
    <button class="mg-btn mg-btn-secondary" type="button" data-agent-package-create>Create 3-part package</button>
    <a class="mg-btn mg-btn-soft" href="/merchant-agent-approvals.php">Open review queue</a>
  </section>
</section>
<section class="mg-app-panel mg-agent-timeline-panel">
  <div class="mg-app-panel-head is-compact"><div><h2>Agent Timeline</h2><p>Recent agent activity and review workflow progress.</p></div></div>
  <div class="mg-agent-timeline" data-agent-timeline></div>
</section>
