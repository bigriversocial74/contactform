<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-customer-agent-timeline.php';

function mg_agent_analytics_event_types(): array
{
    return mg_customer_agent_timeline_event_types();
}

function mg_agent_analytics_json($value): array
{
    return mg_automation_json($value);
}

function mg_agent_analytics_bucket_date(?string $createdAt): string
{
    $time = strtotime((string)$createdAt);
    return $time ? date('Y-m-d', $time) : 'unknown';
}

function mg_agent_analytics_rate(int $part, int $whole): int
{
    return $whole > 0 ? (int)round(($part / $whole) * 100) : 0;
}

function mg_agent_analytics_empty_summary(): array
{
    return [
        'recommendations_generated' => 0,
        'approvals' => 0,
        'rejections' => 0,
        'deferrals' => 0,
        'approval_rate' => 0,
        'execution_started' => 0,
        'execution_completed' => 0,
        'execution_failed' => 0,
        'execution_skipped' => 0,
        'execution_completion_rate' => 0,
        'failed_skipped_rate' => 0,
        'message_drafts_created' => 0,
        'messages_sent' => 0,
        'draft_to_send_rate' => 0,
        'followups_created' => 0,
        'followup_conversion_rate' => 0,
        'customers_touched' => 0,
        'events_total' => 0,
    ];
}

function mg_agent_analytics_rows(PDO $pdo, int $merchantId, array $input = []): array
{
    $days = max(1, min(365, (int)($input['days'] ?? 90)));
    $types = mg_agent_analytics_event_types();
    $in = implode(',', array_fill(0, count($types), '?'));
    $stmt = $pdo->prepare("SELECT public_id,event_type,event_context_json,campaign_id,contact_id,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type IN ({$in}) AND created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY) ORDER BY created_at DESC,id DESC LIMIT 1000");
    $stmt->execute(array_merge([$merchantId], $types));
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = mg_agent_analytics_json($row['event_context_json'] ?? null);
        $type = (string)$row['event_type'];
        $rows[] = [
            'id' => (string)$row['public_id'],
            'event_type' => $type,
            'group' => mg_customer_agent_timeline_group($type),
            'playbook_key' => (string)($ctx['playbook_key'] ?? ''),
            'playbook_title' => (string)($ctx['playbook_title'] ?? $ctx['playbook_key'] ?? 'Unknown playbook'),
            'campaign_id' => $row['campaign_id'] !== null ? (int)$row['campaign_id'] : null,
            'campaign_title' => (string)($ctx['campaign_title'] ?? ''),
            'contact_id' => $row['contact_id'] !== null ? (int)$row['contact_id'] : null,
            'customer_email' => strtolower((string)($ctx['customer_email'] ?? $ctx['email'] ?? '')),
            'customer_name' => (string)($ctx['customer_name'] ?? ''),
            'approval_id' => (string)($ctx['approval_id'] ?? ''),
            'execution_id' => (string)($ctx['execution_id'] ?? ''),
            'message_draft_id' => (string)($ctx['message_draft_id'] ?? ''),
            'result_summary' => (string)($ctx['result_summary'] ?? $ctx['note'] ?? ''),
            'created_at' => $row['created_at'] ?? null,
            'bucket_date' => mg_agent_analytics_bucket_date($row['created_at'] ?? null),
            'context' => $ctx,
        ];
    }
    return $rows;
}

function mg_agent_analytics_customer_key(array $row): string
{
    if (!empty($row['contact_id'])) return 'contact:' . (int)$row['contact_id'];
    if (!empty($row['customer_email'])) return 'email:' . strtolower((string)$row['customer_email']);
    $ctx = is_array($row['context'] ?? null) ? $row['context'] : [];
    foreach (['campaign_contact_id','contact_public_id','customer_public_id','customer_user_id','user_id'] as $key) {
        if (($ctx[$key] ?? '') !== '') return $key . ':' . (string)$ctx[$key];
    }
    return '';
}

function mg_agent_analytics_summary(array $rows): array
{
    $s = mg_agent_analytics_empty_summary();
    $customers = [];
    foreach ($rows as $row) {
        $type = (string)$row['event_type'];
        $s['events_total']++;
        if ($type === 'crm.playbook.triggered') $s['recommendations_generated']++;
        if ($type === 'crm.agent.approval.approved') $s['approvals']++;
        if ($type === 'crm.agent.approval.rejected') $s['rejections']++;
        if ($type === 'crm.agent.approval.deferred') $s['deferrals']++;
        if ($type === 'crm.agent.execution.started') $s['execution_started']++;
        if ($type === 'crm.agent.execution.completed') $s['execution_completed']++;
        if ($type === 'crm.agent.execution.failed') $s['execution_failed']++;
        if ($type === 'crm.agent.execution.skipped') $s['execution_skipped']++;
        if ($type === 'crm.agent.message.draft.created') $s['message_drafts_created']++;
        if ($type === 'crm.agent.message.sent') $s['messages_sent']++;
        if ($type === 'crm.followup.created' || $type === 'crm.agent.approval.task_created' || $type === 'crm.agent.message.followup_created') $s['followups_created']++;
        $customerKey = mg_agent_analytics_customer_key($row);
        if ($customerKey !== '') $customers[$customerKey] = true;
    }
    $decisions = $s['approvals'] + $s['rejections'] + $s['deferrals'];
    $s['approval_rate'] = mg_agent_analytics_rate($s['approvals'], $decisions);
    $s['execution_completion_rate'] = mg_agent_analytics_rate($s['execution_completed'], max($s['execution_started'], $s['execution_completed'] + $s['execution_failed'] + $s['execution_skipped']));
    $s['failed_skipped_rate'] = mg_agent_analytics_rate($s['execution_failed'] + $s['execution_skipped'], $s['execution_completed'] + $s['execution_failed'] + $s['execution_skipped']);
    $s['draft_to_send_rate'] = mg_agent_analytics_rate($s['messages_sent'], $s['message_drafts_created']);
    $s['followup_conversion_rate'] = mg_agent_analytics_rate($s['followups_created'], max(1, $s['recommendations_generated'] + $s['approvals']));
    $s['customers_touched'] = count($customers);
    return $s;
}

function mg_agent_analytics_grouped(array $rows, string $key, string $labelKey = ''): array
{
    $groups = [];
    foreach ($rows as $row) {
        $id = (string)($row[$key] ?? '');
        if ($id === '') $id = 'unknown';
        if (!isset($groups[$id])) {
            $groups[$id] = mg_agent_analytics_empty_summary();
            $groups[$id]['id'] = $id;
            $groups[$id]['label'] = $labelKey !== '' ? (string)($row[$labelKey] ?? $id) : $id;
        }
        $groups[$id]['events'][] = $row;
    }
    foreach ($groups as $id => $group) {
        $summary = mg_agent_analytics_summary($group['events'] ?? []);
        $summary['id'] = $group['id'];
        $summary['label'] = $group['label'] !== '' && $group['label'] !== 'unknown' ? $group['label'] : ucwords(str_replace(['_', '-'], ' ', $id));
        unset($summary['events']);
        $groups[$id] = $summary;
    }
    usort($groups, static fn($a, $b) => ((int)$b['events_total']) <=> ((int)$a['events_total']));
    return array_slice($groups, 0, 20);
}

function mg_agent_analytics_customer_breakdown(array $rows): array
{
    $groups = [];
    foreach ($rows as $row) {
        $id = mg_agent_analytics_customer_key($row);
        if ($id === '') $id = 'unknown';
        if (!isset($groups[$id])) {
            $groups[$id] = ['events' => [], 'id' => $id, 'label' => (string)($row['customer_name'] ?: $row['customer_email'] ?: 'Unknown customer')];
        }
        $groups[$id]['events'][] = $row;
    }
    foreach ($groups as $id => $group) {
        $summary = mg_agent_analytics_summary($group['events'] ?? []);
        $summary['id'] = $id;
        $summary['label'] = $group['label'];
        $summary['customer_url'] = $id !== 'unknown' && str_starts_with($id, 'email:') ? ('/merchant-customer.php?email=' . rawurlencode(substr($id, 6)) . '&tab=timeline') : '/merchant-customer.php?tab=timeline';
        $groups[$id] = $summary;
    }
    usort($groups, static fn($a, $b) => ((int)$b['events_total']) <=> ((int)$a['events_total']));
    return array_slice($groups, 0, 20);
}

function mg_agent_analytics_daily(array $rows): array
{
    $days = [];
    foreach ($rows as $row) {
        $day = (string)($row['bucket_date'] ?? 'unknown');
        if (!isset($days[$day])) $days[$day] = ['date' => $day, 'recommendations' => 0, 'approvals' => 0, 'executions' => 0, 'messages' => 0, 'followups' => 0, 'events_total' => 0];
        $days[$day]['events_total']++;
        $type = (string)$row['event_type'];
        if ($type === 'crm.playbook.triggered') $days[$day]['recommendations']++;
        if ($type === 'crm.agent.approval.approved') $days[$day]['approvals']++;
        if ($type === 'crm.agent.execution.completed') $days[$day]['executions']++;
        if ($type === 'crm.agent.message.sent') $days[$day]['messages']++;
        if ($type === 'crm.followup.created' || $type === 'crm.agent.approval.task_created' || $type === 'crm.agent.message.followup_created') $days[$day]['followups']++;
    }
    ksort($days);
    return array_values(array_slice($days, -30, null, true));
}

function mg_agent_analytics_recent_events(array $rows): array
{
    return array_slice(array_map(static function (array $row): array {
        return [
            'id' => (string)$row['id'],
            'event_type' => (string)$row['event_type'],
            'label' => mg_customer_agent_timeline_label((string)$row['event_type']),
            'group' => (string)$row['group'],
            'playbook_title' => (string)$row['playbook_title'],
            'customer_name' => (string)$row['customer_name'],
            'customer_email' => (string)$row['customer_email'],
            'result_summary' => (string)$row['result_summary'],
            'created_at' => $row['created_at'] ?? null,
            'action_url' => mg_customer_agent_timeline_url((string)$row['event_type']),
        ];
    }, $rows), 0, 50);
}

function mg_agent_analytics(PDO $pdo, int $merchantId, array $input = []): array
{
    $rows = mg_agent_analytics_rows($pdo, $merchantId, $input);
    return [
        'summary' => mg_agent_analytics_summary($rows),
        'by_playbook' => mg_agent_analytics_grouped($rows, 'playbook_key', 'playbook_title'),
        'by_campaign' => mg_agent_analytics_grouped($rows, 'campaign_title', 'campaign_title'),
        'by_customer' => mg_agent_analytics_customer_breakdown($rows),
        'by_event_type' => mg_agent_analytics_grouped($rows, 'event_type'),
        'daily' => mg_agent_analytics_daily($rows),
        'recent_events' => mg_agent_analytics_recent_events($rows),
        'links' => [
            'agent_monitor' => '/merchant-agent-monitor.php',
            'review_queue' => '/merchant-agent-approvals.php',
            'execution_center' => '/merchant-agent-execution.php',
            'message_outbox' => '/merchant-agent-messages.php',
            'customer_timeline' => '/merchant-customer.php?tab=timeline',
        ],
        'days' => max(1, min(365, (int)($input['days'] ?? 90))),
    ];
}
