<?php
/**
 * Target Drop campaign selector helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/_target_drops.php';

function mg_world_target_drop_campaign_payload_type(string $campaignType): string
{
    return match ($campaignType) {
        'contest_giveaway' => 'contest',
        'agent_offer' => 'offer',
        default => 'reward',
    };
}

function mg_world_target_drop_campaign_select_expr(PDO $pdo, string $table, string $column, string $alias, string $fallback = 'NULL'): string
{
    return mg_world_canvas_column($pdo, $table, $column) ? "{$column} AS {$alias}" : "{$fallback} AS {$alias}";
}

function mg_world_target_drop_remaining(?int $limit, int $issued): ?int
{
    if ($limit === null) return null;
    return max(0, $limit - max(0, $issued));
}

function mg_world_target_drop_campaign_options(PDO $pdo, array $user): array
{
    $merchantId = (int)($user['id'] ?? 0);
    if ($merchantId <= 0 || !mg_world_canvas_table($pdo, 'campaigns')) return [];
    try {
        $hasRewardTemplates = mg_world_canvas_table($pdo, 'reward_templates');
        $campaignIssued = mg_world_target_drop_campaign_select_expr($pdo, 'campaigns', 'issued_count', 'campaign_issued_count', '0');
        $selectReward = 'NULL AS reward_template_public_id, NULL AS reward_template_title, NULL AS reward_template_status, NULL AS reward_quantity_limit, 0 AS reward_issued_count';
        $joinReward = '';
        if ($hasRewardTemplates) {
            $rewardQuantity = mg_world_canvas_column($pdo, 'reward_templates', 'quantity_limit') ? 'rt.quantity_limit AS reward_quantity_limit' : 'NULL AS reward_quantity_limit';
            $rewardIssued = mg_world_canvas_column($pdo, 'reward_templates', 'issued_count') ? 'rt.issued_count AS reward_issued_count' : '0 AS reward_issued_count';
            $selectReward = "rt.public_id AS reward_template_public_id, rt.title AS reward_template_title, rt.status AS reward_template_status, {$rewardQuantity}, {$rewardIssued}";
            $joinReward = ' LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id';
        }
        $rows = mg_world_canvas_rows($pdo, "SELECT c.id, c.public_id, c.title, c.description, c.campaign_type, c.status, c.starts_at, c.ends_at, c.quantity_limit AS campaign_quantity_limit, {$campaignIssued}, c.per_user_limit, c.public_slug, {$selectReward} FROM campaigns c{$joinReward} WHERE c.merchant_user_id=? AND c.status <> 'archived' ORDER BY FIELD(c.status,'active','draft','paused','ended'), c.updated_at DESC, c.id DESC LIMIT 100", [$merchantId]);
        return array_map(static function (array $row): array {
            $type = (string)($row['campaign_type'] ?? 'newsletter_signup');
            $campaignLimit = $row['campaign_quantity_limit'] === null ? null : (int)$row['campaign_quantity_limit'];
            $campaignIssuedCount = (int)($row['campaign_issued_count'] ?? 0);
            $rewardLimit = $row['reward_quantity_limit'] === null ? null : (int)$row['reward_quantity_limit'];
            $rewardIssuedCount = (int)($row['reward_issued_count'] ?? 0);
            $campaignAvailable = mg_world_target_drop_remaining($campaignLimit, $campaignIssuedCount);
            $rewardAvailable = mg_world_target_drop_remaining($rewardLimit, $rewardIssuedCount);
            $effectiveLimit = $rewardLimit !== null ? $rewardLimit : $campaignLimit;
            $effectiveAvailable = $rewardAvailable !== null ? $rewardAvailable : $campaignAvailable;
            return [
                'id' => (string)($row['public_id'] ?? ''),
                'title' => (string)($row['title'] ?? 'Campaign'),
                'description' => (string)($row['description'] ?? ''),
                'campaign_type' => $type,
                'payload_type' => mg_world_target_drop_campaign_payload_type($type),
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
            ];
        }, $rows);
    } catch (Throwable) {
        return [];
    }
}

function mg_world_target_drop_campaign_payload(PDO $pdo, int $merchantId, string $campaignPublicId): ?array
{
    $campaignPublicId = strtolower(trim($campaignPublicId));
    if ($merchantId <= 0 || $campaignPublicId === '' || !mg_world_canvas_table($pdo, 'campaigns')) return null;
    try {
        $rows = mg_world_canvas_rows($pdo, "SELECT id, public_id, title, campaign_type, status, starts_at, ends_at, quantity_limit, per_user_limit FROM campaigns WHERE merchant_user_id=? AND public_id=? AND status <> 'archived' LIMIT 1", [$merchantId, $campaignPublicId]);
        if (!$rows) return null;
        $row = $rows[0];
        $type = (string)($row['campaign_type'] ?? 'newsletter_signup');
        return [
            'campaign_id' => (int)$row['id'],
            'campaign_public_id' => (string)$row['public_id'],
            'campaign_title' => (string)$row['title'],
            'payload_type' => mg_world_target_drop_campaign_payload_type($type),
            'quantity_limit' => $row['quantity_limit'] === null ? null : (int)$row['quantity_limit'],
            'claim_limit_per_user' => (int)($row['per_user_limit'] ?? 1),
        ];
    } catch (Throwable) {
        return null;
    }
}

function mg_world_target_drop_enrich_input_with_campaign(PDO $pdo, array $user, array $input): array
{
    $merchantId = (int)($user['id'] ?? 0);
    $campaignPublicId = trim((string)($input['campaign_public_id'] ?? ''));
    if ($campaignPublicId === '') return $input;
    $campaign = mg_world_target_drop_campaign_payload($pdo, $merchantId, $campaignPublicId);
    if (!$campaign) return $input;
    $input['campaign_public_id'] = $campaign['campaign_public_id'];
    $input['campaign_title'] = $campaign['campaign_title'];
    $input['payload_type'] = $campaign['payload_type'];
    if (!isset($input['quantity_limit']) || $input['quantity_limit'] === '') $input['quantity_limit'] = $campaign['quantity_limit'];
    if (!isset($input['claim_limit_per_user']) || $input['claim_limit_per_user'] === '') $input['claim_limit_per_user'] = $campaign['claim_limit_per_user'];
    return $input;
}
