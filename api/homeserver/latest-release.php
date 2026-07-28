<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-releases.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-entitlements.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$entitlement = mg_homeserver_require_capability(
    $pdo,
    $user,
    'homeserver.download',
    'HomeServer installer access requires an active paid or complimentary Microgifter package.'
);
$schemaReady = mg_homeserver_release_schema_ready($pdo);
$roles = (array)($user['roles'] ?? []);
$permissions = (array)($user['permissions'] ?? []);
$canManage = in_array('super_admin', $roles, true)
    || in_array('admin.settings.manage', $permissions, true)
    || (function_exists('mg_api_user_has_permission') && mg_api_user_has_permission($user, 'admin.settings.manage'));

$release = null;
if ($schemaReady) {
    $row = mg_homeserver_release_latest(
        $pdo,
        (string)($_GET['channel'] ?? 'stable'),
        (string)($_GET['architecture'] ?? 'x64')
    );
    if ($row) $release = mg_homeserver_release_row_payload($row);
}

mg_ok([
    'schema_ready' => $schemaReady,
    'release' => $release,
    'entitlement' => mg_homeserver_entitlement_payload($pdo, $user, $entitlement),
    'can_manage_releases' => $canManage,
    'admin_url' => $canManage ? '/admin/homeserver-releases.php' : null,
]);
