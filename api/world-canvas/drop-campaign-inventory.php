<?php
/**
 * Target Drop campaign/reward inventory lookup for launch preflight.
 */
declare(strict_types=1);

require_once __DIR__ . '/_target_drop_campaigns.php';

$user = mg_require_permission('merchant.locations.manage');
$pdo = mg_db();

try {
    $input = mg_input();
    $merchantId = (int)($user['id'] ?? 0);
    $campaignPublicId = trim((string)($input['campaign_public_id'] ?? $input['campaign_id'] ?? ''));
    $dropPublicId = trim((string)($input['target_drop_id'] ?? $input['drop_id'] ?? $input['id'] ?? ''));

    if ($campaignPublicId === '' && $dropPublicId !== '' && mg_world_target_drops_ready($pdo)) {
        $rows = mg_world_canvas_rows($pdo, 'SELECT campaign_public_id FROM merchant_target_drops WHERE public_id=? AND merchant_user_id=? LIMIT 1', [$dropPublicId, $merchantId]);
        $campaignPublicId = trim((string)($rows[0]['campaign_public_id'] ?? ''));
    }

    if ($campaignPublicId === '') {
        mg_ok([
            'campaign_found' => false,
            'message' => 'Attach a campaign before sending this Target Drop.',
        ]);
    }

    if (!mg_world_canvas_table($pdo, 'campaigns')) {
        mg_ok([
            'campaign_found' => false,
            'campaign_public_id' => $campaignPublicId,
            'message' => 'Campaign table is not installed.',
        ]);
    }

    $hasRewardTemplates = mg_world_canvas_table($pdo, 'reward_templates');
    $campaignIssued = mg_world_canvas_column($pdo, 'campaigns', 'issued_count') ? 'c.issued_count AS campaign_issued_count' : '0 AS campaign_issued_count';
    $selectReward = 'NULL AS reward_public_id, NULL AS reward_title, NULL AS reward_status, NULL AS reward_quantity_limit, 0 AS reward_issued_count, NULL AS reward_type';
    $joinReward = '';
    if ($hasRewardTemplates) {
        $rewardQuantity = mg_world_canvas_column($pdo, 'reward_templates', 'quantity_limit') ? 'rt.quantity_limit AS reward_quantity_limit' : 'NULL AS reward_quantity_limit';
        $rewardIssued = mg_world_canvas_column($pdo, 'reward_templates', 'issued_count') ? 'rt.issued_count AS reward_issued_count' : '0 AS reward_issued_count';
        $rewardType = mg_world_canvas_column($pdo, 'reward_templates', 'reward_type') ? 'rt.reward_type AS reward_type' : 'NULL AS reward_type';
        $selectReward = "rt.public_id AS reward_public_id, rt.title AS reward_title, rt.status AS reward_status, {$rewardQuantity}, {$rewardIssued}, {$rewardType}";
        $joinReward = ' LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id';
    }

    $rows = mg_world_canvas_rows($pdo, "SELECT c.id, c.public_id, c.title, c.campaign_type, c.status, c.quantity_limit AS campaign_quantity_limit, {$campaignIssued}, c.per_user_limit, {$selectReward} FROM campaigns c{$joinReward} WHERE c.merchant_user_id=? AND c.public_id=? AND c.status <> 'archived' LIMIT 1", [$merchantId, $campaignPublicId]);
    if (!$rows) {
        mg_ok([
            'campaign_found' => false,
            'campaign_public_id' => $campaignPublicId,
            'message' => 'Attached campaign was not found for this merchant.',
        ]);
    }

    $row = $rows[0];
    $type = (string)($row['campaign_type'] ?? 'newsletter_signup');
    $campaignLimit = $row['campaign_quantity_limit'] === null ? null : (int)$row['campaign_quantity_limit'];
    $campaignIssuedCount = (int)($row['campaign_issued_count'] ?? 0);
    $rewardLimit = $row['reward_quantity_limit'] === null ? null : (int)$row['reward_quantity_limit'];
    $rewardIssuedCount = (int)($row['reward_issued_count'] ?? 0);
    $campaignAvailable = mg_world_target_drop_remaining($campaignLimit, $campaignIssuedCount);
    $rewardAvailable = mg_world_target_drop_remaining($rewardLimit, $rewardIssuedCount);
    $available = $rewardAvailable !== null ? $rewardAvailable : $campaignAvailable;
    $quantitySource = $rewardAvailable !== null ? 'reward_templates.quantity_limit' : 'campaigns.quantity_limit';

    $campaign = [
        'campaign_id' => (int)$row['id'],
        'campaign_public_id' => (string)$row['public_id'],
        'campaign_title' => (string)$row['title'],
        'payload_type' => mg_world_target_drop_campaign_payload_type($type),
        'quantity_limit' => $campaignLimit,
        'issued_count' => $campaignIssuedCount,
        'available_quantity' => $campaignAvailable,
        'claim_limit_per_user' => (int)($row['per_user_limit'] ?? 1),
    ];

    $reward = null;
    if (!empty($row['reward_public_id'])) {
        $reward = [
            'id' => (string)$row['reward_public_id'],
            'title' => (string)($row['reward_title'] ?? 'Reward'),
            'status' => (string)($row['reward_status'] ?? ''),
            'reward_type' => (string)($row['reward_type'] ?? ''),
            'quantity_limit' => $rewardLimit,
            'issued_count' => $rewardIssuedCount,
            'available_quantity' => $rewardAvailable,
        ];
    }

    mg_ok([
        'campaign_found' => true,
        'campaign' => $campaign,
        'reward' => $reward,
        'available_quantity' => $available,
        'campaign_available_quantity' => $campaignAvailable,
        'reward_available_quantity' => $rewardAvailable,
        'claim_limit_per_user' => (int)($row['per_user_limit'] ?? 1),
        'quantity_source' => $quantitySource,
    ]);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.drop_campaign_inventory_failed', 'Target Drop campaign inventory lookup failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)($user['id'] ?? 0));
    mg_fail('Unable to check campaign reward quantity.', 500);
}
