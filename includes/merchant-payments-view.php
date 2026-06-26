<?php declare(strict_types=1); ?>
<section class="mg-payments-ops" data-payment-operations-center>
  <div class="mg-payments-contract-label">Payments &amp; reconciliation</div>

  <div class="mg-payments-commandbar">
    <nav class="mg-payments-tabs" aria-label="Payment operation sections">
      <a class="is-active" href="#payments-overview">Overview</a>
      <a href="#payments-readiness-panel">Payment Readiness</a>
      <a href="#financial-orders-panel">Checkout</a>
      <a href="#financial-payouts-panel">Payouts</a>
      <a href="#financial-reconciliation-panel">Reconciliation</a>
      <a href="#financial-disputes-panel">Failed Payments</a>
      <a href="#payments-readiness-panel">Tax / Compliance</a>
      <a href="#payment-methods-panel">Settings</a>
    </nav>
    <a class="mg-btn mg-btn-primary" href="#payments-readiness-panel">Review Payment Setup</a>
  </div>

  <section class="mg-payments-hero">
    <div>
      <span class="mg-eyebrow">Payment Operations</span>
      <h1>Checkout readiness center</h1>
      <p>Track Stripe onboarding, checkout, captured payments, refunds, payouts, disputes, ledger balances, and provider reconciliation without mixing transaction identity with PPPM identity.</p>
    </div>
    <span class="mg-status-badge" data-financial-provider>Loading provider</span>
  </section>

  <div class="mg-merchant-kpis mg-payments-kpis" id="payments-overview" data-financial-kpis></div>

  <div class="mg-payments-layout">
    <section class="mg-app-panel mg-payments-panel" id="payments-readiness-panel" data-connect-panel>
      <div class="mg-app-panel-head mg-payments-panel-head">
        <div>
          <span class="mg-eyebrow">Stripe Connect</span>
          <h2>Merchant payment account</h2>
          <p>Connect the merchant payment account before accepting customer payments. Microgifter retains the configured platform share and transfers remaining proceeds to the connected account.</p>
        </div>
        <div class="mg-connect-actions">
          <button class="mg-btn mg-btn-primary" type="button" data-connect-onboard>Start Stripe onboarding</button>
          <button class="mg-btn mg-btn-soft" type="button" data-connect-sync>Refresh account status</button>
        </div>
      </div>
      <div class="mg-app-panel-body">
        <div class="mg-connect-facts" data-connect-facts></div>
        <div class="mg-form-status" data-connect-status></div>
      </div>
    </section>

    <aside class="mg-payments-side">
      <section class="mg-app-panel mg-payments-panel mg-payments-readiness-card">
        <div class="mg-app-panel-head mg-payments-panel-head is-compact"><div><h2>Payment Readiness</h2><p>Operational checks before checkout, payouts, refunds, and live commerce approval.</p></div></div>
        <div class="mg-app-panel-body">
          <div class="mg-payments-readiness-score"><span>Checkout signal</span><strong>Live</strong></div>
          <div class="mg-payments-readiness-list">
            <p><b></b><span>Provider connection and identity verification must be complete before live checkout.</span></p>
            <p><b></b><span>Charges and payouts should both be enabled before campaign sales are promoted.</span></p>
            <p><b></b><span>Refunds, disputes, payouts, and ledger entries should reconcile back to orders.</span></p>
          </div>
        </div>
      </section>

      <section class="mg-app-panel mg-payments-panel mg-payments-actions-card">
        <div class="mg-app-panel-head mg-payments-panel-head is-compact"><div><h2>Quick actions</h2><p>Financial operations.</p></div></div>
        <div class="mg-app-panel-body">
          <a href="#payments-readiness-panel">Review setup</a>
          <a href="#financial-orders-panel">Checkout orders</a>
          <a href="#financial-payouts-panel">Payouts</a>
          <a href="#financial-reconciliation-panel">Run reconciliation</a>
        </div>
      </section>
    </aside>
  </div>

  <section class="mg-app-panel mg-payments-panel" id="payment-methods-panel" data-payment-methods-panel>
    <div class="mg-app-panel-head mg-payments-panel-head">
      <div>
        <span class="mg-eyebrow">Checkout Settings</span>
        <h2>Test payment methods</h2>
        <p>Allow this merchant to use a cash payment option during local testing. Cash payments are marked for manual collection and do not create Stripe charges.</p>
        <div class="mg-form-status" data-payment-methods-status></div>
      </div>
      <form class="mg-connect-actions" data-payment-methods-form>
        <label class="mg-toggle-switch"><input type="checkbox" name="cash_enabled" value="1" data-cash-payment-toggle><span class="mg-toggle-control" aria-hidden="true"></span><span class="mg-toggle-copy"><strong>Cash payments</strong><small>Enable manual cash collection for testing.</small></span></label>
        <button class="mg-btn mg-btn-primary" type="submit">Save cash option</button>
      </form>
    </div>
  </section>

  <div class="mg-financial-tabs mg-payments-financial-tabs">
    <button class="is-active" data-financial-tab="orders">Orders</button>
    <button data-financial-tab="refunds">Refunds</button>
    <button data-financial-tab="payouts">Payouts</button>
    <button data-financial-tab="disputes">Disputes</button>
    <button data-financial-tab="ledger">Ledger</button>
    <button data-financial-tab="reconciliation">Reconciliation</button>
  </div>

  <section data-financial-panel="orders" id="financial-orders-panel">
    <section class="mg-app-panel mg-payments-panel">
      <div class="mg-app-panel-head mg-payments-panel-head"><div><span class="mg-eyebrow">Checkout</span><h2>Order payments</h2><p>Search payment orders by order ID and payment state.</p></div></div>
      <div class="mg-app-panel-body">
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
        <div data-financial-orders></div>
      </div>
    </section>
  </section>

  <section data-financial-panel="refunds" id="financial-refunds-panel" hidden>
    <div class="mg-financial-grid">
      <section class="mg-app-panel mg-payments-panel">
        <div class="mg-app-panel-head mg-payments-panel-head"><div><h2>Refund history</h2><p>Provider-neutral refund state and immutable audit linkage.</p></div></div>
        <div class="mg-app-panel-body"><div data-financial-refunds></div></div>
      </section>
      <section class="mg-app-panel mg-payments-panel">
        <div class="mg-app-panel-head mg-payments-panel-head"><div><h2>Create refund</h2><p>Refunds reverse merchant proceeds and the proportional platform share.</p></div></div>
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

  <section data-financial-panel="payouts" id="financial-payouts-panel" hidden><section class="mg-app-panel mg-payments-panel"><div class="mg-app-panel-head mg-payments-panel-head"><div><span class="mg-eyebrow">Payouts</span><h2>Payout activity</h2><p>Provider payout status, net settlement, gross amount, fees, and arrival dates.</p></div></div><div class="mg-app-panel-body"><div data-financial-payouts></div></div></section></section>
  <section data-financial-panel="disputes" id="financial-disputes-panel" hidden><section class="mg-app-panel mg-payments-panel"><div class="mg-app-panel-head mg-payments-panel-head"><div><span class="mg-eyebrow">Exceptions</span><h2>Disputes and failed payment risk</h2><p>Review dispute reason, due date, amount, and current provider state.</p></div></div><div class="mg-app-panel-body"><div data-financial-disputes></div></div></section></section>
  <section data-financial-panel="ledger" id="financial-ledger-panel" hidden><section class="mg-app-panel mg-payments-panel"><div class="mg-app-panel-head mg-payments-panel-head"><div><h2>Double-entry ledger summary</h2><p>Processor clearing, merchant proceeds, platform revenue, refunds, payouts, and adjustments.</p></div></div><div class="mg-app-panel-body"><div data-financial-ledger></div></div></section></section>
  <section data-financial-panel="reconciliation" id="financial-reconciliation-panel" hidden>
    <div class="mg-financial-grid">
      <section class="mg-app-panel mg-payments-panel"><div class="mg-app-panel-head mg-payments-panel-head"><div><h2>Reconciliation runs</h2><p>Compare internal order and ledger totals to provider settlement data.</p></div></div><div class="mg-app-panel-body"><div data-financial-reconciliation></div></div></section>
      <section class="mg-app-panel mg-payments-panel"><div class="mg-app-panel-head mg-payments-panel-head"><div><h2>Run reconciliation</h2></div></div><div class="mg-app-panel-body"><form class="mg-merchant-form" data-reconciliation-form><div class="mg-grid-2"><label>From<input type="date" name="from" required></label><label>To<input type="date" name="to" required></label></div><div data-reconciliation-status></div><button class="mg-btn mg-btn-primary" type="submit">Run reconciliation</button></form></div></section>
    </div>
  </section>
</section>