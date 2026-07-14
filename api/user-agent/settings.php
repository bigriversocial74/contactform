<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
$user=mg_require_api_user();
$pdo=mg_db();
if (strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='GET') {
    mg_user_agent_api_run(static fn():array=>['settings'=>mg_personal_agent_settings($pdo,(int)$user['id']),'models'=>mg_personal_agent_available_models($pdo)]);
}
mg_require_method('POST');
$input=mg_input();
mg_require_csrf_for_write($input);
mg_user_agent_api_run(static fn():array=>['settings'=>mg_personal_agent_update_settings($pdo,(int)$user['id'],$input),'models'=>mg_personal_agent_available_models($pdo)]);
