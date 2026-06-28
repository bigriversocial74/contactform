<?php
declare(strict_types=1);

require_once __DIR__ . '/ai/merchant-plan-actions.php';

function mg_agent_digest_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_agent_digest_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function mg_agent_digest_clean(mixed $value, int $max = 500): string
{
    $text = trim((string) $value);
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return mb_substr($text, 0, $max);
}

function mg_agent_digest_notification_id(int $merchantId, string $sourceType, string $sourceId): string
{
    return 'agn_' . substr(hash('sha256', $merchantId . '|' . $sourceType . '|' . $sourceId), 0, 24);
}

function mg_agent_digest_decisions(PDO $pdo, int $merchantId): array
{
    $types = ['merchant.agent_notification.read','merchant.agent_notification.archived'];
    $in = implode(',', array_fill(0, count($types), '?'));
    $decisions = [];
    try {
        $stmt = $pdo->prepare("SELECT event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type IN ({$in}) ORDER BY id DESC LIMIT 500");
        $stmt->execute(array_merge([$merchantId], $types));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ctx = mg_agent_digest_json($row['event_context_json'] ?? null);
            $id = (string)($ctx['notification_id'] ?? '');
            if ($id === '' || isset($decisions[$id])) continue;
            $decisions[$id] = [
                'event_type' => (string)$row['event_type'],
                'archived' => (string)$row['event_type'] === 'merchant.agent_notification.archived',
                'read' => true,
                'created_at' => $row['created_at'] ?? null,
            ];
        }
    } catch (Throwable) {}
    return $decisions;
}

function mg_agent_digest_action_url(string $type, array $payload = []): string
{
    if ($type === 'pending_review') return '/merchant-agent-approvals.php';
    if ($type === 'failed_execution') return '/merchant-agent-execution.php?filter=failed';
    if ($type === 'completed_result') {
        $url = trim((string)($payload['url'] ?? ''));
        return $url !== '' && str_starts_with($url, '/') && !str_starts_with($url, '//') ? $url : '/merchant-agent-execution.php?filter=completed';
    }
    if ($type === 'chat_bridge') return '/merchant-agent-chat.php';
    if ($type === 'package_created') return '/merchant-agent-approvals.php';
    return '/merchant-agent-chat.php';
}

function mg_agent_digest_item(array $args): array
{
    $item = [
        'id' => (string)($args['id'] ?? ''),
        'source_type' => (string)($args['source_type'] ?? 'agent'),
        'source_id' => (string)($args['source_id'] ?? ''),
        'kind' => (string)($args['kind'] ?? 'agent'),
        'status' => (string)($args['status'] ?? 'new'),
        'severity' => (string)($args['severity'] ?? 'info'),
        'title' => mg_agent_digest_clean($args['title'] ?? 'Agent notification', 160),
        'body' => mg_agent_digest_clean($args['body'] ?? '', 700),
        'action_label' => mg_agent_digest_clean($args['action_label'] ?? 'Open', 80),
        'action_url' => (string)($args['action_url'] ?? '/merchant-agent-chat.php'),
        'source_url' => (string)($args['source_url'] ?? ''),
        'result_url' => (string)($args['result_url'] ?? ''),
        'created_at' => $args['created_at'] ?? null,
        'context' => is_array($args['context'] ?? null) ? $args['context'] : [],
    ];
    $item['is_unread'] = empty($args['read']);
    $item['is_archived'] = !empty($args['archived']);
    return $item;
}

function mg_agent_digest_ai_plan_items(PDO $pdo, int $merchantId): array
{
    $items = [];
    try {
        $stmt = $pdo->prepare("SELECT i.public_id,i.action_key,i.status,i.title,i.reason,i.risk_level,i.confidence,i.suggested_payload_json,i.created_at,i.updated_at,p.public_id plan_public_id
            FROM ai_merchant_plan_items i
            INNER JOIN ai_merchant_plans p ON p.id=i.plan_id
            WHERE p.merchant_user_id=? AND i.status IN ('recommended','deferred','failed','executed','rejected')
            ORDER BY i.updated_at DESC,i.id DESC LIMIT 100");
        $stmt->execute([$merchantId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = (string)$row['status'];
            $payload = mg_agent_digest_json($row['suggested_payload_json'] ?? null);
            $execution = [];
            if ($status === 'executed') {
                $eventStmt = $pdo->prepare("SELECT event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type='merchant.ai_plan_item.executed' AND event_context_json LIKE ? ORDER BY id DESC LIMIT 1");
                $eventStmt->execute([$merchantId, '%' . $row['public_id'] . '%']);
                $eventRow = $eventStmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($eventRow)) {
                    $eventCtx = mg_agent_digest_json($eventRow['event_context_json'] ?? null);
                    $execution = is_array($eventCtx['execution'] ?? null) ? $eventCtx['execution'] : [];
                }
            }
            $source = (string)($payload['source'] ?? 'ai_merchant_plan');
            $sourceLabel = str_replace('_', ' ', $source);
            if ($source === 'merchant_agent_chat') $sourceLabel = 'Agent Chat';
            if ($source === 'agent_draft_package') $sourceLabel = 'Draft Package';
            $kind = match ($status) {
                'recommended', 'deferred' => 'pending_review',
                'failed' => 'failed_execution',
                'executed' => 'completed_result',
                'rejected' => 'archived_result',
                default => 'agent',
            };
            $severity = match ($status) {
                'failed' => 'high',
                'recommended', 'deferred' => 'medium',
                'executed' => 'success',
                default => 'info',
            };
            $resultUrl = (string)($execution['url'] ?? '');
            $items[] = mg_agent_digest_item([
                'id' => mg_agent_digest_notification_id($merchantId, 'ai_plan_item', (string)$row['public_id']),
                'source_type' => 'ai_plan_item',
                'source_id' => (string)$row['public_id'],
                'kind' => $kind,
                'status' => $status,
                'severity' => $severity,
                'title' => $status === 'executed' ? 'Agent result created: ' . $row['title'] : 'Agent review item: ' . $row['title'],
                'body' => ($sourceLabel ? $sourceLabel . ': ' : '') . (string)($row['reason'] ?? $payload['reason'] ?? 'Agent recommendation updated.'),
                'action_label' => $status === 'executed' ? 'Open result' : 'Open review queue',
                'action_url' => $status === 'executed' ? mg_agent_digest_action_url('completed_result', $execution) : mg_agent_digest_action_url($kind),
                'source_url' => !empty($payload['source_chat_message_id']) ? '/merchant-agent-chat.php?chat=' . rawurlencode((string)$payload['source_chat_message_id']) : '/merchant-agent-chat.php',
                'result_url' => $resultUrl,
                'created_at' => $row['updated_at'] ?? $row['created_at'] ?? null,
                'context' => [
                    'plan_id' => (string)$row['plan_public_id'],
                    'item_id' => (string)$row['public_id'],
                    'action_key' => (string)$row['action_key'],
                    'risk_level' => (string)$row['risk_level'],
                    'confidence' => $row['confidence'] !== null ? (float)$row['confidence'] : null,
                    'resource_type' => (string)($execution['resource_type'] ?? ''),
                    'resource_id' => (string)($execution['resource_id'] ?? ''),
                ],
            ]);
        }
    } catch (Throwable) {}
    return $items;
}

function mg_agent_digest_events(PDO $pdo, int $merchantId): array
{
    $events = [];
    $types = ['merchant.agent_chat.sent_to_review','merchant.agent_package.created','merchant.agent_briefing.created','crm.agent.execution.failed','crm.agent.execution.completed'];
    $in = implode(',', array_fill(0, count($types), '?'));
    try {
        $stmt = $pdo->prepare("SELECT public_id,event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type IN ({$in}) ORDER BY id DESC LIMIT 100");
        $stmt->execute(array_merge([$merchantId], $types));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ctx = mg_agent_digest_json($row['event_context_json'] ?? null);
            $type = (string)$row['event_type'];
            $kind = str_contains($type, 'failed') ? 'failed_execution' : (str_contains($type, 'completed') ? 'completed_result' : 'agent_update');
            $title = match ($type) {
                'merchant.agent_chat.sent_to_review' => 'Agent card sent to review',
                'merchant.agent_package.created' => 'Draft package created',
                'merchant.agent_briefing.created' => 'Daily briefing created',
                'crm.agent.execution.failed' => 'Agent execution failed',
                'crm.agent.execution.completed' => 'Agent execution completed',
                default => 'Agent update',
            };
            $events[] = mg_agent_digest_item([
                'id' => mg_agent_digest_notification_id($merchantId, 'campaign_event', (string)$row['public_id']),
                'source_type' => 'campaign_event',
                'source_id' => (string)$row['public_id'],
                'kind' => $kind,
                'status' => $type,
                'severity' => str_contains($type, 'failed') ? 'high' : 'info',
                'title' => $title,
                'body' => mg_agent_digest_clean($ctx['title'] ?? $ctx['result_summary'] ?? $ctx['note'] ?? $type, 500),
                'action_label' => str_contains($type, 'execution') ? 'Open execution center' : 'Open agent workspace',
                'action_url' => str_contains($type, 'execution') ? '/merchant-agent-execution.php' : '/merchant-agent-chat.php',
                'source_url' => '/merchant-agent-chat.php',
                'result_url' => (string)($ctx['execution']['url'] ?? ''),
                'created_at' => $row['created_at'] ?? null,
                'context' => $ctx,
            ]);
        }
    } catch (Throwable) {}
    return $events;
}

function mg_agent_digest_apply_decisions(array $items, array $decisions, string $filter = 'all'): array
{
    foreach ($items as &$item) {
        $decision = $decisions[$item['id']] ?? [];
        if (!empty($decision)) {
            $item['is_unread'] = false;
            $item['is_archived'] = !empty($decision['archived']);
        }
    }
    unset($item);
    return array_values(array_filter($items, static function (array $item) use ($filter): bool {
        if ($filter !== 'archived' && !empty($item['is_archived'])) return false;
        return match ($filter) {
            'unread' => !empty($item['is_unread']),
            'pending' => (string)$item['kind'] === 'pending_review',
            'results' => (string)$item['kind'] === 'completed_result',
            'failed' => (string)$item['kind'] === 'failed_execution',
            'archived' => !empty($item['is_archived']),
            default => true,
        };
    }));
}

function mg_agent_digest_items(PDO $pdo, int $merchantId, string $filter = 'all', int $limit = 50): array
{
    $filter = in_array($filter, ['all','unread','pending','results','failed','archived'], true) ? $filter : 'all';
    $items = array_merge(mg_agent_digest_ai_plan_items($pdo, $merchantId), mg_agent_digest_events($pdo, $merchantId));
    $items = mg_agent_digest_apply_decisions($items, mg_agent_digest_decisions($pdo, $merchantId), $filter);
    usort($items, static function (array $a, array $b): int {
        return (strtotime((string)($b['created_at'] ?? '')) ?: 0) <=> (strtotime((string)($a['created_at'] ?? '')) ?: 0);
    });
    return array_slice($items, 0, max(1, min(100, $limit)));
}

function mg_agent_digest_counts(PDO $pdo, int $merchantId): array
{
    $all = mg_agent_digest_items($pdo, $merchantId, 'all', 100);
    $archived = mg_agent_digest_items($pdo, $merchantId, 'archived', 100);
    $counts = ['all' => count($all), 'unread' => 0, 'pending' => 0, 'results' => 0, 'failed' => 0, 'archived' => count($archived)];
    foreach ($all as $item) {
        if (!empty($item['is_unread'])) $counts['unread']++;
        if ((string)$item['kind'] === 'pending_review') $counts['pending']++;
        if ((string)$item['kind'] === 'completed_result') $counts['results']++;
        if ((string)$item['kind'] === 'failed_execution') $counts['failed']++;
    }
    $counts['pending_reviews'] = $counts['pending'];
    $counts['completed_results'] = $counts['results'];
    $counts['failed_executions'] = $counts['failed'];
    $counts['unread_agent_notifications'] = $counts['unread'];
    return $counts;
}

function mg_agent_digest_record_decision(PDO $pdo, int $merchantId, int $userId, string $notificationId, string $action): void
{
    $action = strtolower(trim($action));
    if ($notificationId === '' || !in_array($action, ['mark_read','archive'], true)) mg_fail('Valid agent notification action is required.', 422);
    $eventType = $action === 'archive' ? 'merchant.agent_notification.archived' : 'merchant.agent_notification.read';
    $publicId = mg_agent_digest_uuid();
    $ctx = ['notification_id' => $notificationId, 'action' => $action, 'acted_by_user_id' => $userId];
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
        ->execute([$publicId, $merchantId, null, null, $eventType, json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
}

function mg_agent_digest_response(PDO $pdo, int $merchantId, string $filter = 'all', int $limit = 50): array
{
    return ['filter' => $filter, 'counts' => mg_agent_digest_counts($pdo, $merchantId), 'items' => mg_agent_digest_items($pdo, $merchantId, $filter, $limit)];
}
