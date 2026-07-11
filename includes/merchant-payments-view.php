<?php declare(strict_types=1); ?>
<section class="mg-payments-ops" data-payment-operations-center>
  <div class="mg-merchant-kpis mg-payments-kpis" data-financial-kpis aria-label="Payment metrics"></div>

  <nav class="mg-payments-page-tabs" role="tablist" aria-label="Payment sections">
    <button class="is-active" type="button" role="tab" aria-selected="true" data-payments-tab="methods">Payments</button>
    <button type="button" role="tab" aria-selected="false" data-payments-tab="orders">Orders</button>
    <button type="button" role="tab" aria-selected="false" data-payments-tab="refunds">Refunds</button>
    <button type="button" role="tab" aria-selected="false" data-payments-tab="payouts">Payouts</button>
    <button type="button" role="tab" aria-selected="false" data-payments-tab="disputes">Disputes</button>
    <button type="button" role="tab" aria-selected="false" data-payments-tab="reconciliation">Reconciliation</button>
  </nav>

  <section class="mg-payments-page is-active" data-payments-page="methods" id="payments-methods-panel">
    <section class="mg-app-panel mg-payments-panel">
      <div class="mg-app-panel-head mg-payments-panel-head">
        <div>
          <span class="mg-eyebrow">Payment Methods</span>
          <h2>Accepted payment options</h2>
          <p>Choose the payment methods this merchant can present at checkout, then connect Stripe through Stripe’s secure account authorization flow.</p>
        </div>
        <span class="mg-status-badge" data-financial-provider>Loading payment status</span>
      </div>
      <div class="mg-app-panel-body">
        <form class="mg-payment-methods-form" data-payment-methods-form>
          <div class="mg-payment-method-grid">
            <label class="mg-payment-method-card">
              <span class="mg-payment-method-icon" aria-hidden="true">$</span>
              <span class="mg-payment-method-copy">
                <strong>Cash payments</strong>
                <small>Allow manual cash collection for local or test transactions. No Stripe charge is created.</small>
              </span>
              <span class="mg-toggle-switch">
                <input type="checkbox" name="cash_enabled" value="1" data-cash-payment-toggle>
                <span class="mg-toggle-control" aria-hidden="true"></span>
                <span class="mg-toggle-label">Enable</span>
              </span>
            </label>

            <label class="mg-payment-method-card">
              <span class="mg-payment-method-icon is-stripe" aria-hidden="true">S</span>
              <span class="mg-payment-method-copy">
                <strong>Stripe payments</strong>
                <small>Enable card-payment availability for this merchant. Checkout remains blocked until the connected Stripe account is ready.</small>
              </span>
              <span class="mg-toggle-switch">
                <input type="checkbox" name="stripe_enabled" value="1" data-stripe-payment-toggle>
                <span class="mg-toggle-control" aria-hidden="true"></span>
                <span class="mg-toggle-label">Enable</span>
              </span>
            </label>
          </div>

          <div class="mg-payment-method-footer">
            <div class="mg-form-status" data-payment-methods-status aria-live="polite"></div>
            <button class="mg-btn mg-btn-primary" type="submit" data-payment-methods-save>Save payment methods</button>
          </div>
        </form>

        <section class="mg-stripe-connect-card" data-stripe-connect-card aria-labelledby="mg-stripe-connect-title">
          <div class="mg-stripe-connect-brand" aria-hidden="true">S</div>
          <div class="mg-stripe-connect-main">
            <div class="mg-stripe-connect-heading">
              <div>
                <span class="mg-eyebrow">Official Stripe Connect</span>
                <h3 id="mg-stripe-connect-title">Connect your Stripe account</h3>
                <p>Stripe will let you sign in to an existing Stripe account or create a new account, then authorize Microgifter to process merchant payments.</p>
              </div>
              <span class="mg-status-badge is-missing" data-stripe-connect-status-badge>Not connected</span>
            </div>

            <div class="mg-stripe-connect-alert" data-stripe-connect-feedback hidden role="status" aria-live="polite"></div>
            <div class="mg-stripe-connect-platform" data-stripe-connect-platform></div>

            <div class="mg-stripe-connect-status-grid" aria-label="Stripe account readiness">
              <article><span>Account</span><strong data-stripe-account-state>Not connected</strong></article>
              <article><span>Details</span><strong data-stripe-details-state>Pending</strong></article>
              <article><span>Payments</span><strong data-stripe-charges-state>Disabled</strong></article>
              <article><span>Payouts</span><strong data-stripe-payouts-state>Disabled</strong></article>
            </div>

            <div class="mg-stripe-connect-requirements" data-stripe-connect-requirements hidden>
              <strong>Stripe still needs</strong>
              <ul data-stripe-requirements-list></ul>
            </div>

            <div class="mg-stripe-connect-meta" data-stripe-connect-meta hidden></div>
            <div class="mg-form-status" data-stripe-connect-action-status aria-live="polite"></div>

            <div class="mg-stripe-connect-actions">
              <button class="mg-btn mg-btn-primary" type="button" data-stripe-connect-start>Connect or create Stripe account</button>
              <button class="mg-btn mg-btn-soft" type="button" data-stripe-connect-sync hidden>Refresh Stripe status</button>
              <a class="mg-btn mg-btn-soft" href="https://dashboard.stripe.com/" target="_blank" rel="noopener noreferrer" data-stripe-connect-dashboard hidden>Open Stripe Dashboard</a>
              <button class="mg-btn mg-btn-ghost" type="button" data-stripe-connect-disconnect hidden>Disconnect</button>
            </div>
            <p class="mg-stripe-connect-note">Microgifter never receives or stores the merchant’s Stripe password. Stripe returns only the connected account ID and authorization result.</p>
          </div>
        </section>
      </div>
    </section>
  </section>

  <section class="mg-payments-page" data-payments-page="orders" id="financial-orders-panel" hidden>
    <section class="mg-app-panel mg-payments-panel">
      <div class="mg-app-panel-head mg-payments-panel-head">
        <div><span class="mg-eyebrow">Checkout</span><h2>Order payments</h2><p>Search payment orders by order ID and payment state.</p></div>
      </div>
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

  <section class="mg-payments-page" data-payments-page="refunds" id="financial-refunds-panel" hidden>
    <div class="mg-financial-grid">
      <section class="mg-app-panel mg-payments-panel">
        <div class="mg-app-panel-head mg-payments-panel-head"><div><span class="mg-eyebrow">Refunds</span><h2>Refund history</h2><p>Provider-neutral refund state and immutable audit linkage.</p></div></div>
        <div class="mg-app-panel-body"><div data-financial-refunds></div></div>
      </section>
      <section class="mg-app-panel mg-payments-panel">
        <div class="mg-app-panel-head mg-payments-panel-head"><div><span class="mg-eyebrow">Refund Action</span><h2>Create refund</h2><p>Refunds reverse merchant proceeds and the proportional platform share.</p></div></div>
        <div class="mg-app-panel-body">
          <form class="mg-merchant-form" data-refund-form>
            <label>Order ID<input name="order_id" required></label>
            <label>Amount, cents<input name="amount_cents" type="number" min="1" required></label>
            <label>Reason<select name="reason"><option value="requested_by_customer">Requested by customer</option><option value="duplicate">Duplicate</option><option value="fraudulent">Fraudulent</option><option value="product_unavailable">Product unavailable</option><option value="merchant_error">Merchant error</option><option value="other">Other</option></select></label>
            <input type="hidden" name="idempotency_key">
            <div class="mg-form-status" data-refund-status aria-live="polite"></div>
            <button class="mg-btn mg-btn-primary" type="submit">Create refund</button>
          </form>
        </div>
      </section>
    </div>
  </section>

  <section class="mg-payments-page" data-payments-page="payouts" id="financial-payouts-panel" hidden>
    <section class="mg-app-panel mg-payments-panel">
      <div class="mg-app-panel-head mg-payments-panel-head"><div><span class="mg-eyebrow">Payouts</span><h2>Payout activity</h2><p>Provider payout status, net settlement, gross amount, fees, and arrival dates.</p></div></div>
      <div class="mg-app-panel-body"><div data-financial-payouts></div></div>
    </section>
  </section>

  <section class="mg-payments-page" data-payments-page="disputes" id="financial-disputes-panel" hidden>
    <section class="mg-app-panel mg-payments-panel">
      <div class="mg-app-panel-head mg-payments-panel-head"><div><span class="mg-eyebrow">Exceptions</span><h2>Disputes and failed payment risk</h2><p>Review dispute reason, due date, amount, and current provider state.</p></div></div>
      <div class="mg-app-panel-body"><div data-financial-disputes></div></div>
    </section>
  </section>

  <section class="mg-payments-page" data-payments-page="reconciliation" id="financial-reconciliation-panel" hidden>
    <div class="mg-financial-grid">
      <section class="mg-app-panel mg-payments-panel">
        <div class="mg-app-panel-head mg-payments-panel-head"><div><span class="mg-eyebrow">Reconciliation</span><h2>Reconciliation runs</h2><p>Compare internal order and ledger totals to provider settlement data.</p></div></div>
        <div class="mg-app-panel-body"><div data-financial-reconciliation></div></div>
      </section>
      <section class="mg-app-panel mg-payments-panel">
        <div class="mg-app-panel-head mg-payments-panel-head"><div><span class="mg-eyebrow">New Run</span><h2>Run reconciliation</h2><p>Review a selected date range against recorded payment and settlement activity.</p></div></div>
        <div class="mg-app-panel-body">
          <form class="mg-merchant-form" data-reconciliation-form>
            <div class="mg-grid-2"><label>From<input type="date" name="from" required></label><label>To<input type="date" name="to" required></label></div>
            <div class="mg-form-status" data-reconciliation-status aria-live="polite"></div>
            <button class="mg-btn mg-btn-primary" type="submit">Run reconciliation</button>
          </form>
        </div>
      </section>
    </div>

    <section class="mg-app-panel mg-payments-panel mg-ledger-panel">
      <div class="mg-app-panel-head mg-payments-panel-head"><div><span class="mg-eyebrow">Ledger</span><h2>Double-entry ledger summary</h2><p>Processor clearing, merchant proceeds, platform revenue, refunds, payouts, and adjustments.</p></div></div>
      <div class="mg-app-panel-body"><div data-financial-ledger></div></div>
    </section>
  </section>
</section>
