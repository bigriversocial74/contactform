<?php
declare(strict_types=1);
?>
<section class="mg-merchant-heading">
  <div>
    <span class="mg-eyebrow">Reward templates</span>
    <h1>Wallet-ready rewards</h1>
    <p>Create reusable local value objects for campaigns, QR pickups, and future agent discovery.</p>
  </div>
  <div class="mg-heading-actions">
    <a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Campaigns</a>
    <a class="mg-btn mg-btn-primary" href="/build.php">Create paid microgift</a>
  </div>
</section>

<section class="mg-app-panel">
  <div class="mg-app-panel-head"><div><h2>Template builder</h2><p>Templates define the item that lands in the Microgifter inbox.</p></div></div>
  <div class="mg-app-panel-body">
    <form class="mg-merchant-form" data-stage12-template-builder>
      <input type="hidden" name="template_id" value="">
      <div class="mg-grid-2">
        <label>Reward type<select name="reward_type"><option value="dollar_credit">Dollar Credit</option><option value="free_item">Free Item</option><option value="discount">Discount</option><option value="perk_upgrade">Perk / Upgrade</option><option value="event_reward">Event Reward</option><option value="custom">Custom</option></select></label>
        <label>Status<select name="status"><option value="draft">Draft</option><option value="active">Active</option><option value="paused">Paused</option><option value="archived">Archived</option></select></label>
      </div>
      <label>Template title<input name="title" placeholder="Coffee credit" required maxlength="180"></label>
      <label>Description<textarea name="description" placeholder="Explain what the customer receives."></textarea></label>
      <div class="mg-grid-2"><label>Value amount<input name="value_amount" placeholder="10.00"></label><label>Expiration rule<select name="expiration_rule"><option value="none">No expiration</option><option value="after_issue">After issue</option><option value="after_claim">After claim</option><option value="fixed_date">Fixed date</option><option value="event_date">Event date</option></select></label></div>
      <div class="mg-grid-2"><label>Quantity limit<input name="quantity_limit" type="number" min="1" placeholder="Unlimited"></label><label>Per-user limit<input name="per_user_limit" type="number" min="1" value="1"></label></div>
      <label><input type="checkbox" name="agent_discoverable" value="1"> Agent-discoverable offer</label>
      <label>Agent summary<input name="agent_summary" placeholder="Short recommendation summary"></label>
      <label>Agent categories<input name="agent_categories" placeholder="coffee, lunch, local rewards"></label>
      <label>Redemption instructions<textarea name="redemption_instructions" placeholder="Show this reward to staff."></textarea></label>
      <div class="mg-form-status" data-stage12-template-status>Ready to save a reward template.</div>
      <div class="mg-heading-actions"><button class="mg-btn mg-btn-primary" type="submit" data-stage12-template-save>Save template</button><button class="mg-btn mg-btn-ghost" type="button" data-stage12-template-new>New template</button></div>
    </form>
  </div>
</section>
<section class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>Saved templates</h2><p>Reusable reward objects available for campaigns and wallet delivery.</p></div></div><div class="mg-app-panel-body"><div class="mg-product-list" data-stage12-template-list></div></div></section>
<script src="/assets/js/stage12-reward-templates.js" defer></script>
