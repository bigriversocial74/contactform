<?php
declare(strict_types=1);

require_once __DIR__ . '/_trigger_zones.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

function mg_canvas_trigger_analytics_json(mixed $value): array
{
    if (is_array($value)) return $value;
    $raw = trim((string)$value);
    if ($raw === '') return [];
    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable) {
        return [];
    }
}

function mg_canvas_trigger_analytics_bool(array $data, string $key): bool
{
    $value = $data[$key] ?? null;
    if (is_bool($value)) return $value;
    if (is_numeric($value)) return (int)$value > 0;
    return trim((string)$value) !== '';
}

try {
    mg_rate_limit('merchant_canvas.trigger_zone_analytics', 'user:' . (int)$user['id'], 180, 60);
    mg_store_canvas_require_tables($pdo, ['mg_store_sessions','mg_store_session_events','mg_customer_store_history'], 'Store Canvas analytics');
    $merchantUserId = (int)$user['id'];
    $zoneId = mg_store_safe_public_id($_GET['zone_id'] ?? '', 'Trigger zone');
    $zone = mg_canvas_trigger_zone_load($pdo, $merchantUserId, $zoneId);
    if (!$zone) {
        $zones = mg_canvas_trigger_zone_list($pdo, $merchantUserId);
        foreach ($zones as $candidate) {
            if ((string)($candidate['id'] ?? '') === $zoneId) {
                $zone = $candidate;
                break;
            }
        }
    }
    if (!$zone) throw new RuntimeException('Trigger zone is not available.');

    $stmt = $pdo->prepare(
        "SELECT e.public_id,e.store_session_id,e.customer_user_id,e.event_label,e.event_data_json,e.created_at,
                s.public_id session_public_id,s.source_feed_post_id,
                cp.display_name customer_name,cp.avatar_url customer_avatar_url
         FROM mg_store_session_events e
         LEFT JOIN mg_store_sessions s ON s.id=e.store_session_id
         LEFT JOIN public_profiles cp ON cp.user_id=e.customer_user_id
         WHERE e.merchant_user_id=? AND e.event_type='campaign_trigger_zone'
         ORDER BY e.created_at DESC,e.id DESC
         LIMIT 500"
    );
    $stmt->execute([$merchantUserId]);

    $events = [];
    $stats = [
        'fires' => 0,
        'messages_sent' => 0,
        'rewards_sent' => 0,
        'stamp_debits' => 0,
        'stamp_debit_errors' => 0,
        'unique_customers' => 0,
        'last_triggered_at' => null,
    ];
    $customers = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $data = mg_canvas_trigger_analytics_json($row['event_data_json'] ?? null);
        if ((string)($data['trigger_zone_id'] ?? '') !== $zoneId) continue;
        $stats['fires']++;
        if ($stats['last_triggered_at'] === null) $stats['last_triggered_at'] = (string)$row['created_at'];
        $customerId = (int)$row['customer_user_id'];
        $customers[$customerId] = true;
        $messageSent = mg_canvas_trigger_analytics_bool($data, 'message_id') || mg_canvas_trigger_analytics_bool($data, 'message_sent');
        $rewardSent = mg_canvas_trigger_analytics_bool($data, 'wallet_item_id') || mg_canvas_trigger_analytics_bool($data, 'reward_sent');
        $stampDebited = mg_canvas_trigger_analytics_bool($data, 'stamp_ledger_entry_id') || !empty($data['stamp_debited']);
        $stampDebitError = trim((string)($data['stamp_debit_error'] ?? '')) !== '';
        if ($messageSent) $stats['messages_sent']++;
        if ($rewardSent) $stats['rewards_sent']++;
        if ($stampDebited) $stats['stamp_debits']++;
        if ($stampDebitError) $stats['stamp_debit_errors']++;
        if (count($events) < 60) {
            $name = trim((string)($row['customer_name'] ?? '')) ?: ('Customer #' . $customerId);
            $events[] = [
                'id' => (string)$row['public_id'],
                'session_id' => (string)($row['session_public_id'] ?? ''),
                'customer_user_id' => $customerId,
                'customer_name' => $name,
                'customer_avatar_url' => (string)($row['customer_avatar_url'] ?? ''),
                'event_label' => (string)($row['event_label'] ?? 'Campaign trigger zone'),
                'created_at' => (string)$row['created_at'],
                'message_sent' => $messageSent,
                'reward_sent' => $rewardSent,
                'stamp_debited' => $stampDebited,
                'stamp_debit_error' => $stampDebitError ? (string)$data['stamp_debit_error'] : '',
                'message_id' => (string)($data['message_id'] ?? ''),
                'wallet_item_id' => (string)($data['wallet_item_id'] ?? ''),
                'stamp_ledger_entry_id' => (string)($data['stamp_ledger_entry_id'] ?? ''),
                'campaign_id' => (string)($data['campaign_id'] ?? $data['selected_campaign_id'] ?? ''),
                'campaign_title' => (string)($data['campaign_title'] ?? ''),
                'priority' => (int)($data['trigger_priority'] ?? $zone['priority'] ?? 3),
            ];
        }
    }
    $stats['unique_customers'] = count($customers);

    $conversionRate = $stats['fires'] > 0 ? round(($stats['rewards_sent'] / $stats['fires']) * 100, 1) : 0.0;
    mg_ok([
        'zone' => mg_canvas_trigger_zone_public($zone + ['campaign_title' => $zone['campaign_title'] ?? null]),
        'stats' => $stats + ['reward_fire_rate' => $conversionRate],
        'events' => $events,
        'stamp_action_key' => 'store_canvas_auto_message_send',
        'checked_at' => date('Y-m-d H:i:s'),
    ]);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.trigger_zone_analytics_failed', 'Store Canvas trigger analytics failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], (int)$user['id']);
    mg_fail('Unable to load trigger analytics.', 500);
}
