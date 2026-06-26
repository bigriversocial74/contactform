<?php
declare(strict_types=1);

$show_header_create = $show_header_create ?? true;
$show_header_signals = $show_header_signals ?? true;
$show_header_cart = $show_header_cart ?? true;
$user_roles = is_array($user_roles ?? null) ? $user_roles : [];
$user_permissions = is_array($user_permissions ?? null) ? $user_permissions : [];
$can_merchant_nav = $can_merchant_nav ?? (
    in_array('merchant', $user_roles, true)
    || in_array('admin', $user_roles, true)
    || in_array('super_admin', $user_roles, true)
    || in_array('merchant.manage', $user_permissions, true)
    || in_array('merchant.workspace.view', $user_permissions, true)
    || in_array('merchant.crm.view', $user_permissions, true)
    || in_array('merchant.products.manage', $user_permissions, true)
    || !empty($account_storefront_url)
);
?>
<div class="mg-header-actions" data-header-template="logged-in">
  <?php if ($show_header_create): ?>
    <a class="mg-header-create" href="/build.php" data-header-create data-global-create aria-label="Create" aria-haspopup="dialog" aria-controls="mg-create-menu" aria-expanded="false">+</a>
  <?php endif; ?>

  <?php if ($show_header_signals): ?>
    <div class="mg-header-signal" data-header-signal="notifications">
      <button class="mg-header-icon" type="button" data-header-signal-trigger aria-expanded="false" aria-label="System notifications">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a6 6 0 0 0-6 6v3.6L4.7 15a1 1 0 0 0 .88 1.5h12.84A1 1 0 0 0 19.3 15L18 12.6V9a6 6 0 0 0-6-6Z" fill="currentColor"/></svg>
        <span class="mg-header-badge" data-notification-badge hidden>0</span>
      </button>
      <div class="mg-header-signal-panel" data-header-signal-panel="notifications">
        <div class="mg-header-signal-panel-head"><div><span>Notifications</span><strong>Activity &amp; alerts</strong></div><a href="/notifications.php">View all</a></div>
        <div class="mg-header-signal-empty"><strong>Loading notifications…</strong><p>Gift, claim, campaign, delivery, and account updates will appear here.</p></div>
      </div>
    </div>
    <div class="mg-header-signal" data-header-signal="messages">
      <button class="mg-header-icon" type="button" data-header-signal-trigger aria-expanded="false" aria-label="Messages">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 3v-3a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" fill="currentColor"/></svg>
        <span class="mg-header-badge" data-message-badge hidden>0</span>
      </button>
      <div class="mg-header-signal-panel" data-header-signal-panel="messages">
        <div class="mg-header-signal-panel-head"><div><span>Messages</span><strong>Gift conversations</strong></div><a href="/messages.php">View all</a></div>
        <div class="mg-header-signal-empty"><strong>Loading messages…</strong><p>New conversations and gift replies will appear here.</p></div>
      </div>
    </div>
  <?php endif; ?>

  <div class="mg-account-menu" data-mg-auth-menu>
    <button class="mg-account-trigger" type="button" data-mg-auth-trigger aria-expanded="false">
      <span class="mg-avatar"><?= mg_e($display_initial) ?></span>
      <span class="mg-account-copy">
        <span class="mg-account-name"><?= mg_e($display_name) ?></span>
        <span class="mg-account-role"><?= mg_e($user_roles[0] ?? 'member') ?></span>
      </span>
      <span class="mg-account-caret">⌄</span>
    </button>
    <div class="mg-account-actions">
      <div class="mg-account-menu-head">
        <span class="mg-account-status-light"></span>
        <span class="mg-account-head-copy">
          <span class="mg-account-head-name"><?= mg_e($display_name) ?></span>
          <span class="mg-account-head-email"><?= mg_e($display_email) ?></span>
        </span>
        <span class="mg-account-session-label">SESSION</span>
      </div>
      <?php $menuIndex = 1; ?>
      <a class="mg-account-action" href="/inbox.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>IN/OUT Box</span></a>
      <a class="mg-account-action" href="/feed.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>My Feed</span></a>
      <?php if ($account_profile_url): ?><a class="mg-account-action" href="<?= mg_e($account_profile_url) ?>"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>My Profile</span></a><?php endif; ?>
      <?php if ($can_merchant_nav && $account_storefront_url): ?><a class="mg-account-action" href="<?= mg_e($account_storefront_url) ?>"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>My Storefront</span></a><?php endif; ?>
      <a class="mg-account-action" href="/account.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Profile Settings</span></a>
      <a class="mg-account-action" href="/account-commerce.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Commerce center</span></a>
      <?php if ($can_merchant_nav): ?>
        <a class="mg-account-action" href="/merchant.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Merchant Dashboard</span></a>
        <a class="mg-account-action" href="/merchant-crm.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Merchant CRM</span></a>
      <?php endif; ?>
      <?php if ($can_sales_crm): ?><a class="mg-account-action" href="/sales-crm.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Sales CRM</span></a><?php endif; ?>
      <?php if ($can_admin_dashboard): ?><a class="mg-account-action" href="/account-admin.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Admin dashboard</span></a><?php endif; ?>
      <a class="mg-account-action mg-account-upgrade" href="/pricing.php"><span class="mg-account-index">UP</span><span>Upgrade</span></a>
      <a class="mg-account-action" href="/account-subscriptions.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>My Subscriptions</span></a>
      <button class="mg-account-action mg-account-logout" type="button" data-auth-logout><span class="mg-account-index">00</span><span>Sign out</span></button>
    </div>
  </div>

  <?php if ($show_header_cart): ?>
    <button class="mg-cart-header-button" type="button" data-cart-trigger aria-label="Open shopping cart" aria-expanded="false">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 9.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L21 7H7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="19" r="1.5" fill="currentColor"/><circle cx="18" cy="19" r="1.5" fill="currentColor"/></svg>
      <span class="mg-cart-header-badge" data-cart-badge hidden>0</span>
    </button>
  <?php endif; ?>
</div>