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
    if (mg_ac_wallet_action_id($publicId) !== null) {
        mg_fail('Read state is not stored for standalone wallet rewards.', 409);
    }
    if (!mg_action_center_mutation_current_contract($pdo, $user, $publicId)) {
        mg_fail('Action Center item not found.', 404);
    }
    mg_action_center_mark_read($pdo, (int)$user['id'], $publicId);
    mg_action_center_mutation_ok($pdo, $user, 'read', $publicId, $publicId, ['status' => 'read'], 'Marked as read.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
}
