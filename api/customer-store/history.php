<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$userId = (int)$user['id'];

function mg_customer_store_event_label(string $type, ?string $label): string
{
    $label = trim((string)$label);
    if ($label !== '') return $label;
    return match ($type) {
        'entered_store', 'store_entered' => 'Entered store',
        'message_received' => 'Merchant message received',
        'reward_sent', 'received_reward' => 'Reward received',
        'claimed_reward' => 'Reward claimed',
        'viewed_product' => 'Viewed product',
        'sent_gift' => 'Gift sent',
        'store_exited', 'exited_store' => 'Exited store',
        default => ucwords(str_replace(['_', '-'], ' ', $type)),
    };
}

function mg_customer_store_event_project(array $row): array
{
    $metadata = [];
    $raw = trim((string)($row['event_data_json'] ?? ''));
    if ($raw !== '') {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) $metadata = $decoded;
        } catch (Throwable) {}
    }
    return [
        'id' => (string)$row['public_id'],
        'type' => (string)$row['event_type'],
        'label' => mg_customer_store_event_label((string)$row['event_type'], $row['event_label'] ?? null),
        'created_at' => $row['created_at'] ?? null,
        'wallet_item_id' => (string)($metadata['wallet_item_id'] ?? ''),
        'campaign_id' => (string)($metadata['campaign_id'] ?? ''),
        'reward_template_id' => (string)($metadata['reward_template_id'] ?? ''),
        'message_id' => (string)($metadata['message_id'] ?? ''),
        'source_system' => (string)($metadata['source_system'] ?? 'store_canvas'),
    ];
}

function mg_customer_store_visit_project(array $row, array $events): array
{
    $status = (string)($row['status'] ?? '');
    $started = (string)($row['started_at'] ?? $row['entered_at'] ?? '');
    $ended = $row['ended_at'] ?? $row['exited_at'] ?? null;
    $duration = (int)($row['duration_seconds'] ?? 0);
    if ($duration <= 0 && $started !== '') {
        $start = strtotime($started);
        $end = $ended ? strtotime((string)$ended) : time();
        if ($start !== false && $end !== false) $duration = max(0, $end - $start);
    }
    $summary = trim((string)($row['summary'] ?? ''));
    if ($summary === '') $summary = 'Visited ' . (trim((string)($row['merchant_name'] ?? '')) ?: 'merchant store');
    return [
        'id' => (string)($row['history_public_id'] ?? ''),
        'session_id' => (string)$row['session_public_id'],
        'summary' => $summary,
        'status' => $status,
        'started_at' => $started,
        'ended_at' => $ended,
        'duration_seconds' => $duration,
        'exit_reason' => $row['exit_reason'] ?? null,
        'merchant' => [
            'id' => (string)($row['merchant_user_id'] ?? ''),
            'name' => trim((string)($row['merchant_name'] ?? '')) ?: 'Merchant Store',
            'slug' => (string)($row['merchant_slug'] ?? ''),
            'avatar_url' => mg_store_avatar_url($row['merchant_avatar_url'] ?? null),
        ],
        'source_post' => [
            'id' => $row['source_post_public_id'] !== null ? (string)$row['source_post_public_id'] : null,
            'headline' => $row['source_post_headline'] !== null ? (string)$row['source_post_headline'] : null,
        ],
        'counts' => [
            'messages_received' => (int)($row['messages_received_count'] ?? $row['messages_event_count'] ?? 0),
            'rewards_received' => (int)($row['rewards_received_count'] ?? $row['rewards_event_count'] ?? 0),
            'rewards_claimed' => (int)($row['rewards_claimed_count'] ?? $row['claims_event_count'] ?? 0),
            'products_viewed' => (int)($row['products_viewed_count'] ?? $row['products_event_count'] ?? 0),
            'gifts_sent' => (int)($row['gifts_sent_count'] ?? $row['gifts_event_count'] ?? 0),
        ],
        'links' => [
            'inbox' => '/inbox.php?store_session=' . rawurlencode((string)$row['session_public_id']),
            'messages' => '/messages.php?source=store_canvas&session=' . rawurlencode((string)$row['session_public_id']),
        ],
        'events' => $events,
    ];
}

try {
    if (!mg_store_canvas_schema_ready($pdo)) {
        mg_ok(['schema_ready'=>false,'visits'=>[],'summary'=>['visits'=>0,'messages'=>0,'rewards'=>0,'claims'=>0]], 'Store Canvas tables are not installed.');
    }

    $sessionFilter = strtolower(trim((string)($_GET['session'] ?? $_GET['session_id'] ?? '')));
    if ($sessionFilter !== '' && (strlen($sessionFilter) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $sessionFilter))) mg_fail('Invalid store session.', 422);
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

    $where = 's.customer_user_id = ?';
    $params = [$userId];
    if ($sessionFilter !== '') {
        $where .= ' AND s.public_id = ?';
        $params[] = $sessionFilter;
    }

    $sql = "SELECT s.id session_db_id,s.public_id session_public_id,s.customer_user_id,s.merchant_user_id,s.status,s.entered_at,s.last_active_at,s.exited_at,s.exit_reason,
                   h.public_id history_public_id,h.summary,h.started_at,h.ended_at,h.duration_seconds,h.messages_received_count,h.rewards_received_count,h.rewards_claimed_count,h.products_viewed_count,h.gifts_sent_count,
                   mp.display_name merchant_name,mp.slug merchant_slug,mp.avatar_url merchant_avatar_url,
                   fp.public_id source_post_public_id,fp.headline source_post_headline,
                   (SELECT COUNT(*) FROM mg_store_session_events e WHERE e.store_session_id=s.id AND e.event_type='message_received') messages_event_count,
                   (SELECT COUNT(*) FROM mg_store_session_events e WHERE e.store_session_id=s.id AND e.event_type IN ('reward_sent','received_reward')) rewards_event_count,
                   (SELECT COUNT(*) FROM mg_store_session_events e WHERE e.store_session_id=s.id AND e.event_type='claimed_reward') claims_event_count,
                   (SELECT COUNT(*) FROM mg_store_session_events e WHERE e.store_session_id=s.id AND e.event_type='viewed_product') products_event_count,
                   (SELECT COUNT(*) FROM mg_store_session_events e WHERE e.store_session_id=s.id AND e.event_type='sent_gift') gifts_event_count
            FROM mg_store_sessions s
            LEFT JOIN mg_customer_store_history h ON h.store_session_id=s.id
            LEFT JOIN public_profiles mp ON mp.user_id=s.merchant_user_id
            LEFT JOIN feed_posts fp ON fp.id=s.source_feed_post_id
            WHERE {$where}
            ORDER BY s.entered_at DESC,s.id DESC
            LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $visits = [];
    $totals = ['visits'=>0,'messages'=>0,'rewards'=>0,'claims'=>0];
    $eventStmt = $pdo->prepare('SELECT public_id,event_type,event_label,event_data_json,created_at FROM mg_store_session_events WHERE store_session_id=? AND customer_user_id=? ORDER BY created_at ASC,id ASC LIMIT 100');
    foreach ($rows as $row) {
        $eventStmt->execute([(int)$row['session_db_id'], $userId]);
        $events = array_map('mg_customer_store_event_project', $eventStmt->fetchAll(PDO::FETCH_ASSOC));
        $visit = mg_customer_store_visit_project($row, $events);
        $visits[] = $visit;
        $totals['visits']++;
        $totals['messages'] += (int)$visit['counts']['messages_received'];
        $totals['rewards'] += (int)$visit['counts']['rewards_received'];
        $totals['claims'] += (int)$visit['counts']['rewards_claimed'];
    }

    mg_ok(['schema_ready'=>true,'visits'=>$visits,'summary'=>$totals]);
} catch (Throwable $error) {
    mg_security_log('error', 'customer_store.history_failed', 'Customer store history lookup failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], $userId);
    mg_fail('Unable to load Store History.', 500);
}
