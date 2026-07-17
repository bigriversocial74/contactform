<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center_contract.php';

mg_require_method('GET');
$user = mg_require_api_user();
$userId = (int) $user['id'];
$publicId = trim((string) ($_GET['id'] ?? ''));
if ($publicId === '') mg_fail('Action Center item id is required.', 422);

$pdo = mg_db();
$item = mg_action_center_detail($pdo, $userId, $publicId);

if ($item === null) {
    $walletId = mg_ac_wallet_action_id($publicId);
    if ($walletId !== null) {
        $wallet = mg_ac_wallet_load_for_user($pdo, $walletId, $userId, mg_ac_wallet_user_email($user), false);
        if ($wallet) $item = mg_ac_wallet_public_item($wallet);
    }
}

if ($item === null) mg_fail('Action Center item not found.', 404);
$items = mg_action_center_contract_items($pdo, $userId, [$item]);
if ($items === []) mg_fail('Action Center item not found.', 404);

mg_ok([
    'contract_version' => MG_ACTION_CENTER_CONTRACT_VERSION,
    'item' => $items[0],
]);
