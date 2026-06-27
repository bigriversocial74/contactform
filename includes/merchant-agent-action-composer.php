<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-growth-plan.php';

function mg_agent_composer_event_types(): array
{
    return ['crm.agent.composer.draft_created','crm.agent.composer.submitted_for_review','crm.agent.composer.message_seeded','crm.agent.composer.followup_seeded'];
}

function mg_agent_composer_action_types(): array
{
    return ['review_queue_action','message_draft','followup_task','campaign_repeat','customer_reactivation'];
}

function mg_agent_composer_id(int $merchantId, string $sourceId, string $actionType): string
{
    return 'aac_' . substr(hash('sha256', $merchantId . '|composer|' . $sourceId . '|' . $actionType), 0, 24);
}

function mg_agent_composer_status_from_event(string $eventType): string
{
    $map = [
        'crm.agent.composer.draft_created' => 'draft_created',
        'crm.agent.composer.submitted_for_review' => 'submitted_for_review',
        'crm.agent.composer.message_seeded' => 'message_seeded',
        'crm.agent.composer.followup_seeded' => 'followup_seeded',
    ];
    return $map[$eventType] ?? 'ready';
}

function mg_agent_composer_latest_events(PDO $pdo, int $merchantId): array
{
    $types = mg_agent_composer_event_types();
    $in = implode(',', array_fill(0, count($types), '?'));
    $stmt = $pdo->prepare("SELECT public_id,event_type,event_context_json,campaign_id,contact_id,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type IN ({$in}) ORDER BY created_at DESC,id DESC LIMIT 300");
    $stmt->execute(array_merge([$merchantId], $types));
    $latest = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = mg_automation_json($row['event_context_json'] ?? null);
        $composerId = (string)($ctx['composer_draft_id'] ?? '');
        if ($composerId === '' || isset($latest[$composerId])) continue;
        $latest[$composerId] = [
            'event_id' => (string)$row['public_id'],
            'event_type' => (string)$row['event_type'],
            'status' => mg_agent_composer_status_from_event((string)$row['event_type']),
            'created_at' => $row['created_at'] ?? null,
            'campaign_id' => $row['campaign_id'] !== null ? (int)$row['campaign_id'] : null,
            'contact_id' => $row['contact_id'] !== null ? (int)$row['contact_id'] : null,
            'context' => $ctx,
        ];
    }
    return $latest;
}

function mg_agent_composer_action_type_for_growth(array $action): string
{
    $type = (string)($action['type'] ?? '');
    if ($type === 'customer') return 'customer_reactivation';
    if ($type === 'campaign') return 'campaign_repeat';
    if ($type === 'playbook') return 'review_queue_action';
    if ($type === 'starter') return 'message_draft';
    return 'review_queue_action';
}

function mg_agent_composer_source_item(int $merchantId, array $action, int $index, ?array $latest): array
{
    $sourceId = 'growth_' . substr(hash('sha256', (string)($action['type'] ?? '') . '|' . (string)($action['label'] ?? '') . '|' . $index), 0, 18);
    $actionType = mg_agent_composer_action_type_for_growth($action);
    $composerId = mg_agent_composer_id($merchantId, $sourceId, $actionType);
    $latestCtx = $latest && is_array($latest['context'] ?? null) ? $latest['context'] : [];
    $targetLabel = (string)($action['label'] ?? 'Growth planner action');
    return [
        'composer_draft_id' => $composerId,
        'source_recommendation_id' => $sourceId,
        'source' => 'growth_planner',
        'status' => $latest ? (string)$latest['status'] : 'ready',
        'action_type' => (string)($latestCtx['action_type'] ?? $actionType),
        'target_type' => (string)($latestCtx['target_type'] ?? $action['type'] ?? 'growth_action'),
        'target_label' => (string)($latestCtx['target_label'] ?? $targetLabel),
        'title' => (string)($latestCtx['title'] ?? $action['title'] ?? 'Compose merchant action'),
        'body' => (string)($latestCtx['body'] ?? $action['body'] ?? 'Prepare this action for merchant review.'),
        'expected_claims' => (int)($latestCtx['expected_claims'] ?? $action['expected_claims'] ?? 0),
        'expected_revenue_cents' => (int)($latestCtx['expected_revenue_cents'] ?? $action['expected_revenue_cents'] ?? 0),
        'expected_psr_impact_cents' => (int)($latestCtx['expected_psr_impact_cents'] ?? $action['expected_psr_impact_cents'] ?? 0),
        'required_messages' => (int)($latestCtx['required_messages'] ?? $action['required_messages'] ?? 0),
        'required_followups' => (int)($latestCtx['required_followups'] ?? $action['required_followups'] ?? 0),
        'merchant_note' => (string)($latestCtx['merchant_note'] ?? ''),
        'message_body' => (string)($latestCtx['message_body'] ?? mg_agent_composer_default_message($action)),
        'created_at' => $latest['created_at'] ?? null,
        'latest_event' => $latest,
        'review_queue_url' => '/merchant-agent-approvals.php',
        'message_outbox_url' => '/merchant-agent-messages.php',
        'followups_url' => '/merchant-followups.php',
    ];
}

function mg_agent_composer_default_message(array $action): string
{
    $label = trim((string)($action['label'] ?? ''));
    $title = trim((string)($action['title'] ?? ''));
    $base = $label !== '' ? $label : ($title !== '' ? $title : 'this offer');
    return 'Hi — we have a quick local reward related to ' . $base . '. Reply or visit us to claim it while it is available.';
}

function mg_agent_composer_queue(PDO $pdo, int $merchantId, array $input = []): array
{
    $filter = strtolower(trim((string)($input['filter'] ?? 'all')));
    $limit = max(1, min(100, (int)($input['limit'] ?? 50)));
    $plan = mg_agent_growth_plan($pdo, $merchantId, [
        'goal' => (string)($input['goal'] ?? 'revenue'),
        'timeframe' => (int)($input['timeframe'] ?? 90),
        'risk' => (string)($input['risk'] ?? 'balanced'),
        'effort' => (string)($input['effort'] ?? 'medium'),
    ]);
    $latest = mg_agent_composer_latest_events($pdo, $merchantId);
    $items = [];
    foreach (($plan['recommended_agent_actions'] ?? []) as $index => $action) {
        $sourceId = 'growth_' . substr(hash('sha256', (string)($action['type'] ?? '') . '|' . (string)($action['label'] ?? '') . '|' . $index), 0, 18);
        $actionType = mg_agent_composer_action_type_for_growth($action);
        $composerId = mg_agent_composer_id($merchantId, $sourceId, $actionType);
        $item = mg_agent_composer_source_item($merchantId, $action, (int)$index, $latest[$composerId] ?? null);
        if ($filter !== '' && $filter !== 'all' && (string)$item['status'] !== $filter && (string)$item['action_type'] !== $filter) continue;
        $items[] = $item;
    }
    $summary = ['total' => count($items), 'ready' => 0, 'draft_created' => 0, 'submitted_for_review' => 0, 'message_seeded' => 0, 'followup_seeded' => 0];
    foreach ($items as $item) {
        $status = (string)($item['status'] ?? 'ready');
        if (isset($summary[$status])) $summary[$status]++;
    }
    return [
        'items' => array_slice($items, 0, $limit),
        'summary' => $summary,
        'filters' => ['all','ready','draft_created','submitted_for_review','message_seeded','followup_seeded','review_queue_action','message_draft','followup_task','campaign_repeat','customer_reactivation'],
        'plan_summary' => $plan['summary'] ?? [],
        'links' => [
            'growth_planner' => '/merchant-agent-growth-plan.php',
            'review_queue' => '/merchant-agent-approvals.php',
            'message_outbox' => '/merchant-agent-messages.php',
            'followups' => '/merchant-followups.php',
        ],
    ];
}

function mg_agent_composer_find_item(PDO $pdo, int $merchantId, string $composerDraftId): ?array
{
    foreach (mg_agent_composer_queue($pdo, $merchantId, ['limit' => 100])['items'] as $item) {
        if ((string)$item['composer_draft_id'] === $composerDraftId) return $item;
    }
    return null;
}

function mg_agent_composer_record(PDO $pdo, int $merchantId, string $eventType, array $item, array $extra = []): string
{
    $context = array_merge([
        'composer_draft_id' => (string)$item['composer_draft_id'],
        'source_recommendation_id' => (string)$item['source_recommendation_id'],
        'source' => (string)$item['source'],
        'action_type' => (string)$item['action_type'],
        'target_type' => (string)$item['target_type'],
        'target_label' => (string)$item['target_label'],
        'title' => (string)$item['title'],
        'body' => (string)$item['body'],
        'expected_claims' => (int)$item['expected_claims'],
        'expected_revenue_cents' => (int)$item['expected_revenue_cents'],
        'expected_psr_impact_cents' => (int)$item['expected_psr_impact_cents'],
        'required_messages' => (int)$item['required_messages'],
        'required_followups' => (int)$item['required_followups'],
        'merchant_approval_required' => true,
        'guardrail_applied' => 'Merchant must review composed agent work before execution or customer communication.',
    ], $extra);
    return mg_automation_record_event($pdo, $merchantId, $eventType, $context, null, null);
}

function mg_agent_composer_message_item(array $item, array $extra): array
{
    $messageBody = trim((string)($extra['message_body'] ?? $item['message_body'] ?? ''));
    if ($messageBody === '') $messageBody = mg_agent_composer_default_message($item);
    return [
        'message_draft_id' => 'amd_' . substr(hash('sha256', (string)$item['composer_draft_id'] . '|message'), 0, 24),
        'execution_id' => '',
        'approval_id' => (string)$item['composer_draft_id'],
        'playbook_key' => (string)$item['action_type'],
        'playbook_title' => (string)$item['title'],
        'customer_name' => '',
        'customer_email' => '',
        'campaign_title' => (string)$item['target_label'],
        'message_body' => $messageBody,
        'guardrail_applied' => 'Seeded from Action Composer. Merchant must approve final communication.',
        'campaign_id' => null,
        'contact_id' => null,
    ];
}

function mg_agent_composer_perform(PDO $pdo, int $merchantId, int $actorId, array $item, string $action, array $input = []): array
{
    $action = strtolower(trim($action));
    $allowed = ['create_draft','submit_for_review','seed_message','seed_followup'];
    if (!in_array($action, $allowed, true)) return ['status' => 'skipped', 'reason' => 'unknown_action'];
    $note = trim((string)($input['merchant_note'] ?? $input['note'] ?? ''));
    $messageBody = trim((string)($input['message_body'] ?? $item['message_body'] ?? ''));
    $baseExtra = [
        'decided_by_user_id' => $actorId,
        'merchant_note' => $note,
        'message_body' => $messageBody,
    ];
    if ($action === 'create_draft') {
        $eventId = mg_agent_composer_record($pdo, $merchantId, 'crm.agent.composer.draft_created', $item, $baseExtra);
        return ['status' => 'draft_created', 'event_id' => $eventId];
    }
    if ($action === 'submit_for_review') {
        $eventId = mg_agent_composer_record($pdo, $merchantId, 'crm.agent.composer.submitted_for_review', $item, $baseExtra);
        return ['status' => 'submitted_for_review', 'event_id' => $eventId, 'review_queue_url' => '/merchant-agent-approvals.php'];
    }
    if ($action === 'seed_message') {
        $eventId = mg_agent_composer_record($pdo, $merchantId, 'crm.agent.composer.message_seeded', $item, $baseExtra);
        $messageItem = mg_agent_composer_message_item($item, $baseExtra);
        $messageEventId = mg_automation_record_event($pdo, $merchantId, 'crm.agent.message.draft.created', [
            'message_draft_id' => (string)$messageItem['message_draft_id'],
            'execution_id' => '',
            'approval_id' => (string)$item['composer_draft_id'],
            'playbook_key' => (string)$messageItem['playbook_key'],
            'playbook_title' => (string)$messageItem['playbook_title'],
            'customer_name' => '',
            'customer_email' => '',
            'campaign_title' => (string)$messageItem['campaign_title'],
            'draft_body' => (string)$messageItem['message_body'],
            'message_body' => (string)$messageItem['message_body'],
            'why' => (string)$item['body'],
            'guardrail_applied' => (string)$messageItem['guardrail_applied'],
            'expected_action' => 'Review and approve this composer-seeded message before sending.',
            'composer_draft_id' => (string)$item['composer_draft_id'],
            'decided_by_user_id' => $actorId,
            'merchant_note' => $note,
        ], null, null);
        return ['status' => 'message_seeded', 'event_id' => $eventId, 'message_event_id' => $messageEventId, 'message_draft_id' => $messageItem['message_draft_id'], 'message_outbox_url' => '/merchant-agent-messages.php'];
    }
    $eventId = mg_agent_composer_record($pdo, $merchantId, 'crm.agent.composer.followup_seeded', $item, $baseExtra);
    return ['status' => 'followup_seeded', 'event_id' => $eventId, 'followups_url' => '/merchant-followups.php'];
}

function mg_agent_composer_approval_items(PDO $pdo, int $merchantId): array
{
    $items = [];
    foreach (mg_agent_composer_latest_events($pdo, $merchantId) as $composerId => $latest) {
        if ((string)($latest['event_type'] ?? '') !== 'crm.agent.composer.submitted_for_review') continue;
        $ctx = is_array($latest['context'] ?? null) ? $latest['context'] : [];
        $approvalId = 'agq_' . substr(hash('sha256', $merchantId . '|composer|' . $composerId), 0, 24);
        $items[] = [
            'approval_id' => $approvalId,
            'source_type' => 'composer',
            'source_id' => (string)$composerId,
            'playbook_key' => (string)($ctx['action_type'] ?? 'composer_action'),
            'playbook_title' => (string)($ctx['title'] ?? 'Composed agent action'),
            'customer_name' => '',
            'customer_email' => '',
            'campaign_title' => (string)($ctx['target_label'] ?? ''),
            'why' => (string)($ctx['body'] ?? 'Action Composer submitted this item for merchant review.'),
            'guardrail_applied' => (string)($ctx['guardrail_applied'] ?? 'Merchant approval is required.'),
            'expected_action' => (string)($ctx['action_type'] ?? 'review_queue_action'),
            'recommended_next_action' => 'Review composed agent action.',
            'risk_level' => 'medium',
            'merchant_approval_required' => true,
            'can_create_task' => true,
            'action_type' => (string)($ctx['action_type'] ?? 'review_queue_action'),
            'status' => 'pending',
            'customer_url' => '/merchant-customer.php?tab=timeline',
            'message_url' => '/merchant-agent-messages.php',
            'reward_url' => '/merchant-campaigns.php',
        ];
    }
    return $items;
}
