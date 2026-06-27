<?php
declare(strict_types=1);
?>
<section class="mg-merchant-heading mg-merchant-notifications-heading">
  <div>
    <span class="mg-eyebrow">Merchant notification center</span>
    <h1>Tips, voucher messages, and reward alerts</h1>
    <p>Track customer voucher messages, wallet reward tips, campaign reward movement, and redemption activity from one merchant-facing feed.</p>
  </div>
  <span class="mg-status-badge" data-merchant-notification-status>Loading feed</span>
</section>
<div class="mg-merchant-notification-kpis" data-merchant-notification-kpis></div>
<section class="mg-app-panel mg-merchant-notification-panel">
  <div class="mg-app-panel-head">
    <div>
      <h2>Merchant activity feed</h2>
      <p>Operational alerts and user notifications are merged so staff can act from one queue.</p>
    </div>
    <button class="mg-btn mg-btn-secondary" type="button" data-merchant-notification-refresh>Refresh</button>
  </div>
  <div class="mg-merchant-notification-tabs" data-merchant-notification-tabs>
    <button type="button" data-filter="all" class="is-active">All <span data-count="all">0</span></button>
    <button type="button" data-filter="unread">Unread <span data-count="unread">0</span></button>
    <button type="button" data-filter="tips">Tips <span data-count="tips">0</span></button>
    <button type="button" data-filter="messages">Messages <span data-count="messages">0</span></button>
    <button type="button" data-filter="rewards">Rewards <span data-count="rewards">0</span></button>
    <button type="button" data-filter="redemptions">Redemptions <span data-count="redemptions">0</span></button>
  </div>
  <div class="mg-app-panel-body">
    <div class="mg-merchant-notification-feed" data-merchant-notification-feed>
      <div class="mg-empty-state"><p>Loading merchant notifications…</p></div>
    </div>
  </div>
</section>
