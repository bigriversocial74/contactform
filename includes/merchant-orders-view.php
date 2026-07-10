<?php declare(strict_types=1); ?>
<section class="mg-orders" data-merchant-orders-root>
  <header class="mg-orders-hero">
    <div>
      <span class="mg-eyebrow">Commerce operations</span>
      <h1>Orders &amp; delivery recovery</h1>
      <p>Inspect customer orders, payment state, PPPM and Microgift issuance, Action Center delivery, refunds, disputes, and recovery history from one merchant-owned workspace.</p>
    </div>
    <div class="mg-orders-hero-actions">
      <span>Updated <strong data-orders-updated>—</strong></span>
      <button class="mg-btn mg-btn-ghost" type="button" data-orders-refresh>Refresh</button>
    </div>
  </header>

  <section class="mg-orders-kpis" data-orders-kpis aria-label="Order operations summary"></section>

  <form class="mg-orders-filters" data-orders-filters role="search">
    <label class="is-search">Search
      <input type="search" name="q" maxlength="120" autocomplete="off" placeholder="Order, customer, or product">
    </label>
    <label>Payment
      <select name="payment_status">
        <option value="all">All payments</option>
        <option value="unpaid">Unpaid</option>
        <option value="requires_action">Requires action</option>
        <option value="authorized">Authorized</option>
        <option value="paid">Paid</option>
        <option value="partially_refunded">Partially refunded</option>
        <option value="refunded">Refunded</option>
        <option value="disputed">Disputed</option>
        <option value="failed">Failed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </label>
    <label>Delivery
      <select name="fulfillment_status">
        <option value="all">All delivery states</option>
        <option value="pending">Pending</option>
        <option value="issuing">Issuing</option>
        <option value="issued">Issued</option>
        <option value="partial">Partial</option>
        <option value="failed">Failed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </label>
    <label>From<input type="date" name="date_from"></label>
    <label>To<input type="date" name="date_to"></label>
    <label class="mg-orders-attention"><input type="checkbox" name="attention" value="1"><span>Needs attention only</span></label>
    <div class="mg-orders-filter-actions">
      <button class="mg-btn mg-btn-primary" type="submit">Apply filters</button>
      <button class="mg-btn mg-btn-ghost" type="reset">Reset</button>
    </div>
  </form>

  <section class="mg-app-panel mg-orders-panel">
    <div class="mg-app-panel-head mg-orders-panel-head">
      <div><h2>Customer orders</h2><p data-orders-summary>Loading merchant orders…</p></div>
      <span class="mg-orders-protected">Merchant scoped</span>
    </div>
    <div class="mg-orders-live" data-orders-live role="status" aria-live="polite"></div>
    <div class="mg-orders-state" data-orders-loading><strong>Loading orders</strong><span>Preparing payment and delivery truth.</span></div>
    <div class="mg-orders-state" data-orders-error hidden role="alert"><strong>Unable to load orders</strong><span data-orders-error-message>The order workspace could not be loaded.</span><button class="mg-btn mg-btn-soft" type="button" data-orders-retry>Try again</button></div>
    <div class="mg-orders-state" data-orders-empty hidden><strong>No matching orders</strong><span>Try broader search, status, or date filters.</span></div>
    <div class="mg-orders-table-wrap" data-orders-content hidden>
      <table class="mg-orders-table">
        <thead><tr><th>Order</th><th>Customer</th><th>Payment</th><th>Delivery</th><th>Total</th><th>Updated</th><th></th></tr></thead>
        <tbody data-orders-list></tbody>
      </table>
    </div>
    <footer class="mg-orders-pagination" data-orders-pagination hidden>
      <span data-orders-page-label></span>
      <div><button class="mg-btn mg-btn-ghost" type="button" data-orders-prev>Previous</button><button class="mg-btn mg-btn-soft" type="button" data-orders-next>Next</button></div>
    </footer>
  </section>

  <div class="mg-orders-drawer-layer" data-orders-drawer-layer hidden>
    <button class="mg-orders-drawer-backdrop" type="button" data-orders-close aria-label="Close order details"></button>
    <aside class="mg-orders-drawer" data-orders-drawer role="dialog" aria-modal="true" aria-labelledby="mg-orders-drawer-title" tabindex="-1">
      <header class="mg-orders-drawer-head">
        <div><span class="mg-eyebrow">Merchant order</span><h2 id="mg-orders-drawer-title" data-orders-drawer-title>Order details</h2><p data-orders-drawer-subtitle>Payment, delivery, and lifecycle truth.</p></div>
        <button class="mg-orders-drawer-close" type="button" data-orders-close aria-label="Close order details">×</button>
      </header>
      <div class="mg-orders-drawer-body">
        <div class="mg-orders-state" data-orders-detail-loading><strong>Loading order</strong><span>Preparing payment, issuance, and recovery records.</span></div>
        <div class="mg-orders-state" data-orders-detail-error hidden role="alert"><strong>Unable to load order</strong><span data-orders-detail-error-message>The order detail could not be loaded.</span><button class="mg-btn mg-btn-soft" type="button" data-orders-detail-retry>Try again</button></div>
        <div class="mg-orders-detail" data-orders-detail hidden>
          <section class="mg-orders-detail-section"><header><div><h3>Overview</h3><p>Customer-safe identity, totals, and canonical state.</p></div></header><div class="mg-orders-facts" data-orders-facts></div></section>
          <section class="mg-orders-detail-section"><header><div><h3>Delivery truth</h3><p>Expected units compared with PPPM, Microgift, and Action Center projections.</p></div><span data-orders-delivery-state></span></header><div data-orders-issuance></div></section>
          <section class="mg-orders-detail-section"><header><div><h3>Order items</h3><p>Version-bound product snapshots and per-line issuance.</p></div></header><div data-orders-items></div></section>
          <section class="mg-orders-detail-section"><header><div><h3>Payment activity</h3><p>Payment attempts, transactions, refunds, and disputes.</p></div></header><div data-orders-payments></div></section>
          <section class="mg-orders-detail-section"><header><div><h3>Lifecycle timeline</h3><p>Order status transitions and audited recovery events.</p></div></header><div data-orders-timeline></div></section>
          <section class="mg-orders-detail-section mg-orders-recovery"><header><div><h3>Delivery recovery</h3><p>Verify and repair missing PPPM items, Microgifts, links, and Action Center projections. This operation is transactional and safe to repeat.</p></div><span class="mg-orders-protected">Audited</span></header><div class="mg-orders-recovery-actions"><button class="mg-btn mg-btn-primary" type="button" data-orders-reconcile>Verify / repair delivery</button><div class="mg-orders-live" data-orders-reconcile-status role="status" aria-live="polite"></div></div></section>
        </div>
      </div>
    </aside>
  </div>
</section>
