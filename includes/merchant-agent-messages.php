<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-execution.php';

function mg_agent_message_event_types(): array
{
    return ['crm.agent.message.draft.created','crm.agent.message.draft.edited','crm.agent.message.draft.approved','crm.agent.message.sent','crm.agent.message.discarded','crm.agent.message.followup_created'];
}

function mg_agent_message_source_types(): array
{
    return ['crm.agent.message.draft.created'];
}

function mg_agent_message_id(int $merchantId, string $executionId, string $approvalId): string
{
    return 'amd_' . substr(hash('sha256', $merchantId . '|message|' . $executionId . '|' . $approvalId), 0, 24);
}

function mg_agent_message_status_from_event(string $eventType): string
{
    $map = [
        'crm.agent.message.draft.created' => 'draft',
        'crm.agent.message.draft.edited' => 'edited',
        'crm.agent.message.draft.approved' => 'approved',
        'crm.agent.message.sent' => 'sent',
        'crm.agent.message.discarded' => 'discarded',
        'crm.agent.message.followup_created' => 'followup_created',
    ];
    return $map[$eventType] ?? 'draft';
}

function mg_agent_message_latest_events(PDO $pdo, int $merchantId): array
{
    $types = mg_agent_message_event_types();
    $in = implode(',', array_fill(0, count($types), '?'));
    $stmt = $pdo->prepare("SELECT public_id,event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type IN ({$in}) ORDER BY created_at DESC,id DESC LIMIT 300");
    $stmt->execute(array_merge([$merchantId], $types));
    $latest = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = mg_automation_json($row['event_context_json'] ?? null);
        $messageId = (string)($ctx['message_draft_id'] ?? '');
        if ($messageId === '') {
            $executionId = (string)($ctx['execution_id'] ?? '');
            $approvalId = (string)($ctx['approval_id'] ?? '');
            if ($executionId !== '' || $approvalId !== '') $messageId = mg_agent_message_id($merchantId, $executionId, $approvalId);
        }
        if ($messageId === '' || isset($latest[$messageId])) continue;
        $latest[$messageId] = [
            'event_id' => (string)$row['public_id'],
            'event_type' => (string)$row['event_type'],
            'status' => mg_agent_message_status_from_event((string)$row['event_type']),
            'created_at' => $row['created_at'] ?? null,
            'context' => $ctx,
        ];
    }
    return $latest;
}

function mg_agent_message_source_events(PDO $pdo, int $merchantId): array
{
    $types = mg_agent_message_source_types();
    $in = implode(',', array_fill(0, count($types), '?'));
    $stmt = $pdo->prepare("SELECT public_id,event_type,event_context_json,campaign_id,contact_id,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type IN ({$in}) ORDER BY created_at DESC,id DESC LIMIT 250");
    $stmt->execute(array_merge([$merchantId], $types));
    $rows = [];
    $seen = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = mg_automation_json($row['event_context_json'] ?? null);
        $executionId = (string)($ctx['execution_id'] ?? '');
        $approvalId = (string)($ctx['approval_id'] ?? '');
        $messageId = (string)($ctx['message_draft_id'] ?? mg_agent_message_id($merchantId, $executionId, $approvalId));
        if ($messageId === '' || isset($seen[$messageId])) continue;
        $seen[$messageId] = true;
        $rows[] = [
            'event_id' => (string)$row['public_id'],
            'event_type' => (string)$row['event_type'],
            'campaign_id' => $row['campaign_id'] !== null ? (int)$row['campaign_id'] : null,
            'contact_id' => $row['contact_id'] !== null ? (int)$row['contact_id'] : null,
            'created_at' => $row['created_at'] ?? null,
            'context' => $ctx,
            'message_draft_id' => $messageId,
        ];
    }
    return $rows;
}

function mg_agent_message_body(array $source, ?array $latest): string
{
    $latestCtx = $latest && is_array($latest['context'] ?? null) ? $latest['context'] : [];
    $sourceCtx = is_array($source['context'] ?? null) ? $source['context'] : [];
    return (string)($latestCtx['draft_body'] ?? $latestCtx['message_body'] ?? $sourceCtx['draft_body'] ?? '');
}

function mg_agent_message_item(int $merchantId, array $source, ?array $latest): array
{
    $ctx = is_array($source['context'] ?? null) ? $source['context'] : [];
    $executionId = (string)($ctx['execution_id'] ?? '');
    $approvalId = (string)($ctx['approval_id'] ?? '');
    $messageId = (string)($source['message_draft_id'] ?? $ctx['message_draft_id'] ?? mg_agent_message_id($merchantId, $executionId, $approvalId));
    $status = $latest ? (string)($latest['status'] ?? 'draft') : 'draft';
    $item = [
        'message_draft_id' => $messageId,
        'execution_id' => $executionId,
        'approval_id' => $approvalId,
        'source_event_id' => (string)$source['event_id'],
        'source_event_type' => (string)$source['event_type'],
        'status' => $status,
        'playbook_key' => (string)($ctx['playbook_key'] ?? ''),
        'playbook_title' => (string)($ctx['playbook_title'] ?? 'Agent message'),
        'customer_name' => (string)($ctx['customer_name'] ?? ''),
        'customer_email' => (string)($ctx['customer_email'] ?? ''),
        'campaign_title' => (string)($ctx['campaign_title'] ?? ''),
        'message_body' => mg_agent_message_body($source, $latest),
        'why' => (string)($ctx['why'] ?? 'Agent drafted this message from a reviewed action.'),
        'guardrail_applied' => (string)($ctx['guardrail_applied'] ?? 'Merchant must approve final customer communication.'),
        'expected_action' => (string)($ctx['expected_action'] ?? 'Review message before customer communication.'),
        'created_at' => $source['created_at'] ?? null,
        'latest_event' => $latest,
        'campaign_id' => $source['campaign_id'] ?? null,
        'contact_id' => $source['contact_id'] ?? null,
    ];
    $item['actions'] = mg_agent_message_action_options($item);
    return $item;
}

function mg_agent_message_action_options(array $item): array
{
    $status = (string)($item['status'] ?? 'draft');
    if ($status === 'sent' || $status === 'discarded' || $status === 'followup_created') return [];
    $actions = ['edit_draft','approve_draft','discard_draft','convert_to_followup_task'];
    if ($status === 'approved' || $status === 'edited' || $status === 'draft') $actions[] = 'send_message';
    return $actions;
}

function mg_agent_message_queue(PDO $pdo, int $merchantId, array $input = []): array
{
    $filter = strtolower(trim((string)($input['filter'] ?? 'all')));
    $limit = max(1, min(100, (int)($input['limit'] ?? 60)));
    $latest = mg_agent_message_latest_events($pdo, $merchantId);
    $items = [];
    foreach (mg_agent_message_source_events($pdo, $merchantId) as $source) {
        $messageId = (string)($source['message_draft_id'] ?? '');
        $item = mg_agent_message_item($merchantId, $source, $latest[$messageId] ?? null);
        if ($filter !== '' && $filter !== 'all' && (string)$item['status'] !== $filter) continue;
        $items[] = $item;
    }
    $summary = ['total' => count($items), 'draft' => 0, 'edited' => 0, 'approved' => 0, 'sent' => 0, 'discarded' => 0, 'followup_created' => 0];
    foreach ($items as $item) {
        $status = (string)($item['status'] ?? 'draft');
        if (isset($summary[$status])) $summary[$status]++;
    }
    return [
        'items' => array_slice($items, 0, $limit),
        'summary' => $summary,
        'filters' => ['all','draft','edited','approved','sent','discarded','followup_created'],
        'events' => mg_agent_message_event_types(),
    ];
}

function mg_agent_message_find_item(PDO $pdo, int $merchantId, string $messageDraftId): ?array
{
    foreach (mg_agent_message_queue($pdo, $merchantId, ['limit' => 100])['items'] as $item) {
        if ((string)$item['message_draft_id'] === $messageDraftId) return $item;
    }
    return null;
}

function mg_agent_message_record(PDO $pdo, int $merchantId, string $eventType, array $item, array $extra = []): string
{
    $context = array_merge([
        'message_draft_id' => (string)$item['message_draft_id'],
        'execution_id' => (string)$item['execution_id'],
        'approval_id' => (string)$item['approval_id'],
        'playbook_key' => (string)$item['playbook_key'],
        'playbook_title' => (string)$item['playbook_title'],
        'customer_name' => (string)$item['customer_name'],
        'customer_email' => (string)$item['customer_email'],
        'campaign_title' => (string)$item['campaign_title'],
        'message_body' => (string)$item['message_body'],
        'guardrail_applied' => (string)$item['guardrail_applied'],
    ], $extra);
    $campaignId = isset($item['campaign_id']) ? $item['campaign_id'] : null;
    $contactId = isset($item['contact_id']) ? $item['contact_id'] : null;
    return mg_automation_record_event($pdo, $merchantId, $eventType, $context, $campaignId, $contactId);
}

function mg_agent_message_perform(PDO $pdo, int $merchantId, int $actorId, array $item, string $action, array $input = []): array
{
    $action = strtolower(trim($action));
    $allowed = ['edit_draft','approve_draft','send_message','discard_draft','convert_to_followup_task'];
    if (!in_array($action, $allowed, true)) return ['status' => 'skipped', 'reason' => 'unknown_action'];
    $note = trim((string)($input['note'] ?? ''));
    if ($action === 'edit_draft') {
        $body = trim((string)($input['message_body'] ?? ''));
        if ($body === '') $body = (string)$item['message_body'];
        $eventId = mg_agent_message_record($pdo, $merchantId, 'crm.agent.message.draft.edited', $item, ['decided_by_user_id' => $actorId, 'draft_body' => $body, 'message_body' => $body, 'note' => $note]);
        return ['status' => 'edited', 'event_id' => $eventId, 'message_body' => $body];
    }
    if ($action === 'approve_draft') {
        $eventId = mg_agent_message_record($pdo, $merchantId, 'crm.agent.message.draft.approved', $item, ['decided_by_user_id' => $actorId, 'note' => $note]);
        return ['status' => 'approved', 'event_id' => $eventId];
    }
    if ($action === 'send_message') {
        $eventId = mg_agent_message_record($pdo, $merchantId, 'crm.agent.message.sent', $item, ['decided_by_user_id' => $actorId, 'note' => $note, 'sent_via' => 'merchant_reviewed_agent_message']);
        return ['status' => 'sent', 'event_id' => $eventId];
    }
    if ($action === 'discard_draft') {
        $eventId = mg_agent_message_record($pdo, $merchantId, 'crm.agent.message.discarded', $item, ['decided_by_user_id' => $actorId, 'note' => $note]);
        return ['status' => 'discarded', 'event_id' => $eventId];
    }
    $rec = null;
    if ((string)($item['approval_id'] ?? '') !== '') {
        foreach (mg_agent_execution_queue($pdo, $merchantId, ['limit' => 100])['items'] as $exec) {
            if ((string)($exec['approval_id'] ?? '') === (string)$item['approval_id']) {
                $rec = mg_agent_execution_find_recommendation($pdo, $merchantId, $exec);
                break;
            }
        }
    }
    $taskResult = null;
    if ($rec) {
        $defs = mg_crm_playbook_defs();
        $def = $defs[(string)$rec['playbook_key']] ?? null;
        if ($def) $taskResult = mg_crm_playbook_create_followup($pdo, $merchantId, $rec, $def);
    }
    $eventId = mg_agent_message_record($pdo, $merchantId, 'crm.agent.message.followup_created', $item, ['decided_by_user_id' => $actorId, 'task_result' => $taskResult, 'note' => $note]);
    return ['status' => 'followup_created', 'event_id' => $eventId, 'task_result' => $taskResult];
}
