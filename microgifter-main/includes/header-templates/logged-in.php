<?php
declare(strict_types=1);
?>
<div class="mg-header-actions" data-header-template="logged-in">
  <div class="mg-header-signal" data-header-signal="notifications"><button class="mg-header-icon" type="button" data-header-signal-trigger aria-expanded="false" aria-label="System notifications"><svg viewBox="0 0 24 24"><path d="M12 3a6 6 0 0 0-6 6v3.6L4.7 15a1 1 0 0 0 .88 1.5h12.84A1 1 0 0 0 19.3 15L18 12.6V9a6 6 0 0 0-6-6Z" fill="currentColor"/></svg><span class="mg-header-badge" data-notification-badge hidden>0</span></button></div>
  <div class="mg-header-signal" data-header-signal="messages"><button class="mg-header-icon" type="button" data-header-signal-trigger aria-expanded="false" aria-label="Messages"><svg viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 3v-3a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" fill="currentColor"/></svg><span class="mg-header-badge" data-message-badge hidden>0</span></button></div>
  <div class="mg-account-menu" data-mg-auth-menu>
    <button class="mg-account-trigger" type="button" data-mg-auth-trigger aria-expanded="false"><span class="mg-avatar"><?= mg_e($display_initial) ?></span><span class="mg-account-copy"><span class="mg-account-name"><?= mg_e($display_name) ?></span><span class="mg-account-role"><?= mg_e($user_roles[0] ?? 'member') ?></span></span><span class="mg-account-caret">⌄</span></button>
    <div class="mg-account-actions">
      <div class="mg-account-menu-head"><span class="mg-account-status-light"></span><span class="mg-account-head-copy"><span class="mg-account-head-name"><?= mg_e($display_name) ?></span><span class="mg-account-head-email"><?= mg_e($display_email) ?></span></span><span class="mg-account-session-label">SESSION</span></div>
      <?php $menuIndex = 1; ?>
      <a class="mg-account-action" href="/inbox.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>IN/OUT Box</span></a>
      <a class="mg-account-action" href="/feed.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>My Feed</span></a>
      <a class="mg-account-action" href="/account.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Profile Settings</span></a>
      <a class="mg-account-action" href="/account-commerce.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Commerce center</span></a>
      <a class="mg-account-action" href="/merchant.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Merchant Dashboard</span></a>
      <a class="mg-account-action" href="/archived-agents.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Archived agents</span></a>
      <?php if ($can_sales_crm): ?><a class="mg-account-action" href="/sales-crm.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>CRM dashboard</span></a><?php endif; ?>
      <?php if ($can_admin_dashboard): ?><a class="mg-account-action" href="/account-admin.php"><span class="mg-account-index"><?= str_pad((string) $menuIndex++, 2, '0', STR_PAD_LEFT) ?></span><span>Admin dashboard</span></a><?php endif; ?>
      <button class="mg-account-action mg-account-logout" type="button" data-auth-logout><span class="mg-account-index">00</span><span>Sign out</span></button>
    </div>
  </div>
</div>