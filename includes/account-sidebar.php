<?php
declare(strict_types=1);
$accountView = $accountView ?? 'overview';
$accountNav = [
  'overview' => ['Commerce', 'Overview', 'Account summary', '/account-commerce.php#overview'],
  'orders' => ['Commerce', 'Orders', 'Orders and receipts', '/account-commerce.php#orders'],
  'items' => ['Commerce', 'Items', 'Purchased and owned items', '/account-commerce.php#items'],
];
if (function_exists('mg_user_has_merchant_access') && mg_user_has_merchant_access()) {
  $accountNav['store-canvas'] = ['Merchant', 'Store Canvas', 'Live avatars, CRM, and agent messaging', '/merchant-canvas.php'];
}
$accountNav += [
  'cart' => ['Checkout', 'Cart', 'Checkout and payment draft', '/cart.php'],
  'inbox' => ['Gifts', 'Inbox', 'Received and redeemable gifts', '/inbox.php'],
  'sent' => ['Gifts', 'Sent', 'Gifts sent to recipients', '/sent.php'],
  'claimed' => ['Gifts', 'Claimed', 'Redeemed gift history', '/claimed.php'],
  'store-history' => ['Activity', 'Store History', 'Merchant visits, messages, and rewards', '/store-history.php'],
  'messages' => ['Activity', 'Messages', 'Gift and recipient conversations', '/messages.php'],
  'notifications' => ['Activity', 'Notifications', 'Activity and account alerts', '/notifications.php'],
  'preferences' => ['Activity', 'Preferences', 'Notification delivery settings', '/notification-preferences.php'],
  'settings' => ['Account', 'Profile Settings', 'Name, email, and security', '/account.php'],
  'subscriptions' => ['Account', 'My Subscriptions', 'Plans, billing, and access', '/account-subscriptions.php'],
];
$currentSection = '';
?>
<button class="mg-account-sidebar-toggle" type="button" data-account-sidebar-toggle aria-expanded="false" aria-controls="account-sidebar">Account menu</button>
<div class="mg-account-sidebar-backdrop" data-account-sidebar-backdrop hidden></div>
<aside class="mg-account-sidebar mg-customer-account-sidebar" id="account-sidebar" data-account-sidebar>
  <div class="mg-account-sidebar-head">
    <a class="mg-account-sidebar-logo" href="/inbox.php" aria-label="Microgifter account home">
      <img src="/images/logo_main_drk.png" alt="Microgifter">
      <span>Customer account</span>
    </a>
    <button type="button" data-account-sidebar-close aria-label="Close account menu">Close</button>
  </div>
  <nav class="mg-account-side-nav" aria-label="Customer account">
    <?php foreach($accountNav as $key=>$item): ?>
      <?php if ($currentSection !== $item[0]): $currentSection = $item[0]; ?>
        <span class="mg-account-nav-section"><?= mg_e($currentSection) ?></span>
      <?php endif; ?>
      <a class="<?= $accountView === $key ? 'is-active' : '' ?>" href="<?= mg_e($item[3]) ?>" data-account-nav="<?= mg_e($key) ?>">
        <strong><?= mg_e($item[1]) ?></strong>
        <span><?= mg_e($item[2]) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="mg-account-sidebar-actions"><a class="mg-btn mg-btn-primary" href="/cart.php">Open cart</a><a class="mg-btn mg-btn-soft" href="/discover.php">Continue shopping</a></div>
</aside>
