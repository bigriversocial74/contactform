<?php
declare(strict_types=1);
?>
<section class="mg-campaign-stamps" data-campaign-stamps-center>
  <div class="mg-campaign-stamps-toolbar">
    <nav class="mg-campaign-stamps-tabs" aria-label="Campaign stamp sections">
      <a class="is-active" href="#stamp-overview">Overview</a>
      <a href="#stamp-inventory">Inventory</a>
      <a href="#stamp-inventory">Usage</a>
      <a href="#stamp-buy">Buy Stamps</a>
      <a href="#campaign-stamps">Campaign Spend</a>
      <a href="/merchant-stamps.php">Ledger</a>
    </nav>
    <a class="mg-btn mg-btn-primary" href="#stamp-buy">Buy Stamps</a>
  </div>

  <section class="mg-campaign-stamps-kpis" id="stamp-overview" aria-label="Campaign stamp metrics">
    <article><span>Available stamps</span><strong data-stamp-kpi-available>—</strong><small>Current balance</small></article>
    <article><span>Used 30D</span><strong data-stamp-kpi-used>—</strong><small>Recent distribution</small></article>
    <article><span>Active campaigns</span><strong data-stamp-kpi-campaigns>—</strong><small>Using stamp channels</small></article>
    <article><span>Estimated reach</span><strong data-stamp-kpi-reach>—</strong><small>At 1 stamp/send</small></article>
    <article><span>Next need</span><strong data-stamp-kpi-need>—</strong><small>Suggested reserve</small></article>
  </section>

  <div class="mg-campaign-stamps-layout">
    <section class="mg-app-panel mg-campaign-stamps-panel" id="stamp-inventory">
      <div class="mg-app-panel-head mg-campaign-stamps-panel-head">
        <div>
          <span class="mg-eyebrow">Campaign Credits</span>
          <h2>Stamp inventory and usage</h2>
          <p>Track campaign distribution fuel across feed, email, QR, SMS, and agentic discovery channels.</p>
        </div>
        <div class="mg-heading-actions">
          <a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Campaigns</a>
          <a class="mg-btn mg-btn-soft" href="/merchant-stamps.php">Stamp Ledger</a>
        </div>
      </div>
      <div class="mg-app-panel-body">
        <div class="mg-stamp-inventory-grid">
          <article><span>Email / Feed</span><strong>1</strong><small>Stamp per recipient or send</small></article>
          <article><span>QR Claim Prompt</span><strong>1</strong><small>Stamp per QR prompt</small></article>
          <article><span>Agentic Discovery</span><strong>2</strong><small>Stamps per discovery send</small></article>
          <article><span>SMS</span><strong>3</strong><small>Stamps per recipient</small></article>
        </div>
        <div class="mg-stamp-usage-chart" aria-hidden="true"><span style="height:34%"></span><span style="height:52%"></span><span style="height:42%"></span><span style="height:72%"></span><span style="height:58%"></span><span style="height:86%"></span><span style="height:64%"></span></div>
      </div>
    </section>

    <aside class="mg-campaign-stamps-side">
      <section class="mg-app-panel mg-campaign-stamps-panel mg-stamp-readiness">
        <div class="mg-app-panel-head mg-campaign-stamps-panel-head is-compact"><div><h2>Stamp Readiness</h2><p>Balance and campaign coverage.</p></div></div>
        <div class="mg-app-panel-body">
          <div class="mg-stamp-readiness-score"><span>Credit signal</span><strong data-stamp-readiness-score>—</strong></div>
          <div class="mg-stamp-readiness-list">
            <p><b></b><span data-stamp-ready-primary>Load a campaign and record usage to update the stamp balance.</span></p>
            <p><b></b><span data-stamp-ready-secondary>Keep a reserve before launching email, SMS, or QR distribution.</span></p>
            <p><b></b><span data-stamp-ready-tertiary>SMS and agentic discovery burn credits faster than feed or email.</span></p>
          </div>
        </div>
      </section>

      <section class="mg-app-panel mg-campaign-stamps-panel mg-stamp-actions" id="stamp-buy">
        <div class="mg-app-panel-head mg-campaign-stamps-panel-head is-compact"><div><h2>Suggested packages</h2><p>Quick planning shortcuts.</p></div></div>
        <div class="mg-app-panel-body">
          <a href="/merchant-stamps.php">Starter reserve <strong>500</strong></a>
          <a href="/merchant-stamps.php">Local campaign <strong>2,500</strong></a>
          <a href="/merchant-stamps.php">Growth push <strong>10,000</strong></a>
          <a href="/merchant-stamps.php">Open ledger <strong>→</strong></a>
        </div>
      </section>
    </aside>
  </div>

  <section class="mg-app-panel mg-campaign-stamps-panel" id="campaign-stamps">
    <div class="mg-app-panel-head mg-campaign-stamps-panel-head"><div><span class="mg-eyebrow">Campaign Spend</span><h2>Record campaign send</h2><p>Email uses 1 Stamp per recipient. SMS uses 3 Stamps per recipient. Agentic discovery uses 2 Stamps.</p></div></div>
    <div class="mg-app-panel-body">
      <form class="mg-merchant-form mg-campaign-stamps-form" data-stage12-campaign-send>
        <div class="mg-grid-2"><label>Campaign<select name="campaign_id" data-stage12-campaign-send-select><option value="">General distribution / no campaign selected</option></select></label><label>Channel<select name="channel" data-stamp-channel><option value="feed">Feed campaign · 1 Stamp</option><option value="email">Email list · 1 Stamp per recipient</option><option value="sms">SMS · 3 Stamps per recipient</option><option value="qr">QR claim prompt · 1 Stamp</option><option value="agent">Agentic discovery · 2 Stamps</option></select></label></div>
        <div class="mg-grid-2"><label>Recipient/send count<input name="quantity" type="number" min="1" value="1" data-stamp-quantity></label><label>Reference<input name="reference" placeholder="List, segment, QR code, or batch reference"></label></div>
        <div class="mg-stamp-estimate"><span>Estimated debit</span><strong data-stamp-estimate>1 Stamp</strong></div>
        <label>Internal note<textarea name="note" placeholder="Optional note for the Stamp ledger."></textarea></label>
        <div class="mg-form-status" data-stage12-campaign-send-status>Ready to record a Stamped campaign distribution.</div>
        <button class="mg-btn mg-btn-primary" type="submit">Record Stamped distribution</button>
      </form>
    </div>
  </section>
</section>
<script src="/assets/js/stage12-campaign-send.js" defer></script>