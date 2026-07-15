<?php
declare(strict_types=1);
$giftCenterFolder=in_array($giftCenterFolder??'inbox',['inbox','sent','claimed'],true)?$giftCenterFolder:'inbox';
$giftCenterTitles=['inbox'=>'Inbox','sent'=>'Sent','claimed'=>'Claimed'];
$giftCenterTitle=$giftCenterTitles[$giftCenterFolder];
$giftCenterDemoEnabled=mg_has_role('super_admin');
?>
<link rel="stylesheet" href="/assets/css/gift-action-center-modals.css">
<link rel="stylesheet" href="/assets/css/gift-envelope-presentation.css">
<link rel="stylesheet" href="/assets/css/gift-action-center-search.css">
<link rel="stylesheet" href="/assets/css/gift-action-center-user-search-fix.css?v=1.0.0">
<link rel="stylesheet" href="/assets/css/gift-action-center-feed-v3.css?v=3.2.0">
<section class="mg-app-shell mg-gift-center-page" data-gift-center data-feed-version="3" data-initial-folder="<?= mg_e($giftCenterFolder) ?>" data-demo-enabled="<?= $giftCenterDemoEnabled?'true':'false' ?>">
  <?php require __DIR__ . '/gift-center-sidebar.php'; ?>

  <div class="mg-app-workspace mg-gift-center-workspace">
    <section class="mg-gift-center-main" aria-label="<?= mg_e($giftCenterTitle) ?> gifts">
      <div class="mg-gift-toolbar">
        <div class="mg-gift-toolbar-actions">
          <label class="mg-gift-search-shell" aria-label="Search users">
            <span class="mg-gift-search-icon" aria-hidden="true"></span>
            <input type="search" data-gift-search data-user-profile-search placeholder="Search users" autocomplete="off" aria-expanded="false" aria-controls="mg-gift-search-results">
            <button type="button" data-gift-search-clear aria-label="Clear search" hidden>×</button>
            <div class="mg-gift-search-results" id="mg-gift-search-results" data-gift-search-results role="listbox" hidden></div>
          </label>
          <button class="mg-btn mg-btn-secondary" type="button" data-gift-refresh>Refresh</button>
        </div>
      </div>
      <?php if ($giftCenterFolder === 'inbox'): ?>
        <aside class="mg-gift-inbox-sponsored" aria-label="Sponsored inbox recommendation">
          <section class="mg-sponsored-placement" data-mg-ad-placement="inbox_recommendation" data-mg-ad-limit="1" aria-label="Sponsored recommendation"></section>
        </aside>
      <?php endif; ?>
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
<script src="/assets/js/gift-action-center-claim-restore.js" defer></script>
<script src="/assets/js/gift-action-center-modal-portal.js?v=1.1.0" defer></script>
<script src="/assets/js/gift-envelope-presentation.js" defer></script>
<script src="/assets/js/gift-action-center-feed-v3.js?v=3.1.0" defer></script>
<script src="/assets/js/gift-action-center-user-search.js" defer></script>
<script src="/assets/js/gift-action-center-user-search-fix.js?v=1.0.0" defer></script>