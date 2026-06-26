<?php declare(strict_types=1); ?>
<link rel="stylesheet" href="/assets/css/pppm-ops-extra.css">
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
      <a href="#pppm-readiness">Exceptions</a>
    </nav>
    <a class="mg-btn mg-btn-primary" href="#pppm-items-panel">Review Gift Lifecycle</a>
  </div>

  <section class="mg-pppm-hero">
    <div>
      <span class="mg-eyebrow">PPPM Lifecycle</span>
      <h1>Gift operations center</h1>
      <p>Track every order, issuance request, and individually stamped PPPM item from source through delivery, claim, redemption, expiration, refund, and exception review.</p>
    </div>
    <span class="mg-status-badge">Lifecycle active</span>
  </section>

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

    <aside class="mg-pppm-side" id="pppm-readiness">
      <section class="mg-app-panel mg-pppm-panel mg-pppm-readiness-card">
        <div class="mg-app-panel-head mg-pppm-panel-head is-compact"><div><h2>Lifecycle Readiness</h2><p>Operational checks for sent, claimed, expired, refunded, and exception states.</p></div></div>
        <div class="mg-app-panel-body">
          <div class="mg-pppm-readiness-score"><span>Gift signal</span><strong>Live</strong></div>
          <div class="mg-pppm-readiness-list">
            <p><b></b><span>Review expiring gifts before they become inactive or require support follow-up.</span></p>
            <p><b></b><span>Claim failures, refunds, voids, and cancelled items should be reviewed as exceptions.</span></p>
            <p><b></b><span>Lifecycle activity should reconcile to claims, payments, delivery attempts, and operational notes.</span></p>
          </div>
        </div>
      </section>

      <section class="mg-app-panel mg-pppm-panel mg-pppm-actions-card">
        <div class="mg-app-panel-head mg-pppm-panel-head is-compact"><div><h2>Quick actions</h2><p>Gift operations.</p></div></div>
        <div class="mg-app-panel-body">
          <a href="/merchant-claims.php">Claims operations</a>
          <a href="/merchant-payments.php">Payment reconciliation</a>
          <a href="/merchant-locations.php">Redemption locations</a>
          <a href="/merchant-products.php">Product catalog</a>
        </div>
      </section>
    </aside>
  </section>
</section>