<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/user_model_workflows.php';

mg_require_method('GET');

$user = mg_require_api_user();
$userId = (int) $user['id'];

mg_ok([
    'models' => mg_user_active_model_codes($userId),
    'model_assignments' => mg_user_model_assignments($userId),
    'active_model' => mg_current_active_model_context($userId),
], 'Current user models.');
