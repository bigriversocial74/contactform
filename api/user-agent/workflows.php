<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
mg_require_method('GET');
$user=mg_require_permission('agent.personal.workflows.manage');
mg_user_agent_api_run(static fn():array=>mg_personal_workflows_dashboard(mg_db(),(int)$user['id']));
