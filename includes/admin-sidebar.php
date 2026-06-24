<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-permission-matrix.php';

$adminActive = $adminActive ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
$adminPermissions = is_array($user_permissions ?? null)
    ? $user_permissions
    : (is_array($user['permissions'] ?? null) ? $user['permissions'] : []);
$adminRoles = is_array($user_roles ?? null)
    ? $user_roles
    : (is_array($user['roles'] ?? null) ? $user['roles'] : []);

$adminMatrixUser = is_array($user ?? null) ? $user : [];
$adminMatrixUser['permissions'] = $adminPermissions;
$adminMatrixUser['roles'] = $adminRoles;

$canAdminPage = static fn(string $pageKey): bool => mg_admin_user_can_view_page($adminMatrixUser, $pageKey);

$canUsers = $canAdminPage('admin.users');
$canPendingModels = $canAdminPage('admin.pending_models');
$canMerchantCatalog = $canAdminPage('admin.merchant_catalog');
$canCommerce = mg_admin_commerce_user_can_read_any($adminMatrixUser);
$canModeration = $canAdminPage('admin.moderation');
$canPackageModeration = $canCommerce;
$canStampHealth = $canCommerce;
$canHealth = $canAdminPage('admin.system_health');
$canLifecycleHealth = $canAdminPage('admin.lifecycle_health');
$canSettings = $canAdminPage('admin.settings');
$canAi = $canAdminPage('admin.ai');
$canPayments = $canAdminPage('admin.payments');
$canAudit = $canAdminPage('admin.audit_logs');
$canSecurity = $canAdminPage('admin.security_logs');
$canSessions = $canAdminPage('admin.sessions');
$canOpsQueue = $canAdminPage('admin.ops_queue');

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
    'pending-models' => [
        'label' => 'Pending models',
        'detail' => 'Model approval queue',
        'href' => '/admin/pending-models.php',
        'visible' => $canPendingModels,
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
    'package-moderation' => [
        'label' => 'Package moderation',
        'detail' => 'Review and implementation',
        'href' => '/admin/package-moderation.php',
        'visible' => $canPackageModeration,
    ],
    'stamp-health' => [
        'label' => 'Stamp health',
        'detail' => 'Usage economy checks',
        'href' => '/admin/stamp-health.php',
        'visible' => $canStampHealth,
    ],
    'payments' => [
        'label' => 'Stripe payments',
        'detail' => 'Credentials and readiness',
        'href' => '/admin-payments.php',
        'visible' => $canPayments,
    ],
    'moderation' => [
        'label' => 'Moderation',
        'detail' => 'Reports and review',
        'href' => '/admin/moderation.php',
        'visible' => $canModeration,
    ],
    'audit-logs' => [
        'label' => 'Audit logs',
        'detail' => 'Administrative activity',
        'href' => '/admin/audit-logs.php',
        'visible' => $canAudit,
    ],
    'security-logs' => [
        'label' => 'Security logs',
        'detail' => 'Security events',
        'href' => '/admin/security-logs.php',
        'visible' => $canSecurity,
    ],
    'sessions' => [
        'label' => 'Sessions',
        'detail' => 'Active user sessions',
        'href' => '/admin/sessions.php',
        'visible' => $canSessions,
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
        'visible' => $canLifecycleHealth,
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
    <a class="mg-brand mg-sidebar-logo" href="/account-admin.php" aria-label="Microgifter admin home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span class="mg-sidebar-logo-text">Microgifter</span></a>
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