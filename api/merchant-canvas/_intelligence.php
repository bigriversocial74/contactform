<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_schema.php';
require_once __DIR__ . '/_trigger_zones.php';

function mg_canvas_intel_tables(PDO $pdo): array
{
    return [
        'settings' => mg_store_canvas_table_exists($pdo, 'mg_store_canvas_settings'),
        'simulations' => mg_store_canvas_table_exists($pdo, 'mg_store_trigger_rule_simulations'),
        'journeys' => mg_store_canvas_table_exists($pdo, 'mg_store_customer_journey_snapshots'),
        'sessions' => mg_store_canvas_table_exists($pdo, 'mg_store_sessions'),
        'events' => mg_store_canvas_table_exists($pdo, 'mg_store_session_events'),
        'triggers' => mg_store_canvas_table_exists($pdo, 'mg_store_trigger_zones'),
    ];
}

function mg_canvas_intel_default_settings(int $merchantUserId): array
{
    return [
        'merchant_user_id' => $merchantUserId,
        'canvas_mode' => 'live',
        'activity_drawer_open' => false,
        'safety_drawer_open' => false,
        'overlay_zone_metrics' => true,
        'overlay_customer_paths' => true,
        'overlay_customer_badges' => true,
        'metadata' => [],
        'persisted' => false,
    ];
}

function mg_canvas_intel_safe_mode(mixed $value): string
{
    $mode = trim((string)$value);
    return in_array($mode, ['live','edit','campaigns','paths','analytics'], true) ? $mode : 'live';
}

function mg_canvas_intel_bool(mixed $value): int
{
    return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true) ? 1 : 0;
}

function mg_canvas_intel_json(?string $raw): array
{
    $raw = trim((string)$raw);
    if ($raw === '') return [];
    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable) {
        return [];
    }
}

function mg_canvas_intel_settings(PDO $pdo, int $merchantUserId): array
{
    $defaults = mg_canvas_intel_default_settings($merchantUserId);
    if (!mg_store_canvas_table_exists($pdo, 'mg_store_canvas_settings')) {
        $defaults['schema_ready'] = false;
        return $defaults;
    }

    $stmt = $pdo->prepare('SELECT * FROM mg_store_canvas_settings WHERE merchant_user_id=? LIMIT 1');
    $stmt->execute([$merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        try {
            $insert = $pdo->prepare('INSERT INTO mg_store_canvas_settings (public_id,merchant_user_id,created_at,updated_at) VALUES (?,?,NOW(),NOW())');
            $insert->execute([mg_public_uuid(), $merchantUserId]);
        } catch (Throwable) {}
        $defaults['schema_ready'] = true;
        return $defaults;
    }

    return [
        'merchant_user_id' => $merchantUserId,
        'canvas_mode' => mg_canvas_intel_safe_mode($row['canvas_mode'] ?? 'live'),
        'activity_drawer_open' => !empty($row['activity_drawer_open']),
        'safety_drawer_open' => !empty($row['safety_drawer_open']),
        'overlay_zone_metrics' => !empty($row['overlay_zone_metrics']),
        'overlay_customer_paths' => !empty($row['overlay_customer_paths']),
        'overlay_customer_badges' => !empty($row['overlay_customer_badges']),
        'metadata' => mg_canvas_intel_json($row['metadata_json'] ?? null),
        'persisted' => true,
        'schema_ready' => true,
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

function mg_canvas_intel_save_settings(PDO $pdo, int $merchantUserId, array $input): array
{
    if (!mg_store_canvas_table_exists($pdo, 'mg_store_canvas_settings')) {
        throw new RuntimeException('Store Canvas persistence tables are not installed. Run database/stage_20f_store_canvas_persistence.sql.');
    }
    $mode = mg_canvas_intel_safe_mode($input['canvas_mode'] ?? 'live');
    $activity = mg_canvas_intel_bool($input['activity_drawer_open'] ?? 0);
    $safety = mg_canvas_intel_bool($input['safety_drawer_open'] ?? 0);
    $zoneMetrics = mg_canvas_intel_bool($input['overlay_zone_metrics'] ?? 1);
    $paths = mg_canvas_intel_bool($input['overlay_customer_paths'] ?? 1);
    $badges = mg_canvas_intel_bool($input['overlay_customer_badges'] ?? 1);
    $metadata = $input['metadata'] ?? [];
    if (!is_array($metadata)) $metadata = [];
    $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $stmt = $pdo->prepare(
        "INSERT INTO mg_store_canvas_settings
         (public_id,merchant_user_id,canvas_mode,activity_drawer_open,safety_drawer_open,overlay_zone_metrics,overlay_customer_paths,overlay_customer_badges,metadata_json,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE canvas_mode=VALUES(canvas_mode),activity_drawer_open=VALUES(activity_drawer_open),safety_drawer_open=VALUES(safety_drawer_open),overlay_zone_metrics=VALUES(overlay_zone_metrics),overlay_customer_paths=VALUES(overlay_customer_paths),overlay_customer_badges=VALUES(overlay_customer_badges),metadata_json=VALUES(metadata_json),updated_at=NOW()"
    );
    $stmt->execute([mg_public_uuid(), $merchantUserId, $mode, $activity, $safety, $zoneMetrics, $paths, $badges, $metadataJson]);
    return mg_canvas_intel_settings($pdo, $merchantUserId);
}

function mg_canvas_intel_count(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function mg_canvas_intel_zone_metrics(PDO $pdo, int $merchantUserId): array
{
    if (!mg_store_canvas_table_exists($pdo, 'mg_store_trigger_zones')) return [];
    $stmt = $pdo->prepare('SELECT public_id,name,priority,status,campaign_public_id,last_triggered_at FROM mg_store_trigger_zones WHERE merchant_user_id=? ORDER BY priority DESC,updated_at DESC LIMIT 100');
    $stmt->execute([$merchantUserId]);
    $zones = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $zone) {
        $zoneId = (string)$zone['public_id'];
        $like = '%' . $zoneId . '%';
        $fires = mg_canvas_intel_count($pdo, "SELECT COUNT(*) FROM mg_store_session_events WHERE merchant_user_id=? AND event_type='campaign_trigger_zone' AND event_data_json LIKE ? AND created_at >= CURDATE()", [$merchantUserId, $like]);
        $messages = mg_canvas_intel_count($pdo, "SELECT COUNT(*) FROM mg_store_session_events WHERE merchant_user_id=? AND event_type='campaign_trigger_zone' AND event_data_json LIKE ? AND event_data_json LIKE '%message_sent%true%' AND created_at >= CURDATE()", [$merchantUserId, $like]);
        $rewards = mg_canvas_intel_count($pdo, "SELECT COUNT(*) FROM mg_store_session_events WHERE merchant_user_id=? AND event_type='campaign_trigger_zone' AND event_data_json LIKE ? AND event_data_json LIKE '%reward_sent%true%' AND created_at >= CURDATE()", [$merchantUserId, $like]);
        $simBlocks = mg_store_canvas_table_exists($pdo, 'mg_store_trigger_rule_simulations') ? mg_canvas_intel_count($pdo, 'SELECT COUNT(*) FROM mg_store_trigger_rule_simulations WHERE merchant_user_id=? AND trigger_zone_public_id=? AND would_block_cooldown=1 AND created_at >= CURDATE()', [$merchantUserId, $zoneId]) : 0;
        $entered = mg_canvas_intel_count($pdo, "SELECT COUNT(DISTINCT store_session_id) FROM mg_store_session_events WHERE merchant_user_id=? AND event_data_json LIKE ? AND created_at >= CURDATE()", [$merchantUserId, $like]);
        $zones[] = [
            'id' => $zoneId,
            'name' => (string)$zone['name'],
            'priority' => (int)$zone['priority'],
            'status' => (string)$zone['status'],
            'campaign_id' => $zone['campaign_public_id'] !== null ? (string)$zone['campaign_public_id'] : null,
            'last_triggered_at' => $zone['last_triggered_at'] !== null ? (string)$zone['last_triggered_at'] : null,
            'today' => ['entered'=>$entered, 'fires'=>$fires, 'cooldown_blocks'=>$simBlocks, 'messages_sent'=>$messages, 'rewards_sent'=>$rewards, 'claims'=>0],
        ];
    }
    return $zones;
}

function mg_canvas_intel_activity(PDO $pdo, int $merchantUserId, int $limit = 30): array
{
    if (!mg_store_canvas_table_exists($pdo, 'mg_store_session_events')) return [];
    $limit = max(1, min(80, $limit));
    $stmt = $pdo->prepare(
        "SELECT e.public_id,e.event_type,e.event_label,e.event_data_json,e.created_at,
                s.public_id store_session_public_id,cp.display_name customer_name,cp.avatar_url customer_avatar_url
         FROM mg_store_session_events e
         LEFT JOIN mg_store_sessions s ON s.id=e.store_session_id
         LEFT JOIN public_profiles cp ON cp.user_id=e.customer_user_id
         WHERE e.merchant_user_id=?
         ORDER BY e.id DESC LIMIT {$limit}"
    );
    $stmt->execute([$merchantUserId]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
            'id' => (string)$row['public_id'],
            'type' => (string)$row['event_type'],
            'label' => (string)($row['event_label'] ?: $row['event_type']),
            'created_at' => (string)$row['created_at'],
            'session_id' => $row['store_session_public_id'] !== null ? (string)$row['store_session_public_id'] : null,
            'customer' => ['name'=>(string)($row['customer_name'] ?: 'Customer'), 'avatar_url'=>mg_store_avatar_url($row['customer_avatar_url'] ?? null)],
            'metadata' => mg_canvas_intel_json($row['event_data_json'] ?? null),
        ];
    }
    return $items;
}

function mg_canvas_intel_journeys(PDO $pdo, int $merchantUserId): array
{
    if (!mg_store_canvas_table_exists($pdo, 'mg_store_sessions')) return [];
    $stmt = $pdo->prepare(
        "SELECT s.id,s.public_id,s.customer_user_id,s.status,s.entered_at,s.exited_at,cp.display_name customer_name
         FROM mg_store_sessions s
         LEFT JOIN public_profiles cp ON cp.user_id=s.customer_user_id
         WHERE s.merchant_user_id=?
         ORDER BY s.last_active_at DESC,s.id DESC LIMIT 30"
    );
    $stmt->execute([$merchantUserId]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $session) {
        $events = [];
        if (mg_store_canvas_table_exists($pdo, 'mg_store_session_events')) {
            $eventStmt = $pdo->prepare('SELECT event_type,event_label,event_data_json,created_at FROM mg_store_session_events WHERE store_session_id=? ORDER BY id ASC LIMIT 20');
            $eventStmt->execute([(int)$session['id']]);
            foreach ($eventStmt->fetchAll(PDO::FETCH_ASSOC) as $event) {
                $events[] = ['type'=>(string)$event['event_type'], 'label'=>(string)($event['event_label'] ?: $event['event_type']), 'created_at'=>(string)$event['created_at'], 'metadata'=>mg_canvas_intel_json($event['event_data_json'] ?? null)];
            }
        }
        $items[] = [
            'session_id' => (string)$session['public_id'],
            'customer_user_id' => (int)$session['customer_user_id'],
            'customer_name' => (string)($session['customer_name'] ?: 'Customer'),
            'status' => (string)$session['status'],
            'entered_at' => (string)$session['entered_at'],
            'exited_at' => $session['exited_at'] !== null ? (string)$session['exited_at'] : null,
            'events' => $events,
        ];
    }
    return $items;
}

function mg_canvas_intel_safety(PDO $pdo, int $merchantUserId): array
{
    $tables = mg_canvas_intel_tables($pdo);
    $zones = $tables['triggers'] ? mg_canvas_intel_count($pdo, 'SELECT COUNT(*) FROM mg_store_trigger_zones WHERE merchant_user_id=?', [$merchantUserId]) : 0;
    $zonesWithoutCampaign = $tables['triggers'] ? mg_canvas_intel_count($pdo, "SELECT COUNT(*) FROM mg_store_trigger_zones WHERE merchant_user_id=? AND status='active' AND (campaign_public_id IS NULL OR campaign_public_id='')", [$merchantUserId]) : 0;
    $paused = $tables['triggers'] ? mg_canvas_intel_count($pdo, "SELECT COUNT(*) FROM mg_store_trigger_zones WHERE merchant_user_id=? AND status='paused'", [$merchantUserId]) : 0;
    $activeCustomers = $tables['sessions'] ? mg_canvas_intel_count($pdo, "SELECT COUNT(*) FROM mg_store_sessions WHERE merchant_user_id=? AND active_key IS NOT NULL AND status IN ('entered','active','idle')", [$merchantUserId]) : 0;
    return [
        'schema' => $tables,
        'score' => $zonesWithoutCampaign > 0 || !$tables['settings'] || !$tables['simulations'] ? 'needs_review' : 'ready',
        'checks' => [
            ['key'=>'persistence_tables','state'=>($tables['settings'] && $tables['simulations'] && $tables['journeys']) ? 'ready' : 'warn','label'=>'Persistence tables','detail'=>($tables['settings'] && $tables['simulations'] && $tables['journeys']) ? 'Stage 20F persistence tables are installed.' : 'Run database/stage_20f_store_canvas_persistence.sql.'],
            ['key'=>'campaign_assignments','state'=>$zonesWithoutCampaign ? 'warn' : 'ready','label'=>'Campaign assignments','detail'=>$zonesWithoutCampaign ? $zonesWithoutCampaign . ' active zone(s) missing campaigns.' : 'Active zones have campaign context.'],
            ['key'=>'paused_zones','state'=>$paused ? 'warn' : 'ready','label'=>'Paused zones','detail'=>$paused . ' paused trigger zone(s).'],
            ['key'=>'duplicate_protection','state'=>'ready','label'=>'Duplicate protection','detail'=>'Entry/cooldown guard and simulator checks available.'],
            ['key'=>'active_customers','state'=>$activeCustomers ? 'info' : 'ready','label'=>'Active customers','detail'=>$activeCustomers . ' active Store Canvas customer(s).'],
        ],
    ];
}

function mg_canvas_intel_simulate(PDO $pdo, int $merchantUserId, array $input): array
{
    if (!mg_store_canvas_table_exists($pdo, 'mg_store_trigger_rule_simulations')) {
        throw new RuntimeException('Rule simulator table is not installed. Run database/stage_20f_store_canvas_persistence.sql.');
    }
    $event = trim((string)($input['simulation_event'] ?? 'enter'));
    if (!in_array($event, ['enter','repeat','return','manual'], true)) $event = 'enter';
    $zonePublicId = trim((string)($input['trigger_zone_id'] ?? ''));
    $sessionPublicId = trim((string)($input['session_id'] ?? ''));

    $zone = null;
    if ($zonePublicId !== '' && preg_match('/^[a-f0-9-]{36}$/i', $zonePublicId) === 1 && mg_store_canvas_table_exists($pdo, 'mg_store_trigger_zones')) {
        $stmt = $pdo->prepare('SELECT * FROM mg_store_trigger_zones WHERE merchant_user_id=? AND public_id=? LIMIT 1');
        $stmt->execute([$merchantUserId, strtolower($zonePublicId)]);
        $zone = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $session = null;
    if ($sessionPublicId !== '' && preg_match('/^[a-f0-9-]{36}$/i', $sessionPublicId) === 1 && mg_store_canvas_table_exists($pdo, 'mg_store_sessions')) {
        $stmt = $pdo->prepare('SELECT * FROM mg_store_sessions WHERE merchant_user_id=? AND public_id=? LIMIT 1');
        $stmt->execute([$merchantUserId, strtolower($sessionPublicId)]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $action = (string)($zone['automation_action'] ?? 'message_and_reward');
    $cooldown = (string)($zone['cooldown_policy'] ?? 'fifteen_minutes');
    $zoneName = (string)($zone['name'] ?? 'Trigger Zone');
    $zoneId = $zone !== null ? (string)$zone['public_id'] : ($zonePublicId !== '' ? strtolower($zonePublicId) : null);
    $cooldownBlocked = $event === 'repeat';

    if (!$cooldownBlocked && $zoneId && $session && mg_store_canvas_table_exists($pdo, 'mg_store_session_events')) {
        $like = '%' . $zoneId . '%';
        $blocked = mg_canvas_intel_count($pdo, "SELECT COUNT(*) FROM mg_store_session_events WHERE store_session_id=? AND event_type='campaign_trigger_zone' AND event_data_json LIKE ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)", [(int)$session['id'], $like]);
        $cooldownBlocked = $blocked > 0 && $event !== 'return';
    }

    $wouldFire = !$cooldownBlocked && (!is_array($zone) || (string)($zone['status'] ?? 'active') === 'active');
    $wouldMessage = $wouldFire && in_array($action, ['message_and_reward','message_only'], true);
    $wouldReward = $wouldFire && in_array($action, ['message_and_reward','reward_only'], true);
    $label = $cooldownBlocked ? 'Cooldown would block duplicate trigger.' : ($wouldFire ? 'Automation would fire once.' : 'Automation would not fire.');
    $result = [
        'trigger_zone_id' => $zoneId,
        'trigger_zone_name' => $zoneName,
        'session_id' => $session ? (string)$session['public_id'] : ($sessionPublicId ?: null),
        'simulation_event' => $event,
        'automation_action' => $action,
        'cooldown_policy' => $cooldown,
        'would_fire' => $wouldFire,
        'would_send_message' => $wouldMessage,
        'would_send_reward' => $wouldReward,
        'would_block_cooldown' => $cooldownBlocked,
        'label' => $label,
        'message_preview' => $wouldMessage ? 'Hi {first_name}, you entered ' . $zoneName . '.' : '',
        'next_step' => $cooldownBlocked ? 'Do not send until customer exits and cooldown expires.' : 'Send allowed; start cooldown and record activity.',
    ];

    $stmt = $pdo->prepare(
        "INSERT INTO mg_store_trigger_rule_simulations
         (public_id,merchant_user_id,trigger_zone_public_id,trigger_zone_name,store_session_public_id,customer_user_id,simulation_event,automation_action,cooldown_policy,would_fire,would_send_message,would_send_reward,would_block_cooldown,result_label,result_json,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())"
    );
    $stmt->execute([
        mg_public_uuid(), $merchantUserId, $zoneId, mb_substr($zoneName, 0, 180), $session ? (string)$session['public_id'] : ($sessionPublicId ?: null), $session ? (int)$session['customer_user_id'] : null,
        $event, mb_substr($action, 0, 80), mb_substr($cooldown, 0, 80), $wouldFire ? 1 : 0, $wouldMessage ? 1 : 0, $wouldReward ? 1 : 0, $cooldownBlocked ? 1 : 0, mb_substr($label, 0, 255),
        json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);

    return $result;
}
