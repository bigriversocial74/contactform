<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-crm-playbooks.php';

function mg_automation_levels(): array
{
    return [
        'monitor_only' => ['key' => 'monitor_only', 'title' => 'Monitor only', 'description' => 'Automation watches data and records activity, but does not recommend or act.'],
        'recommend_action' => ['key' => 'recommend_action', 'title' => 'Recommend action', 'description' => 'Automation recommends a next action for merchant review.'],
        'create_task' => ['key' => 'create_task', 'title' => 'Create task', 'description' => 'Automation can create follow-up tasks inside the merchant task center.'],
        'draft_message' => ['key' => 'draft_message', 'title' => 'Draft message', 'description' => 'Automation can draft messages for merchant approval before send.'],
        'execute_with_approval' => ['key' => 'execute_with_approval', 'title' => 'Execute with approval', 'description' => 'Automation can execute approved actions after a merchant click.'],
        'fully_automated_later' => ['key' => 'fully_automated_later', 'title' => 'Fully automated later', 'description' => 'Reserved for future agent-managed execution after stronger controls exist.'],
    ];
}

function mg_agent_autonomy_levels(): array
{
    return [
        'advisory' => [
            'key' => 'advisory',
            'rank' => 10,
            'title' => 'Advisory only',
            'description' => 'Agent can analyze and recommend. No drafts, tasks, queue changes, or execution without a user action.',
        ],
        'review_queue' => [
            'key' => 'review_queue',
            'rank' => 20,
            'title' => 'Controlled review',
            'description' => 'Agent can create review-ready cards and queue items. Merchant approval is still required before execution.',
        ],
        'approval_first' => [
            'key' => 'approval_first',
            'rank' => 30,
            'title' => 'Approval first',
            'description' => 'Agent can prepare drafts, tasks, and execution plans, but every action remains approval-gated.',
        ],
        'trusted_autopilot' => [
            'key' => 'trusted_autopilot',
            'rank' => 40,
            'title' => 'Trusted autopilot',
            'description' => 'Reserved for future limited auto-execution with budgets, risk caps, audit logs, and admin ceiling approval.',
        ],
    ];
}

function mg_agent_autonomy_rank(string $level): int
{
    $levels = mg_agent_autonomy_levels();
    return (int)($levels[$level]['rank'] ?? 0);
}

function mg_agent_autonomy_platform_ceiling(): string
{
    $configured = trim((string)(getenv('MG_AGENT_AUTONOMY_PLATFORM_CEILING') ?: ''));
    $levels = mg_agent_autonomy_levels();
    return isset($levels[$configured]) ? $configured : 'approval_first';
}

function mg_agent_autonomy_normalize(mixed $raw): array
{
    $levels = mg_agent_autonomy_levels();
    $platformCeiling = mg_agent_autonomy_platform_ceiling();
    $default = [
        'merchant_level' => 'review_queue',
        'platform_ceiling' => $platformCeiling,
        'effective_level' => 'review_queue',
        'allow_message_drafts' => true,
        'allow_task_creation' => true,
        'allow_review_queue' => true,
        'allow_execution_without_approval' => false,
        'daily_action_budget' => 10,
        'high_risk_requires_approval' => true,
        'updated_at' => null,
    ];
    $incoming = is_array($raw) ? $raw : [];
    $merchantLevel = (string)($incoming['merchant_level'] ?? $incoming['level'] ?? $default['merchant_level']);
    if (!isset($levels[$merchantLevel])) $merchantLevel = $default['merchant_level'];
    $effective = mg_agent_autonomy_rank($merchantLevel) > mg_agent_autonomy_rank($platformCeiling) ? $platformCeiling : $merchantLevel;
    return array_merge($default, [
        'merchant_level' => $merchantLevel,
        'platform_ceiling' => $platformCeiling,
        'effective_level' => $effective,
        'allow_message_drafts' => mg_automation_bool($incoming['allow_message_drafts'] ?? $default['allow_message_drafts'], true),
        'allow_task_creation' => mg_automation_bool($incoming['allow_task_creation'] ?? $default['allow_task_creation'], true),
        'allow_review_queue' => mg_automation_bool($incoming['allow_review_queue'] ?? $default['allow_review_queue'], true),
        'allow_execution_without_approval' => false,
        'daily_action_budget' => max(0, min(100, (int)($incoming['daily_action_budget'] ?? $default['daily_action_budget']))),
        'high_risk_requires_approval' => true,
        'updated_at' => $incoming['updated_at'] ?? null,
    ]);
}

function mg_automation_bool(mixed $value, bool $default = false): bool
{
    if (is_bool($value)) return $value;
    if (is_numeric($value)) return (int)$value === 1;
    if (is_string($value)) return in_array(strtolower(trim($value)), ['1','true','yes','on','enabled'], true);
    return $default;
}

function mg_automation_default_settings(): array
{
    $settings = [];
    foreach (mg_crm_playbook_defs() as $key => $def) {
        $taskReady = (string)($def['action_type'] ?? '') === 'create_followup_task';
        $draftReady = (string)($def['action_type'] ?? '') === 'message_draft';
        $settings[$key] = [
            'playbook_key' => $key,
            'playbook_title' => (string)$def['title'],
            'enabled' => true,
            'automation_level' => $taskReady ? 'create_task' : ($draftReady ? 'draft_message' : 'recommend_action'),
            'require_approval' => true,
            'auto_create_followups' => $taskReady,
            'auto_draft_messages' => $draftReady,
            'max_actions_per_day' => 5,
            'agent_can_monitor' => true,
            'agent_can_recommend' => true,
            'agent_can_create_task' => $taskReady,
            'agent_requires_approval' => true,
        ];
    }
    return $settings;
}

function mg_automation_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_automation_normalize_settings(array $raw): array
{
    $defaults = mg_automation_default_settings();
    $levels = array_keys(mg_automation_levels());
    $out = [];
    foreach ($defaults as $key => $default) {
        $incoming = $raw[$key] ?? [];
        if (!is_array($incoming)) $incoming = [];
        $level = (string)($incoming['automation_level'] ?? $default['automation_level']);
        if (!in_array($level, $levels, true)) $level = (string)$default['automation_level'];
        $max = max(0, min(100, (int)($incoming['max_actions_per_day'] ?? $default['max_actions_per_day'])));
        $out[$key] = array_merge($default, [
            'enabled' => mg_automation_bool($incoming['enabled'] ?? $default['enabled'], (bool)$default['enabled']),
            'automation_level' => $level,
            'require_approval' => mg_automation_bool($incoming['require_approval'] ?? $default['require_approval'], true),
            'auto_create_followups' => mg_automation_bool($incoming['auto_create_followups'] ?? $default['auto_create_followups'], (bool)$default['auto_create_followups']),
            'auto_draft_messages' => mg_automation_bool($incoming['auto_draft_messages'] ?? $default['auto_draft_messages'], (bool)$default['auto_draft_messages']),
            'max_actions_per_day' => $max,
            'agent_can_monitor' => mg_automation_bool($incoming['agent_can_monitor'] ?? $default['agent_can_monitor'], true),
            'agent_can_recommend' => mg_automation_bool($incoming['agent_can_recommend'] ?? $default['agent_can_recommend'], true),
            'agent_can_create_task' => mg_automation_bool($incoming['agent_can_create_task'] ?? $default['agent_can_create_task'], false),
            'agent_requires_approval' => mg_automation_bool($incoming['agent_requires_approval'] ?? $default['agent_requires_approval'], true),
        ]);
    }
    return $out;
}

function mg_automation_current_settings(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare("SELECT event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type='crm.automation.settings.updated' ORDER BY created_at DESC,id DESC LIMIT 1");
    $stmt->execute([$merchantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $context = mg_automation_json($row['event_context_json'] ?? null);
    $settings = mg_automation_normalize_settings($context['settings'] ?? []);
    $autonomy = mg_agent_autonomy_normalize($context['agent_autonomy'] ?? []);
    if (!empty($row['created_at'])) $autonomy['updated_at'] = $row['created_at'];
    return ['settings' => $settings, 'agent_autonomy' => $autonomy, 'updated_at' => $row['created_at'] ?? null, 'source' => $row ? 'merchant_saved' : 'defaults'];
}

function mg_automation_settings_summary(array $settings): array
{
    $summary = ['total' => count($settings), 'enabled' => 0, 'approval_required' => 0, 'task_creation' => 0, 'message_drafts' => 0, 'agent_create_task' => 0];
    foreach ($settings as $setting) {
        if (!empty($setting['enabled'])) $summary['enabled']++;
        if (!empty($setting['require_approval']) || !empty($setting['agent_requires_approval'])) $summary['approval_required']++;
        if (!empty($setting['auto_create_followups'])) $summary['task_creation']++;
        if (!empty($setting['auto_draft_messages'])) $summary['message_drafts']++;
        if (!empty($setting['agent_can_create_task'])) $summary['agent_create_task']++;
    }
    return $summary;
}

function mg_automation_save_settings(PDO $pdo, int $merchantId, int $actorId, array $settings, array $agentAutonomy = []): string
{
    $eventId = mg_crm_playbook_uuid();
    $context = [
        'settings' => $settings,
        'agent_autonomy' => mg_agent_autonomy_normalize($agentAutonomy),
        'updated_by_user_id' => $actorId,
        'guardrail_version' => 2,
        'agent_permission_model' => true,
    ];
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')->execute([$eventId, $merchantId, null, null, 'crm.automation.settings.updated', json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    return $eventId;
}

function mg_automation_record_event(PDO $pdo, int $merchantId, string $eventType, array $context, ?int $campaignId = null, ?int $contactId = null): string
{
    $eventId = mg_crm_playbook_uuid();
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')->execute([$eventId, $merchantId, $campaignId, $contactId, $eventType, json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    return $eventId;
}

function mg_automation_log_event_types(): array
{
    return [
        'crm.playbook.triggered',
        'crm.followup.created',
        'crm.automation.settings.updated',
        'crm.automation.approval.granted',
        'crm.automation.approval.rejected',
        'crm.automation.message.drafted',
        'crm.agent.message.draft.created',
        'merchant.ai_plan_item.approved',
        'merchant.ai_plan_item.deferred',
        'merchant.ai_plan_item.rejected',
        'merchant.ai_plan_item.executed',
        'merchant.ai.alert.created',
        'merchant.ai.recommendation.package_upgrade',
        'merchant.ai.recommendation.location_fix',
        'merchant.ai.recommendation.api_integration',
        'merchant.ai.recommendation.claim_review',
        'merchant.ai.recommendation.reward_optimization',
        'merchant.ai.recommendation.campaign_optimization',
    ];
}

function mg_automation_log(PDO $pdo, int $merchantId, int $limit = 50): array
{
    $limit = max(1, min(150, $limit));
    $types = mg_automation_log_event_types();
    $in = implode(',', array_fill(0, count($types), '?'));
    $stmt = $pdo->prepare("SELECT ce.public_id,ce.event_type,ce.event_context_json,ce.created_at,c.title campaign_title,cc.public_id contact_public_id,cc.email contact_email,cc.name contact_name FROM campaign_events ce LEFT JOIN campaigns c ON c.id=ce.campaign_id AND c.merchant_user_id=ce.merchant_user_id LEFT JOIN campaign_contacts cc ON cc.id=ce.contact_id AND cc.merchant_user_id=ce.merchant_user_id WHERE ce.merchant_user_id=? AND ce.event_type IN ({$in}) ORDER BY ce.created_at DESC,ce.id DESC LIMIT {$limit}");
    $stmt->execute(array_merge([$merchantId], $types));
    return array_map(static function (array $row): array {
        $ctx = mg_automation_json($row['event_context_json'] ?? null);
        $playbook = (string)($ctx['playbook_title'] ?? $ctx['title'] ?? $ctx['action_key'] ?? $ctx['playbook_key'] ?? 'Automation');
        $type = (string)$row['event_type'];
        $label = match ($type) {
            'crm.automation.settings.updated' => 'Settings updated',
            'crm.automation.approval.granted', 'merchant.ai_plan_item.approved' => 'Merchant approved action',
            'crm.automation.approval.rejected', 'merchant.ai_plan_item.rejected' => 'Merchant rejected action',
            'merchant.ai_plan_item.deferred' => 'Merchant deferred action',
            'merchant.ai_plan_item.executed' => 'AI recommendation created resource',
            'crm.automation.message.drafted', 'crm.agent.message.draft.created' => 'Message drafted',
            'crm.followup.created' => 'Task created',
            'crm.playbook.triggered' => 'Playbook triggered',
            'merchant.ai.alert.created' => 'Merchant alert created',
            'merchant.ai.recommendation.package_upgrade' => 'Package recommendation recorded',
            'merchant.ai.recommendation.location_fix' => 'Location recommendation recorded',
            'merchant.ai.recommendation.api_integration' => 'API recommendation recorded',
            'merchant.ai.recommendation.claim_review' => 'Claim review recommendation recorded',
            'merchant.ai.recommendation.reward_optimization' => 'Reward recommendation recorded',
            'merchant.ai.recommendation.campaign_optimization' => 'Campaign recommendation recorded',
            default => $type,
        };
        return [
            'id' => (string)$row['public_id'],
            'event_type' => $type,
            'label' => $label,
            'playbook' => $playbook,
            'customer_name' => (string)($row['contact_name'] ?? ''),
            'customer_email' => (string)($row['contact_email'] ?? ''),
            'campaign_title' => (string)($row['campaign_title'] ?? ''),
            'automation_level' => (string)($ctx['automation_level'] ?? ''),
            'agent_autonomy' => $ctx['agent_autonomy'] ?? null,
            'status' => (string)($ctx['status'] ?? $ctx['action_status'] ?? 'recorded'),
            'requires_approval' => !empty($ctx['requires_approval']) || !empty($ctx['agent_requires_approval']) || !empty($ctx['merchant_approval_required']),
            'created_at' => $row['created_at'] ?? null,
            'context' => $ctx,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}
