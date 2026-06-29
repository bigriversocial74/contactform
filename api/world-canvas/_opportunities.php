<?php
declare(strict_types=1);

require_once __DIR__ . '/_insights.php';

function mg_world_opportunities_ready(PDO $pdo): bool
{
    return mg_world_canvas_table($pdo, 'world_merchant_opportunities');
}

function mg_world_opportunity_priority(int $score): string
{
    if ($score >= 90) return 'urgent';
    if ($score >= 65) return 'high';
    if ($score >= 30) return 'medium';
    return 'low';
}

function mg_world_opportunity_action_for(array $insight): array
{
    $type = (string)($insight['type'] ?? 'world_signal');
    return match ($type) {
        'drop_recommendation', 'nearby_demand', 'conversation_momentum' => ['type'=>'create_reward_drop','label'=>'Create reward drop','href'=>''],
        'claim_signal', 'claim_momentum' => ['type'=>'open_store_canvas','label'=>'Open Store Canvas','href'=>'/merchant-canvas.php'],
        'reward_drop_conversion', 'drop_network' => ['type'=>'duplicate_drop','label'=>'Duplicate best drop','href'=>''],
        'gift_movement' => ['type'=>'create_distribution_plan','label'=>'Create distribution plan','href'=>'/distribution.php?action=create'],
        'geo_anchor_quality', 'geo_coverage' => ['type'=>'create_campaign','label'=>'Create geo campaign','href'=>'/campaigns.php?action=create'],
        default => ['type'=>'message_cluster','label'=>'Message cluster','href'=>''],
    };
}

function mg_world_opportunity_project(array $row): array
{
    return [
        'id' => (string)$row['public_id'],
        'type' => (string)$row['opportunity_type'],
        'priority' => (string)$row['priority'],
        'title' => (string)$row['title'],
        'summary' => (string)$row['summary'],
        'recommended_action' => (string)($row['recommended_action'] ?? ''),
        'action_type' => (string)($row['action_type'] ?? ''),
        'action_label' => (string)($row['action_label'] ?? ''),
        'action_href' => (string)($row['action_href'] ?? ''),
        'score' => (int)$row['score'],
        'status' => (string)$row['status'],
        'cluster_key' => (string)($row['cluster_key'] ?? ''),
        'location_key' => (string)($row['location_key'] ?? ''),
        'generated_at' => (string)$row['generated_at'],
    ];
}

function mg_world_opportunity_from_insight(PDO $pdo, array $viewer, array $insight, array $ctx): array
{
    $action = mg_world_opportunity_action_for($insight);
    $score = (int)($insight['score'] ?? 0);
    $summary = (string)($insight['summary'] ?? '');
    $recommendation = (string)($insight['recommendation'] ?? '');
    return [
        'public_id' => mg_public_uuid(),
        'opportunity_type' => (string)($insight['type'] ?? 'world_signal'),
        'priority' => mg_world_opportunity_priority($score),
        'title' => (string)($insight['title'] ?? 'World Canvas opportunity'),
        'summary' => $summary,
        'recommended_action' => $recommendation !== '' ? $recommendation : (string)$action['label'],
        'action_type' => (string)$action['type'],
        'action_label' => (string)$action['label'],
        'action_href' => (string)$action['href'],
        'score' => $score,
        'status' => 'open',
        'merchant_user_id' => (int)($viewer['id'] ?? 0),
        'conversation_id' => isset($ctx['conversation']['id']) ? (int)$ctx['conversation']['id'] : null,
        'cluster_key' => (string)($ctx['cluster_key'] ?? ''),
        'location_key' => (string)($ctx['location_key'] ?? ''),
        'source_counts_json' => json_encode($insight['counts'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'metadata_json' => json_encode(['source'=>'world_insights_engine'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'generated_at' => date('Y-m-d H:i:s'),
    ];
}

function mg_world_opportunity_save(PDO $pdo, array $opportunity): array
{
    if (!mg_world_opportunities_ready($pdo)) return $opportunity;
    try {
        $key = implode(':', [
            (int)$opportunity['merchant_user_id'],
            (string)$opportunity['opportunity_type'],
            (string)$opportunity['cluster_key'],
            (string)$opportunity['location_key'],
            (string)$opportunity['title'],
        ]);
        $hash = hash('sha256', $key);
        $stmt = $pdo->prepare('SELECT * FROM world_merchant_opportunities WHERE opportunity_key_hash=? LIMIT 1');
        $stmt->execute([$hash]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $stmt = $pdo->prepare("UPDATE world_merchant_opportunities SET priority=?,summary=?,recommended_action=?,action_type=?,action_label=?,action_href=?,score=GREATEST(score,?),source_counts_json=?,metadata_json=?,generated_at=NOW(),expires_at=DATE_ADD(NOW(), INTERVAL 2 DAY),updated_at=NOW(),status=IF(status='expired','open',status) WHERE id=?");
            $stmt->execute([
                (string)$opportunity['priority'],
                (string)$opportunity['summary'],
                (string)$opportunity['recommended_action'],
                (string)$opportunity['action_type'],
                (string)$opportunity['action_label'],
                (string)$opportunity['action_href'],
                (int)$opportunity['score'],
                (string)$opportunity['source_counts_json'],
                (string)$opportunity['metadata_json'],
                (int)$existing['id'],
            ]);
            return mg_world_canvas_rows($pdo, 'SELECT * FROM world_merchant_opportunities WHERE id=?', [(int)$existing['id']])[0] ?? $existing;
        }
        $stmt = $pdo->prepare("INSERT INTO world_merchant_opportunities (public_id,opportunity_key_hash,opportunity_key,merchant_user_id,conversation_id,cluster_key,location_key,opportunity_type,priority,title,summary,recommended_action,action_type,action_label,action_href,score,status,source_counts_json,metadata_json,generated_at,expires_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'open',?,?,NOW(),DATE_ADD(NOW(), INTERVAL 2 DAY),NOW())");
        $stmt->execute([
            (string)$opportunity['public_id'],
            $hash,
            $key,
            (int)$opportunity['merchant_user_id'],
            $opportunity['conversation_id'],
            (string)$opportunity['cluster_key'] !== '' ? (string)$opportunity['cluster_key'] : null,
            (string)$opportunity['location_key'] !== '' ? (string)$opportunity['location_key'] : null,
            (string)$opportunity['opportunity_type'],
            (string)$opportunity['priority'],
            (string)$opportunity['title'],
            (string)$opportunity['summary'],
            (string)$opportunity['recommended_action'],
            (string)$opportunity['action_type'],
            (string)$opportunity['action_label'],
            (string)$opportunity['action_href'],
            (int)$opportunity['score'],
            (string)$opportunity['source_counts_json'],
            (string)$opportunity['metadata_json'],
        ]);
        return mg_world_canvas_rows($pdo, 'SELECT * FROM world_merchant_opportunities WHERE id=?', [(int)$pdo->lastInsertId()])[0] ?? $opportunity;
    } catch (Throwable) {
        return $opportunity;
    }
}

function mg_world_opportunities_payload(PDO $pdo, array $viewer, array $input): array
{
    if (!mg_world_insight_viewer_is_merchant($pdo, $viewer)) {
        return ['merchant_enabled'=>false, 'opportunities'=>[], 'message'=>'Merchant account required.'];
    }
    $ctx = mg_world_insight_context($pdo, $input);
    $insightsPayload = mg_world_insights_payload($pdo, $viewer, $input);
    $opportunities = [];
    foreach (($insightsPayload['insights'] ?? []) as $insight) {
        $raw = mg_world_opportunity_from_insight($pdo, $viewer, $insight, $ctx);
        $saved = mg_world_opportunity_save($pdo, $raw);
        $opportunities[] = mg_world_opportunity_project($saved);
    }
    usort($opportunities, static fn(array $a, array $b): int => ((int)$b['score']) <=> ((int)$a['score']));
    return [
        'merchant_enabled' => true,
        'scope' => (string)($insightsPayload['scope'] ?? 'global'),
        'cluster_key' => (string)($insightsPayload['cluster_key'] ?? ''),
        'location_key' => (string)($insightsPayload['location_key'] ?? ''),
        'conversation_id' => (string)($insightsPayload['conversation_id'] ?? ''),
        'opportunities' => array_slice($opportunities, 0, 6),
    ];
}

function mg_world_opportunity_update_status(PDO $pdo, array $viewer, array $input): array
{
    if (!mg_world_opportunities_ready($pdo)) throw new RuntimeException('Merchant opportunity table is not installed yet.');
    if (!mg_world_insight_viewer_is_merchant($pdo, $viewer)) throw new RuntimeException('Merchant account required.');
    $id = trim((string)($input['opportunity_id'] ?? ''));
    $status = trim((string)($input['status'] ?? 'viewed'));
    if ($id === '' || preg_match('/^[A-Za-z0-9._:-]+$/', $id) !== 1) throw new InvalidArgumentException('Opportunity is required.');
    if (!in_array($status, ['open','viewed','converted','dismissed'], true)) throw new InvalidArgumentException('Invalid opportunity status.');
    $stmt = $pdo->prepare('SELECT * FROM world_merchant_opportunities WHERE public_id=? AND merchant_user_id=? LIMIT 1');
    $stmt->execute([$id, (int)$viewer['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Opportunity was not found.');
    $pdo->prepare('UPDATE world_merchant_opportunities SET status=?,updated_at=NOW() WHERE id=?')->execute([$status, (int)$row['id']]);
    return mg_world_opportunity_project(mg_world_canvas_rows($pdo, 'SELECT * FROM world_merchant_opportunities WHERE id=?', [(int)$row['id']])[0] ?? $row);
}
