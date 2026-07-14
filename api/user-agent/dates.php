<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
$user=mg_require_api_user();
$pdo=mg_db();
if (strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='GET') {
    $days=max(1,min(365,(int)($_GET['days']??120)));
    mg_user_agent_api_run(static fn():array=>['dates'=>mg_personal_agent_upcoming_dates($pdo,(int)$user['id'],$days,250)]);
}
mg_require_method('POST');
$input=mg_input();
mg_require_csrf_for_write($input);
mg_user_agent_api_run(static fn():array=>['date'=>mg_personal_agent_create_date($pdo,(int)$user['id'],$input)]);
