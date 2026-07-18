<?php
declare(strict_types=1);
require_once __DIR__ . '/_action_center_mutation_contract.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $publicId = mg_action_center_mutation_public_id($input['id'] ?? $input['action_item_id'] ?? '');
    $pdo = mg_db();
    if (mg_ac_wallet_action_id($publicId) !== null) mg_fail('Standalone wallet rewards do not use archive restore.', 409);
    mg_action_center_restore($pdo, (int)$user['id'], $publicId);
    mg_action_center_mutation_ok($pdo, $user, 'restore', $publicId, $publicId, ['status' => 'restored'], 'Item restored.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
}
