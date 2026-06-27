<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-agent-monitor.php';

function mg_agent_approval_event_types(): array
{
    return ['crm.agent.approval.approved','crm.agent.approval.rejected','crm.agent.approval.deferred','crm.agent.approval.task_created'];
}

function mg_agent_approval_id(int $merchantId, string $sourceType, string $sourceId): string
{
    return 'agq_' . substr(hash('sha256', $merchantId . '|' . $sourceType . '|' . $sourceId), 0, 24);
}

function mg_agent_approval_risk_level(array $rec, array $guardrail): string
{
    $type = (string)($rec['action_type'] ?? '');
    if (!empty($guardrail['blocked_by_daily_limit'])) return 'high';
    if ($type === 'message_draft') return 'low';
    if ($type === 'create_followup_task') return 'low';
    if ($type === 'suggest_reward_invite' || $type === 'suggest_reward_or_message') return 'medium';
    return 'medium';
}

function mg_agent_approval_decisions(PDO $pdo, int $merchantId): array
{
    $types = mg_agent_approval_event_types();
    $in = implode(',', array_fill(0, count($types), '?'));
    $stmt = $pdo->prepare("SELECT public_id,event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type IN ({$in}) ORDER BY created_at DESC,id DESC LIMIT 250");
    $stmt->execute(array_merge([$merchantId], $types));
    $decisions = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = mg_automation_json($row['event_context_json'] ?? null);
        $approvalId = (string)($ctx['approval_id'] ?? '');
        if ($approvalId === '' || isset($decisions[$approvalId])) continue;
        $decisions[$approvalId] = [
            'approval_id' => $approvalId,
            'event_id' => (string)$row['public_id'],
            'event_type' => (string)$row['event_type'],
            'decision' => str_replace('crm.agent.approval.', '', (string)$row['event_type']),
            'created_at' => $row['created_at'] ?? null,
            'context' => $ctx,
        ];
    }
    return $decisions;
}

function mg_agent_approval_recommendation_item(int $merchantId, array $rec, array $setting, array $usage): array
{
    $guardrail = mg_agent_monitor_guardrail_summary($setting, $usage);
    $approvalId = mg_agent_approval_id($merchantId, 'recommendation', (string)$rec['id']);
    $taskAllowed = !empty($guardrail['task_creation_allowed']);
    return [
        'approval_id' => $approvalId,
        'source_type' => 'recommendation',
        'source_id' => (string)$rec['id'],
        'playbook_key' => (string)$rec['playbook_key'],
        'playbook_title' => (string)$rec['playbook_title'],
        'customer_name' => (string)($rec['customer_name'] ?? ''),
        'customer_email' => (string)($rec['customer_email'] ?? ''),
        'campaign_title' => (string)($rec['campaign_title'] ?? ''),
        'why' => (string)($rec['reason'] ?? 'Customer matched an agent-monitored playbook.'),
        'guardrail_applied' => mg_agent_monitor_explain_status($setting, $guardrail, 1),
        'expected_action' => (string)($rec['recommended_next_action'] ?? 'Review the recommendation.'),
        'recommended_next_action' => (string)($rec['recommended_next_action'] ?? 'Review the recommendation.'),
        'risk_level' => mg_agent_approval_risk_level($rec, $guardrail),
        'merchant_approval_required' => true,
        'can_create_task' => $taskAllowed,
        'action_type' => (string)($rec['action_type'] ?? ''),
        'status' => 'pending',
        'customer_url' => (string)($rec['customer_url'] ?? ''),
        'message_url' => (string)($rec['message_url'] ?? ''),
        'reward_url' => (string)($rec['reward_url'] ?? ''),
        '_rec' => $rec,
    ];
}

function mg_agent_approval_event_item(int $merchantId, array $event): array
{
    $ctx = is_array($event['context'] ?? null) ? $event['context'] : [];
    $sourceId = (string)($event['id'] ?? $ctx['recommendation_id'] ?? '');
    $approvalId = mg_agent_approval_id($merchantId, 'event', $sourceId);
    return [
        'approval_id' => $approvalId,
        'source_type' => 'event',
        'source_id' => $sourceId,
        'playbook_key' => (string)($ctx['playbook_key'] ?? ''),
        'playbook_title' => (string)($event['playbook'] ?? $ctx['playbook_title'] ?? 'Automation event'),
        'customer_name' => (string)($event['customer_name'] ?? ''),
        'customer_email' => (string)($event['customer_email'] ?? ''),
        'campaign_title' => (string)($event['campaign_title'] ?? ''),
        'why' => (string)($ctx['note'] ?? $ctx['recommendation_id'] ?? 'Automation event requires merchant review.'),
        'guardrail_applied' => 'Merchant approval was required by automation guardrail.',
        'expected_action' => (string)($ctx['recommended_next_action'] ?? 'Review this approval-gated automation event.'),
        'recommended_next_action' => (string)($ctx['recommended_next_action'] ?? 'Review this approval-gated automation event.'),
        'risk_level' => 'medium',
        'merchant_approval_required' => true,
        'can_create_task' => false,
        'action_type' => (string)($ctx['action_type'] ?? ''),
        'status' => 'pending',
        'customer_url' => '',
        'message_url' => '',
        'reward_url' => '',
        '_event' => $event,
    ];
}

function mg_agent_approval_queue(PDO $pdo, int $merchantId, array $input = []): array
{
    $filter = strtolower(trim((string)($input['filter'] ?? 'all')));
    $limit = max(1, min(100, (int)($input['limit'] ?? 40)));
    $current = mg_automation_current_settings($pdo, $merchantId);
    $settings = $current['settings'];
    $usage = mg_agent_monitor_daily_usage($pdo, $merchantId);
    $decisions = mg_agent_approval_decisions($pdo, $merchantId);
    $items = [];
    foreach (mg_crm_playbook_scan($pdo, $merchantId, ['limit' => min(75, $limit)]) as $rec) {
        $key = (string)($rec['playbook_key'] ?? '');
        $setting = $settings[$key] ?? [];
        $guardrail = mg_agent_monitor_guardrail_summary($setting, $usage[$key] ?? ['total' => 0]);
        if (empty($setting['enabled']) || empty($guardrail['approval_required'])) continue;
        $item = mg_agent_approval_recommendation_item($merchantId, $rec, $setting, $usage[$key] ?? ['total' => 0]);
        if (isset($decisions[$item['approval_id']])) { $item['status'] = (string)$decisions[$item['approval_id']]['decision']; $item['decision'] = $decisions[$item['approval_id']]; }
        $items[] = $item;
    }
    foreach (mg_automation_log($pdo, $merchantId, 60) as $event) {
        if (empty($event['requires_approval'])) continue;
        $item = mg_agent_approval_event_item($merchantId, $event);
        if (isset($decisions[$item['approval_id']])) { $item['status'] = (string)$decisions[$item['approval_id']]['decision']; $item['decision'] = $decisions[$item['approval_id']]; }
        $items[] = $item;
    }
    if ($filter !== '' && $filter !== 'all') {
        $items = array_values(array_filter($items, static fn(array $item): bool => (string)($item['status'] ?? 'pending') === $filter || (string)($item['risk_level'] ?? '') === $filter));
    }
    $summary = ['total' => count($items), 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'deferred' => 0, 'task_created' => 0, 'high_risk' => 0, 'medium_risk' => 0, 'low_risk' => 0];
    foreach ($items as $item) {
        $status = (string)($item['status'] ?? 'pending');
        if (isset($summary[$status])) $summary[$status]++;
        $risk = (string)($item['risk_level'] ?? 'medium') . '_risk';
        if (isset($summary[$risk])) $summary[$risk]++;
    }
    $publicItems = array_map(static function (array $item): array {
        unset($item['_rec'], $item['_event']);
        return $item;
    }, array_slice($items, 0, $limit));
    return ['items' => $publicItems, 'summary' => $summary, 'filters' => ['all','pending','approved','rejected','deferred','task_created','low','medium','high'], 'settings_source' => $current['source']];
}

function mg_agent_approval_find_item(PDO $pdo, int $merchantId, string $approvalId): ?array
{
    foreach (mg_agent_approval_queue($pdo, $merchantId, ['limit' => 100])['items'] as $publicItem) {
        if ((string)$publicItem['approval_id'] === $approvalId) {
            $sourceId = (string)$publicItem['source_id'];
            if ((string)$publicItem['source_type'] === 'recommendation') {
                foreach (mg_crm_playbook_scan($pdo, $merchantId, ['limit' => 75]) as $rec) {
                    if ((string)$rec['id'] === $sourceId) {
                        $publicItem['_rec'] = $rec;
                        return $publicItem;
                    }
                }
            }
            return $publicItem;
        }
    }
    return null;
}

function mg_agent_approval_record_decision(PDO $pdo, int $merchantId, int $actorId, array $item, string $action, array $input = []): array
{
    $action = strtolower(trim($action));
    $map = ['approve' => 'crm.agent.approval.approved', 'reject' => 'crm.agent.approval.rejected', 'defer' => 'crm.agent.approval.deferred', 'create_task' => 'crm.agent.approval.task_created'];
    if (!isset($map[$action])) return ['status' => 'skipped', 'reason' => 'unknown_action'];
    $rec = is_array($item['_rec'] ?? null) ? $item['_rec'] : [];
    $campaignDbId = !empty($rec['_campaign_db_id']) ? (int)$rec['_campaign_db_id'] : null;
    $contactDbId = !empty($rec['_contact_db_id']) ? (int)$rec['_contact_db_id'] : null;
    $context = [
        'approval_id' => (string)$item['approval_id'],
        'approval_action' => $action,
        'source_type' => (string)$item['source_type'],
        'source_id' => (string)$item['source_id'],
        'playbook_key' => (string)$item['playbook_key'],
        'playbook_title' => (string)$item['playbook_title'],
        'why' => (string)$item['why'],
        'guardrail_applied' => (string)$item['guardrail_applied'],
        'expected_action' => (string)$item['expected_action'],
        'risk_level' => (string)$item['risk_level'],
        'merchant_approval_required' => true,
        'decided_by_user_id' => $actorId,
        'note' => trim((string)($input['note'] ?? '')),
    ];
    if ($action === 'defer') $context['defer_until'] = trim((string)($input['defer_until'] ?? '')) ?: date('Y-m-d', strtotime('+3 days'));
    $eventId = mg_automation_record_event($pdo, $merchantId, $map[$action], $context, $campaignDbId, $contactDbId);
    $taskResult = null;
    if ($action === 'create_task' && $rec) {
        $defs = mg_crm_playbook_defs();
        $def = $defs[(string)$rec['playbook_key']] ?? null;
        if ($def) $taskResult = mg_crm_playbook_create_followup($pdo, $merchantId, $rec, $def);
    }
    return ['status' => str_replace('crm.agent.approval.', '', $map[$action]), 'event_id' => $eventId, 'task_result' => $taskResult];
}
