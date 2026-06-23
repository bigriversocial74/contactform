<?php
declare(strict_types=1);
?>
<section class="mg-merchant-heading">
  <div>
    <span class="mg-eyebrow">Reward templates</span>
    <h1>Wallet-ready rewards</h1>
    <p>Create reusable local value objects for campaigns, manual sends, QR pickups, and future agent discovery.</p>
  </div>
  <div class="mg-heading-actions">
    <a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Campaigns</a>
    <a class="mg-btn mg-btn-primary" href="/build.php">Create paid microgift</a>
  </div>
</section>

<section class="mg-app-panel">
  <div class="mg-app-panel-head"><div><h2>Template builder contract</h2><p>Templates define the thing that lands in the Microgifter inbox. Campaigns define how it gets there.</p></div></div>
  <div class="mg-app-panel-body">
    <form class="mg-merchant-form" data-stage12-template-builder>
      <div class="mg-grid-2">
        <label>Reward type
          <select name="reward_type"><option value="dollar_credit">Dollar Credit</option><option value="free_item">Free Item</option><option value="discount">Discount</option><option value="perk_upgrade">Perk / Upgrade</option><option value="event_reward">Event Reward</option><option value="custom">Custom</option></select>
        </label>
        <label>Status
          <select name="status"><option value="draft">Draft</option><option value="active">Active</option><option value="paused">Paused</option></select>
        </label>
      </div>
      <label>Template title<input name="title" placeholder="$10 Coffee Credit"></label>
      <label>Description<textarea name="description" placeholder="Explain what the customer receives."></textarea></label>
      <div class="mg-grid-2">
        <label>Value amount<input name="value_amount" placeholder="10.00"></label>
        <label>Expiration rule<select name="expiration_rule"><option value="none">No expiration</option><option value="after_issue">After issue</option><option value="after_claim">After claim</option><option value="fixed_date">Fixed date</option></select></label>
      </div>
      <label><input type="checkbox" name="agent_discoverable" value="1"> Agent-discoverable offer</label>
      <label>Redemption instructions<textarea name="redemption_instructions" placeholder="Show this reward to staff before checkout."></textarea></label>
      <div class="mg-form-status">Schema is in place. Save endpoints and list APIs land in the next implementation slice.</div>
      <button class="mg-btn mg-btn-primary" type="button">Save template draft</button>
    </form>
  </div>
</section>
