<?php declare(strict_types=1); ?>
<section class="mg-merchant-heading">
  <div>
    <span class="mg-eyebrow">Commerce operations</span>
    <h1>Payments &amp; reconciliation</h1>
    <p>Track Stripe onboarding, checkout, captured payments, refunds, payouts, disputes, ledger balances, and provider reconciliation without mixing transaction identity with PPPM identity.</p>
  </div>
  <span class="mg-status-badge" data-financial-provider>Loading provider</span>
</section>

<section class="mg-connect-panel" data-payment-methods-panel>
  <div>
    <span class="mg-eyebrow">Test payment methods</span>
    <h2>Pay with cash</h2>
    <p>Allow this merchant to use a cash payment option during local testing. Cash payments are marked for manual collection and do not create Stripe charges.</p>
    <div class="mg-form-status" data-payment-methods-status></div>
  </div>
  <form class="mg-connect-actions" data-payment-methods-form>
    <label class="mg-toggle-switch"><input type="checkbox" name="cash_enabled" value="1" data-cash-payment-toggle><span class="mg-toggle-control" aria-hidden="true"></span><span class="mg-toggle-copy"><strong>Cash payments</strong><small>Enable manual cash collection for testing.</small></span></label>
    <button class="mg-btn mg-btn-primary" type="submit">Save cash option</button>
  </form>
</section>

<section class="mg-connect-panel" data-connect-panel>
  <div>
    <span class="mg-eyebrow">Stripe Connect</span>
    <h2>Merchant payment account</h2>
    <p>Connect the merchant’s Stripe account before accepting customer payments. Microgifter retains the configured platform share and transfers the remaining proceeds to the connected account.</p>
    <div class="mg-connect-facts" data-connect-facts></div>
    <div class="mg-form-status" data-connect-status></div>
  </div>
  <div class="mg-connect-actions">
    <button class="mg-btn mg-btn-primary" type="button" data-connect-onboard>Start Stripe onboarding</button>
    <button class="mg-btn mg-btn-soft" type="button" data-connect-sync>Refresh account status</button>
  </div>
</section>

<div class="mg-merchant-kpis" data-financial-kpis></div>
<div class="mg-financial-tabs">
  <button class="is-active" data-financial-tab="orders">Orders</button>
  <button data-financial-tab="refunds">Refunds</button>
  <button data-financial-tab="payouts">Payouts</button>
  <button data-financial-tab="disputes">Disputes</button>
  <button data-financial-tab="ledger">Ledger</button>
  <button data-financial-tab="reconciliation">Reconciliation</button>
</div>

<section data-financial-panel="orders">
  <div class="mg-financial-toolbar">
    <input type="search" data-financial-search placeholder="Search order ID">
    <select data-financial-status>
      <option value="all">All statuses</option>
      <option value="unpaid">Unpaid</option>
      <option value="paid">Paid</option>
      <option value="partially_refunded">Partially refunded</option>
      <option value="refunded">Refunded</option>
      <option value="disputed">Disputed</option>
      <option value="failed">Failed</option>
    </select>
  </div>
  <section class="mg-app-panel"><div class="mg-app-panel-body"><div data-financial-orders></div></div></section>
</section>

<section data-financial-panel="refunds" hidden>
  <div class="mg-financial-grid">
    <section class="mg-app-panel">
      <div class="mg-app-panel-head"><div><h2>Refund history</h2><p>Provider-neutral refund state and immutable audit linkage.</p></div></div>
      <div class="mg-app-panel-body"><div data-financial-refunds></div></div>
    </section>
    <section class="mg-app-panel">
      <div class="mg-app-panel-head"><div><h2>Create refund</h2><p>Refunds reverse merchant proceeds and the proportional platform share.</p></div></div>
      <div class="mg-app-panel-body">
        <form class="mg-merchant-form" data-refund-form>
          <label>Order ID<input name="order_id" required></label>
          <label>Amount, cents<input name="amount_cents" type="number" min="1" required></label>
          <label>Reason<select name="reason"><option value="requested_by_customer">Requested by customer</option><option value="duplicate">Duplicate</option><option value="fraudulent">Fraudulent</option><option value="product_unavailable">Product unavailable</option><option value="merchant_error">Merchant error</option><option value="other">Other</option></select></label>
          <input type="hidden" name="idempotency_key">
          <div data-refund-status></div>
          <button class="mg-btn mg-btn-primary" type="submit">Create refund</button>
        </form>
      </div>
    </section>
  </div>
</section>
<section data-financial-panel="payouts" hidden><section class="mg-app-panel"><div class="mg-app-panel-body"><div data-financial-payouts></div></div></section></section>
<section data-financial-panel="disputes" hidden><section class="mg-app-panel"><div class="mg-app-panel-body"><div data-financial-disputes></div></div></section></section>
<section data-financial-panel="ledger" hidden><section class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>Double-entry ledger summary</h2><p>Processor clearing, merchant proceeds, platform revenue, refunds, payouts, and adjustments.</p></div></div><div class="mg-app-panel-body"><div data-financial-ledger></div></div></section></section>
<section data-financial-panel="reconciliation" hidden>
  <div class="mg-financial-grid">
    <section class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>Reconciliation runs</h2><p>Compare internal order and ledger totals to provider settlement data.</p></div></div><div class="mg-app-panel-body"><div data-financial-reconciliation></div></div></section>
    <section class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>Run reconciliation</h2></div></div><div class="mg-app-panel-body"><form class="mg-merchant-form" data-reconciliation-form><div class="mg-grid-2"><label>From<input type="date" name="from" required></label><label>To<input type="date" name="to" required></label></div><div data-reconciliation-status></div><button class="mg-btn mg-btn-primary" type="submit">Run reconciliation</button></form></div></section>
  </div>
</section>