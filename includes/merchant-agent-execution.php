<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-approvals.php';

function mg_agent_execution_event_types(): array
{
    return ['crm.agent.execution.started','crm.agent.execution.completed','crm.agent.execution.failed','crm.agent.execution.skipped','crm.agent.message.draft.created'];
}

function mg_agent_execution_source_types(): array
{
    return ['crm.agent.approval.approved','crm.agent.approval.task_created'];
}

function mg_agent_execution_id(int $merchantId, string $approvalId): string
{
    return 'age_' . substr(hash('sha256', $merchantId . '|execution|' . $approvalId), 0, 24);
}

function mg_agent_execution_json_context($value): array
{
    return mg_automation_json($value);
}

function mg_agent_execution_latest_events(PDO $pdo, int $merchantId): array
{
    $types = mg_agent_execution_event_types();
    $in = implode(',', array_fill(0, count($types), '?'));
    $stmt = $pdo->prepare("SELECT public_id,event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type IN ({$in}) ORDER BY created_at DESC,id DESC LIMIT 300");
    $stmt->execute(array_merge([$merchantId], $types));
    $latest = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = mg_agent_execution_json_context($row['event_context_json'] ?? null);
        $approvalId = (string)($ctx['approval_id'] ?? '');
        if ($approvalId === '' || isset($latest[$approvalId])) continue;
        $latest[$approvalId] = [
            'event_id' => (string)$row['public_id'],
            'event_type' => (string)$row['event_type'],
            'created_at' => $row['created_at'] ?? null,
            'context' => $ctx,
        ];
    }
    return $latest;
}

function mg_agent_execution_source_events(PDO $pdo, int $merchantId): array
{
    $types = mg_agent_execution_source_types();
    $in = implode(',', array_fill(0, count($types), '?'));
    $stmt = $pdo->prepare("SELECT public_id,event_type,event_context_json,campaign_id,contact_id,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type IN ({$in}) ORDER BY created_at DESC,id DESC LIMIT 250");
    $stmt->execute(array_merge([$merchantId], $types));
    $rows = [];
    $seen = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = mg_agent_execution_json_context($row['event_context_json'] ?? null);
        $approvalId = (string)($ctx['approval_id'] ?? '');
        if ($approvalId === '' || isset($seen[$approvalId])) continue;
        $seen[$approvalId] = true;
        $rows[] = [
            'event_id' => (string)$row['public_id'],
            'event_type' => (string)$row['event_type'],
            'campaign_id' => $row['campaign_id'] !== null ? (int)$row['campaign_id'] : null,
            'contact_id' => $row['contact_id'] !== null ? (int)$row['contact_id'] : null,
            'created_at' => $row['created_at'] ?? null,
            'context' => $ctx,
        ];
    }
    return $rows;
}

function mg_agent_execution_state(array $source, ?array $latest): string
{
    if ((string)$source['event_type'] === 'crm.agent.approval.task_created') return 'completed';
    if (!$latest) return 'approved_not_executed';
    $type = (string)($latest['event_type'] ?? '');
    if ($type === 'crm.agent.execution.started') return 'executing';
    if ($type === 'crm.agent.execution.completed' || $type === 'crm.agent.message.draft.created') return 'completed';
    if ($type === 'crm.agent.execution.failed') return 'failed';
    if ($type === 'crm.agent.execution.skipped') return 'skipped';
    return 'approved_not_executed';
}

function mg_agent_execution_action_options(array $item): array
{
    $state = (string)($item['state'] ?? 'approved_not_executed');
    if ($state === 'completed' || $state === 'skipped' || $state === 'executing') return [];
    $actions = ['execute_approved_action','create_followup_task','draft_customer_message','mark_skipped'];
    if ($state === 'failed') $actions[] = 'retry_failed_execution';
    return $actions;
}

function mg_agent_execution_item(int $merchantId, array $source, ?array $latest): array
{
    $ctx = is_array($source['context'] ?? null) ? $source['context'] : [];
    $approvalId = (string)($ctx['approval_id'] ?? '');
    $state = mg_agent_execution_state($source, $latest);
    $executionId = mg_agent_execution_id($merchantId, $approvalId);
    $item = [
        'execution_id' => $executionId,
        'approval_id' => $approvalId,
        'source_event_id' => (string)$source['event_id'],
        'source_event_type' => (string)$source['event_type'],
        'state' => $state,
        'playbook_key' => (string)($ctx['playbook_key'] ?? ''),
        'playbook_title' => (string)($ctx['playbook_title'] ?? 'Agent action'),
        'why' => (string)($ctx['why'] ?? 'Merchant reviewed this agent action.'),
        'guardrail_applied' => (string)($ctx['guardrail_applied'] ?? 'Reviewed by merchant before execution.'),
        'expected_action' => (string)($ctx['expected_action'] ?? 'Execute reviewed action.'),
        'risk_level' => (string)($ctx['risk_level'] ?? 'medium'),
        'action_type' => (string)($ctx['action_type'] ?? ''),
        'customer_name' => (string)($ctx['customer_name'] ?? ''),
        'customer_email' => (string)($ctx['customer_email'] ?? ''),
        'campaign_title' => (string)($ctx['campaign_title'] ?? ''),
        'created_at' => $source['created_at'] ?? null,
        'latest_event' => $latest,
        'result_summary' => $latest ? (string)($latest['context']['result_summary'] ?? $latest['context']['note'] ?? $latest['event_type'] ?? '') : 'Approved, not executed.',
        'campaign_id' => $source['campaign_id'] ?? null,
        'contact_id' => $source['contact_id'] ?? null,
    ];
    $item['actions'] = mg_agent_execution_action_options($item);
    return $item;
}

function mg_agent_execution_queue(PDO $pdo, int $merchantId, array $input = []): array
{
    $filter = strtolower(trim((string)($input['filter'] ?? 'all')));
    $limit = max(1, min(100, (int)($input['limit'] ?? 60)));
    $latest = mg_agent_execution_latest_events($pdo, $merchantId);
    $items = [];
    foreach (mg_agent_execution_source_events($pdo, $merchantId) as $source) {
        $ctx = is_array($source['context'] ?? null) ? $source['context'] : [];
        $approvalId = (string)($ctx['approval_id'] ?? '');
        $item = mg_agent_execution_item($merchantId, $source, $latest[$approvalId] ?? null);
        if ($filter !== '' && $filter !== 'all' && (string)$item['state'] !== $filter) continue;
        $items[] = $item;
    }
    $summary = ['total' => count($items), 'approved_not_executed' => 0, 'executing' => 0, 'completed' => 0, 'failed' => 0, 'skipped' => 0];
    foreach ($items as $item) {
        $state = (string)($item['state'] ?? 'approved_not_executed');
        if (isset($summary[$state])) $summary[$state]++;
    }
    return [
        'items' => array_slice($items, 0, $limit),
        'summary' => $summary,
        'filters' => ['all','approved_not_executed','executing','completed','failed','skipped'],
        'events' => mg_agent_execution_event_types(),
    ];
}

function mg_agent_execution_find_item(PDO $pdo, int $merchantId, string $executionId): ?array
{
    foreach (mg_agent_execution_queue($pdo, $merchantId, ['limit' => 100])['items'] as $item) {
        if ((string)$item['execution_id'] === $executionId) return $item;
    }
    return null;
}

function mg_agent_execution_find_recommendation(PDO $pdo, int $merchantId, array $item): ?array
{
    $sourceId = '';
    if (preg_match('/source_id";s:\d+:"([^"]+)"/', serialize($item), $m)) $sourceId = $m[1];
    $approvalId = (string)($item['approval_id'] ?? '');
    foreach (mg_agent_approval_queue($pdo, $merchantId, ['limit' => 100])['items'] as $approval) {
        if ((string)$approval['approval_id'] !== $approvalId) continue;
        $sourceId = (string)($approval['source_id'] ?? $sourceId);
        break;
    }
    if ($sourceId === '') return null;
    foreach (mg_crm_playbook_scan($pdo, $merchantId, ['limit' => 75]) as $rec) {
        if ((string)$rec['id'] === $sourceId) return $rec;
    }
    return null;
}

function mg_agent_execution_record(PDO $pdo, int $merchantId, string $eventType, array $item, array $extra = []): string
{
    $context = array_merge([
        'execution_id' => (string)$item['execution_id'],
        'approval_id' => (string)$item['approval_id'],
        'playbook_key' => (string)$item['playbook_key'],
        'playbook_title' => (string)$item['playbook_title'],
        'expected_action' => (string)$item['expected_action'],
        'guardrail_applied' => (string)$item['guardrail_applied'],
        'risk_level' => (string)$item['risk_level'],
    ], $extra);
    $campaignId = isset($item['campaign_id']) ? $item['campaign_id'] : null;
    $contactId = isset($item['contact_id']) ? $item['contact_id'] : null;
    return mg_automation_record_event($pdo, $merchantId, $eventType, $context, $campaignId, $contactId);
}

function mg_agent_execution_perform(PDO $pdo, int $merchantId, int $actorId, array $item, string $action, array $input = []): array
{
    $action = strtolower(trim($action));
    $allowed = ['execute_approved_action','create_followup_task','draft_customer_message','mark_skipped','retry_failed_execution'];
    if (!in_array($action, $allowed, true)) return ['status' => 'skipped', 'reason' => 'unknown_action'];
    if ($action === 'mark_skipped') {
        $eventId = mg_agent_execution_record($pdo, $merchantId, 'crm.agent.execution.skipped', $item, ['decided_by_user_id' => $actorId, 'note' => trim((string)($input['note'] ?? 'Skipped by merchant.'))]);
        return ['status' => 'skipped', 'event_id' => $eventId];
    }
    if ($action === 'draft_customer_message') {
        $draft = trim((string)($input['draft_body'] ?? ''));
        if ($draft === '') $draft = 'Hi — following up with a quick note based on your recent Microgifter activity.';
        $eventId = mg_agent_execution_record($pdo, $merchantId, 'crm.agent.message.draft.created', $item, ['decided_by_user_id' => $actorId, 'draft_body' => $draft, 'result_summary' => 'Customer message draft created.']);
        return ['status' => 'completed', 'event_id' => $eventId, 'draft_body' => $draft];
    }
    $started = mg_agent_execution_record($pdo, $merchantId, 'crm.agent.execution.started', $item, ['decided_by_user_id' => $actorId, 'execution_action' => $action]);
    $taskResult = null;
    try {
        if ($action === 'create_followup_task' || $action === 'execute_approved_action' || $action === 'retry_failed_execution') {
            $rec = mg_agent_execution_find_recommendation($pdo, $merchantId, $item);
            if ($rec) {
                $defs = mg_crm_playbook_defs();
                $def = $defs[(string)$rec['playbook_key']] ?? null;
                if ($def) $taskResult = mg_crm_playbook_create_followup($pdo, $merchantId, $rec, $def);
            }
        }
        $summary = $taskResult ? 'Follow-up task action completed.' : 'Reviewed action marked completed.';
        $completed = mg_agent_execution_record($pdo, $merchantId, 'crm.agent.execution.completed', $item, ['decided_by_user_id' => $actorId, 'started_event_id' => $started, 'execution_action' => $action, 'task_result' => $taskResult, 'result_summary' => $summary]);
        return ['status' => 'completed', 'started_event_id' => $started, 'event_id' => $completed, 'task_result' => $taskResult];
    } catch (Throwable $error) {
        $failed = mg_agent_execution_record($pdo, $merchantId, 'crm.agent.execution.failed', $item, ['decided_by_user_id' => $actorId, 'started_event_id' => $started, 'execution_action' => $action, 'error_class' => $error::class, 'result_summary' => 'Execution failed and can be retried.']);
        return ['status' => 'failed', 'started_event_id' => $started, 'event_id' => $failed, 'error_class' => $error::class];
    }
}
