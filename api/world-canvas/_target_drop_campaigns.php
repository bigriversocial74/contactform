<?php
/**
 * Target Drop campaign selector helpers.
 *
 * Campaigns and their attached reward templates remain authoritative. Target
 * Drops only store a validated binding and never create or issue rewards.
 */
declare(strict_types=1);

require_once __DIR__ . '/_target_drops.php';
require_once dirname(__DIR__, 2) . '/includes/campaign-types.php';

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

function mg_world_target_drop_campaign_type_is_public(string $campaignType): bool
{
    $definition = mg_campaign_type_registry()[$campaignType] ?? null;
    return is_array($definition) && !empty($definition['public_enabled']) && empty($definition['internal_only']);
}

function mg_world_target_drop_campaign_row_payload(array $row): array
{
    $type = (string)($row['campaign_type'] ?? 'newsletter_signup');
    $campaignLimit = $row['campaign_quantity_limit'] === null ? null : (int)$row['campaign_quantity_limit'];
    $campaignIssued = (int)($row['campaign_issued_count'] ?? 0);
    $rewardLimit = $row['reward_quantity_limit'] === null ? null : (int)$row['reward_quantity_limit'];
    $rewardIssued = (int)($row['reward_issued_count'] ?? 0);
    $campaignAvailable = mg_world_target_drop_remaining($campaignLimit, $campaignIssued);
    $rewardAvailable = mg_world_target_drop_remaining($rewardLimit, $rewardIssued);
    $effectiveLimit = $rewardLimit !== null ? $rewardLimit : $campaignLimit;
    $effectiveAvailable = $rewardAvailable !== null ? $rewardAvailable : $campaignAvailable;
    $rewardPublicId = trim((string)($row['reward_template_public_id'] ?? ''));
    $rewardStatus = (string)($row['reward_template_status'] ?? '');
    $eligible = (string)($row['status'] ?? '') === 'active'
        && (empty($row['starts_at']) || strtotime((string)$row['starts_at']) <= time())
        && (empty($row['ends_at']) || strtotime((string)$row['ends_at']) >= time())
        && mg_world_target_drop_campaign_type_is_public($type)
        && $rewardPublicId !== ''
        && $rewardStatus === 'active'
        && ($effectiveAvailable === null || $effectiveAvailable > 0);

    return [
        'id' => (string)($row['public_id'] ?? ''),
        'campaign_id' => (int)($row['id'] ?? 0),
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
        'claim_limit_per_user' => max(1, (int)($row['per_user_limit'] ?? 1)),
        'public_slug' => $row['public_slug'] ?? null,
        'reward_template_id' => $rewardPublicId !== '' ? $rewardPublicId : null,
        'reward_template_title' => $row['reward_template_title'] ?? null,
        'reward_template_status' => $rewardStatus !== '' ? $rewardStatus : null,
        'eligible' => $eligible,
    ];
}

function mg_world_target_drop_campaign_query_parts(PDO $pdo): array
{
    $campaignIssued = mg_world_canvas_column($pdo, 'campaigns', 'issued_count')
        ? 'c.issued_count AS campaign_issued_count'
        : '0 AS campaign_issued_count';
    $campaignDescription = mg_world_canvas_column($pdo, 'campaigns', 'description')
        ? 'c.description'
        : "'' AS description";
    $campaignPublicSlug = mg_world_canvas_column($pdo, 'campaigns', 'public_slug')
        ? 'c.public_slug'
        : 'NULL AS public_slug';
    $selectReward = 'NULL AS reward_template_public_id, NULL AS reward_template_title, NULL AS reward_template_status, NULL AS reward_quantity_limit, 0 AS reward_issued_count';
    $joinReward = '';

    if (mg_world_canvas_table($pdo, 'reward_templates')) {
        $rewardQuantity = mg_world_canvas_column($pdo, 'reward_templates', 'quantity_limit')
            ? 'rt.quantity_limit AS reward_quantity_limit'
            : 'NULL AS reward_quantity_limit';
        $rewardIssued = mg_world_canvas_column($pdo, 'reward_templates', 'issued_count')
            ? 'rt.issued_count AS reward_issued_count'
            : '0 AS reward_issued_count';
        $selectReward = "rt.public_id AS reward_template_public_id, rt.title AS reward_template_title, rt.status AS reward_template_status, {$rewardQuantity}, {$rewardIssued}";
        $joinReward = ' LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id AND rt.merchant_user_id=c.merchant_user_id';
    }

    return [
        'select' => "c.id,c.public_id,c.title,{$campaignDescription},c.campaign_type,c.status,c.starts_at,c.ends_at,c.quantity_limit AS campaign_quantity_limit,{$campaignIssued},c.per_user_limit,{$campaignPublicSlug},{$selectReward}",
        'join' => $joinReward,
    ];
}

function mg_world_target_drop_campaign_options(PDO $pdo, array $user): array
{
    $merchantId = (int)($user['id'] ?? 0);
    if ($merchantId <= 0 || !mg_world_canvas_table($pdo, 'campaigns') || !mg_world_canvas_table($pdo, 'reward_templates')) return [];

    try {
        $parts = mg_world_target_drop_campaign_query_parts($pdo);
        $rows = mg_world_canvas_rows(
            $pdo,
            "SELECT {$parts['select']} FROM campaigns c{$parts['join']}
             WHERE c.merchant_user_id=?
               AND c.status='active'
               AND (c.starts_at IS NULL OR c.starts_at<=NOW())
               AND (c.ends_at IS NULL OR c.ends_at>=NOW())
               AND rt.id IS NOT NULL
               AND rt.status='active'
             ORDER BY c.updated_at DESC,c.id DESC
             LIMIT 100",
            [$merchantId]
        );
        $options = array_map('mg_world_target_drop_campaign_row_payload', $rows);
        return array_values(array_filter($options, static fn(array $campaign): bool => !empty($campaign['eligible'])));
    } catch (Throwable) {
        return [];
    }
}

function mg_world_target_drop_campaign_payload(PDO $pdo, int $merchantId, string $campaignPublicId): ?array
{
    $campaignPublicId = strtolower(trim($campaignPublicId));
    if ($merchantId <= 0 || $campaignPublicId === '' || !mg_world_canvas_table($pdo, 'campaigns') || !mg_world_canvas_table($pdo, 'reward_templates')) return null;

    try {
        $parts = mg_world_target_drop_campaign_query_parts($pdo);
        $rows = mg_world_canvas_rows(
            $pdo,
            "SELECT {$parts['select']} FROM campaigns c{$parts['join']}
             WHERE c.merchant_user_id=? AND c.public_id=? LIMIT 1",
            [$merchantId, $campaignPublicId]
        );
        if (!$rows) return null;
        $campaign = mg_world_target_drop_campaign_row_payload($rows[0]);
        return !empty($campaign['eligible']) ? $campaign : null;
    } catch (Throwable) {
        return null;
    }
}

function mg_world_target_drop_enrich_input_with_campaign(PDO $pdo, array $user, array $input): array
{
    $merchantId = (int)($user['id'] ?? 0);
    $campaignPublicId = trim((string)($input['campaign_public_id'] ?? ''));

    if ($campaignPublicId === '') {
        $input['campaign_id'] = null;
        $input['campaign_public_id'] = '';
        $input['campaign_title'] = '';
        $input['reward_template_public_id'] = '';
        $input['reward_template_title'] = '';
        $input['payload_type'] = 'reward';
        $input['quantity_limit'] = null;
        $input['claim_limit_per_user'] = 1;
        return $input;
    }

    $campaign = mg_world_target_drop_campaign_payload($pdo, $merchantId, $campaignPublicId);
    if (!$campaign) {
        throw new RuntimeException('Select an active merchant-owned campaign with an active available reward.');
    }

    $input['campaign_id'] = $campaign['campaign_id'];
    $input['campaign_public_id'] = $campaign['id'];
    $input['campaign_title'] = $campaign['title'];
    $input['reward_template_public_id'] = $campaign['reward_template_id'];
    $input['reward_template_title'] = $campaign['reward_template_title'];
    $input['payload_type'] = $campaign['payload_type'];
    $input['quantity_limit'] = $campaign['available_quantity'];
    $input['claim_limit_per_user'] = $campaign['claim_limit_per_user'];
    return $input;
}
