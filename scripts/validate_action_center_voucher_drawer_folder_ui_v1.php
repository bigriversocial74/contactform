<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'include' => 'includes/gift-action-center.php',
    'runtime' => 'assets/js/gift-action-center-runtime-v4.js',
    'extension' => 'assets/js/gift-action-center-voucher-drawer-v1.js',
    'styles' => 'assets/css/gift-action-center-voucher-drawer-v1.css',
    'detail' => 'api/account/action-center-voucher-detail.php',
    'token' => 'api/account/action-center-voucher-token.php',
    'actions' => 'assets/js/gift-action-center-actions.js',
    'claim' => 'assets/js/gift-action-center-claim-restore.js',
    'search' => 'assets/js/gift-action-center-user-search-v2.js',
];

$source = [];
foreach ($paths as $key => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) throw new RuntimeException("Missing {$key} file: {$relative}");
    $source[$key] = (string) file_get_contents($path);
}

$runtimePosition = strpos($source['include'], 'gift-action-center-runtime-v4.js');
$extensionPosition = strpos($source['include'], 'gift-action-center-voucher-drawer-v1.js');
$actionsPosition = strpos($source['include'], 'gift-action-center-actions.js');

$checks = [
    'Site-wide user search remains enabled and unchanged' => str_contains($source['include'], 'data-user-profile-search placeholder="Search users"') && str_contains($source['include'], 'gift-action-center-user-search-v2.js?v=2.0.0'),
    'Enhanced drawer assets load after Runtime v4 and before mutation actions' => $runtimePosition !== false && $extensionPosition !== false && $actionsPosition !== false && $runtimePosition < $extensionPosition && $extensionPosition < $actionsPosition,
    'Drawer stylesheet is cache-busted' => str_contains($source['include'], 'gift-action-center-voucher-drawer-v1.css?v=1.0.0'),
    'Voucher detail endpoint requires authenticated GET access' => str_contains($source['detail'], "mg_require_method('GET')") && str_contains($source['detail'], 'mg_require_api_user()'),
    'Voucher detail endpoint scopes canonical items to the signed-in owner' => str_contains($source['detail'], 'ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL'),
    'Voucher detail endpoint uses exact purchased product version data' => str_contains($source['detail'], 'COALESCE(coi.product_version_id,i.product_version_id)') && str_contains($source['detail'], 'cpv.terms_json') && str_contains($source['detail'], 'cpv.expiration_policy_json'),
    'Voucher detail endpoint returns delivery ownership events' => str_contains($source['detail'], 'FROM microgift_delivery_events e') && str_contains($source['detail'], 'sender_user_id') && str_contains($source['detail'], 'recipient_user_id'),
    'Voucher detail endpoint supports reward wallet details' => str_contains($source['detail'], 'mg_ac_wallet_action_id') && str_contains($source['detail'], "'kind' => 'wallet_reward'"),
    'Drawer uses the established signed voucher token endpoint' => str_contains($source['extension'], '/api/account/action-center-voucher-token.php?action_item_id=') && str_contains($source['token'], "'qr_image_url'"),
    'Drawer shows exact contract image, value, terms, location, timeline, and redemption' => str_contains($source['extension'], 'info.presentation.image_url') && str_contains($source['extension'], 'info.snapshot.value_cents') && str_contains($source['extension'], 'Terms and expiration') && str_contains($source['extension'], 'Ownership timeline') && str_contains($source['extension'], 'Redemption'),
    'Drawer exposes protected scan code copy behavior' => str_contains($source['extension'], 'data-voucher-claim-code') && str_contains($source['extension'], 'data-copy-voucher-code') && str_contains($source['extension'], 'navigator.clipboard.writeText'),
    'Extension intercepts only the Load action in capture phase' => str_contains($source['extension'], "'[data-gift-action=\"load\"]'") && str_contains($source['extension'], 'event.stopImmediatePropagation') && str_contains($source['extension'], '}, true);'),
    'Inbox row polish preserves Regift Claim and Load' => str_contains($source['runtime'], "actionButton(c,'send','Regift'") && str_contains($source['runtime'], "actionButton(c,'claim','Claim'") && str_contains($source['runtime'], "actionButton(c,'load','Load'"),
    'Sent production actions preserve Follow Up and Load' => str_contains($source['runtime'], "actionButton(c,'follow-up','Follow Up'") && str_contains($source['actions'], "type === 'follow-up'") && str_contains($source['actions'], '/api/account/action-center-'),
    'Claimed production actions preserve Message Tip and Load' => str_contains($source['runtime'], "actionButton(c,'message','Message'") && str_contains($source['runtime'], "actionButton(c,'tip','Tip'") && str_contains($source['actions'], "type === 'message'") && str_contains($source['actions'], "type === 'tip'"),
    'Claim remains on the isolated claim workflow' => str_contains($source['include'], 'gift-action-center-claim-restore.js') && str_contains($source['runtime'], "mg:gift-claim:open"),
    'Folder-specific empty states are defined' => str_contains($source['extension'], 'No gifts in your Inbox') && str_contains($source['extension'], 'No sent gifts') && str_contains($source['extension'], 'No claimed gifts'),
    'Row UI includes value badges status tones and folder context' => str_contains($source['extension'], 'mg-gift-value-chip') && str_contains($source['extension'], 'mg-gift-folder-context') && str_contains($source['styles'], '.mg-gift-row-polished-v1.is-status-redeemed'),
    'Responsive row and drawer layouts are defined' => str_contains($source['styles'], '@media (max-width: 860px)') && str_contains($source['styles'], '@media (max-width: 620px)') && str_contains($source['styles'], 'width: 100vw !important'),
    'No voucher detail mutation SQL is introduced' => !str_contains($source['detail'], 'INSERT INTO') && !str_contains($source['detail'], 'UPDATE ') && !str_contains($source['detail'], 'DELETE FROM'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Action Center voucher drawer and folder UI validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Action Center voucher drawer and folder UI validation passed (' . count($checks) . ' checks).' . PHP_EOL;
