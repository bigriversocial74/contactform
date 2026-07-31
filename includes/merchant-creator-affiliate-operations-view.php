<?php declare(strict_types=1); ?>
<section class="mg-caops" data-caops>
  <header class="mg-caops-head">
    <div>
      <span>Creator Campaign Operations · Phase 16</span>
      <h1>Creator Affiliate Operations</h1>
      <p>Define payout policy, verify campaign readiness, reconcile exceptions, and manage Creator eligibility without executing external transfers.</p>
    </div>
    <div class="mg-action-row">
      <a class="mg-btn" href="/merchant-creator-campaigns.php">Campaigns</a>
      <a class="mg-btn" href="/merchant-creator-payouts.php">Payout Records</a>
      <button class="mg-btn mg-btn-primary" type="button" data-caops-scan>Run Reconciliation</button>
    </div>
  </header>

  <section class="mg-caops-boundary">
    <strong>Payment boundary</strong>
    <span>Every payout remains manually approved and provider-neutral. Microgifter records an external reference only after the merchant confirms payment.</span>
  </section>

  <section class="mg-caops-metrics" data-caops-metrics></section>

  <section class="mg-caops-grid">
    <article class="mg-caops-panel mg-caops-policy">
      <header><div><small>Operating policy</small><h2>Payout schedule and rules</h2></div><span data-caops-policy-state>Not configured</span></header>
      <form data-caops-policy-form>
        <div class="mg-caops-fields">
          <label>Currency<input name="currency" value="USD" maxlength="3" required></label>
          <label>Status<select name="status"><option value="active">Active</option><option value="paused">Paused</option></select></label>
          <label>Cadence<select name="cadence"><option value="manual">Manual</option><option value="weekly">Weekly</option><option value="biweekly">Biweekly</option><option value="monthly">Monthly</option></select></label>
          <label data-weekday-field>Payout weekday<select name="payout_weekday"><option value="1">Monday</option><option value="2">Tuesday</option><option value="3">Wednesday</option><option value="4">Thursday</option><option value="5" selected>Friday</option><option value="6">Saturday</option><option value="7">Sunday</option></select></label>
          <label data-monthday-field>Payout day<input name="payout_day_of_month" type="number" min="1" max="28" value="15"></label>
          <label>Commission hold days<input name="hold_days" type="number" min="0" max="90" value="7" required></label>
          <label>Minimum payout<input name="minimum_payout_amount" type="number" min="0" step="0.01" value="25.00" required></label>
          <label>Dispute window days<input name="dispute_window_days" type="number" min="0" max="120" value="30" required></label>
        </div>
        <label>Payment method label<input name="method_label" maxlength="120" placeholder="ACH, PayPal, check, or approved provider"></label>
        <label>Creator payment instructions<textarea name="payment_instructions" rows="4" maxlength="2000" placeholder="Explain timing, approval, provider reference, failed-payment handling, and refund adjustments."></textarea></label>
        <footer><span data-caops-next-date></span><button class="mg-btn mg-btn-primary" type="submit">Save Payout Policy</button></footer>
      </form>
    </article>

    <article class="mg-caops-panel mg-caops-summary">
      <header><div><small>Launch quality</small><h2>Merchant setup health</h2></div></header>
      <div data-caops-readiness-summary class="mg-caops-readiness-summary"></div>
      <p>Each campaign is scored across lifecycle, products, purchase compensation, budget, approved Creators, and active tracking.</p>
    </article>
  </section>

  <nav class="mg-caops-tabs" aria-label="Creator affiliate operations views">
    <button class="is-active" type="button" data-caops-tab="cases">Reconciliation</button>
    <button type="button" data-caops-tab="campaigns">Campaign readiness</button>
    <button type="button" data-caops-tab="participants">Creator payout readiness</button>
  </nav>

  <div class="mg-caops-state" data-caops-state>Loading Creator affiliate operations…</div>
  <section class="mg-caops-list" data-caops-list></section>

  <dialog data-caops-profile-dialog>
    <form data-caops-profile-form>
      <header><div><small>Creator eligibility</small><h2 data-caops-profile-title>Payout profile</h2></div><button type="button" data-caops-close aria-label="Close">×</button></header>
      <input type="hidden" name="participant_id">
      <label>Currency<input name="currency" value="USD" maxlength="3" required></label>
      <label>Status<select name="status"><option value="pending_review">Pending review</option><option value="eligible">Eligible</option><option value="blocked">Blocked</option><option value="incomplete">Incomplete</option></select></label>
      <label>Payment method<input name="method_label" maxlength="120" required></label>
      <label>Creator-specific minimum payout<input name="minimum_payout_amount" type="number" min="0" step="0.01" value="0.00"></label>
      <label>Eligibility note<textarea name="eligibility_note" rows="4" maxlength="2000"></textarea></label>
      <footer><button type="button" class="mg-btn" data-caops-close>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Save Eligibility</button></footer>
    </form>
  </dialog>

  <dialog data-caops-payout-dialog>
    <form data-caops-payout-form>
      <header><div><small>Manual payout assembly</small><h2 data-caops-payout-title>Create payout record</h2></div><button type="button" data-caops-close aria-label="Close">×</button></header>
      <input type="hidden" name="participant_id">
      <input type="hidden" name="idempotency_key">
      <label>Currency<input name="currency" value="USD" maxlength="3" required></label>
      <p data-caops-payout-balance></p>
      <p>The payout will include only payout-ready committed reservations that satisfy the configured hold period and effective minimum. It will begin in Draft and still require approval.</p>
      <footer><button type="button" class="mg-btn" data-caops-close>Cancel</button><button class="mg-btn mg-btn-primary" type="submit">Create Draft Payout</button></footer>
    </form>
  </dialog>
</section>
