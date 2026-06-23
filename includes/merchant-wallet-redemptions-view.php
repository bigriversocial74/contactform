<?php
declare(strict_types=1);
?>
<section class="mg-merchant-heading">
  <div>
    <span class="mg-eyebrow">Merchant wallet</span>
    <h1>Complete wallet rewards</h1>
    <p>Validate a claimed wallet item and close the campaign loop from issued to completed.</p>
  </div>
  <div class="mg-heading-actions"><a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Campaigns</a></div>
</section>
<section class="mg-app-panel" data-stage12-redemptions>
  <div class="mg-app-panel-head"><div><h2>Complete wallet item</h2><p>Enter the wallet item ID shown by the customer.</p></div></div>
  <div class="mg-app-panel-body">
    <form class="mg-merchant-form" data-redemption-form>
      <label>Wallet item ID<input name="wallet_item_id" required placeholder="Wallet item UUID"></label>
      <label>Location code<input name="location_code" placeholder="Optional location code"></label>
      <div class="mg-form-status" data-redemption-status>Ready to complete a claimed wallet item.</div>
      <button class="mg-btn mg-btn-primary" type="submit">Complete reward</button>
    </form>
  </div>
</section>
