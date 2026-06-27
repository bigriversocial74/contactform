<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-automation-controls.php';

function mg_agent_monitor_daily_usage(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare("SELECT event_type,event_context_json,COUNT(*) total FROM campaign_events WHERE merchant_user_id=? AND event_type IN ('crm.followup.created','crm.automation.approval.granted','crm.automation.message.drafted','crm.playbook.triggered') AND created_at >= CURDATE() GROUP BY event_type,event_context_json");
    $stmt->execute([$merchantId]);
    $usage = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = mg_automation_json($row['event_context_json'] ?? null);
        $key = (string)($ctx['playbook_key'] ?? 'general');
        if (!isset($usage[$key])) $usage[$key] = ['playbook_key' => $key, 'total' => 0, 'created_tasks' => 0, 'approvals' => 0, 'drafts' => 0, 'triggers' => 0];
        $count = (int)($row['total'] ?? 0);
        $usage[$key]['total'] += $count;
        if ($row['event_type'] === 'crm.followup.created') $usage[$key]['created_tasks'] += $count;
        if ($row['event_type'] === 'crm.automation.approval.granted') $usage[$key]['approvals'] += $count;
        if ($row['event_type'] === 'crm.automation.message.drafted') $usage[$key]['drafts'] += $count;
        if ($row['event_type'] === 'crm.playbook.triggered') $usage[$key]['triggers'] += $count;
    }
    return $usage;
}

function mg_agent_monitor_guardrail_summary(array $setting, array $usage): array
{
    $max = (int)($setting['max_actions_per_day'] ?? 0);
    $used = (int)($usage['total'] ?? 0);
    $enabled = !empty($setting['enabled']);
    $monitor = $enabled && !empty($setting['agent_can_monitor']);
    $recommend = $enabled && !empty($setting['agent_can_recommend']);
    $task = $enabled && ($max > 0) && (!empty($setting['agent_can_create_task']) || !empty($setting['auto_create_followups']));
    $approval = !empty($setting['require_approval']) || !empty($setting['agent_requires_approval']);
    return [
        'monitoring_enabled' => $monitor,
        'recommendations_active' => $recommend,
        'task_creation_allowed' => $task,
        'approval_required' => $approval,
        'blocked_by_daily_limit' => $enabled && ($max <= 0 || $used >= $max),
        'daily_limit' => $max,
        'daily_used' => $used,
        'daily_remaining' => max(0, $max - $used),
    ];
}

function mg_agent_monitor_statuses(PDO $pdo, int $merchantId, array $recommendations): array
{
    $current = mg_automation_current_settings($pdo, $merchantId);
    $settings = $current['settings'];
    $usage = mg_agent_monitor_daily_usage($pdo, $merchantId);
    $recCounts = [];
    foreach ($recommendations as $rec) {
        $key = (string)($rec['playbook_key'] ?? 'general');
        $recCounts[$key] = ($recCounts[$key] ?? 0) + 1;
    }
    $statuses = [];
    foreach ($settings as $key => $setting) {
        $guardrail = mg_agent_monitor_guardrail_summary($setting, $usage[$key] ?? ['total' => 0]);
        $state = 'monitoring';
        if (empty($setting['enabled']) || empty($setting['agent_can_monitor'])) $state = 'blocked';
        elseif (!empty($guardrail['blocked_by_daily_limit'])) $state = 'blocked_by_daily_limit';
        elseif (($recCounts[$key] ?? 0) > 0 && !empty($guardrail['approval_required'])) $state = 'needs_approval';
        elseif (($recCounts[$key] ?? 0) > 0 && !empty($guardrail['task_creation_allowed'])) $state = 'ready_to_run';
        elseif (($recCounts[$key] ?? 0) > 0) $state = 'recommendation_only';
        $statuses[] = [
            'playbook_key' => $key,
            'playbook_title' => (string)$setting['playbook_title'],
            'automation_level' => (string)$setting['automation_level'],
            'state' => $state,
            'recommendations_waiting' => (int)($recCounts[$key] ?? 0),
            'usage' => $usage[$key] ?? ['playbook_key' => $key, 'total' => 0, 'created_tasks' => 0, 'approvals' => 0, 'drafts' => 0, 'triggers' => 0],
            'guardrail' => $guardrail,
            'why' => mg_agent_monitor_explain_status($setting, $guardrail, (int)($recCounts[$key] ?? 0)),
        ];
    }
    return $statuses;
}

function mg_agent_monitor_explain_status(array $setting, array $guardrail, int $recommendations): string
{
    if (empty($setting['enabled'])) return 'This playbook is disabled by merchant automation settings.';
    if (empty($setting['agent_can_monitor'])) return 'The agent is blocked from monitoring this playbook.';
    if (!empty($guardrail['blocked_by_daily_limit'])) return 'The agent is blocked by the daily action limit for this playbook.';
    if ($recommendations > 0 && !empty($guardrail['approval_required'])) return 'The agent found matching recommendations, but merchant approval is required before execution.';
    if ($recommendations > 0 && !empty($guardrail['task_creation_allowed'])) return 'The agent found matching recommendations and task creation is allowed inside guardrails.';
    if ($recommendations > 0) return 'The agent found recommendations, but this playbook is currently recommendation-only.';
    return 'The agent is monitoring this playbook and no action is needed right now.';
}

function mg_agent_monitor_recommendation_item(array $rec, array $settingsByKey): array
{
    $key = (string)($rec['playbook_key'] ?? '');
    $setting = $settingsByKey[$key] ?? [];
    $guardrail = mg_agent_monitor_guardrail_summary($setting, ['total' => 0]);
    $filter = 'recommendation_only';
    if (empty($setting['enabled']) || !empty($guardrail['blocked_by_daily_limit'])) $filter = 'blocked';
    elseif (!empty($guardrail['approval_required'])) $filter = 'needs_approval';
    elseif (!empty($guardrail['task_creation_allowed'])) $filter = 'ready_to_run';
    return [
        'id' => 'rec:' . (string)($rec['id'] ?? ''),
        'type' => 'recommendation',
        'filter' => $filter,
        'label' => 'Recommendation waiting',
        'playbook_key' => $key,
        'playbook_title' => (string)($rec['playbook_title'] ?? 'Retention playbook'),
        'customer_name' => (string)($rec['customer_name'] ?? ''),
        'customer_email' => (string)($rec['customer_email'] ?? ''),
        'campaign_title' => (string)($rec['campaign_title'] ?? ''),
        'status' => $filter,
        'created_at' => null,
        'why' => (string)($rec['reason'] ?? 'Customer matched a retention trigger.'),
        'guardrail_applied' => mg_agent_monitor_explain_status($setting, $guardrail, 1),
        'recommended_next_action' => (string)($rec['recommended_next_action'] ?? 'Review the recommended action.'),
        'requires_approval' => !empty($guardrail['approval_required']),
        'customer_url' => (string)($rec['customer_url'] ?? ''),
    ];
}

function mg_agent_monitor_event_item(array $event): array
{
    $type = (string)($event['event_type'] ?? '');
    $ctx = is_array($event['context'] ?? null) ? $event['context'] : [];
    $filter = match (true) {
        $type === 'crm.followup.created' => 'created_task',
        str_starts_with($type, 'crm.automation.approval.') => 'needs_approval',
        $type === 'crm.automation.message.drafted' => 'ready_to_run',
        default => 'monitoring',
    };
    return [
        'id' => 'event:' . (string)($event['id'] ?? ''),
        'type' => 'event',
        'filter' => $filter,
        'label' => (string)($event['label'] ?? $type),
        'playbook_key' => (string)($ctx['playbook_key'] ?? ''),
        'playbook_title' => (string)($event['playbook'] ?? 'Automation'),
        'customer_name' => (string)($event['customer_name'] ?? ''),
        'customer_email' => (string)($event['customer_email'] ?? ''),
        'campaign_title' => (string)($event['campaign_title'] ?? ''),
        'status' => (string)($event['status'] ?? 'recorded'),
        'created_at' => $event['created_at'] ?? null,
        'why' => (string)($ctx['note'] ?? $ctx['recommendation_id'] ?? $event['label'] ?? 'Automation event recorded.'),
        'guardrail_applied' => !empty($event['requires_approval']) ? 'Merchant approval was required by guardrail.' : 'Recorded inside merchant automation history.',
        'recommended_next_action' => (string)($ctx['recommended_next_action'] ?? 'Review activity and continue monitoring.'),
        'requires_approval' => !empty($event['requires_approval']),
        'customer_url' => '',
    ];
}

function mg_agent_monitor_payload(PDO $pdo, int $merchantId, array $input = []): array
{
    $filter = strtolower(trim((string)($input['filter'] ?? 'all')));
    $limit = max(1, min(100, (int)($input['limit'] ?? 40)));
    $current = mg_automation_current_settings($pdo, $merchantId);
    $recs = mg_crm_playbook_scan($pdo, $merchantId, ['limit' => min(75, $limit)]);
    $events = mg_automation_log($pdo, $merchantId, min(100, $limit));
    $statuses = mg_agent_monitor_statuses($pdo, $merchantId, $recs);
    $items = [];
    foreach ($recs as $rec) $items[] = mg_agent_monitor_recommendation_item($rec, $current['settings']);
    foreach ($events as $event) $items[] = mg_agent_monitor_event_item($event);
    if ($filter !== '' && $filter !== 'all') {
        $items = array_values(array_filter($items, static fn(array $item): bool => (string)($item['filter'] ?? '') === $filter));
    }
    usort($items, static function (array $a, array $b): int {
        $at = strtotime((string)($a['created_at'] ?? '')) ?: PHP_INT_MAX;
        $bt = strtotime((string)($b['created_at'] ?? '')) ?: PHP_INT_MAX;
        return $at <=> $bt;
    });
    $summary = ['total_items' => count($items), 'needs_approval' => 0, 'ready_to_run' => 0, 'blocked' => 0, 'created_task' => 0, 'recommendation_only' => 0, 'monitoring' => 0];
    foreach ($items as $item) {
        $key = (string)($item['filter'] ?? 'monitoring');
        if (isset($summary[$key])) $summary[$key]++;
    }
    return [
        'settings_source' => $current['source'],
        'updated_at' => $current['updated_at'],
        'summary' => $summary,
        'statuses' => $statuses,
        'items' => array_slice($items, 0, $limit),
        'filters' => ['all','needs_approval','ready_to_run','blocked','created_task','recommendation_only','monitoring'],
    ];
}
