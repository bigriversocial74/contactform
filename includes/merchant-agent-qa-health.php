<?php
declare(strict_types=1);

require_once __DIR__ . '/ai/merchant-agent-chat-memory.php';
require_once __DIR__ . '/merchant-agent-policy.php';
require_once __DIR__ . '/merchant-agent-memory.php';
require_once __DIR__ . '/merchant-agent-notification-digest.php';
require_once __DIR__ . '/merchant-agent-approvals.php';
require_once __DIR__ . '/merchant-agent-execution.php';

function mg_agent_qa_check(string $key, string $label, string $status, string $detail = '', array $meta = []): array
{
    return ['key' => $key, 'label' => $label, 'status' => $status, 'detail' => $detail, 'meta' => $meta];
}

function mg_agent_qa_status(bool $ok, bool $warn = false): string
{
    if ($ok) return 'pass';
    return $warn ? 'warn' : 'fail';
}

function mg_agent_qa_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        try {
            $safeTable = str_replace(['\\', "'", '%', '_'], ['\\\\', "\\'", '\\%', '\\_'], $table);
            $stmt = $pdo->query("SHOW TABLES LIKE '" . $safeTable . "'");
            return $stmt ? (bool)$stmt->fetchColumn() : false;
        } catch (Throwable) {
            return false;
        }
    }
}

function mg_agent_qa_count(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function mg_agent_qa_latest_error(PDO $pdo, int $merchantId): array
{
    try {
        if (!mg_agent_qa_table_exists($pdo, 'campaign_events')) return [];
        $stmt = $pdo->prepare("SELECT event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND (event_type LIKE '%failed%' OR event_type LIKE '%error%') ORDER BY id DESC LIMIT 1");
        $stmt->execute([$merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return [];
        $ctx = mg_agent_memory_json($row['event_context_json'] ?? null);
        return ['event_type' => (string)$row['event_type'], 'created_at' => $row['created_at'] ?? null, 'context' => $ctx, 'summary' => (string)($ctx['result_summary'] ?? $ctx['error'] ?? $ctx['note'] ?? $row['event_type'])];
    } catch (Throwable) {
        return [];
    }
}

function mg_agent_qa_model_state(PDO $pdo): array
{
    try {
        if (!mg_agent_qa_table_exists($pdo, 'ai_models') || !mg_agent_qa_table_exists($pdo, 'ai_providers')) return [];
        $stmt = $pdo->query("SELECT p.provider_key,p.enabled provider_enabled,p.env_var_name,m.model_key,m.enabled model_enabled,m.is_default FROM ai_models m INNER JOIN ai_providers p ON p.id=m.provider_id WHERE p.provider_key='anthropic' ORDER BY m.is_default DESC,m.sort_order ASC LIMIT 5");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        return $rows ?: [];
    } catch (Throwable) {
        return [];
    }
}

function mg_agent_qa_health(PDO $pdo, array $user): array
{
    $merchantId = (int)$user['id'];
    $checks = [];

    $envKey = getenv('MG_ANTHROPIC_API_KEY') ?: ($_ENV['MG_ANTHROPIC_API_KEY'] ?? '');
    $checks[] = mg_agent_qa_check('anthropic_env', 'Anthropic API key', $envKey !== '' ? 'pass' : 'fail', $envKey !== '' ? 'MG_ANTHROPIC_API_KEY is present.' : 'MG_ANTHROPIC_API_KEY is missing.');

    $models = mg_agent_qa_model_state($pdo);
    $enabledModel = false;
    foreach ($models as $model) {
        if (!empty($model['provider_enabled']) && !empty($model['model_enabled'])) $enabledModel = true;
    }
    $checks[] = mg_agent_qa_check('claude_model', 'Claude model catalog', $enabledModel ? 'pass' : 'fail', $enabledModel ? 'Anthropic provider and model are enabled.' : 'No enabled Anthropic model found.', ['models' => $models]);

    $tables = ['campaign_events','ai_providers','ai_models','ai_merchant_plans','ai_merchant_plan_items'];
    $tableMeta = [];
    $tablesOk = true;
    foreach ($tables as $table) {
        $exists = mg_agent_qa_table_exists($pdo, $table);
        $tableMeta[$table] = $exists;
        if (!$exists) $tablesOk = false;
    }
    $checks[] = mg_agent_qa_check('stage_19c_tables', 'Stage 19C tables', $tablesOk ? 'pass' : 'fail', $tablesOk ? 'Required AI and event tables exist.' : 'One or more required AI/event tables are missing.', $tableMeta);

    $policy = mg_agent_policy_response($pdo, $merchantId);
    $checks[] = mg_agent_qa_check('policy_loaded', 'Agent policy loaded', !empty($policy['policy']) ? 'pass' : 'fail', !empty($policy['policy']) ? 'Policy response is available.' : 'Policy response failed.', ['policy' => $policy['policy'] ?? []]);

    $memory = mg_agent_memory_summary($pdo, $merchantId);
    $checks[] = mg_agent_qa_check('memory_loaded', 'Agent memory loaded', isset($memory['counts']) ? 'pass' : 'fail', isset($memory['counts']) ? 'Memory summary is available.' : 'Memory summary failed.', ['counts' => $memory['counts'] ?? []]);

    $review = mg_agent_approval_queue($pdo, $merchantId, ['limit' => 5]);
    $checks[] = mg_agent_qa_check('review_queue', 'Review queue available', isset($review['summary']) ? 'pass' : 'fail', isset($review['summary']) ? 'Review queue API can build queue state.' : 'Review queue failed.', ['summary' => $review['summary'] ?? []]);

    $execution = mg_agent_execution_queue($pdo, $merchantId, ['limit' => 5]);
    $checks[] = mg_agent_qa_check('execution_layer', 'Execution result layer', isset($execution['summary']) ? 'pass' : 'fail', isset($execution['summary']) ? 'Execution Center can build result state.' : 'Execution Center failed.', ['summary' => $execution['summary'] ?? []]);

    $digest = mg_agent_digest_response($pdo, $merchantId, 'all', 5);
    $checks[] = mg_agent_qa_check('notification_digest', 'Notification digest', isset($digest['counts']) ? 'pass' : 'fail', isset($digest['counts']) ? 'Digest counts and items are available.' : 'Digest failed.', ['counts' => $digest['counts'] ?? []]);

    $demoLocked = function_exists('mg_agent_cmd_is_super_admin') ? !mg_agent_cmd_is_super_admin($user) : true;
    $checks[] = mg_agent_qa_check('demo_permission', 'Demo permission lock', $demoLocked ? 'pass' : 'warn', $demoLocked ? 'Current user is not treated as super-admin for demo data.' : 'Current user has super-admin demo access.', ['can_demo' => !$demoLocked]);

    $chatMessages = mg_agent_qa_table_exists($pdo, 'campaign_events') ? mg_agent_qa_count($pdo, "SELECT COUNT(*) FROM campaign_events WHERE merchant_user_id=? AND event_type IN ('merchant.agent_chat.user','merchant.agent_chat.assistant')", [$merchantId]) : 0;
    $reviewItems = (mg_agent_qa_table_exists($pdo, 'ai_merchant_plan_items') && mg_agent_qa_table_exists($pdo, 'ai_merchant_plans')) ? mg_agent_qa_count($pdo, "SELECT COUNT(*) FROM ai_merchant_plan_items i INNER JOIN ai_merchant_plans p ON p.id=i.plan_id WHERE p.merchant_user_id=?", [$merchantId]) : 0;
    $executedItems = (mg_agent_qa_table_exists($pdo, 'ai_merchant_plan_items') && mg_agent_qa_table_exists($pdo, 'ai_merchant_plans')) ? mg_agent_qa_count($pdo, "SELECT COUNT(*) FROM ai_merchant_plan_items i INNER JOIN ai_merchant_plans p ON p.id=i.plan_id WHERE p.merchant_user_id=? AND i.status='executed'", [$merchantId]) : 0;
    $latestError = mg_agent_qa_latest_error($pdo, $merchantId);

    $fail = count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'fail'));
    $warn = count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'warn'));
    $pass = count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'pass'));
    $score = (int)round(($pass / max(1, count($checks))) * 100);

    return [
        'score' => $score,
        'status' => $fail > 0 ? 'fail' : ($warn > 0 ? 'warn' : 'pass'),
        'summary' => ['pass' => $pass, 'warn' => $warn, 'fail' => $fail, 'total' => count($checks)],
        'checks' => $checks,
        'counts' => ['chat_messages' => $chatMessages, 'review_items' => $reviewItems, 'executed_items' => $executedItems, 'digest_unread' => (int)($digest['counts']['unread_agent_notifications'] ?? 0)],
        'latest_error' => $latestError,
        'quick_links' => [
            ['label' => 'Agent Chat', 'url' => '/merchant-agent-chat.php'],
            ['label' => 'Review Queue', 'url' => '/merchant-agent-approvals.php'],
            ['label' => 'Execution Center', 'url' => '/merchant-agent-execution.php'],
            ['label' => 'Notifications', 'url' => '/merchant-notifications.php'],
            ['label' => 'Controls', 'url' => '/merchant-automation.php'],
        ],
    ];
}
