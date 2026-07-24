<?php
declare(strict_types=1);

require_once __DIR__ . '/campaign-landing-foundation.php';
require_once __DIR__ . '/public-donations-feature.php';

/**
 * Phase 7 public reporting for Public Donations campaigns.
 *
 * This layer is intentionally read-only. It exposes campaign presentation,
 * approved Community public profiles, and aggregate donation attribution only.
 * It never reads or returns contact, Wallet, Inbox, PPPM, Microgift, claim,
 * recipient, internal-note, or ownership identifiers.
 */

function mg_public_donations_public_schema_ready(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $required = [
        'campaigns',
        'reward_templates',
        'users',
        'public_profiles',
        'campaign_community_assignments',
        'campaign_donation_rewards',
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

function mg_public_donations_public_ref(mixed $value): string
{
    $ref = strtolower(trim((string)$value));
    if ($ref === '' || strlen($ref) > 190) {
        return '';
    }
    return preg_match('/^[a-z0-9](?:[a-z0-9-]{0,188}[a-z0-9])?$/', $ref) === 1 ? $ref : '';
}

function mg_public_donations_public_profile_eligible(array $row): bool
{
    return trim((string)($row['merchant_profile_slug'] ?? '')) !== ''
        && (string)($row['merchant_profile_status'] ?? '') === 'active'
        && in_array((string)($row['merchant_profile_visibility'] ?? ''), ['public', 'unlisted'], true);
}

function mg_public_donations_public_indexable(array $row): bool
{
    return mg_public_donations_public_profile_eligible($row)
        && (string)($row['merchant_profile_visibility'] ?? '') === 'public'
        && (string)($row['status'] ?? '') === 'active';
}

function mg_public_donations_public_value_label(array $row): string
{
    $rewardType = strtolower(trim((string)($row['reward_type'] ?? '')));
    $valueType = strtolower(trim((string)($row['value_type'] ?? '')));
    $title = trim((string)($row['reward_title'] ?? '')) ?: 'Promotional reward';

    if ($valueType === 'percent' && $row['value_percent'] !== null) {
        $percent = rtrim(rtrim(number_format((float)$row['value_percent'], 2), '0'), '.');
        return $percent . '% promotional reward';
    }
    if (in_array($valueType, ['free_item', 'custom'], true)
        || in_array($rewardType, ['free_item', 'perk_upgrade', 'event_reward', 'custom'], true)) {
        return $title;
    }

    $cents = max(0, (int)($row['value_amount_cents'] ?? 0));
    $currency = strtoupper(trim((string)($row['reward_currency'] ?? 'USD')) ?: 'USD');
    if ($cents < 1) {
        return $title;
    }

    return $currency . ' ' . number_format($cents / 100, 2) . ' stated promotional value';
}

function mg_public_donations_public_campaign(PDO $pdo, string $campaignRef): ?array
{
    $campaignRef = mg_public_donations_public_ref($campaignRef);
    if ($campaignRef === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT campaign.id,campaign.public_id,campaign.public_slug,campaign.merchant_user_id,
                campaign.title,campaign.description,campaign.form_headline,campaign.form_description,
                campaign.status,campaign.starts_at,campaign.ends_at,campaign.rules_json,
                merchant.display_name AS merchant_display_name,merchant.full_name AS merchant_full_name,
                profile.slug AS merchant_profile_slug,profile.display_name AS merchant_profile_display_name,
                profile.headline AS merchant_profile_headline,profile.avatar_url AS merchant_profile_avatar_url,
                profile.cover_url AS merchant_profile_cover_url,profile.location_label AS merchant_profile_location,
                profile.status AS merchant_profile_status,profile.visibility AS merchant_profile_visibility,
                reward.public_id AS reward_public_id,reward.title AS reward_title,
                reward.description AS reward_description,reward.reward_type,reward.value_type,
                reward.value_amount_cents,reward.value_percent,reward.currency AS reward_currency,
                reward.metadata_json AS reward_metadata_json
           FROM campaigns campaign
           INNER JOIN users merchant ON merchant.id=campaign.merchant_user_id
           LEFT JOIN public_profiles profile ON profile.user_id=campaign.merchant_user_id
           LEFT JOIN reward_templates reward ON reward.id=campaign.reward_template_id
          WHERE campaign.campaign_type='public_donation'
            AND campaign.status='active'
            AND (campaign.public_id=? OR campaign.public_slug=?)
            AND (campaign.starts_at IS NULL OR campaign.starts_at<=NOW())
            AND (campaign.ends_at IS NULL OR campaign.ends_at>=NOW())
          LIMIT 1"
    );
    $stmt->execute([$campaignRef, $campaignRef]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    if (!mg_public_donations_is_enabled_for((int)$row['merchant_user_id'], function_exists('mg_current_user') ? mg_current_user() : null)) {
        return null;
    }

    return $row;
}

function mg_public_donations_public_impact(PDO $pdo, int $merchantId, int $campaignId): array
{
    $rewardStmt = $pdo->prepare(
        "SELECT COUNT(*) AS gross_allocated,
                COALESCE(SUM(reward.status='recalled'),0) AS recalled,
                COALESCE(SUM(reward.status='allocated'),0) AS net_allocated,
                COUNT(DISTINCT reward.original_community_user_id) AS funded_accounts
           FROM campaign_donation_rewards reward
          WHERE reward.merchant_user_id=? AND reward.campaign_id=?"
    );
    $rewardStmt->execute([$merchantId, $campaignId]);
    $reward = $rewardStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $currencyStmt = $pdo->prepare(
        "SELECT reward.currency_snapshot AS currency,
                COALESCE(SUM(reward.value_cents_snapshot),0) AS gross_cents,
                COALESCE(SUM(CASE WHEN reward.status='recalled' THEN reward.value_cents_snapshot ELSE 0 END),0) AS recalled_cents,
                COALESCE(SUM(CASE WHEN reward.status='allocated' THEN reward.value_cents_snapshot ELSE 0 END),0) AS net_cents
           FROM campaign_donation_rewards reward
          WHERE reward.merchant_user_id=? AND reward.campaign_id=?
          GROUP BY reward.currency_snapshot
          ORDER BY reward.currency_snapshot"
    );
    $currencyStmt->execute([$merchantId, $campaignId]);
    $values = [];
    foreach ($currencyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $values[] = [
            'currency' => (string)$row['currency'],
            'gross_cents' => (int)$row['gross_cents'],
            'recalled_cents' => (int)$row['recalled_cents'],
            'net_cents' => (int)$row['net_cents'],
        ];
    }

    $assignmentStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT assignment.community_user_id)
           FROM campaign_community_assignments assignment
          WHERE assignment.merchant_user_id=?
            AND assignment.campaign_id=?
            AND assignment.status IN ('active','paused')"
    );
    $assignmentStmt->execute([$merchantId, $campaignId]);
    $supportedAccounts = (int)$assignmentStmt->fetchColumn();

    $visibleStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT assignment.community_user_id)
           FROM campaign_community_assignments assignment
           INNER JOIN public_profiles profile
                   ON profile.user_id=assignment.community_user_id
                  AND profile.status='active'
                  AND profile.visibility IN ('public','unlisted')
                  AND profile.slug<>''
          WHERE assignment.merchant_user_id=?
            AND assignment.campaign_id=?
            AND assignment.status IN ('active','paused')
            AND assignment.public_display_status='approved'"
    );
    $visibleStmt->execute([$merchantId, $campaignId]);
    $visibleAccounts = (int)$visibleStmt->fetchColumn();

    return [
        'supported_accounts' => $supportedAccounts,
        'publicly_featured_accounts' => $visibleAccounts,
        'anonymous_accounts' => max(0, $supportedAccounts - $visibleAccounts),
        'funded_accounts' => (int)($reward['funded_accounts'] ?? 0),
        'gross_allocated' => (int)($reward['gross_allocated'] ?? 0),
        'recalled' => (int)($reward['recalled'] ?? 0),
        'net_allocated' => (int)($reward['net_allocated'] ?? 0),
        'stated_value_by_currency' => $values,
    ];
}

function mg_public_donations_public_community_cards(PDO $pdo, int $merchantId, int $campaignId): array
{
    $stmt = $pdo->prepare(
        "SELECT profile.slug,profile.display_name,profile.headline,profile.avatar_url,profile.cover_url,
                profile.location_label,profile.visibility,
                COUNT(reward.id) AS gross_allocated,
                COALESCE(SUM(reward.status='recalled'),0) AS recalled,
                COALESCE(SUM(reward.status='allocated'),0) AS net_allocated,
                COALESCE(SUM(CASE WHEN reward.status='allocated' THEN reward.value_cents_snapshot ELSE 0 END),0) AS net_value_cents,
                MIN(reward.currency_snapshot) AS currency,
                COUNT(DISTINCT reward.currency_snapshot) AS currency_count
           FROM campaign_community_assignments assignment
           INNER JOIN public_profiles profile
                   ON profile.user_id=assignment.community_user_id
                  AND profile.status='active'
                  AND profile.visibility IN ('public','unlisted')
                  AND profile.slug<>''
           LEFT JOIN campaign_donation_rewards reward
                  ON reward.merchant_user_id=assignment.merchant_user_id
                 AND reward.campaign_id=assignment.campaign_id
                 AND reward.original_community_user_id=assignment.community_user_id
          WHERE assignment.merchant_user_id=?
            AND assignment.campaign_id=?
            AND assignment.status IN ('active','paused')
            AND assignment.public_display_status='approved'
          GROUP BY assignment.community_user_id,profile.slug,profile.display_name,profile.headline,
                   profile.avatar_url,profile.cover_url,profile.location_label,profile.visibility
          ORDER BY net_allocated DESC,profile.display_name ASC
          LIMIT 100"
    );
    $stmt->execute([$merchantId, $campaignId]);
    $cards = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = trim((string)$row['display_name']) ?: 'Community member';
        $slug = trim((string)$row['slug']);
        $cards[] = [
            'display_name' => mb_substr($name, 0, 180),
            'headline' => mb_substr(trim((string)$row['headline']), 0, 240),
            'location' => mb_substr(trim((string)$row['location_label']), 0, 180),
            'avatar_url' => mg_campaign_landing_safe_url($row['avatar_url'] ?? null),
            'cover_url' => mg_campaign_landing_safe_url($row['cover_url'] ?? null),
            'profile_url' => '/profile.php?slug=' . rawurlencode($slug),
            'profile_indexable' => (string)$row['visibility'] === 'public',
            'support' => [
                'gross_allocated' => (int)$row['gross_allocated'],
                'recalled' => (int)$row['recalled'],
                'net_allocated' => (int)$row['net_allocated'],
                'net_stated_value_cents' => (int)$row['net_value_cents'],
                'currency' => (int)$row['currency_count'] === 1 ? (string)$row['currency'] : null,
                'mixed_currency' => (int)$row['currency_count'] > 1,
            ],
        ];
    }

    return $cards;
}

function mg_public_donations_public_payload(PDO $pdo, string $campaignRef): ?array
{
    if (!mg_public_donations_public_schema_ready($pdo)) {
        throw new RuntimeException('Public Donations public reporting schema is incomplete.');
    }

    $row = mg_public_donations_public_campaign($pdo, $campaignRef);
    if (!$row) {
        return null;
    }

    $campaignId = (int)$row['id'];
    $merchantId = (int)$row['merchant_user_id'];
    $slug = trim((string)$row['public_slug']);
    $ref = $slug !== '' ? $slug : (string)$row['public_id'];
    $profileEligible = mg_public_donations_public_profile_eligible($row);
    $profileSlug = trim((string)$row['merchant_profile_slug']);
    $merchantName = trim((string)$row['merchant_profile_display_name'])
        ?: (trim((string)$row['merchant_display_name']) ?: (trim((string)$row['merchant_full_name']) ?: 'Microgifter merchant'));
    $headline = trim((string)$row['form_headline']) ?: (string)$row['title'];
    $description = trim((string)$row['form_description'])
        ?: (trim((string)$row['description']) ?: 'See how this merchant is allocating promotional rewards directly to Community accounts.');
    $canonical = '/public-donations.php?campaign=' . rawurlencode($ref);
    $campaignImage = mg_campaign_landing_campaign_image($row);
    $rewardImage = mg_campaign_landing_reward_cover($row);
    $primaryImage = $campaignImage ?? $rewardImage ?? mg_campaign_landing_safe_url($row['merchant_profile_cover_url'] ?? null);
    $indexable = mg_public_donations_public_indexable($row);

    return [
        'campaign' => [
            'id' => (string)$row['public_id'],
            'slug' => $slug !== '' ? $slug : null,
            'title' => (string)$row['title'],
            'headline' => $headline,
            'description' => $description,
            'status' => 'active',
            'starts_at' => $row['starts_at'] !== null ? (string)$row['starts_at'] : null,
            'ends_at' => $row['ends_at'] !== null ? (string)$row['ends_at'] : null,
            'image_url' => $campaignImage,
            'public_url' => $canonical,
            'public_transactional' => false,
            'public_mode' => 'informational',
        ],
        'merchant' => [
            'display_name' => mb_substr($merchantName, 0, 180),
            'headline' => $profileEligible ? mb_substr(trim((string)$row['merchant_profile_headline']), 0, 240) : '',
            'location' => $profileEligible ? mb_substr(trim((string)$row['merchant_profile_location']), 0, 180) : '',
            'avatar_url' => $profileEligible ? mg_campaign_landing_safe_url($row['merchant_profile_avatar_url'] ?? null) : null,
            'cover_url' => $profileEligible ? mg_campaign_landing_safe_url($row['merchant_profile_cover_url'] ?? null) : null,
            'profile_url' => $profileEligible ? '/profile.php?slug=' . rawurlencode($profileSlug) : null,
            'community_url' => $profileEligible ? '/profile.php?slug=' . rawurlencode($profileSlug) . '&tab=community' : null,
            'offers_url' => $profileEligible ? '/profile.php?slug=' . rawurlencode($profileSlug) . '&tab=products' : '/discover.php',
            'profile_visibility' => $profileEligible ? (string)$row['merchant_profile_visibility'] : 'unavailable',
        ],
        'reward' => [
            'title' => trim((string)$row['reward_title']) ?: 'Promotional reward',
            'description' => mb_substr(trim((string)$row['reward_description']), 0, 1000),
            'reward_type' => trim((string)$row['reward_type']),
            'value_label' => mg_public_donations_public_value_label($row),
            'image_url' => $rewardImage,
        ],
        'impact' => mg_public_donations_public_impact($pdo, $merchantId, $campaignId),
        'community_accounts' => mg_public_donations_public_community_cards($pdo, $merchantId, $campaignId),
        'governance' => [
            'merchant_allocated' => true,
            'public_purchase_available' => false,
            'public_request_available' => false,
            'tax_deductible_contribution' => false,
            'cash_donation' => false,
            'statement' => 'The merchant allocates promotional rewards directly to selected Community accounts. The public cannot purchase, request, join, claim, or reserve rewards from this page.',
        ],
        'privacy' => [
            'approved_public_profiles_only' => true,
            'anonymous_accounts_in_aggregate' => true,
            'final_recipient_identity_exposed' => false,
            'contact_data_exposed' => false,
            'claim_or_ownership_identifiers_exposed' => false,
        ],
        'seo' => [
            'indexable' => $indexable,
            'robots' => $indexable ? 'index,follow' : 'noindex,nofollow',
            'canonical' => $canonical,
            'title' => $headline . ' | Microgifter',
            'description' => mb_substr($description, 0, 300),
            'image_url' => $primaryImage,
        ],
        'generated_at' => gmdate('c'),
    ];
}
