<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

mg_require_method('GET');
$user = mg_require_api_user();
mg_rate_limit('user_agent.ai_credits.read','user:' . (int)$user['id'],180,60);

mg_user_agent_api_run(static function () use ($user): array {
    $pdo = mg_db();
    $context = mg_ai_credit_package_context($pdo, (int)$user['id']);
    $credits = mg_personal_agent_ai_credit_apply_package_gate(
        mg_ai_credit_snapshot($pdo, (int)$user['id'], 'anthropic'),
        $context
    );

    return [
        'credits'=>$credits,
        'systematic_access'=>true,
        'ai_api_access'=>!empty($credits['can_use']),
        'manage_url'=>'/account-subscriptions.php',
    ];
});
