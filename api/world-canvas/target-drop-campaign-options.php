<?php
/**
 * Safe Target Drop campaign options endpoint.
 *
 * This endpoint is intentionally isolated from the main Target Drops list API so
 * campaign selector failures cannot break the World Canvas page load.
 */
declare(strict_types=1);

require_once __DIR__ . '/_target_drops.php';

$user = mg_require_permission('merchant.locations.manage');
$pdo = mg_db();

function mg_world_target_campaign_options_remaining(?int $limit, int $issued): ?int
{
    if ($limit === null) return null;
    return max(0, $limit - max(0, $issued));
}

function mg_world_target_campaign_options_payload_type(string $campaignType): string
{
    return match ($campaignType) {
        'contest_giveaway' => 'contest',
        'agent_offer' => 'offer',
        default => 'reward',
    };
}

try {
    $merchantId = (int)($user['id'] ?? 0);
    if ($merchantId <= 0 || !mg_world_canvas_table($pdo, 'campaigns')) {
        mg_ok(['campaigns' => [], 'schema_ready' => false]);
    }

    $hasRewardTemplates = mg_world_canvas_table($pdo, 'reward_templates');
    $campaignIssued = mg_world_canvas_column($pdo, 'campaigns', 'issued_count') ? 'c.issued_count AS campaign_issued_count' : '0 AS campaign_issued_count';
    $rewardSelect = 'NULL AS reward_template_public_id, NULL AS reward_template_title, NULL AS reward_template_status, NULL AS reward_quantity_limit, 0 AS reward_issued_count, NULL AS reward_type';
    $rewardJoin = '';

    if ($hasRewardTemplates) {
        $rewardQuantity = mg_world_canvas_column($pdo, 'reward_templates', 'quantity_limit') ? 'rt.quantity_limit AS reward_quantity_limit' : 'NULL AS reward_quantity_limit';
        $rewardIssued = mg_world_canvas_column($pdo, 'reward_templates', 'issued_count') ? 'rt.issued_count AS reward_issued_count' : '0 AS reward_issued_count';
        $rewardType = mg_world_canvas_column($pdo, 'reward_templates', 'reward_type') ? 'rt.reward_type AS reward_type' : 'NULL AS reward_type';
        $rewardSelect = "rt.public_id AS reward_template_public_id, rt.title AS reward_template_title, rt.status AS reward_template_status, {$rewardQuantity}, {$rewardIssued}, {$rewardType}";
        $rewardJoin = ' LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id';
    }

    $rows = mg_world_canvas_rows($pdo, "SELECT c.id, c.public_id, c.title, c.description, c.campaign_type, c.status, c.starts_at, c.ends_at, c.quantity_limit AS campaign_quantity_limit, {$campaignIssued}, c.per_user_limit, c.public_slug, {$rewardSelect} FROM campaigns c{$rewardJoin} WHERE c.merchant_user_id=? AND c.status <> 'archived' ORDER BY FIELD(c.status,'active','draft','paused','ended'), c.updated_at DESC, c.id DESC LIMIT 100", [$merchantId]);

    $campaigns = array_map(static function (array $row): array {
        $campaignType = (string)($row['campaign_type'] ?? 'newsletter_signup');
        $campaignLimit = $row['campaign_quantity_limit'] === null ? null : (int)$row['campaign_quantity_limit'];
        $campaignIssued = (int)($row['campaign_issued_count'] ?? 0);
        $rewardLimit = $row['reward_quantity_limit'] === null ? null : (int)$row['reward_quantity_limit'];
        $rewardIssued = (int)($row['reward_issued_count'] ?? 0);
        $campaignAvailable = mg_world_target_campaign_options_remaining($campaignLimit, $campaignIssued);
        $rewardAvailable = mg_world_target_campaign_options_remaining($rewardLimit, $rewardIssued);
        $effectiveLimit = $rewardLimit !== null ? $rewardLimit : $campaignLimit;
        $effectiveAvailable = $rewardAvailable !== null ? $rewardAvailable : $campaignAvailable;

        return [
            'id' => (string)($row['public_id'] ?? ''),
            'title' => (string)($row['title'] ?? 'Campaign'),
            'description' => (string)($row['description'] ?? ''),
            'campaign_type' => $campaignType,
            'payload_type' => mg_world_target_campaign_options_payload_type($campaignType),
            'status' => (string)($row['status'] ?? 'draft'),
            'starts_at' => $row['starts_at'] ?? null,
            'ends_at' => $row['ends_at'] ?? null,
            'quantity_limit' => $effectiveLimit,
            'available_quantity' => $effectiveAvailable,
            'campaign_quantity_limit' => $campaignLimit,
            'campaign_available_quantity' => $campaignAvailable,
            'reward_quantity_limit' => $rewardLimit,
            'reward_available_quantity' => $rewardAvailable,
            'claim_limit_per_user' => (int)($row['per_user_limit'] ?? 1),
            'public_slug' => $row['public_slug'] ?? null,
            'reward_template_id' => $row['reward_template_public_id'] ?? null,
            'reward_template_title' => $row['reward_template_title'] ?? null,
            'reward_template_status' => $row['reward_template_status'] ?? null,
            'reward_type' => $row['reward_type'] ?? null,
        ];
    }, $rows);

    mg_ok([
        'schema_ready' => true,
        'campaigns' => $campaigns,
    ]);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.target_drop_campaign_options_failed', 'Safe Target Drop campaign options lookup failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)($user['id'] ?? 0));
    mg_ok([
        'schema_ready' => false,
        'campaigns' => [],
        'message' => 'Unable to load campaign options.',
    ]);
}
