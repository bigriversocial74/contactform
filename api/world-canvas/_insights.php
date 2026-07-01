<?php
declare(strict_types=1);

require_once __DIR__ . '/_reward_drops.php';

function mg_world_insights_ready(PDO $pdo): bool
{
    return mg_world_canvas_table($pdo, 'world_insight_snapshots');
}

function mg_world_insight_text(mixed $value, int $max = 220): string
{
    $text = trim((string)$value);
    $text = preg_replace('/\s+/', ' ', $text) ?? '';
    return mb_substr($text, 0, $max);
}

function mg_world_insight_card(string $type, string $severity, string $title, string $summary, string $recommendation = '', string $actionLabel = '', string $actionHref = '', int $score = 0, array $counts = []): array
{
    return [
        'type' => $type,
        'severity' => in_array($severity, ['info','opportunity','warning','critical'], true) ? $severity : 'info',
        'title' => mg_world_insight_text($title, 180),
        'summary' => mg_world_insight_text($summary, 600),
        'recommendation' => mg_world_insight_text($recommendation, 600),
        'action_label' => mg_world_insight_text($actionLabel, 120),
        'action_href' => mg_world_insight_text($actionHref, 500),
        'score' => max(0, min(9999, $score)),
        'counts' => $counts,
    ];
}

function mg_world_insight_context(PDO $pdo, array $input): array
{
    $conversation = null;
    $clusterKey = mg_world_insight_text($input['cluster_key'] ?? '', 220);
    $locationKey = mg_world_insight_text($input['location_key'] ?? '', 160);
    $conversationPublicId = mg_world_insight_text($input['conversation_id'] ?? '', 120);
    if ($conversationPublicId !== '' && mg_world_conversations_ready($pdo)) {
        try {
            $conversation = mg_world_conversation_load_by_public_id($pdo, $conversationPublicId);
            $clusterKey = $clusterKey !== '' ? $clusterKey : (string)$conversation['cluster_key'];
            $locationKey = $locationKey !== '' ? $locationKey : (string)($conversation['location_key'] ?? '');
        } catch (Throwable) {}
    }
    return ['conversation'=>$conversation, 'cluster_key'=>$clusterKey, 'location_key'=>$locationKey];
}

function mg_world_insight_merchant_id_from_location(string $locationKey): ?int
{
    if (preg_match('/^merchant:(\d+)$/', $locationKey, $m) === 1) return (int)$m[1];
    return null;
}

function mg_world_insight_store_counts(PDO $pdo, string $locationKey): array
{
    $merchantId = mg_world_insight_merchant_id_from_location($locationKey);
    if (!$merchantId || !mg_world_canvas_store_ready($pdo)) return ['active_avatars'=>0,'geo_anchors'=>0,'events_today'=>0,'claims_today'=>0,'reward_events_today'=>0];
    $active = mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM mg_store_sessions WHERE merchant_user_id=? AND active_key IS NOT NULL AND status IN ('entered','active','idle') AND exited_at IS NULL", [$merchantId]);
    $geo = mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_latitude') ? mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM mg_store_sessions WHERE merchant_user_id=? AND active_key IS NOT NULL AND status IN ('entered','active','idle') AND exited_at IS NULL AND avatar_latitude IS NOT NULL AND avatar_longitude IS NOT NULL", [$merchantId]) : 0;
    $events = mg_world_canvas_count($pdo, 'SELECT COUNT(*) FROM mg_store_session_events WHERE merchant_user_id=? AND created_at >= CURDATE()', [$merchantId]);
    $claims = mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM mg_store_session_events WHERE merchant_user_id=? AND created_at >= CURDATE() AND event_type LIKE '%claim%'", [$merchantId]);
    $rewardEvents = mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM mg_store_session_events WHERE merchant_user_id=? AND created_at >= CURDATE() AND (event_type LIKE '%reward%' OR event_type LIKE '%drop%')", [$merchantId]);
    return ['active_avatars'=>$active,'geo_anchors'=>$geo,'events_today'=>$events,'claims_today'=>$claims,'reward_events_today'=>$rewardEvents];
}

function mg_world_insight_drop_counts(PDO $pdo, ?array $conversation, string $clusterKey): array
{
    if (!mg_world_reward_drops_ready($pdo)) return ['drops'=>0,'active_drops'=>0,'claims'=>0,'remaining'=>0,'exhausted'=>0];
    $params = [];
    $where = '1=1';
    if ($conversation) {
        $where .= ' AND conversation_id=?';
        $params[] = (int)$conversation['id'];
    } elseif ($clusterKey !== '') {
        $where .= ' AND cluster_key_hash=?';
        $params[] = hash('sha256', $clusterKey);
    } else {
        return ['drops'=>0,'active_drops'=>0,'claims'=>0,'remaining'=>0,'exhausted'=>0];
    }
    $dropRows = mg_world_canvas_rows($pdo, "SELECT id,status,quantity_remaining FROM world_reward_drops WHERE {$where}", $params);
    $dropIds = array_map(static fn(array $row): int => (int)$row['id'], $dropRows);
    $claims = 0;
    if ($dropIds) {
        $placeholders = implode(',', array_fill(0, count($dropIds), '?'));
        $claims = mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM world_reward_drop_claims WHERE reward_drop_id IN ({$placeholders}) AND status IN ('claimed','redeemed')", $dropIds);
    }
    return [
        'drops' => count($dropRows),
        'active_drops' => count(array_filter($dropRows, static fn(array $row): bool => (string)$row['status'] === 'active')),
        'claims' => $claims,
        'remaining' => array_sum(array_map(static fn(array $row): int => (int)$row['quantity_remaining'], $dropRows)),
        'exhausted' => count(array_filter($dropRows, static fn(array $row): bool => (string)$row['status'] === 'exhausted')),
    ];
}

function mg_world_insight_conversation_counts(?array $conversation): array
{
    if (!$conversation) return ['participants'=>0,'messages'=>0];
    return ['participants'=>(int)$conversation['participant_count'], 'messages'=>(int)$conversation['message_count']];
}

function mg_world_insight_viewer_is_merchant(PDO $pdo, array $viewer): bool
{
    $viewerId = (int)($viewer['id'] ?? 0);
    if ($viewerId < 1) return false;
    $profileType = '';
    try {
        $stmt = $pdo->prepare('SELECT profile_type FROM public_profiles WHERE user_id=? LIMIT 1');
        $stmt->execute([$viewerId]);
        $profileType = (string)($stmt->fetchColumn() ?: '');
    } catch (Throwable) {}
    return mg_store_user_is_merchant($pdo, $viewerId, $profileType);
}

function mg_world_insight_cluster(PDO $pdo, array $viewer, array $input): array
{
    $ctx = mg_world_insight_context($pdo, $input);
    $conversation = $ctx['conversation'];
    $clusterKey = (string)$ctx['cluster_key'];
    $locationKey = (string)$ctx['location_key'];
    $store = mg_world_insight_store_counts($pdo, $locationKey);
    $drops = mg_world_insight_drop_counts($pdo, $conversation, $clusterKey);
    $conv = mg_world_insight_conversation_counts($conversation);
    $isMerchant = mg_world_insight_viewer_is_merchant($pdo, $viewer);
    $cards = [];

    if ($conv['participants'] >= 2 || $conv['messages'] >= 1) {
        $cards[] = mg_world_insight_card('conversation_momentum', 'opportunity', 'Conversation momentum detected', $conv['participants'] . ' participants and ' . $conv['messages'] . ' messages are active in this cluster.', 'Keep the conversation moving with a merchant response, a question, or a limited reward drop.', '', '', $conv['participants'] * 12 + $conv['messages'] * 8, $conv);
    }
    if ($store['active_avatars'] >= 2) {
        $cards[] = mg_world_insight_card('nearby_demand', 'opportunity', 'Nearby demand pocket forming', $store['active_avatars'] . ' avatars are active around this merchant location right now.', 'Create a focused reward drop or campaign while the cluster is active.', $isMerchant ? 'Create reward drop' : '', '', $store['active_avatars'] * 15 + $store['events_today'], $store);
    }
    if ($drops['drops'] === 0 && ($conv['participants'] >= 2 || $store['active_avatars'] >= 2)) {
        $cards[] = mg_world_insight_card('drop_recommendation', 'opportunity', 'Reward drop recommended', 'This cluster has activity but no reward drop attached yet.', 'Add a small controlled reward drop to test conversion from attention into claim activity.', $isMerchant ? 'Create reward drop' : '', '', 72, $drops + $conv + $store);
    }
    if ($drops['drops'] > 0) {
        $conversion = $drops['claims'] > 0 && ($drops['claims'] + $drops['remaining']) > 0 ? round(($drops['claims'] / max(1, $drops['claims'] + $drops['remaining'])) * 100) : 0;
        $cards[] = mg_world_insight_card('reward_drop_conversion', $drops['claims'] > 0 ? 'opportunity' : 'info', 'Reward drop conversion', $drops['claims'] . ' claims from ' . $drops['drops'] . ' reward drops. ' . $drops['remaining'] . ' rewards remain.', $drops['claims'] > 0 ? 'Keep this drop active or duplicate it into a nearby heat zone.' : 'The drop is live but has not converted yet. Improve the message or increase visibility in the conversation.', '', '', $conversion + $drops['claims'] * 12, $drops);
    }
    if ($store['geo_anchors'] > 0) {
        $cards[] = mg_world_insight_card('geo_anchor_quality', 'info', 'Geo-anchored avatar signal', $store['geo_anchors'] . ' avatars in this area are placed from saved coordinates.', 'Use geo-anchored activity to validate local demand before launching a larger campaign.', '', '', $store['geo_anchors'] * 9, $store);
    }
    if ($store['claims_today'] > 0) {
        $cards[] = mg_world_insight_card('claim_signal', 'opportunity', 'Claim activity is happening today', $store['claims_today'] . ' claim signals were recorded today for this merchant location.', 'Follow up with a campaign, referral prompt, or post-claim offer.', $isMerchant ? 'Open Store Canvas' : '', $isMerchant ? '/merchant-canvas.php' : '', 50 + $store['claims_today'] * 12, $store);
    }
    if (!$cards) {
        $cards[] = mg_world_insight_card('watch_cluster', 'info', 'Watching this cluster', 'World Canvas is monitoring this area for conversation, reward, claim, and geo-anchor signals.', 'As activity increases, Microgifter will recommend drops, campaigns, or merchant actions.', '', '', 10, $store + $conv + $drops);
    }
    usort($cards, static fn(array $a, array $b): int => ((int)$b['score']) <=> ((int)$a['score']));
    return array_slice($cards, 0, 6);
}

function mg_world_insight_global(PDO $pdo, array $viewer): array
{
    $summary = mg_world_canvas_summary($pdo);
    $cards = [];
    $isMerchant = mg_world_insight_viewer_is_merchant($pdo, $viewer);
    if ((int)$summary['active_customers'] > 0) {
        $cards[] = mg_world_insight_card('active_world', 'info', 'World activity is live', $summary['active_customers'] . ' active avatars across ' . $summary['live_stores'] . ' live stores.', 'Open conversation clusters to see where rewards and claims are forming.', '', '', (int)$summary['demand_pulse'], $summary);
    }
    if ((int)$summary['geo_anchored_avatars'] > 0) {
        $cards[] = mg_world_insight_card('geo_coverage', 'info', 'Geo coverage increasing', $summary['geo_anchored_avatars'] . ' avatars are currently anchored by saved coordinates.', 'Use Geo Anchors mode to see which activity is location-backed.', '', '', (int)$summary['geo_anchored_avatars'] * 10, $summary);
    }
    if ((int)$summary['gifts_moving'] > 0) {
        $cards[] = mg_world_insight_card('gift_movement', 'opportunity', 'Gifts are moving', $summary['gifts_moving'] . ' reward or gift signals are moving today.', 'Use Gift Movement mode to identify where attention is converting into action.', '', '', (int)$summary['gifts_moving'] * 12, $summary);
    }
    if ((int)$summary['claims_today'] > 0) {
        $cards[] = mg_world_insight_card('claim_momentum', 'opportunity', 'Claims are converting today', $summary['claims_today'] . ' claim signals have been recorded today.', 'Create a follow-up campaign for claim-adjacent activity.', $isMerchant ? 'Open Store Canvas' : '', $isMerchant ? '/merchant-canvas.php' : '', 50 + (int)$summary['claims_today'] * 18, $summary);
    }
    if (mg_world_reward_drops_ready($pdo)) {
        $activeDrops = mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM world_reward_drops WHERE status='active'");
        $claims = mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM world_reward_drop_claims WHERE claimed_at >= CURDATE() AND status IN ('claimed','redeemed')");
        if ($activeDrops > 0 || $claims > 0) {
            $cards[] = mg_world_insight_card('drop_network', 'opportunity', 'Reward drop network signal', $activeDrops . ' active reward drops and ' . $claims . ' drop claims today.', 'Watch which clusters convert, then duplicate the best drop into similar heat zones.', '', '', $activeDrops * 8 + $claims * 15, ['active_drops'=>$activeDrops,'claims_today'=>$claims]);
        }
    }
    if (!$cards) {
        $cards[] = mg_world_insight_card('setup_world', 'info', 'World Canvas is ready', 'As avatars enter stores, start conversations, claim drops, and save coordinates, insights will appear here.', 'Start by entering a store from the feed or creating merchant activity.', '', '', 5, $summary);
    }
    usort($cards, static fn(array $a, array $b): int => ((int)$b['score']) <=> ((int)$a['score']));
    return array_slice($cards, 0, 6);
}

function mg_world_insight_snapshot(PDO $pdo, array $insight, string $scopeType, string $scopeKey, ?array $conversation = null, ?int $merchantUserId = null): void
{
    if (!mg_world_insights_ready($pdo)) return;
    try {
        $key = $scopeType . ':' . $scopeKey . ':' . (string)$insight['type'] . ':' . (string)$insight['title'];
        $hash = hash('sha256', $key);
        $existing = mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM world_insight_snapshots WHERE insight_key_hash=? AND status='active' AND generated_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)", [$hash]);
        if ($existing > 0) return;
        $stmt = $pdo->prepare("INSERT INTO world_insight_snapshots (public_id,insight_key_hash,insight_key,insight_type,scope_type,scope_key,merchant_user_id,conversation_id,severity,title,summary,recommendation,action_label,action_href,score,status,source_counts_json,metadata_json,generated_at,expires_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active',?,?,NOW(),DATE_ADD(NOW(), INTERVAL 2 HOUR),NOW())");
        $stmt->execute([
            mg_public_uuid(),
            $hash,
            $key,
            (string)$insight['type'],
            $scopeType,
            $scopeKey !== '' ? $scopeKey : null,
            $merchantUserId,
            $conversation ? (int)$conversation['id'] : null,
            (string)$insight['severity'],
            (string)$insight['title'],
            (string)$insight['summary'],
            (string)$insight['recommendation'],
            (string)$insight['action_label'],
            (string)$insight['action_href'],
            (int)$insight['score'],
            json_encode($insight['counts'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            json_encode(['generated_by'=>'live_world_insights'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    } catch (Throwable) {}
}

function mg_world_insights_payload(PDO $pdo, array $viewer, array $input): array
{
    $ctx = mg_world_insight_context($pdo, $input);
    $scope = $ctx['conversation'] || $ctx['cluster_key'] !== '' || $ctx['location_key'] !== '' ? 'cluster' : 'global';
    $insights = $scope === 'cluster' ? mg_world_insight_cluster($pdo, $viewer, $input) : mg_world_insight_global($pdo, $viewer);
    $scopeKey = $scope === 'cluster' ? (($ctx['cluster_key'] ?: $ctx['location_key']) ?: (string)($ctx['conversation']['public_id'] ?? '')) : 'global';
    $merchantId = mg_world_insight_merchant_id_from_location((string)$ctx['location_key']);
    foreach ($insights as $insight) {
        mg_world_insight_snapshot($pdo, $insight, $scope, $scopeKey, $ctx['conversation'], $merchantId);
    }
    return [
        'scope' => $scope,
        'cluster_key' => (string)$ctx['cluster_key'],
        'location_key' => (string)$ctx['location_key'],
        'conversation_id' => $ctx['conversation'] ? (string)$ctx['conversation']['public_id'] : '',
        'insights' => $insights,
    ];
}
