<?php
declare(strict_types=1);
$giftCenterFolder=in_array($giftCenterFolder??'inbox',['inbox','sent','claimed'],true)?$giftCenterFolder:'inbox';
$giftCenterTitle=['inbox'=>'Inbox','sent'=>'Sent','claimed'=>'Claimed'][$giftCenterFolder];
$giftCenterDemoEnabled=mg_has_role('super_admin');
$giftCenterCopy=[
    'inbox'=>'Received Microgifts ready to open, regift, claim, or load into the PPPM view.',
    'sent'=>'Outbound Microgifts, transfer history, and private Follow Up actions.',
    'claimed'=>'Redeemed Microgifts, redemption history, merchant messages, and tip actions.',
][$giftCenterFolder];
?>
<link rel="stylesheet" href="/assets/css/gift-action-center-modal-fix.css">
<section class="mg-app-shell mg-gift-center-page" data-gift-center data-initial-folder="<?= mg_e($giftCenterFolder) ?>" data-demo-enabled="<?= $giftCenterDemoEnabled?'true':'false' ?>">
  <?php require __DIR__ . '/agent-sidebar.php'; ?>

  <div class="mg-app-workspace mg-gift-center-workspace">
    <header class="mg-gift-center-header">
      <div>
        <span class="mg-eyebrow">Microgifter wallet</span>
        <h1 data-gift-folder-label><?= mg_e($giftCenterTitle) ?></h1>
        <p data-gift-folder-description><?= mg_e($giftCenterCopy) ?></p>
      </div>
      <div class="mg-gift-center-header-actions">
        <a class="mg-btn mg-btn-secondary" href="/merchant-agent-chat.php">Ask Agent</a>
        <a class="mg-btn mg-btn-secondary" href="/merchant-crm.php">Merchant CRM</a>
        <a class="mg-btn mg-btn-primary" href="/feed.php">My Feed</a>
      </div>
    </header>

    <section class="mg-gift-center-main" aria-label="<?= mg_e($giftCenterTitle) ?> gifts">
      <div class="mg-gift-toolbar">
        <div>
          <span class="mg-eyebrow">Gift lifecycle</span>
          <strong data-gift-folder-subtitle><?= mg_e($giftCenterCopy) ?></strong>
        </div>
        <div class="mg-gift-toolbar-actions">
          <input type="search" data-gift-search placeholder="Search gifts, merchants, people, status…" aria-label="Search gifts">
          <button class="mg-btn mg-btn-secondary" type="button" data-gift-refresh>Refresh</button>
        </div>
      </div>
      <div class="mg-gift-feed-column">
        <div class="mg-gift-list" data-gift-list></div>
      </div>
    </section>
  </div>

  <div class="mg-gift-drawer-backdrop" data-gift-drawer-backdrop hidden></div>
  <aside class="mg-gift-drawer" data-gift-drawer aria-hidden="true" aria-label="Loaded PPPM content">
    <header class="mg-gift-drawer-header">
      <div><span class="mg-account-eyebrow">Loaded PPPM content</span><strong data-gift-drawer-title>Gift content</strong></div>
      <button class="mg-gift-drawer-close" type="button" data-gift-drawer-close aria-label="Close loaded content">×</button>
    </header>
    <div class="mg-gift-drawer-content" data-gift-drawer-content></div>
  </aside>

  <div class="mg-action-modal-backdrop" data-action-modal-backdrop hidden></div>
  <section class="mg-action-modal" data-action-modal aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="gift-action-modal-title">
    <header class="mg-action-modal-header">
      <div><span class="mg-account-eyebrow" data-action-modal-eyebrow>Gift action</span><h2 id="gift-action-modal-title" data-action-modal-title>Action</h2></div>
      <button type="button" data-action-modal-close aria-label="Close form">×</button>
    </header>
    <div class="mg-action-modal-body" data-action-modal-body></div>
  </section>
</section>
<script src="/assets/js/gift-action-center-actions.js" defer></script>
<script src="/assets/js/gift-action-center-claim-qr.js" defer></script>
<script src="/assets/js/gift-action-center-modal-portal.js" defer></script>