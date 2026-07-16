<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
mg_require_method('GET');
$user = mg_require_api_user();
mg_user_agent_api_run(static function () use ($user): array {
    $pdo = mg_db();
    if (function_exists('mg_personal_agent_dashboard_with_contact_intelligence')) {
        return mg_personal_agent_dashboard_with_contact_intelligence($pdo, (int) $user['id']);
    }
    return mg_personal_agent_dashboard($pdo, (int) $user['id']);
});
