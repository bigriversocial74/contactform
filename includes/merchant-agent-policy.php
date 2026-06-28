<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-memory.php';

function mg_agent_policy_actions(): array
{
    return ['create_campaign_draft','update_campaign_draft','pause_campaign','resume_campaign','create_reward_template_draft','update_reward_template_draft','create_crm_followup_task','create_message_draft','create_report_snapshot','create_merchant_alert','recommend_package_upgrade','recommend_location_fix','recommend_api_integration','recommend_claim_review','recommend_reward_optimization','recommend_campaign_optimization'];
}

function mg_agent_policy_defaults(): array
{
    return [
        'enabled' => true,
        'memory_learning_enabled' => true,
        'require_approval_all' => true,
        'admin_review_high_risk' => false,
        'auto_defer_high_risk' => true,
        'max_risk_level' => 'medium',
        'min_confidence' => 0.65,
        'allowed_action_keys' => mg_agent_policy_actions(),
        'avoid_action_keys' => [],
        'note' => 'Approval-first merchant agent policy.',
    ];
}

function mg_agent_policy_event(PDO $pdo, int $merchantId, string $type, array $ctx): string
{
    return mg_agent_memory_insert($pdo, $merchantId, $type, $ctx);
}

function mg_agent_policy_latest(PDO $pdo, int $merchantId): array
{
    try {
        $stmt = $pdo->prepare("SELECT event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type='merchant.agent_policy.saved' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return [];
        $ctx = mg_agent_memory_json($row['event_context_json'] ?? null);
        $ctx['created_at'] = $row['created_at'] ?? null;
        return $ctx;
    } catch (Throwable) {
        return [];
    }
}

function mg_agent_policy_bool(mixed $value): bool
{
    return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on';
}

function mg_agent_policy_sanitize(array $input): array
{
    $defaults = mg_agent_policy_defaults();
    $allowed = array_values(array_intersect(array_map('strval', $input['allowed_action_keys'] ?? $defaults['allowed_action_keys']), mg_agent_policy_actions()));
    if (!$allowed) $allowed = $defaults['allowed_action_keys'];
    $avoid = array_values(array_intersect(array_map('strval', $input['avoid_action_keys'] ?? []), mg_agent_policy_actions()));
    $risk = (string)($input['max_risk_level'] ?? $defaults['max_risk_level']);
    if (!in_array($risk, ['low','medium','high'], true)) $risk = 'medium';
    $confidence = max(0.0, min(1.0, (float)($input['min_confidence'] ?? $defaults['min_confidence'])));
    return [
        'enabled' => mg_agent_policy_bool($input['enabled'] ?? true),
        'memory_learning_enabled' => mg_agent_policy_bool($input['memory_learning_enabled'] ?? true),
        'require_approval_all' => true,
        'admin_review_high_risk' => mg_agent_policy_bool($input['admin_review_high_risk'] ?? false),
        'auto_defer_high_risk' => mg_agent_policy_bool($input['auto_defer_high_risk'] ?? true),
        'max_risk_level' => $risk,
        'min_confidence' => $confidence,
        'allowed_action_keys' => $allowed,
        'avoid_action_keys' => $avoid,
        'note' => mg_agent_memory_clean($input['note'] ?? 'Approval-first merchant agent policy.', 500),
    ];
}

function mg_agent_policy_save(PDO $pdo, int $merchantId, int $userId, array $input): array
{
    $policy = mg_agent_policy_sanitize($input['policy'] ?? $input);
    $policy['saved_by_user_id'] = $userId;
    mg_agent_policy_event($pdo, $merchantId, 'merchant.agent_policy.saved', $policy);
    mg_agent_policy_event($pdo, $merchantId, 'merchant.agent_policy.audit', ['action' => 'policy_saved', 'saved_by_user_id' => $userId, 'policy' => $policy]);
    return mg_agent_policy_response($pdo, $merchantId);
}

function mg_agent_policy_memory_events(PDO $pdo, int $merchantId): array
{
    $types = ['merchant.agent_memory.preference_removed','merchant.agent_memory.avoid_removed','merchant.agent_memory.reset','merchant.agent_memory.learning_paused','merchant.agent_memory.learning_resumed'];
    $in = implode(',', array_fill(0, count($types), '?'));
    try {
        $stmt = $pdo->prepare("SELECT event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type IN ({$in}) ORDER BY id DESC LIMIT 300");
        $stmt->execute(array_merge([$merchantId], $types));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

function mg_agent_policy_memory_filtered(PDO $pdo, int $merchantId): array
{
    $memory = mg_agent_memory_summary($pdo, $merchantId);
    $removedPrefs = [];
    $removedAvoid = [];
    $resetAt = null;
    $learningPaused = false;
    foreach (mg_agent_policy_memory_events($pdo, $merchantId) as $row) {
        $type = (string)($row['event_type'] ?? '');
        $ctx = mg_agent_memory_json($row['event_context_json'] ?? null);
        if ($type === 'merchant.agent_memory.reset' && $resetAt === null) $resetAt = $row['created_at'] ?? null;
        if ($type === 'merchant.agent_memory.preference_removed') $removedPrefs[(string)($ctx['label'] ?? $ctx['preference'] ?? '')] = true;
        if ($type === 'merchant.agent_memory.avoid_removed') $removedAvoid[(string)($ctx['action_key'] ?? '')] = true;
        if ($type === 'merchant.agent_memory.learning_paused') $learningPaused = true;
        if ($type === 'merchant.agent_memory.learning_resumed') $learningPaused = false;
    }
    $memory['preferences'] = array_values(array_filter($memory['preferences'], static fn(array $p): bool => empty($removedPrefs[(string)($p['label'] ?? '')])));
    $memory['avoid_actions'] = array_values(array_filter($memory['avoid_actions'], static fn(array $a): bool => empty($removedAvoid[(string)($a['action_key'] ?? '')])));
    $memory['reset_at'] = $resetAt;
    $memory['learning_paused'] = $learningPaused;
    return $memory;
}

function mg_agent_policy_memory_action(PDO $pdo, int $merchantId, int $userId, array $input): array
{
    $action = strtolower(mg_agent_memory_clean($input['action'] ?? '', 80));
    if ($action === 'remove_preference') {
        mg_agent_policy_event($pdo, $merchantId, 'merchant.agent_memory.preference_removed', ['label' => mg_agent_memory_clean($input['label'] ?? '', 220), 'saved_by_user_id' => $userId]);
    } elseif ($action === 'remove_avoid_action') {
        mg_agent_policy_event($pdo, $merchantId, 'merchant.agent_memory.avoid_removed', ['action_key' => mg_agent_memory_clean($input['action_key'] ?? '', 120), 'saved_by_user_id' => $userId]);
    } elseif ($action === 'reset_memory') {
        mg_agent_policy_event($pdo, $merchantId, 'merchant.agent_memory.reset', ['saved_by_user_id' => $userId, 'note' => 'Memory hidden from control center from this point forward.']);
    } elseif ($action === 'pause_learning') {
        mg_agent_policy_event($pdo, $merchantId, 'merchant.agent_memory.learning_paused', ['saved_by_user_id' => $userId]);
    } elseif ($action === 'resume_learning') {
        mg_agent_policy_event($pdo, $merchantId, 'merchant.agent_memory.learning_resumed', ['saved_by_user_id' => $userId]);
    } else {
        mg_fail('Unknown memory control action.', 422);
    }
    mg_agent_policy_event($pdo, $merchantId, 'merchant.agent_policy.audit', ['action' => $action, 'saved_by_user_id' => $userId]);
    return mg_agent_policy_response($pdo, $merchantId);
}

function mg_agent_policy_response(PDO $pdo, int $merchantId): array
{
    $policy = array_merge(mg_agent_policy_defaults(), array_intersect_key(mg_agent_policy_latest($pdo, $merchantId), mg_agent_policy_defaults()));
    $memory = mg_agent_policy_memory_filtered($pdo, $merchantId);
    return [
        'policy' => $policy,
        'memory' => $memory,
        'actions' => mg_agent_policy_actions(),
        'audit' => mg_agent_policy_audit($pdo, $merchantId),
        'rules' => [
            'high_risk' => $policy['admin_review_high_risk'] ? 'High-risk items should be escalated for admin review.' : 'High-risk items are deferred or require explicit merchant review.',
            'confidence' => 'Recommendations below confidence threshold should be de-prioritized or explained.',
            'approval' => 'All merchant agent actions remain approval-first.',
        ],
    ];
}

function mg_agent_policy_audit(PDO $pdo, int $merchantId): array
{
    try {
        $stmt = $pdo->prepare("SELECT event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND (event_type LIKE 'merchant.agent_policy.%' OR event_type LIKE 'merchant.agent_memory.%') ORDER BY id DESC LIMIT 25");
        $stmt->execute([$merchantId]);
        return array_map(static function (array $row): array {
            $ctx = mg_agent_memory_json($row['event_context_json'] ?? null);
            return ['event_type' => (string)$row['event_type'], 'title' => (string)($ctx['action'] ?? $ctx['feedback'] ?? $ctx['title'] ?? $row['event_type']), 'created_at' => $row['created_at'] ?? null, 'context' => $ctx];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable) {
        return [];
    }
}

function mg_agent_policy_prompt_context(PDO $pdo, int $merchantId): array
{
    $state = mg_agent_policy_response($pdo, $merchantId);
    return [
        'policy' => $state['policy'],
        'memory' => $state['memory'],
        'rules' => $state['rules'],
        'guidance' => 'Apply merchant policy before creating recommendation cards. Use only allowed_action_keys, avoid avoid_action_keys, cap risk to max_risk_level, and explain any low-confidence recommendation.',
    ];
}
