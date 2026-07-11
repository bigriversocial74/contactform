<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_wallet_pppm_authority.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);
$walletId = strtolower(trim((string)($input['wallet_item_id'] ?? $input['reward_id'] ?? '')));
$result = mg_wallet_claim_to_pppm(mg_db(), $user, $walletId, $input);
mg_ok($result, 'Reward added to your Microgifter Inbox.');
