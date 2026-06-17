<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/user_model_workflows.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_api_user();
$userId = (int) $user['id'];

if ($method === 'GET') {
    mg_ok([
        'active_model' => mg_current_active_model_context($userId),
        'models' => mg_user_active_model_codes($userId),
    ], 'Active model context.');
}

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $model = trim((string) ($input['model'] ?? ''));
    if ($model === '') {
        mg_fail('Model is required.', 422, ['model' => 'Model is required.']);
    }
    mg_ok(mg_set_active_model_context($userId, $model), 'Active model context updated.');
}

mg_fail('Method not allowed.', 405);
