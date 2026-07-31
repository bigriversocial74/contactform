<?php declare(strict_types=1); ?>
<section class="mg-ccpayout-shell" data-ccpayout-creator>
  <header class="mg-ccpayout-head">
    <div>
      <a href="/creator-campaign-earnings.php">← My Affiliate Earnings</a>
      <span>Creator Affiliate Finance</span>
      <h1>My Payouts</h1>
      <p>Review merchant payout terms, eligibility, scheduled records, external payment confirmation, refund-related changes, and disputes tied to your campaigns.</p>
    </div>
    <div class="mg-action-row"><a class="mg-btn" href="/creator-campaign-messages.php">Messages</a><button class="mg-btn mg-btn-primary" type="button" data-ccpayout-dispute>Open Dispute</button></div>
  </header>
  <section class="mg-ccpayout-policy-summary" data-ccpayout-policy></section>
  <section class="mg-ccpayout-metrics" data-ccpayout-totals></section>
  <nav class="mg-ccpayout-tabs"><button class="is-active" data-ccpayout-tab="payouts">Payouts</button><button data-ccpayout-tab="profiles">Eligibility</button><button data-ccpayout-tab="disputes">Disputes</button></nav>
  <div class="mg-ccpayout-state" data-ccpayout-state>Loading payout records…</div><section class="mg-ccpayout-list" data-ccpayout-list></section>
  <dialog data-ccpayout-dispute-dialog><form data-ccpayout-dispute-form><header><h2>Open Dispute</h2><button type="button" data-ccpayout-close>×</button></header><label>Record type<select name="source_type"><option value="payout">Payout</option><option value="reservation">Budget commitment</option><option value="earning">Earning</option></select></label><label>Record ID<input name="source_public_id" required></label><label>What needs review?<textarea name="reason" rows="5" maxlength="2000" required></textarea></label><div><button type="button" class="mg-btn" data-ccpayout-close>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Open Dispute</button></div></form></dialog>
</section>
