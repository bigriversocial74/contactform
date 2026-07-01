<?php
declare(strict_types=1);

require_once __DIR__ . '/_opportunities.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

function mg_world_exec_count(PDO $pdo, string $sql, array $params = []): int
{
    return mg_world_canvas_count($pdo, $sql, $params);
}

function mg_world_exec_rows(PDO $pdo, string $sql, array $params = []): array
{
    return mg_world_canvas_rows($pdo, $sql, $params);
}

function mg_world_exec_top_merchants(PDO $pdo): array
{
    if (!mg_world_canvas_store_ready($pdo)) return [];
    $rows = mg_world_exec_rows($pdo, "SELECT e.merchant_user_id, COALESCE(pp.display_name,'Merchant') merchant_name, COUNT(*) total_events, SUM(CASE WHEN e.event_type LIKE '%claim%' THEN 1 ELSE 0 END) claims, SUM(CASE WHEN e.event_type LIKE '%reward%' OR e.event_type LIKE '%drop%' OR e.event_type LIKE '%gift%' THEN 1 ELSE 0 END) rewards FROM mg_store_session_events e LEFT JOIN public_profiles pp ON pp.user_id=e.merchant_user_id WHERE e.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY e.merchant_user_id, pp.display_name ORDER BY total_events DESC LIMIT 8");
    return array_map(static fn(array $row): array => ['merchant_user_id'=>(int)$row['merchant_user_id'],'merchant_name'=>(string)$row['merchant_name'],'total_events'=>(int)$row['total_events'],'claims'=>(int)$row['claims'],'rewards'=>(int)$row['rewards']], $rows);
}

function mg_world_exec_heat_zones(PDO $pdo): array
{
    if (!mg_world_canvas_store_ready($pdo)) return [];
    $rows = mg_world_exec_rows($pdo, "SELECT s.merchant_user_id, COALESCE(pp.display_name,'Merchant') merchant_name, COUNT(*) active_avatars, SUM(CASE WHEN s.avatar_latitude IS NOT NULL AND s.avatar_longitude IS NOT NULL THEN 1 ELSE 0 END) geo_anchors, MAX(s.last_active_at) last_active_at FROM mg_store_sessions s LEFT JOIN public_profiles pp ON pp.user_id=s.merchant_user_id WHERE s.active_key IS NOT NULL AND s.status IN ('entered','active','idle') AND s.exited_at IS NULL GROUP BY s.merchant_user_id, pp.display_name ORDER BY active_avatars DESC LIMIT 8");
    return array_map(static fn(array $row): array => ['merchant_user_id'=>(int)$row['merchant_user_id'],'merchant_name'=>(string)$row['merchant_name'],'active_avatars'=>(int)$row['active_avatars'],'geo_anchors'=>(int)$row['geo_anchors'],'last_active_at'=>(string)$row['last_active_at']], $rows);
}

function mg_world_exec_reward_drop_stats(PDO $pdo): array
{
    if (!mg_world_reward_drops_ready($pdo)) return ['active_drops'=>0,'drop_claims_today'=>0,'remaining'=>0,'exhausted'=>0];
    return [
        'active_drops' => mg_world_exec_count($pdo, "SELECT COUNT(*) FROM world_reward_drops WHERE status='active'"),
        'drop_claims_today' => mg_world_exec_count($pdo, "SELECT COUNT(*) FROM world_reward_drop_claims WHERE claimed_at >= CURDATE() AND status IN ('claimed','redeemed')"),
        'remaining' => mg_world_exec_count($pdo, "SELECT COALESCE(SUM(quantity_remaining),0) FROM world_reward_drops WHERE status IN ('active','paused')"),
        'exhausted' => mg_world_exec_count($pdo, "SELECT COUNT(*) FROM world_reward_drops WHERE status='exhausted'"),
    ];
}

function mg_world_exec_conversation_stats(PDO $pdo): array
{
    if (!mg_world_conversations_ready($pdo)) return ['active_conversations'=>0,'messages_today'=>0,'participants'=>0];
    return [
        'active_conversations' => mg_world_exec_count($pdo, "SELECT COUNT(*) FROM world_conversations WHERE status IN ('active','quiet')"),
        'messages_today' => mg_world_exec_count($pdo, "SELECT COUNT(*) FROM world_conversation_messages WHERE created_at >= CURDATE() AND status='visible'"),
        'participants' => mg_world_exec_count($pdo, "SELECT COALESCE(SUM(participant_count),0) FROM world_conversations WHERE status IN ('active','quiet')"),
    ];
}

function mg_world_exec_opportunity_stats(PDO $pdo): array
{
    if (!mg_world_opportunities_ready($pdo)) return ['open'=>0,'high'=>0,'urgent'=>0,'score'=>0];
    $open = mg_world_exec_count($pdo, "SELECT COUNT(*) FROM world_merchant_opportunities WHERE status IN ('open','viewed')");
    $high = mg_world_exec_count($pdo, "SELECT COUNT(*) FROM world_merchant_opportunities WHERE status IN ('open','viewed') AND priority IN ('high','urgent')");
    $urgent = mg_world_exec_count($pdo, "SELECT COUNT(*) FROM world_merchant_opportunities WHERE status IN ('open','viewed') AND priority='urgent'");
    $score = mg_world_exec_count($pdo, "SELECT COALESCE(ROUND(AVG(score)),0) FROM world_merchant_opportunities WHERE status IN ('open','viewed')");
    return ['open'=>$open,'high'=>$high,'urgent'=>$urgent,'score'=>$score];
}

function mg_world_exec_trend(PDO $pdo): array
{
    if (!mg_world_canvas_store_ready($pdo)) return [];
    $rows = mg_world_exec_rows($pdo, "SELECT DATE_FORMAT(created_at,'%Y-%m-%d %H:00:00') bucket, COUNT(*) total, SUM(CASE WHEN event_type LIKE '%claim%' THEN 1 ELSE 0 END) claims, SUM(CASE WHEN event_type LIKE '%reward%' OR event_type LIKE '%gift%' OR event_type LIKE '%drop%' THEN 1 ELSE 0 END) rewards FROM mg_store_session_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY DATE_FORMAT(created_at,'%Y-%m-%d %H:00:00') ORDER BY bucket ASC");
    return array_map(static fn(array $row): array => ['bucket'=>(string)$row['bucket'],'total'=>(int)$row['total'],'claims'=>(int)$row['claims'],'rewards'=>(int)$row['rewards']], $rows);
}

try {
    mg_rate_limit('world_canvas.executive', 'user:' . (int)$user['id'], 120, 60);
    $summary = mg_world_canvas_summary($pdo);
    $drops = mg_world_exec_reward_drop_stats($pdo);
    $conversations = mg_world_exec_conversation_stats($pdo);
    $opportunities = mg_world_exec_opportunity_stats($pdo);
    $executiveScore = min(100, (int)$summary['demand_pulse'] + ($drops['active_drops'] * 2) + ($conversations['active_conversations'] * 3) + ($opportunities['high'] * 4));
    mg_ok([
        'summary' => $summary,
        'executive_score' => $executiveScore,
        'reward_drops' => $drops,
        'conversations' => $conversations,
        'opportunities' => $opportunities,
        'top_merchants' => mg_world_exec_top_merchants($pdo),
        'heat_zones' => mg_world_exec_heat_zones($pdo),
        'trend' => mg_world_exec_trend($pdo),
        'visibility' => ['customer_activity_anonymized'=>true,'merchant_crm_private'=>true,'executive_aggregate_view'=>true],
    ]);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.executive_failed', 'World Canvas executive dashboard failed.', ['exception_class'=>$error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to load World Canvas executive dashboard.', 500);
}
