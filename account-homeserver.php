<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/homeserver-entitlements.php';

$user = mg_current_user();
if (!$user) {
    header('Location: /signin.php?return=' . rawurlencode('/account-homeserver.php'), true, 302);
    exit;
}

$entitlement = mg_homeserver_entitlement_context(mg_db(), $user);
if (!mg_homeserver_entitlement_has($entitlement, 'homeserver.manage')) {
    header('Location: /account-subscriptions.php?homeserver=upgrade', true, 302);
    exit;
}

define('MG_ACCOUNT_VIEW', 'homeserver');
require __DIR__ . '/account.php';
