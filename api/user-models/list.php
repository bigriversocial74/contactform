<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/user_model_workflows.php';

mg_require_method('GET');

$user = mg_refresh_session_user();
$userId = $user ? (int) $user['id'] : null;

mg_ok([
    'models' => mg_list_user_models_with_user_state($userId),
    'authenticated' => $user !== null,
], 'User models.');
