<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/user_model_workflows.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$user = mg_require_api_user();
$model = trim((string) ($input['model'] ?? ''));

if ($model === '') {
    mg_fail('Model is required.', 422, ['model' => 'Model is required.']);
}

$result = mg_request_user_model((int) $user['id'], $model);
$fresh = mg_load_user_auth((int) $user['id']);
if ($fresh) {
    mg_set_session_user($fresh);
}

mg_ok([
    'request' => $result,
    'user' => $fresh ? mg_public_user($fresh) : $user,
], 'User model request submitted.');
