<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
mg_user_agent_api_run(static fn():array=>mg_personal_agent_chat_v2(mg_db(),(int)$user['id'],$input));
