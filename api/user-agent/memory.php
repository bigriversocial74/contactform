<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
$user=mg_require_api_user();
$pdo=mg_db();
if (strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='GET') {
    $include=filter_var($_GET['include_archived']??false,FILTER_VALIDATE_BOOLEAN);
    mg_user_agent_api_run(static fn():array=>['memory'=>mg_personal_agent_memory($pdo,(int)$user['id'],$include)]);
}
mg_require_method('POST');
$input=mg_input();
mg_require_csrf_for_write($input);
mg_user_agent_api_run(static function() use($pdo,$user,$input):array {
    $action=mg_personal_agent_text($input['action']??'save',40);
    if ($action==='archive') {
        return ['memory'=>mg_personal_agent_archive_memory($pdo,(int)$user['id'],mg_personal_agent_text($input['memory_id']??'',80))];
    }
    return ['memory'=>mg_personal_agent_save_memory($pdo,(int)$user['id'],$input)];
});
