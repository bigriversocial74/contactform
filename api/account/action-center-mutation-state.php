<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center_mutation_contract.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $action = mg_action_center_mutation_action((string)($input['action'] ?? ''));
    $actionItemId = mg_action_center_mutation_public_id($input['action_item_id'] ?? $input['id'] ?? '');
    $preferredActionItemId = trim((string)($input['preferred_action_item_id'] ?? ''));
    if ($preferredActionItemId !== '') {
        $preferredActionItemId = mg_action_center_mutation_public_id($preferredActionItemId, 'Replacement Action Center item');
    }
    $viewFolder = mg_action_center_mutation_view_folder($input['folder'] ?? null);
    $viewLimit = mg_action_center_mutation_view_limit($input['view_limit'] ?? 15);

    $result = [
        'status' => 'synchronized',
        'duplicate' => !empty($input['duplicate']),
    ];

    mg_action_center_mutation_ok(
        mg_db(),
        $user,
        $action,
        $actionItemId,
        $preferredActionItemId !== '' ? $preferredActionItemId : null,
        $result,
        'Action Center synchronized.',
        200,
        $viewFolder,
        $viewLimit
    );
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_security_log('error', 'action_center.mutation_sync_failed', 'Unable to reconcile Action Center mutation state.', [
        'action' => (string)($input['action'] ?? ''),
        'action_item_id' => (string)($input['action_item_id'] ?? $input['id'] ?? ''),
        'exception_class' => $error::class,
    ], (int)($user['id'] ?? 0));
    mg_fail('Unable to synchronize the Action Center.', 500);
}
