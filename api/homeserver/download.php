<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';
require_once dirname(__DIR__, 2) . '/includes/homeserver-entitlements.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$entitlement = mg_homeserver_entitlement_payload($pdo, $user);

mg_audit('homeserver.installer_delegated_to_vp3', 'homeserver_release', [
    'software_authority' => 'vp3',
    'requested_release' => mb_substr(trim((string)($_GET['release'] ?? 'latest')), 0, 190),
], (int)$user['id']);

mg_fail(
    'HomeServer installers are licensed and distributed by VP3.',
    409,
    [
        'error_code' => 'vp3_installer_authority',
        'software_authority' => 'vp3',
        'installer_authority' => 'vp3',
        'vp3_account_url' => 'https://vp3.me',
        'microgifter_provider_access' => $entitlement,
    ]
);
