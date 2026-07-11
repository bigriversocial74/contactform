<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_wallet_pppm_authority.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);
$walletId = strtolower(trim((string)($input['reward_id'] ?? $input['wallet_item_id'] ?? '')));
$action = strtolower(trim((string)($input['action'] ?? 'claim')));

if ($action === 'mark_viewed') {
    mg_ok([
        'wallet_item_id'=>$walletId,
        'deprecated_wallet_ui'=>true,
        'redirect_url'=>'/inbox.php',
    ], 'Rewards are managed in your Microgifter Inbox.');
}

if ($action === 'refresh_code') {
    mg_fail('Standalone wallet claim codes are retired. Open the reward in your Microgifter Inbox.', 410);
}

if ($action !== 'claim') mg_fail('Invalid reward action.', 422);
$result = mg_wallet_claim_to_pppm(mg_db(), $user, $walletId, $input);
mg_ok($result, 'Reward added to your Microgifter Inbox.');
