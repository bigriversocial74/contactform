<?php
declare(strict_types=1);

require_once __DIR__ . '/campaign-landing-foundation.php';
require_once __DIR__ . '/public-donations-feature.php';

/**
 * Phase 8 public merchant-profile Community reporting.
 *
 * The helper reads original Community assignments and aggregate reward
 * lifecycle only. It never selects downstream recipient names, contact data,
 * Inbox records, claim codes, internal notes, or ownership identifiers.
 */

function mg_public_profile_community_schema_ready(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $required = [
        'public_profiles',
        'users',
        'campaigns',
        'reward_templates',
        'campaign_community_assignments',
        'campaign_donation_rewards',
        'wallet_items',
        'pppm_items',
        'microgift_instances',
    ];

    try {
        $placeholders = implode(',', array_fill(0, count($required), '?'));
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA=DATABASE()
                AND TABLE_NAME IN ({$placeholders})"
        );
        $stmt->execute($required);
        return $cache[$key] = (int)$stmt->fetchColumn() === count($required);
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}

function mg_public_profile_community_owner(PDO $pdo, string $slug): ?array
{
    $slug = strtolower(trim($slug));
    if ($slug === '' || strlen($slug) > 120 || preg_match('/^[a-z0-9](?:[a-z0-9-]{0,118}[a-z0-9])?$/', $slug) !== 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT profile.user_id,profile.slug,profile.status,profile.visibility,
                COALESCE(NULLIF(profile.display_name,''),NULLIF(user.display_name,''),user.full_name) AS display_name
           FROM public_profiles profile
           INNER JOIN users user ON user.id=profile.user_id
          WHERE profile.slug=?
          LIMIT 1"
    );
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_public_profile_community_reward_flags(): array
{
    $regifted = "(
        wallet.user_id<>reward.original_community_user_id
        OR pppm.owner_user_id<>reward.original_community_user_id
        OR microgift.owner_user_id<>reward.original_community_user_id
    )";
    $claimed = "(
        wallet.claimed_at IS NOT NULL OR microgift.claimed_at IS NOT NULL
        OR wallet.status IN ('claimed','redeemed')
        OR pppm.status IN ('claim_pending','verified','redeemed')
        OR microgift.status IN ('claim_pending','claimed','redeemable','redeemed')
    )";
    $redeemed = "(
        wallet.redeemed_at IS NOT NULL OR microgift.redeemed_at IS NOT NULL
        OR wallet.status='redeemed' OR pppm.status='redeemed' OR microgift.status='redeemed'
    )";

    return compact('regifted', 'claimed', 'redeemed');
}

function mg_public_profile_community_history_state(array $row): string
{
    $status = strtolower(trim((string)($row['status'] ?? '')));
    $endsAt = !empty($row['ends_at']) ? strtotime((string)$row['ends_at']) : false;
    if ($status === 'ended' || ($endsAt !== false && $endsAt < time())) {
        return 'completed';
    }
    if ($status === 'paused') {
        return 'paused';
    }
    return 'active';
}

function mg_public_profile_community_campaign_metrics(PDO $pdo, int $merchantId): array
{
    $flags = mg_public_profile_community_reward_flags();
    $stmt = $pdo->prepare(
        "SELECT reward.campaign_id,
                COUNT(*) AS gross_allocated,
                COALESCE(SUM(reward.status='recalled'),0) AS recalled,
                COALESCE(SUM(reward.status='allocated'),0) AS net_allocated,
                COALESCE(SUM(reward.status='allocated' AND {$flags['regifted']}),0) AS regifted,
                COALESCE(SUM(reward.status='allocated' AND {$flags['claimed']}),0) AS claimed,
                COALESCE(SUM(reward.status='allocated' AND {$flags['redeemed']}),0) AS redeemed,
                COALESCE(SUM(reward.value_cents_snapshot),0) AS gross_value_cents,
                COALESCE(SUM(CASE WHEN reward.status='recalled' THEN reward.value_cents_snapshot ELSE 0 END),0) AS recalled_value_cents,
                COALESCE(SUM(CASE WHEN reward.status='allocated' THEN reward.value_cents_snapshot ELSE 0 END),0) AS net_value_cents,
                MIN(reward.currency_snapshot) AS currency,
                COUNT(DISTINCT reward.currency_snapshot) AS currency_count
           FROM campaign_donation_rewards reward
           INNER JOIN campaigns campaign
                   ON campaign.id=reward.campaign_id
                  AND campaign.merchant_user_id=reward.merchant_user_id
                  AND campaign.campaign_type='public_donation'
           INNER JOIN wallet_items wallet ON wallet.id=reward.wallet_item_id
           INNER JOIN pppm_items pppm ON pppm.id=reward.pppm_item_id
           INNER JOIN microgift_instances microgift ON microgift.id=reward.microgift_instance_id
          WHERE reward.merchant_user_id=?
          GROUP BY reward.campaign_id"
    );
    $stmt->execute([$merchantId]);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['campaign_id']] = [
            'gross_allocated' => (int)$row['gross_allocated'],
            'recalled' => (int)$row['recalled'],
            'net_allocated' => (int)$row['net_allocated'],
            'regifted' => (int)$row['regifted'],
            'claimed' => (int)$row['claimed'],
            'redeemed' => (int)$row['redeemed'],
            'gross_stated_value_cents' => (int)$row['gross_value_cents'],
            'recalled_stated_value_cents' => (int)$row['recalled_value_cents'],
            'net_stated_value_cents' => (int)$row['net_value_cents'],
            'currency' => (int)$row['currency_count'] === 1 ? (string)$row['currency'] : null,
            'mixed_currency' => (int)$row['currency_count'] > 1,
        ];
    }
    return $map;
}

function mg_public_profile_community_empty_metrics(): array
{
    return [
        'gross_allocated' => 0,
        'recalled' => 0,
        'net_allocated' => 0,
        'regifted' => 0,
        'claimed' => 0,
        'redeemed' => 0,
        'gross_stated_value_cents' => 0,
        'recalled_stated_value_cents' => 0,
        'net_stated_value_cents' => 0,
        'currency' => null,
        'mixed_currency' => false,
    ];
}

function mg_public_profile_community_supported_by_campaign(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare(
        "SELECT source.campaign_id,COUNT(DISTINCT source.community_user_id) AS supported_accounts
           FROM (
                SELECT assignment.campaign_id,assignment.community_user_id
                  FROM campaign_community_assignments assignment
                  INNER JOIN campaigns campaign
                          ON campaign.id=assignment.campaign_id
                         AND campaign.merchant_user_id=assignment.merchant_user_id
                         AND campaign.campaign_type='public_donation'
                 WHERE assignment.merchant_user_id=?
                   AND assignment.status IN ('active','paused')
                UNION
                SELECT reward.campaign_id,reward.original_community_user_id
                  FROM campaign_donation_rewards reward
                  INNER JOIN campaigns campaign
                          ON campaign.id=reward.campaign_id
                         AND campaign.merchant_user_id=reward.merchant_user_id
                         AND campaign.campaign_type='public_donation'
                 WHERE reward.merchant_user_id=?
           ) source
          GROUP BY source.campaign_id"
    );
    $stmt->execute([$merchantId, $merchantId]);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['campaign_id']] = (int)$row['supported_accounts'];
    }
    return $map;
}

function mg_public_profile_community_campaigns(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare(
        "SELECT campaign.id,campaign.public_id,campaign.public_slug,campaign.title,campaign.description,
                campaign.status,campaign.starts_at,campaign.ends_at,campaign.rules_json,campaign.metadata_json,
                reward.title AS reward_title,reward.description AS reward_description,
                reward.metadata_json AS reward_metadata_json
           FROM campaigns campaign
           LEFT JOIN reward_templates reward ON reward.id=campaign.reward_template_id
          WHERE campaign.merchant_user_id=?
            AND campaign.campaign_type='public_donation'
            AND campaign.status IN ('active','paused','ended')
          ORDER BY CASE campaign.status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END,
                   COALESCE(campaign.ends_at,campaign.starts_at,campaign.updated_at) DESC,
                   campaign.id DESC
          LIMIT 100"
    );
    $stmt->execute([$merchantId]);
    $metrics = mg_public_profile_community_campaign_metrics($pdo, $merchantId);
    $supported = mg_public_profile_community_supported_by_campaign($pdo, $merchantId);
    $campaigns = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $state = mg_public_profile_community_history_state($row);
        $id = (int)$row['id'];
        $slug = trim((string)$row['public_slug']);
        $ref = $slug !== '' ? $slug : (string)$row['public_id'];
        $image = mg_campaign_landing_campaign_image($row)
            ?? mg_campaign_landing_reward_cover($row);
        $campaigns[] = [
            'id' => (string)$row['public_id'],
            'slug' => $slug !== '' ? $slug : null,
            'title' => (string)$row['title'],
            'description' => mb_substr(trim((string)$row['description']), 0, 1000),
            'reward_title' => mb_substr(trim((string)$row['reward_title']), 0, 180),
            'status' => (string)$row['status'],
            'history_state' => $state,
            'starts_at' => $row['starts_at'] !== null ? (string)$row['starts_at'] : null,
            'ends_at' => $row['ends_at'] !== null ? (string)$row['ends_at'] : null,
            'image_url' => $image,
            'supported_accounts' => (int)($supported[$id] ?? 0),
            'metrics' => $metrics[$id] ?? mg_public_profile_community_empty_metrics(),
            'url' => $state === 'active'
                ? '/public-donations.php?campaign=' . rawurlencode($ref)
                : null,
        ];
    }

    return $campaigns;
}

function mg_public_profile_community_accounts(PDO $pdo, int $merchantId): array
{
    $flags = mg_public_profile_community_reward_flags();
    $stmt = $pdo->prepare(
        "SELECT assignment.community_user_id,
                profile.slug,profile.display_name,profile.headline,profile.avatar_url,profile.cover_url,
                profile.location_label,profile.visibility,
                COUNT(DISTINCT assignment.campaign_id) AS campaign_count,
                COUNT(reward.id) AS gross_allocated,
                COALESCE(SUM(reward.status='recalled'),0) AS recalled,
                COALESCE(SUM(reward.status='allocated'),0) AS net_allocated,
                COALESCE(SUM(reward.status='allocated' AND {$flags['regifted']}),0) AS regifted,
                COALESCE(SUM(reward.status='allocated' AND {$flags['claimed']}),0) AS claimed,
                COALESCE(SUM(reward.status='allocated' AND {$flags['redeemed']}),0) AS redeemed,
                COALESCE(SUM(CASE WHEN reward.status='allocated' THEN reward.value_cents_snapshot ELSE 0 END),0) AS net_value_cents,
                MIN(reward.currency_snapshot) AS currency,
                COUNT(DISTINCT reward.currency_snapshot) AS currency_count
           FROM campaign_community_assignments assignment
           INNER JOIN campaigns campaign
                   ON campaign.id=assignment.campaign_id
                  AND campaign.merchant_user_id=assignment.merchant_user_id
                  AND campaign.campaign_type='public_donation'
                  AND campaign.status IN ('active','paused','ended')
           INNER JOIN public_profiles profile
                   ON profile.user_id=assignment.community_user_id
                  AND profile.status='active'
                  AND profile.visibility IN ('public','unlisted')
                  AND profile.slug<>''
           LEFT JOIN campaign_donation_rewards reward
                  ON reward.merchant_user_id=assignment.merchant_user_id
                 AND reward.campaign_id=assignment.campaign_id
                 AND reward.original_community_user_id=assignment.community_user_id
           LEFT JOIN wallet_items wallet ON wallet.id=reward.wallet_item_id
           LEFT JOIN pppm_items pppm ON pppm.id=reward.pppm_item_id
           LEFT JOIN microgift_instances microgift ON microgift.id=reward.microgift_instance_id
          WHERE assignment.merchant_user_id=?
            AND assignment.public_display_status='approved'
            AND (assignment.status IN ('active','paused') OR reward.id IS NOT NULL)
          GROUP BY assignment.community_user_id,profile.slug,profile.display_name,profile.headline,
                   profile.avatar_url,profile.cover_url,profile.location_label,profile.visibility
          ORDER BY net_allocated DESC,profile.display_name ASC
          LIMIT 200"
    );
    $stmt->execute([$merchantId]);
    $accounts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = trim((string)$row['display_name']) ?: 'Community member';
        $accounts[] = [
            'display_name' => mb_substr($name, 0, 180),
            'headline' => mb_substr(trim((string)$row['headline']), 0, 240),
            'location' => mb_substr(trim((string)$row['location_label']), 0, 180),
            'avatar_url' => mg_campaign_landing_safe_url($row['avatar_url'] ?? null),
            'cover_url' => mg_campaign_landing_safe_url($row['cover_url'] ?? null),
            'profile_url' => '/profile.php?slug=' . rawurlencode((string)$row['slug']),
            'profile_indexable' => (string)$row['visibility'] === 'public',
            'campaign_count' => (int)$row['campaign_count'],
            'metrics' => [
                'gross_allocated' => (int)$row['gross_allocated'],
                'recalled' => (int)$row['recalled'],
                'net_allocated' => (int)$row['net_allocated'],
                'regifted' => (int)$row['regifted'],
                'claimed' => (int)$row['claimed'],
                'redeemed' => (int)$row['redeemed'],
                'net_stated_value_cents' => (int)$row['net_value_cents'],
                'currency' => (int)$row['currency_count'] === 1 ? (string)$row['currency'] : null,
                'mixed_currency' => (int)$row['currency_count'] > 1,
            ],
        ];
    }
    return $accounts;
}

function mg_public_profile_community_supported_total(PDO $pdo, int $merchantId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT source.community_user_id)
           FROM (
                SELECT assignment.community_user_id
                  FROM campaign_community_assignments assignment
                  INNER JOIN campaigns campaign
                          ON campaign.id=assignment.campaign_id
                         AND campaign.merchant_user_id=assignment.merchant_user_id
                         AND campaign.campaign_type='public_donation'
                         AND campaign.status IN ('active','paused','ended')
                 WHERE assignment.merchant_user_id=?
                   AND assignment.status IN ('active','paused')
                UNION
                SELECT reward.original_community_user_id
                  FROM campaign_donation_rewards reward
                  INNER JOIN campaigns campaign
                          ON campaign.id=reward.campaign_id
                         AND campaign.merchant_user_id=reward.merchant_user_id
                         AND campaign.campaign_type='public_donation'
                         AND campaign.status IN ('active','paused','ended')
                 WHERE reward.merchant_user_id=?
           ) source"
    );
    $stmt->execute([$merchantId, $merchantId]);
    return (int)$stmt->fetchColumn();
}

function mg_public_profile_community_currency_summary(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare(
        "SELECT reward.currency_snapshot AS currency,
                COALESCE(SUM(reward.value_cents_snapshot),0) AS gross_cents,
                COALESCE(SUM(CASE WHEN reward.status='recalled' THEN reward.value_cents_snapshot ELSE 0 END),0) AS recalled_cents,
                COALESCE(SUM(CASE WHEN reward.status='allocated' THEN reward.value_cents_snapshot ELSE 0 END),0) AS net_cents
           FROM campaign_donation_rewards reward
           INNER JOIN campaigns campaign
                   ON campaign.id=reward.campaign_id
                  AND campaign.merchant_user_id=reward.merchant_user_id
                  AND campaign.campaign_type='public_donation'
                  AND campaign.status IN ('active','paused','ended')
          WHERE reward.merchant_user_id=?
          GROUP BY reward.currency_snapshot
          ORDER BY reward.currency_snapshot"
    );
    $stmt->execute([$merchantId]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'currency' => (string)$row['currency'],
            'gross_cents' => (int)$row['gross_cents'],
            'recalled_cents' => (int)$row['recalled_cents'],
            'net_cents' => (int)$row['net_cents'],
        ];
    }
    return $rows;
}

function mg_public_profile_community_summary(array $campaigns, int $supportedAccounts, int $publicAccounts, array $values): array
{
    $summary = [
        'campaigns' => count($campaigns),
        'active_campaigns' => 0,
        'paused_campaigns' => 0,
        'completed_campaigns' => 0,
        'supported_accounts' => $supportedAccounts,
        'publicly_featured_accounts' => $publicAccounts,
        'anonymous_accounts' => max(0, $supportedAccounts - $publicAccounts),
        'gross_allocated' => 0,
        'recalled' => 0,
        'net_allocated' => 0,
        'regifted' => 0,
        'claimed' => 0,
        'redeemed' => 0,
        'stated_value_by_currency' => $values,
    ];

    foreach ($campaigns as $campaign) {
        $state = (string)$campaign['history_state'];
        if ($state === 'completed') {
            $summary['completed_campaigns']++;
        } elseif ($state === 'paused') {
            $summary['paused_campaigns']++;
        } else {
            $summary['active_campaigns']++;
        }
        foreach (['gross_allocated','recalled','net_allocated','regifted','claimed','redeemed'] as $key) {
            $summary[$key] += (int)$campaign['metrics'][$key];
        }
    }

    return $summary;
}

function mg_public_profile_community_build(PDO $pdo, string $slug): array
{
    if (!mg_public_profile_community_schema_ready($pdo)) {
        return [
            'schema_ready' => false,
            'has_data' => false,
            'summary' => [],
            'campaigns' => [],
            'community_accounts' => [],
            'active_campaigns' => [],
            'privacy' => [
                'approved_profiles_only' => true,
                'anonymous_accounts_in_aggregate' => true,
                'final_recipient_identity_exposed' => false,
                'contact_data_exposed' => false,
            ],
        ];
    }

    $owner = mg_public_profile_community_owner($pdo, $slug);
    if (!$owner) {
        throw new RuntimeException('Profile not found.');
    }
    $merchantId = (int)$owner['user_id'];
    if ($merchantId < 1 || !mg_public_donations_is_enabled_for($merchantId, null)) {
        return [
            'schema_ready' => true,
            'has_data' => false,
            'summary' => [],
            'campaigns' => [],
            'community_accounts' => [],
            'active_campaigns' => [],
            'privacy' => [
                'approved_profiles_only' => true,
                'anonymous_accounts_in_aggregate' => true,
                'final_recipient_identity_exposed' => false,
                'contact_data_exposed' => false,
            ],
        ];
    }

    $campaigns = mg_public_profile_community_campaigns($pdo, $merchantId);
    $accounts = mg_public_profile_community_accounts($pdo, $merchantId);
    $supported = mg_public_profile_community_supported_total($pdo, $merchantId);
    $values = mg_public_profile_community_currency_summary($pdo, $merchantId);
    $active = array_values(array_filter(
        $campaigns,
        static fn(array $campaign): bool => (string)$campaign['history_state'] === 'active'
    ));

    return [
        'schema_ready' => true,
        'has_data' => $campaigns !== [] || $supported > 0,
        'summary' => mg_public_profile_community_summary($campaigns, $supported, count($accounts), $values),
        'campaigns' => $campaigns,
        'community_accounts' => $accounts,
        'active_campaigns' => $active,
        'privacy' => [
            'approved_profiles_only' => true,
            'anonymous_accounts_in_aggregate' => true,
            'final_recipient_identity_exposed' => false,
            'contact_data_exposed' => false,
            'claim_or_ownership_identifiers_exposed' => false,
        ],
        'generated_at' => gmdate('c'),
    ];
}

function mg_public_profile_community_enrich_campaign_items(array $items, array $community): array
{
    if (empty($community['schema_ready'])) {
        return $items;
    }

    $activeMap = [];
    foreach (($community['active_campaigns'] ?? []) as $campaign) {
        if (!is_array($campaign)) {
            continue;
        }
        $activeMap[(string)($campaign['id'] ?? '')] = $campaign;
    }

    $enriched = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $type = (string)($item['campaign_type'] ?? $item['type'] ?? '');
        if ($type !== 'public_donation') {
            $enriched[] = $item;
            continue;
        }

        $campaign = $activeMap[(string)($item['id'] ?? '')] ?? null;
        if (!is_array($campaign)) {
            continue;
        }

        $metrics = is_array($campaign['metrics'] ?? null)
            ? $campaign['metrics']
            : mg_public_profile_community_empty_metrics();
        $item['status'] = 'active';
        $item['image_url'] = $campaign['image_url'] ?? null;
        $item['community_accounts_supported'] = (int)($campaign['supported_accounts'] ?? 0);
        $item['gross_rewards_allocated'] = (int)($metrics['gross_allocated'] ?? 0);
        $item['rewards_recalled'] = (int)($metrics['recalled'] ?? 0);
        $item['rewards_allocated'] = (int)($metrics['net_allocated'] ?? 0);
        $item['issued_count'] = (int)($metrics['net_allocated'] ?? 0);
        $item['regifted'] = (int)($metrics['regifted'] ?? 0);
        $item['claimed'] = (int)($metrics['claimed'] ?? 0);
        $item['redeemed'] = (int)($metrics['redeemed'] ?? 0);
        $item['net_stated_value_cents'] = (int)($metrics['net_stated_value_cents'] ?? 0);
        $item['currency'] = $metrics['currency'] ?? null;
        $item['url'] = (string)($campaign['url'] ?? '');
        $item['action_label'] = 'View Campaign';
        $item['card_variant'] = 'public_donation';
        $item['badge'] = 'Public Donations';
        $item['public_transactional'] = false;
        $item['public_mode'] = 'informational';
        $enriched[] = $item;
    }

    return $enriched;
}
