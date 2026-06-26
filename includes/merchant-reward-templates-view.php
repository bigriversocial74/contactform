<?php
declare(strict_types=1);
?>
<section class="mg-reward-library" data-reward-library-manager>
  <div class="mg-reward-toolbar">
    <nav class="mg-reward-tabs" aria-label="Reward library sections">
      <a class="is-active" href="#reward-overview">Overview</a>
      <a href="#reward-list">Active Rewards</a>
      <a href="#reward-builder">Drafts</a>
      <a href="#reward-builder">Gift Cards</a>
      <a href="#reward-builder">Discounts</a>
      <a href="#reward-builder">Experiences</a>
      <a href="#reward-list">Archived</a>
    </nav>
    <a class="mg-btn mg-btn-primary" href="#reward-builder">Create Reward</a>
  </div>

  <section class="mg-reward-kpis" id="reward-overview" aria-label="Reward metrics">
    <article><span>Active rewards</span><strong data-reward-kpi-active>—</strong><small>Available for campaigns</small></article>
    <article><span>Draft rewards</span><strong data-reward-kpi-draft>—</strong><small>Needs setup</small></article>
    <article><span>Issued rewards</span><strong data-reward-kpi-issued>—</strong><small>Template inventory</small></article>
    <article><span>Claimed rewards</span><strong data-reward-kpi-claimed>—</strong><small>Customer claims</small></article>
    <article><span>Redeemed rewards</span><strong data-reward-kpi-redeemed>—</strong><small>Completed commerce</small></article>
  </section>

  <div class="mg-reward-layout">
    <section class="mg-app-panel mg-reward-panel" id="reward-list">
      <div class="mg-app-panel-head mg-reward-panel-head">
        <div>
          <span class="mg-eyebrow">Reward Library</span>
          <h2>Offer assets</h2>
          <p>Reusable wallet-ready rewards for campaigns, QR pickups, distribution programs, and agent discovery.</p>
        </div>
        <div class="mg-heading-actions">
          <a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Campaigns</a>
          <a class="mg-btn mg-btn-soft" href="/merchant-distribution.php">Distribution</a>
        </div>
      </div>
      <div class="mg-app-panel-body">
        <div class="mg-product-list mg-reward-list" data-stage12-template-list></div>
      </div>
    </section>

    <aside class="mg-reward-side">
      <section class="mg-app-panel mg-reward-panel mg-reward-readiness">
        <div class="mg-app-panel-head mg-reward-panel-head is-compact"><div><h2>Reward Readiness</h2><p>What needs attention before scaling campaigns.</p></div></div>
        <div class="mg-app-panel-body">
          <div class="mg-reward-readiness-score"><span>Library score</span><strong data-reward-readiness-score>—</strong></div>
          <div class="mg-reward-readiness-list">
            <p><b></b><span data-reward-ready-primary>Create at least one active reward for campaign distribution.</span></p>
            <p><b></b><span data-reward-ready-secondary>Add redemption instructions so staff know exactly what to honor.</span></p>
            <p><b></b><span data-reward-ready-tertiary>Use expiration and limits to control liability and inventory.</span></p>
          </div>
        </div>
      </section>

      <section class="mg-app-panel mg-reward-panel mg-reward-actions">
        <div class="mg-app-panel-head mg-reward-panel-head is-compact"><div><h2>Quick actions</h2><p>Common reward assets.</p></div></div>
        <div class="mg-app-panel-body">
          <a href="#reward-builder">Create discount</a>
          <a href="#reward-builder">Create gift reward</a>
          <a href="#reward-builder">Create experience</a>
          <a href="/merchant-campaigns.php">Attach to campaign</a>
        </div>
      </section>
    </aside>
  </div>

  <section class="mg-app-panel mg-reward-panel mg-reward-builder-panel" id="reward-builder">
    <div class="mg-app-panel-head mg-reward-panel-head">
      <div><span class="mg-eyebrow">Builder</span><h2>Reward builder</h2><p>Templates define the item that lands in the Microgifter inbox.</p></div>
    </div>
    <div class="mg-app-panel-body">
      <form class="mg-merchant-form mg-reward-builder-form" data-stage12-template-builder>
        <input type="hidden" name="template_id" value="">
        <div class="mg-grid-2"><label>Reward type<select name="reward_type"><option value="dollar_credit">Dollar Credit</option><option value="free_item">Free Item</option><option value="discount">Discount</option><option value="perk_upgrade">Perk / Upgrade</option><option value="event_reward">Event Reward</option><option value="custom">Custom</option></select></label><label>Status<select name="status"><option value="draft">Draft</option><option value="active">Active</option><option value="paused">Paused</option><option value="archived">Archived</option></select></label></div>
        <label>Template title<input name="title" placeholder="Coffee credit" required maxlength="180"></label>
        <label>Description<textarea name="description" placeholder="Explain what the customer receives."></textarea></label>
        <div class="mg-grid-2"><label>Value amount<input name="value_amount" placeholder="10.00"></label><label>Expiration rule<select name="expiration_rule"><option value="none">No expiration</option><option value="after_issue">After issue</option><option value="after_claim">After claim</option><option value="fixed_date">Fixed date</option><option value="event_date">Event date</option></select></label></div>
        <div class="mg-grid-2"><label>Quantity limit<input name="quantity_limit" type="number" min="1" placeholder="Unlimited"></label><label>Per-user limit<input name="per_user_limit" type="number" min="1" value="1"></label></div>
        <label class="mg-reward-check"><input type="checkbox" name="agent_discoverable" value="1"> <span>Agent-discoverable offer</span></label>
        <label>Agent summary<input name="agent_summary" placeholder="Short recommendation summary"></label>
        <label>Agent categories<input name="agent_categories" placeholder="coffee, lunch, local rewards"></label>
        <label>Redemption instructions<textarea name="redemption_instructions" placeholder="Show this reward to staff."></textarea></label>
        <div class="mg-form-status" data-stage12-template-status>Ready to save a reward template.</div>
        <div class="mg-heading-actions"><button class="mg-btn mg-btn-primary" type="submit" data-stage12-template-save>Save template</button><button class="mg-btn mg-btn-ghost" type="button" data-stage12-template-new>New template</button></div>
      </form>
    </div>
  </section>
</section>
<script src="/assets/js/stage12-reward-templates.js" defer></script>