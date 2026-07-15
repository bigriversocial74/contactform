<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

mg_require_method('GET');
$user = mg_require_api_user();
mg_rate_limit('user_agent.ai_credits.read','user:' . (int)$user['id'],180,60);

mg_user_agent_api_run(static function () use ($user): array {
    return [
        'credits'=>mg_ai_credit_snapshot(mg_db(),(int)$user['id'],'anthropic'),
        'manage_url'=>'/account-subscriptions.php',
    ];
});
