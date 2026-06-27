<?php
declare(strict_types=1);
?>
<section class="mg-agent-composer-page" data-merchant-agent-composer>
  <header class="mg-agent-composer-hero">
    <div>
      <span class="mg-eyebrow">Plan-to-workflow bridge</span>
      <h1>Agent Action Composer</h1>
      <p>Turn Growth Planner recommendations into merchant-reviewable workflow drafts, message seeds, follow-up seeds, and review queue items.</p>
    </div>
    <div class="mg-agent-composer-actions">
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-growth-plan.php">Growth Planner</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-approvals.php">Review Queue</a>
      <a class="mg-btn mg-btn-secondary" href="/merchant-agent-messages.php">Message Outbox</a>
      <button class="mg-btn mg-btn-primary" type="button" data-composer-refresh>Refresh composer</button>
    </div>
  </header>

  <section class="mg-agent-composer-kpis" aria-label="Action composer summary">
    <article><strong data-composer-total>—</strong><span>Total items</span></article>
    <article><strong data-composer-ready>—</strong><span>Ready</span></article>
    <article><strong data-composer-drafts>—</strong><span>Drafted</span></article>
    <article><strong data-composer-review>—</strong><span>For review</span></article>
    <article><strong data-composer-messages>—</strong><span>Messages seeded</span></article>
    <article><strong data-composer-followups>—</strong><span>Follow-ups seeded</span></article>
  </section>

  <section class="mg-agent-composer-controls" aria-label="Composer controls">
    <div><label>Goal</label><select data-composer-goal><option value="claims">Claims</option><option value="revenue" selected>Revenue</option><option value="psr">PSR impact</option><option value="reactivation">Customer reactivation</option></select></div>
    <div><label>Timeframe</label><select data-composer-timeframe><option value="30">30 days</option><option value="60">60 days</option><option value="90" selected>90 days</option></select></div>
    <div><label>Risk</label><select data-composer-risk><option value="conservative">Conservative</option><option value="balanced" selected>Balanced</option><option value="aggressive">Aggressive</option></select></div>
    <div><label>Effort</label><select data-composer-effort><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
    <div><label>Filter</label><select data-composer-filter><option value="all">All</option><option value="ready">Ready</option><option value="draft_created">Drafted</option><option value="submitted_for_review">For review</option><option value="message_seeded">Message seeded</option><option value="followup_seeded">Follow-up seeded</option></select></div>
  </section>

  <section class="mg-agent-composer-grid">
    <article class="mg-app-panel mg-agent-composer-panel">
      <div class="mg-app-panel-head"><div><h2>Recommendation Sources</h2><p>Select a Growth Planner recommendation and compose it into the next workflow surface.</p></div><p class="mg-form-status" data-composer-status></p></div>
      <div class="mg-agent-composer-list" data-composer-list><div class="mg-empty-state"><strong>Loading composer items…</strong></div></div>
    </article>
    <article class="mg-app-panel mg-agent-composer-panel">
      <div class="mg-app-panel-head"><div><h2>Composer Draft</h2><p>Adjust action type, target, metrics, note, and message copy before saving.</p></div></div>
      <form class="mg-agent-composer-form" data-composer-form>
        <input type="hidden" data-compose-id>
        <label>Action type<select data-compose-action-type><option value="review_queue_action">Review queue action</option><option value="message_draft">Message draft</option><option value="followup_task">Follow-up task</option><option value="campaign_repeat">Campaign repeat</option><option value="customer_reactivation">Customer reactivation</option></select></label>
        <label>Target<input type="text" data-compose-target placeholder="Campaign, customer, or playbook target"></label>
        <div class="mg-agent-composer-metrics"><label>Expected claims<input type="number" min="0" step="1" data-compose-claims></label><label>Expected revenue<input type="number" min="0" step="1" data-compose-revenue></label><label>Expected PSR<input type="number" min="0" step="1" data-compose-psr></label></div>
        <label>Merchant note<textarea rows="3" data-compose-note placeholder="Internal merchant note"></textarea></label>
        <label>Message body<textarea rows="5" data-compose-message placeholder="Optional customer-safe draft copy"></textarea></label>
        <div class="mg-agent-composer-form-actions"><button type="button" data-compose-run="create_draft">Save draft</button><button type="button" data-compose-run="submit_for_review">Submit for review</button><button type="button" data-compose-run="seed_message">Seed message</button><button type="button" data-compose-run="seed_followup">Seed follow-up</button></div>
      </form>
    </article>
  </section>

  <article class="mg-app-panel mg-agent-composer-panel">
    <div class="mg-app-panel-head"><div><h2>Workflow Links</h2><p>Composer output flows into merchant-controlled review, message, and follow-up surfaces.</p></div></div>
    <div class="mg-agent-composer-links"><a href="/merchant-agent-growth-plan.php">Growth Planner</a><a href="/merchant-agent-approvals.php">Review Queue</a><a href="/merchant-agent-messages.php">Message Outbox</a><a href="/merchant-followups.php">Follow-ups</a><a href="/merchant-agent-execution.php">Execution Center</a></div>
  </article>
</section>
