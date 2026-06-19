<?php
declare(strict_types=1);

$adminActive = $adminActive ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
$adminPermissions = is_array($user_permissions ?? null)
    ? $user_permissions
    : (is_array($user['permissions'] ?? null) ? $user['permissions'] : []);
$adminRoles = is_array($user_roles ?? null)
    ? $user_roles
    : (is_array($user['roles'] ?? null) ? $user['roles'] : []);
$isSuperAdmin = in_array('super_admin', $adminRoles, true);

$canUsers = $isSuperAdmin || in_array('admin.users.view', $adminPermissions, true);
$canMerchantCatalog = $isSuperAdmin
    || in_array('admin.merchants.view', $adminPermissions, true)
    || in_array('admin.catalog.view', $adminPermissions, true);
$canCommerce = $isSuperAdmin || count(array_intersect([
    'admin.commerce.view',
    'merchant.payments.view',
    'subscriptions.admin',
    'microgift.operations.view',
    'tips.reverse',
], $adminPermissions)) > 0;
$canModeration = $isSuperAdmin || count(array_intersect([
    'social.moderate',
    'admin.profiles.moderation.view',
    'admin.profiles.moderation.manage',
], $adminPermissions)) > 0;
$canHealth = $isSuperAdmin || in_array('admin.health.view', $adminPermissions, true);
$canAi = $isSuperAdmin || in_array('admin.settings.manage', $adminPermissions, true);
$canOpsQueue = $isSuperAdmin || count(array_intersect([
    'ops.alerts.assign',
    'ops.alerts.resolve',
], $adminPermissions)) > 0;

$adminNav = [
    'dashboard' => [
        'label' => 'Admin dashboard',
        'detail' => 'Platform overview',
        'href' => '/account-admin.php',
        'visible' => true,
    ],
    'users' => [
        'label' => 'User center',
        'detail' => 'Accounts and access',
        'href' => '/admin/users.php',
        'visible' => $canUsers,
    ],
    'merchant-catalog' => [
        'label' => 'Merchants & catalog',
        'detail' => 'Stores, products, media',
        'href' => '/merchant-catalog-operations.php',
        'visible' => $canMerchantCatalog,
    ],
    'commerce' => [
        'label' => 'Commerce operations',
        'detail' => 'Orders and lifecycle',
        'href' => '/commerce-operations.php',
        'visible' => $canCommerce,
    ],
    'moderation' => [
        'label' => 'Moderation center',
        'detail' => 'Reports and review',
        'href' => '/admin/moderation.php',
        'visible' => $canModeration,
    ],
    'system-health' => [
        'label' => 'System health',
        'detail' => 'Runtime and delivery',
        'href' => '/admin/system-health.php',
        'visible' => $canHealth,
    ],
    'lifecycle-health' => [
        'label' => 'Lifecycle health',
        'detail' => 'Checkout to redemption',
        'href' => '/admin/lifecycle-health.php',
        'visible' => $canHealth,
    ],
    'ops-queue' => [
        'label' => 'Operations queue',
        'detail' => 'Alerts and incidents',
        'href' => '/admin/ops-queue.php',
        'visible' => $canOpsQueue,
    ],
    'ai' => [
        'label' => 'AI providers',
        'detail' => 'Server-side settings',
        'href' => '/admin-ai.php',
        'visible' => $canAi,
    ],
];
?>
<aside class="mg-app-sidebar mg-admin-side" data-admin-sidebar>
  <div class="mg-app-sidebar-brand mg-admin-side-brand">
    <a class="mg-brand" href="/account-admin.php" aria-label="Microgifter admin home"><span>Microgifter</span></a>
    <span class="mg-admin-side-badge">Admin</span>
  </div>

  <div class="mg-admin-side-intro">
    <span>Protected workspace</span>
    <strong>Operations center</strong>
    <p>Permission-aware tools for platform administration.</p>
  </div>

  <nav class="mg-app-side-nav mg-admin-side-nav" aria-label="Administrative pages">
    <?php foreach ($adminNav as $key => $item): ?>
      <?php if (!$item['visible']) { continue; } ?>
      <a class="<?= $adminActive === $key ? 'is-active' : '' ?>" href="<?= mg_e($item['href']) ?>">
        <strong><?= mg_e($item['label']) ?></strong>
        <span><?= mg_e($item['detail']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="mg-admin-side-actions">
    <a class="mg-btn mg-btn-soft" href="/account.php">Account</a>
    <a class="mg-btn mg-btn-ghost" href="/inbox.php">Exit admin</a>
  </div>
</aside>
