<?php
declare(strict_types=1);

require_once __DIR__ . '/_trigger_zones.php';
require_once dirname(__DIR__, 2) . '/includes/campaign-types.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

function mg_canvas_control_count(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function mg_canvas_control_campaigns(PDO $pdo, int $merchantUserId): array
{
    if (!mg_store_canvas_table_exists($pdo, 'campaigns')) return [];
    $hasRewards = mg_store_canvas_table_exists($pdo, 'reward_templates');
    $rewardSelect = $hasRewards
        ? 'rt.public_id reward_template_public_id,rt.title reward_template_title,rt.status reward_template_status,rt.quantity_limit reward_quantity_limit,rt.issued_count reward_issued_count'
        : 'NULL reward_template_public_id,NULL reward_template_title,NULL reward_template_status,NULL reward_quantity_limit,0 reward_issued_count';
    $rewardJoin = $hasRewards ? 'LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id AND rt.merchant_user_id=c.merchant_user_id' : '';
    $stmt = $pdo->prepare(
        "SELECT c.public_id,c.public_slug,c.title,c.description,c.campaign_type,c.status,c.starts_at,c.ends_at,c.quantity_limit,c.issued_count,c.per_user_limit,{$rewardSelect}
         FROM campaigns c {$rewardJoin}
         WHERE c.merchant_user_id=? AND c.status IN ('active','paused','draft')
         ORDER BY FIELD(c.status,'active','paused','draft'),c.updated_at DESC,c.id DESC LIMIT 100"
    );
    $stmt->execute([$merchantUserId]);
    $registry = mg_campaign_type_registry();
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $type = (string)$row['campaign_type'];
        $definition = $registry[$type] ?? [];
        $publicEnabled = !empty($definition['public_enabled']) && empty($definition['internal_only']);
        $path = $publicEnabled ? trim((string)($definition['public_path'] ?? '')) : '';
        $ref = trim((string)($row['public_slug'] ?? '')) ?: (string)$row['public_id'];
        $publicUrl = $path !== '' ? $path . '?campaign=' . rawurlencode($ref) : '';
        $campaignRemaining = $row['quantity_limit'] === null ? null : max(0, (int)$row['quantity_limit'] - (int)$row['issued_count']);
        $rewardRemaining = $row['reward_quantity_limit'] === null ? null : max(0, (int)$row['reward_quantity_limit'] - (int)$row['reward_issued_count']);
        $activeNow = (string)$row['status'] === 'active'
            && (empty($row['starts_at']) || strtotime((string)$row['starts_at']) <= time())
            && (empty($row['ends_at']) || strtotime((string)$row['ends_at']) >= time());
        $hasActiveReward = !empty($row['reward_template_public_id']) && (string)$row['reward_template_status'] === 'active';
        $hasInventory = ($campaignRemaining === null || $campaignRemaining > 0) && ($rewardRemaining === null || $rewardRemaining > 0);
        $items[] = [
            'id' => (string)$row['public_id'],
            'title' => (string)$row['title'],
            'description' => (string)($row['description'] ?? ''),
            'campaign_type' => $type,
            'type_label' => (string)($definition['label'] ?? ucwords(str_replace('_', ' ', $type))),
            'category' => (string)($definition['category'] ?? ''),
            'status' => (string)$row['status'],
            'public_enabled' => $publicEnabled,
            'public_url' => $publicUrl,
            'starts_at' => $row['starts_at'] ?? null,
            'ends_at' => $row['ends_at'] ?? null,
            'quantity_limit' => $row['quantity_limit'] === null ? null : (int)$row['quantity_limit'],
            'issued_count' => (int)$row['issued_count'],
            'remaining' => $campaignRemaining,
            'per_user_limit' => max(1, (int)($row['per_user_limit'] ?? 1)),
            'reward_template_id' => $row['reward_template_public_id'] !== null ? (string)$row['reward_template_public_id'] : null,
            'reward_template_title' => $row['reward_template_title'] !== null ? (string)$row['reward_template_title'] : null,
            'reward_remaining' => $rewardRemaining,
            'can_recommend' => $activeNow && $publicEnabled && $hasActiveReward && $hasInventory,
            'recommendation_delivery' => 'notification_only',
            'reward_issue_policy' => 'campaign_completion_only',
        ];
    }
    return $items;
}

try {
    $merchantUserId = (int)$user['id'];
    mg_rate_limit('merchant_canvas.control_center', 'user:' . $merchantUserId, 180, 60);

    $campaigns = mg_canvas_control_campaigns($pdo, $merchantUserId);
    $zoneSchemaReady = mg_canvas_trigger_zone_schema_ready($pdo);
    $zones = $zoneSchemaReady ? mg_canvas_trigger_zone_list($pdo, $merchantUserId) : [];
    $manualReady = mg_store_canvas_missing_tables($pdo, ['mg_merchant_customer_crm','mg_merchant_canvas_action_receipts']) === [];
    $analyticsReady = mg_store_canvas_table_exists($pdo, 'mg_merchant_canvas_journey_events');
    $activeCustomers = mg_canvas_control_count($pdo, "SELECT COUNT(*) FROM mg_store_sessions WHERE merchant_user_id=? AND active_key IS NOT NULL AND status IN ('entered','active','idle') AND exited_at IS NULL AND last_active_at>=DATE_SUB(NOW(),INTERVAL " . MG_STORE_EXPIRE_MINUTES . " MINUTE)", [$merchantUserId]);
    $todayRecommendations = mg_canvas_control_count($pdo, "SELECT COUNT(*) FROM mg_merchant_canvas_action_receipts WHERE merchant_user_id=? AND action_type='campaign_recommendation' AND status='completed' AND created_at>=CURDATE()", [$merchantUserId]);
    $recommendable = array_values(array_filter($campaigns, static fn(array $campaign): bool => !empty($campaign['can_recommend'])));
    $activeZones = array_values(array_filter($zones, static fn(array $zone): bool => ($zone['status'] ?? '') === 'active'));
    $boundZones = array_values(array_filter($activeZones, static fn(array $zone): bool => trim((string)($zone['campaign_id'] ?? '')) !== ''));

    $suggestions = [];
    if ($recommendable === []) {
        $suggestions[] = ['key'=>'campaign_inventory','level'=>'high','title'=>'Create a recommendable campaign','detail'=>'Publish an active public campaign with an active reward template and available inventory before sending recommendations.'];
    }
    if ($zoneSchemaReady && $zones === []) {
        $suggestions[] = ['key'=>'first_zone','level'=>'medium','title'=>'Create the first campaign zone','detail'=>'Add a visual zone and bind it to an active campaign. Automatic execution remains disabled until the server trigger engine is deployed.'];
    }
    if ($activeZones !== [] && count($boundZones) < count($activeZones)) {
        $suggestions[] = ['key'=>'zone_campaign_binding','level'=>'medium','title'=>'Bind every active zone to a campaign','detail'=>'Unbound zones can visualize intent but cannot produce a merchant-approved campaign recommendation.'];
    }
    if (!$manualReady) {
        $suggestions[] = ['key'=>'manual_schema','level'=>'high','title'=>'Complete manual-operation setup','detail'=>'Import the manual operations migration before using CRM safeguards, notifications, messages, or rewards.'];
    }
    if (!$analyticsReady) {
        $suggestions[] = ['key'=>'analytics_schema','level'=>'medium','title'=>'Enable full journey intelligence','detail'=>'Import the customer journey migration to retain detailed timeline and predictive commerce measurements.'];
    }
    if ($suggestions === []) {
        $suggestions[] = ['key'=>'optimize_frequency','level'=>'info','title'=>'Tune campaign frequency by outcome','detail'=>'Start with once-per-visit or once-per-customer-day limits, then compare notification opens, campaign completions, wallet issues, claims, and redemptions.'];
    }

    mg_ok([
        'summary' => [
            'active_customers' => $activeCustomers,
            'campaigns' => count($campaigns),
            'recommendable_campaigns' => count($recommendable),
            'trigger_zones' => count($zones),
            'active_trigger_zones' => count($activeZones),
            'recommendations_today' => $todayRecommendations,
        ],
        'campaigns' => $campaigns,
        'zones' => $zones,
        'suggestions' => $suggestions,
        'capabilities' => [
            'visual_movement' => true,
            'visual_trigger_zones' => $zoneSchemaReady,
            'customer_intelligence_drawer' => true,
            'campaign_recommendation_notifications' => $manualReady && $recommendable !== [],
            'manual_messages' => $manualReady,
            'manual_campaign_rewards' => $manualReady,
            'automatic_trigger_execution' => false,
            'automatic_proximity_chat' => false,
            'browser_overlap_execution' => false,
            'reward_issue_authority' => 'campaign_completion',
            'reward_destination' => 'wallet_then_inbox_pppm',
        ],
        'readiness' => [
            'canvas_schema' => mg_store_canvas_missing_tables($pdo, ['mg_store_sessions','mg_store_session_events','mg_customer_store_history']) === [],
            'manual_operations_schema' => $manualReady,
            'trigger_zone_schema' => $zoneSchemaReady,
            'journey_analytics_schema' => $analyticsReady,
            'server_trigger_engine' => false,
            'execution_mode' => 'manual_guarded_visual_server_ready',
        ],
        'security' => [
            'merchant_scope' => 'server_enforced',
            'customer_scope' => 'active_store_session',
            'csrf_on_writes' => true,
            'idempotency_receipts' => $manualReady,
            'do_not_message_enforced' => $manualReady,
            'campaign_ownership_enforced' => true,
            'campaign_public_route_registry' => true,
            'recommendation_reward_issued' => false,
            'automatic_side_effects_contained' => true,
        ],
    ]);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.control_center_failed', 'Merchant Canvas control center failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to load Merchant Control Center.', 500);
}
