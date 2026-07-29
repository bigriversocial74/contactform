<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-entitlements.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$entitlement = mg_homeserver_entitlement_context($pdo, $user);

mg_ok([
    'schema_ready' => false,
    'release' => null,
    'entitlement' => mg_homeserver_entitlement_payload($pdo, $user, $entitlement),
    'can_manage_releases' => false,
    'admin_url' => null,
    'software_authority' => 'vp3',
    'installer_authority' => 'vp3',
    'vp3_account_url' => 'https://vp3.me',
    'delegated' => true,
], 'VP3 manages HomeServer licenses, installers, release channels, and software updates.');
