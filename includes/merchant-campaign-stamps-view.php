<?php
declare(strict_types=1);
?>
<section class="mg-merchant-heading">
  <div>
    <span class="mg-eyebrow">Campaign Stamps</span>
    <h1>Stamped campaign distribution</h1>
    <p>Record campaign distribution volume against the Stamp ledger. Feed, email, QR, SMS, and agentic discovery channels use the shared Stamp debit engine.</p>
  </div>
  <div class="mg-heading-actions"><a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Campaigns</a><a class="mg-btn mg-btn-soft" href="/merchant-stamps.php">Stamp Ledger</a></div>
</section>
<section class="mg-app-panel" id="campaign-stamps">
  <div class="mg-app-panel-head"><div><h2>Record campaign send</h2><p>Email uses 1 Stamp per recipient. SMS uses 3 Stamps per recipient. Agentic discovery uses 2 Stamps.</p></div></div>
  <div class="mg-app-panel-body">
    <form class="mg-merchant-form" data-stage12-campaign-send>
      <div class="mg-grid-2"><label>Campaign<select name="campaign_id" data-stage12-campaign-send-select><option value="">General distribution / no campaign selected</option></select></label><label>Channel<select name="channel"><option value="feed">Feed campaign · 1 Stamp</option><option value="email">Email list · 1 Stamp per recipient</option><option value="sms">SMS · 3 Stamps per recipient</option><option value="qr">QR claim prompt · 1 Stamp</option><option value="agent">Agentic discovery · 2 Stamps</option></select></label></div>
      <div class="mg-grid-2"><label>Recipient/send count<input name="quantity" type="number" min="1" value="1"></label><label>Reference<input name="reference" placeholder="List, segment, QR code, or batch reference"></label></div>
      <label>Internal note<textarea name="note" placeholder="Optional note for the Stamp ledger."></textarea></label>
      <div class="mg-form-status" data-stage12-campaign-send-status>Ready to record a Stamped campaign distribution.</div>
      <button class="mg-btn mg-btn-primary" type="submit">Record Stamped distribution</button>
    </form>
  </div>
</section>
<script src="/assets/js/stage12-campaign-send.js" defer></script>
