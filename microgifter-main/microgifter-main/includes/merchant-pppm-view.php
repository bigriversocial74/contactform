<?php declare(strict_types=1); ?>
<section class="mg-merchant-heading"><div><span class="mg-eyebrow">Post-purchase operations</span><h1>Orders &amp; PPPM</h1><p>Track every order, issuance request, and individually stamped PPPM item from source through fulfillment and redemption.</p></div></section>
<div class="mg-ops-tabs"><button class="is-active" data-ops-tab="items">PPPM items</button><button data-ops-tab="orders">Orders &amp; invoices</button></div>
<section data-ops-panel="items">
 <div class="mg-ops-toolbar"><input type="search" data-pppm-search placeholder="Search PPPM ID, order, recipient, or title"><select data-pppm-status><option value="all">All statuses</option><option value="available">Available</option><option value="assigned">Assigned</option><option value="scheduled">Scheduled</option><option value="sent">Sent</option><option value="delivered">Delivered</option><option value="viewed">Viewed</option><option value="claim_pending">Claim pending</option><option value="verified">Verified</option><option value="redeemed">Redeemed</option><option value="expired">Expired</option><option value="cancelled">Cancelled</option><option value="refunded">Refunded</option><option value="voided">Voided</option></select><select data-pppm-source><option value="all">All sources</option></select></div>
 <div class="mg-merchant-kpis" data-pppm-kpis></div>
 <section class="mg-app-panel"><div class="mg-app-panel-body"><div class="mg-pppm-list" data-pppm-list></div></div></section>
</section>
<section data-ops-panel="orders" hidden>
 <div class="mg-ops-toolbar"><input type="search" data-order-search placeholder="Search order or invoice reference"><select data-order-status><option value="all">All statuses</option><option value="pending">Pending</option><option value="validated">Validated</option><option value="issuing">Issuing</option><option value="issued">Issued</option><option value="failed">Failed</option><option value="cancelled">Cancelled</option></select></div>
 <div class="mg-merchant-kpis" data-order-kpis></div>
 <section class="mg-app-panel"><div class="mg-app-panel-body"><div class="mg-order-list" data-order-list></div></div></section>
</section>
