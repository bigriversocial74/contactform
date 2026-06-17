<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/user_model_workflows.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$admin = mg_require_permission('admin.users.view');

$targetUserId = (int) ($input['user_id'] ?? 0);
$model = trim((string) ($input['model'] ?? ''));
$action = trim((string) ($input['action'] ?? ''));
$reason = trim((string) ($input['reason'] ?? ''));

if ($targetUserId <= 0) {
    mg_fail('Target user is required.', 422, ['user_id' => 'Target user is required.']);
}
if ($model === '') {
    mg_fail('Model is required.', 422, ['model' => 'Model is required.']);
}

$actionToStatus = [
    'approve' => 'active',
    'enable' => 'active',
    'reject' => 'rejected',
    'disable' => 'disabled',
    'suspend' => 'suspended',
    'revoke' => 'revoked',
];

if (!isset($actionToStatus[$action])) {
    mg_fail('Invalid action.', 422, ['action' => 'Invalid action.']);
}

$result = mg_admin_set_user_model_status(
    $targetUserId,
    $model,
    $actionToStatus[$action],
    (int) $admin['id'],
    $reason !== '' ? $reason : null
);

mg_ok(['result' => $result], 'User model updated.');
