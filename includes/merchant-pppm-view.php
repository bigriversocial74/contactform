<?php declare(strict_types=1); ?>
<link rel="stylesheet" href="/assets/css/pppm-ops-extra.css?v=2.0.0">
<section class="mg-pppm-ops" data-pppm-operations-center>
  <div class="mg-pppm-contract-label">Orders &amp; PPPM PPPM items</div>

  <div class="mg-pppm-commandbar">
    <nav class="mg-pppm-tabs" aria-label="PPPM lifecycle sections">
      <a class="is-active" href="#pppm-overview">Overview</a>
      <a href="#pppm-items-panel">Received</a>
      <a href="#pppm-items-panel">Sent</a>
      <a href="#pppm-items-panel">Claimed</a>
      <a href="#pppm-items-panel">Expired</a>
      <a href="#pppm-items-panel">Refunded</a>
      <a href="#pppm-items-panel">Regifted</a>
      <a href="#pppm-items-panel">Exceptions</a>
    </nav>
  </div>

  <section class="mg-pppm-layout" id="pppm-overview">
    <section class="mg-app-panel mg-pppm-panel" id="pppm-items-panel">
      <div class="mg-app-panel-head mg-pppm-panel-head">
        <div>
          <span class="mg-eyebrow">Gift Lifecycle</span>
          <h2>PPPM item activity</h2>
          <p>Search gifts by PPPM ID, order, recipient, product title, lifecycle state, funding source, delivery count, claim activity, and value.</p>
        </div>
      </div>
      <div class="mg-app-panel-body">
        <div class="mg-ops-tabs mg-pppm-switch-tabs"><button class="is-active" data-ops-tab="items">PPPM items</button><button data-ops-tab="orders">Orders &amp; invoices</button></div>
        <section data-ops-panel="items">
          <div class="mg-ops-toolbar"><input type="search" data-pppm-search placeholder="Search PPPM ID, order, recipient, or title"><select data-pppm-status><option value="all">All statuses</option><option value="available">Available</option><option value="assigned">Assigned</option><option value="scheduled">Scheduled</option><option value="sent">Sent</option><option value="delivered">Delivered</option><option value="viewed">Viewed</option><option value="claim_pending">Claim pending</option><option value="verified">Verified</option><option value="redeemed">Redeemed</option><option value="expired">Expired</option><option value="cancelled">Cancelled</option><option value="refunded">Refunded</option><option value="voided">Voided</option></select><select data-pppm-source><option value="all">All sources</option></select></div>
          <div class="mg-merchant-kpis mg-pppm-kpis" data-pppm-kpis></div>
          <div class="mg-pppm-list" data-pppm-list></div>
        </section>
        <section data-ops-panel="orders" hidden>
          <div class="mg-ops-toolbar"><input type="search" data-order-search placeholder="Search order or invoice reference"><select data-order-status><option value="all">All statuses</option><option value="pending">Pending</option><option value="validated">Validated</option><option value="issuing">Issuing</option><option value="issued">Issued</option><option value="failed">Failed</option><option value="cancelled">Cancelled</option></select></div>
          <div class="mg-merchant-kpis mg-pppm-kpis" data-order-kpis></div>
          <div class="mg-order-list" data-order-list></div>
        </section>
      </div>
    </section>
  </section>
</section>
