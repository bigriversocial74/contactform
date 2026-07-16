<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

mg_require_method('GET');
$user = mg_require_api_user();

mg_user_agent_api_run(static function () use ($user): array {
    $pdo = mg_db();
    $state = strtolower(trim((string)($_GET['state'] ?? 'saved')));
    $items = mg_personal_agent_opportunity_list($pdo,(int)$user['id'],$state,(int)($_GET['limit'] ?? 50));
    return [
        'items'=>$items,
        'state'=>$state,
        'count'=>count($items),
        'schema_ready'=>mg_personal_agent_opportunity_schema_ready($pdo),
    ];
});
