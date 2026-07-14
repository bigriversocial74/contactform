<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
$user=mg_require_api_user();
$pdo=mg_db();
if (strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='GET') {
    $status=(string)($_GET['status']??'all');
    mg_user_agent_api_run(static fn():array=>['plans'=>mg_personal_agent_plans($pdo,(int)$user['id'],$status,200)]);
}
mg_require_method('POST');
$input=mg_input();
mg_require_csrf_for_write($input);
mg_user_agent_api_run(static function() use($pdo,$user,$input):array {
    $action=mg_personal_agent_text($input['action']??'create',40);
    if ($action==='status') {
        return ['plan'=>mg_personal_agent_update_plan_status($pdo,(int)$user['id'],mg_personal_agent_text($input['plan_id']??'',80),mg_personal_agent_text($input['status']??'',30))];
    }
    return ['plan'=>mg_personal_agent_create_plan($pdo,(int)$user['id'],$input)];
});
