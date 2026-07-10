<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/campaign-types.php';

const MG_PREDICTIVE_CAMPAIGN_TABLE = 'mg_predictive_campaign_recommendations';

function mg_predictive_campaign_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $table;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return $cache[$key] = (int)$stmt->fetchColumn() > 0;
}

function mg_predictive_campaign_schema_ready(PDO $pdo): bool
{
    return mg_predictive_campaign_table_exists($pdo, MG_PREDICTIVE_CAMPAIGN_TABLE);
}

function mg_predictive_campaign_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_predictive_campaign_encode(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_predictive_campaign_clamp(float $value, float $minimum = 0.0, float $maximum = 100.0): float
{
    return round(max($minimum, min($maximum, $value)), 1);
}

function mg_predictive_campaign_scalar(PDO $pdo, string $sql, array $params = [], int|float $fallback = 0): int|float
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return is_numeric($value) ? $value + 0 : $fallback;
    } catch (Throwable) {
        return $fallback;
    }
}

function mg_predictive_campaign_store_snapshot(PDO $pdo, int $merchantId): array
{
    $behavior = [
        'total_profiles' => 0,
        'new_or_exploring' => 0,
        'engaged_or_loyal' => 0,
        'dormant_or_high_risk' => 0,
        'product_explorers' => 0,
        'average_return_probability' => 0.0,
        'average_campaign_probability' => 0.0,
        'average_inactivity_risk' => 0.0,
    ];
    if (mg_predictive_campaign_table_exists($pdo, 'mg_merchant_customer_behavior_profiles')) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) total_profiles,
                    SUM(CASE WHEN relationship_stage IN ('new','exploring') THEN 1 ELSE 0 END) new_or_exploring,
                    SUM(CASE WHEN relationship_stage IN ('engaged','loyal') THEN 1 ELSE 0 END) engaged_or_loyal,
                    SUM(CASE WHEN relationship_stage='dormant' OR inactivity_risk_probability>=65 THEN 1 ELSE 0 END) dormant_or_high_risk,
                    SUM(CASE WHEN dominant_pattern='product_explorer' THEN 1 ELSE 0 END) product_explorers,
                    AVG(return_7d_probability) average_return_probability,
                    AVG(campaign_engagement_probability) average_campaign_probability,
                    AVG(inactivity_risk_probability) average_inactivity_risk
               FROM mg_merchant_customer_behavior_profiles
              WHERE merchant_user_id=?"
        );
        $stmt->execute([$merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (array_keys($behavior) as $key) {
            if (array_key_exists($key, $row) && is_numeric($row[$key])) {
                $behavior[$key] = str_starts_with($key, 'average_') ? round((float)$row[$key], 1) : (int)$row[$key];
            }
        }
    }

    $wallet = [
        'issued' => 0,
        'claimed' => 0,
        'redeemed' => 0,
        'unclaimed_7d' => 0,
        'redeemed_customers' => 0,
        'claim_rate' => 0.0,
        'redemption_rate' => 0.0,
    ];
    if (mg_predictive_campaign_table_exists($pdo, 'wallet_items')) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) issued,
                    SUM(CASE WHEN status IN ('claimed','redeemed') THEN 1 ELSE 0 END) claimed,
                    SUM(CASE WHEN status='redeemed' THEN 1 ELSE 0 END) redeemed,
                    SUM(CASE WHEN status IN ('issued','viewed') AND issued_at<DATE_SUB(NOW(),INTERVAL 7 DAY) THEN 1 ELSE 0 END) unclaimed_7d,
                    COUNT(DISTINCT CASE WHEN status='redeemed' AND user_id IS NOT NULL THEN user_id END) redeemed_customers
               FROM wallet_items
              WHERE merchant_user_id=? AND status<>'cancelled'"
        );
        $stmt->execute([$merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (['issued','claimed','redeemed','unclaimed_7d','redeemed_customers'] as $key) {
            $wallet[$key] = (int)($row[$key] ?? 0);
        }
        $wallet['claim_rate'] = $wallet['issued'] > 0 ? round(($wallet['claimed'] / $wallet['issued']) * 100, 1) : 0.0;
        $wallet['redemption_rate'] = $wallet['issued'] > 0 ? round(($wallet['redeemed'] / $wallet['issued']) * 100, 1) : 0.0;
    }

    $campaigns = ['total' => 0, 'active' => 0, 'draft' => 0, 'contacts' => 0, 'events' => 0];
    if (mg_predictive_campaign_table_exists($pdo, 'campaigns')) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) total,
                    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) active,
                    SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) draft
               FROM campaigns WHERE merchant_user_id=? AND status<>'archived'"
        );
        $stmt->execute([$merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $campaigns['total'] = (int)($row['total'] ?? 0);
        $campaigns['active'] = (int)($row['active'] ?? 0);
        $campaigns['draft'] = (int)($row['draft'] ?? 0);
    }
    if (mg_predictive_campaign_table_exists($pdo, 'campaign_contacts')) {
        $campaigns['contacts'] = (int)mg_predictive_campaign_scalar($pdo, 'SELECT COUNT(*) FROM campaign_contacts WHERE merchant_user_id=?', [$merchantId]);
    }
    if (mg_predictive_campaign_table_exists($pdo, 'campaign_events')) {
        $campaigns['events'] = (int)mg_predictive_campaign_scalar($pdo, 'SELECT COUNT(*) FROM campaign_events WHERE merchant_user_id=?', [$merchantId]);
    }

    $rewards = ['total' => 0, 'active' => 0, 'draft' => 0];
    if (mg_predictive_campaign_table_exists($pdo, 'reward_templates')) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) total,
                    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) active,
                    SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) draft
               FROM reward_templates WHERE merchant_user_id=? AND status<>'archived'"
        );
        $stmt->execute([$merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $rewards['total'] = (int)($row['total'] ?? 0);
        $rewards['active'] = (int)($row['active'] ?? 0);
        $rewards['draft'] = (int)($row['draft'] ?? 0);
    }

    $store = ['recent_customers_30d' => 0, 'recent_sessions_30d' => 0];
    if (mg_predictive_campaign_table_exists($pdo, 'mg_store_sessions')) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) recent_sessions_30d,
                    COUNT(DISTINCT CASE WHEN customer_user_id IS NOT NULL THEN customer_user_id END) recent_customers_30d
               FROM mg_store_sessions
              WHERE merchant_user_id=? AND entered_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)'
        );
        $stmt->execute([$merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $store['recent_sessions_30d'] = (int)($row['recent_sessions_30d'] ?? 0);
        $store['recent_customers_30d'] = (int)($row['recent_customers_30d'] ?? 0);
    }

    return compact('behavior', 'wallet', 'campaigns', 'rewards', 'store');
}

function mg_predictive_campaign_active_rewards(PDO $pdo, int $merchantId): array
{
    if (!mg_predictive_campaign_table_exists($pdo, 'reward_templates')) return [];
    $stmt = $pdo->prepare(
        "SELECT id,public_id,title,description,reward_type,value_type,value_amount_cents,value_percent,currency,
                quantity_limit,issued_count,per_user_limit,status,updated_at
           FROM reward_templates
          WHERE merchant_user_id=? AND status='active'
            AND (quantity_limit IS NULL OR issued_count<quantity_limit)
          ORDER BY updated_at DESC,id DESC LIMIT 50"
    );
    $stmt->execute([$merchantId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_predictive_campaign_choose_reward(array $rewards, array $preferredTypes): ?array
{
    foreach ($preferredTypes as $type) {
        foreach ($rewards as $reward) {
            if ((string)($reward['reward_type'] ?? '') === $type) return $reward;
        }
    }
    return $rewards[0] ?? null;
}

function mg_predictive_campaign_reward_public(array $reward): array
{
    return [
        'id' => (string)($reward['public_id'] ?? ''),
        'title' => (string)($reward['title'] ?? ''),
        'description' => (string)($reward['description'] ?? ''),
        'reward_type' => (string)($reward['reward_type'] ?? 'custom'),
        'value_type' => (string)($reward['value_type'] ?? 'custom'),
        'value_amount_cents' => (int)($reward['value_amount_cents'] ?? 0),
        'value_percent' => $reward['value_percent'] === null ? null : (float)$reward['value_percent'],
        'currency' => (string)($reward['currency'] ?? 'USD'),
        'quantity_limit' => $reward['quantity_limit'] === null ? null : (int)$reward['quantity_limit'],
        'issued_count' => (int)($reward['issued_count'] ?? 0),
        'per_user_limit' => (int)($reward['per_user_limit'] ?? 1),
        'status' => (string)($reward['status'] ?? 'active'),
    ];
}

function mg_predictive_campaign_projection(int $audience, float $engagementProbability, array $reward): array
{
    $audience = max(0, $audience);
    $engagementProbability = mg_predictive_campaign_clamp($engagementProbability, 0, 95);
    $averageOrderCents = 3500;
    $redemptionAfterEngagement = 0.65;
    $projectedEngagements = round($audience * ($engagementProbability / 100), 1);
    $projectedRedemptions = round($projectedEngagements * $redemptionAfterEngagement, 1);
    $valueType = (string)($reward['value_type'] ?? 'custom');
    $rewardUnitCost = match ($valueType) {
        'fixed_amount' => max(0, (int)($reward['value_amount_cents'] ?? 0)),
        'percent' => (int)round($averageOrderCents * (max(0, (float)($reward['value_percent'] ?? 0)) / 100)),
        'free_item' => max(100, (int)($reward['estimated_unit_cost_cents'] ?? 450)),
        default => max(100, (int)($reward['estimated_unit_cost_cents'] ?? 350)),
    };
    $estimatedRewardCost = (int)round($projectedRedemptions * $rewardUnitCost);
    $estimatedRevenueLow = (int)round($projectedRedemptions * $averageOrderCents * 0.75);
    $estimatedRevenueHigh = (int)round($projectedRedemptions * $averageOrderCents * 1.25);
    return [
        'engagement_probability' => $engagementProbability,
        'projected_engagements' => $projectedEngagements,
        'projected_redemptions' => $projectedRedemptions,
        'estimated_reward_cost_cents' => $estimatedRewardCost,
        'estimated_revenue_low_cents' => $estimatedRevenueLow,
        'estimated_revenue_high_cents' => $estimatedRevenueHigh,
        'assumptions' => [
            'average_order_cents' => $averageOrderCents,
            'redemption_after_engagement_percent' => 65,
            'reward_unit_cost_cents' => $rewardUnitCost,
            'projection_is_estimate' => true,
        ],
    ];
}

function mg_predictive_campaign_recommendation(
    string $type,
    string $scope,
    string $title,
    string $summary,
    string $rationale,
    string $audienceName,
    int $audienceCount,
    string $campaignType,
    array $campaignProposal,
    array $rewardProposal,
    array $segmentRules,
    array $evidence,
    float $confidence,
    float $engagementProbability,
    ?array $existingReward
): array {
    $rewardStrategy = $existingReward ? 'reuse_existing' : 'create_draft';
    $projectionReward = $existingReward ? mg_predictive_campaign_reward_public($existingReward) : $rewardProposal;
    return [
        'recommendation_type' => $type,
        'scope_type' => $scope,
        'title' => mb_substr($title, 0, 180),
        'summary' => mb_substr($summary, 0, 500),
        'rationale' => $rationale,
        'audience_name' => mb_substr($audienceName, 0, 190),
        'audience_count' => max(0, $audienceCount),
        'campaign_type' => $campaignType,
        'reward_strategy' => $rewardStrategy,
        'reward_template_id' => $existingReward ? (int)$existingReward['id'] : null,
        'recommended_reward' => $existingReward ? mg_predictive_campaign_reward_public($existingReward) : $rewardProposal,
        'recommended_campaign' => $campaignProposal,
        'segment_rules' => $segmentRules,
        'evidence' => $evidence,
        'projections' => mg_predictive_campaign_projection($audienceCount, $engagementProbability, $projectionReward),
        'confidence_score' => mg_predictive_campaign_clamp($confidence, 5, 95),
    ];
}

function mg_predictive_campaign_opportunities(PDO $pdo, int $merchantId): array
{
    $snapshot = mg_predictive_campaign_store_snapshot($pdo, $merchantId);
    $activeRewards = mg_predictive_campaign_active_rewards($pdo, $merchantId);
    $behavior = $snapshot['behavior'];
    $wallet = $snapshot['wallet'];
    $campaigns = $snapshot['campaigns'];
    $store = $snapshot['store'];
    $items = [];

    $welcomeAudience = max((int)$behavior['new_or_exploring'], (int)$store['recent_customers_30d']);
    if ($welcomeAudience > 0 || (int)$campaigns['active'] === 0) {
        $reward = mg_predictive_campaign_choose_reward($activeRewards, ['discount','free_item','dollar_credit']);
        $rewardProposal = [
            'title' => 'Welcome Visit Reward',
            'description' => 'A reusable welcome reward for first-time and early-stage customers.',
            'reward_type' => 'discount',
            'value_type' => 'percent',
            'value_amount_cents' => 0,
            'value_percent' => 10.0,
            'currency' => 'USD',
            'redemption_instructions' => 'Present this reward during a qualifying merchant purchase.',
            'expiration_rule' => 'after_issue',
            'expiration_days' => 30,
            'quantity_limit' => max(50, $welcomeAudience * 2),
            'per_user_limit' => 1,
            'estimated_unit_cost_cents' => 350,
        ];
        $items[] = mg_predictive_campaign_recommendation(
            'new_customer_welcome', 'store_trend', 'Build an evergreen welcome campaign',
            $welcomeAudience > 0 ? $welcomeAudience . ' recent or early-stage customers can be nurtured with one reusable welcome flow.' : 'No active campaign is running. Establish an evergreen welcome campaign for future first-time customers.',
            'A merchant-level welcome campaign provides a controlled first offer and creates a consistent baseline for measuring acquisition and return behavior.',
            'New and exploring customers', $welcomeAudience, 'newsletter_signup',
            [
                'title' => 'Join the list and get a welcome reward',
                'description' => 'Welcome new customers with a merchant-approved reward and begin measuring return behavior.',
                'form_headline' => 'Join our rewards list',
                'form_description' => 'Enter your information to receive a merchant welcome reward.',
                'success_message' => 'Welcome reward issued.',
                'quantity_limit' => max(50, $welcomeAudience * 2),
                'per_user_limit' => 1,
            ],
            $rewardProposal,
            ['relationship_stage' => ['new','exploring'], 'frequency_cap' => 1, 'scope' => 'merchant_customer_relationship'],
            [
                ['key' => 'early_relationships', 'label' => 'Early relationships', 'value' => $welcomeAudience, 'direction' => 'positive', 'reason' => 'New and exploring customer relationships benefit from one consistent welcome path.'],
                ['key' => 'active_campaigns', 'label' => 'Active campaigns', 'value' => (int)$campaigns['active'], 'direction' => (int)$campaigns['active'] > 0 ? 'neutral' : 'opportunity', 'reason' => 'An evergreen campaign provides a baseline when no active acquisition campaign is available.'],
            ],
            35 + min(35, $welcomeAudience * 2),
            max(18, min(65, (float)$behavior['average_campaign_probability'] ?: 32)),
            $reward
        );
    }

    $comebackAudience = (int)$behavior['dormant_or_high_risk'];
    if ($comebackAudience > 0) {
        $reward = mg_predictive_campaign_choose_reward($activeRewards, ['discount','dollar_credit','free_item']);
        $rewardProposal = [
            'title' => 'Come Back Reward',
            'description' => 'A controlled return-visit reward for dormant or high inactivity-risk customers.',
            'reward_type' => 'discount',
            'value_type' => 'percent',
            'value_amount_cents' => 0,
            'value_percent' => 15.0,
            'currency' => 'USD',
            'redemption_instructions' => 'Use during a qualifying return visit.',
            'expiration_rule' => 'after_issue',
            'expiration_days' => 21,
            'quantity_limit' => max(25, $comebackAudience),
            'per_user_limit' => 1,
            'estimated_unit_cost_cents' => 525,
        ];
        $engagement = max(12, min(65, ((float)$behavior['average_campaign_probability'] * 0.55) + ((float)$behavior['average_return_probability'] * 0.2)));
        $items[] = mg_predictive_campaign_recommendation(
            'comeback_reactivation', 'customer_segment', 'Reconnect with customers at risk of lapsing',
            $comebackAudience . ' customer relationships are dormant or have elevated inactivity risk.',
            'A segment-level comeback campaign uses one approved merchant reward and lets customer behavior determine eligibility and timing without creating a reward per person.',
            'Dormant and high-risk customers', $comebackAudience, 'check_in_reward',
            [
                'title' => 'Come back and unlock a return-visit reward',
                'description' => 'Invite previously engaged customers to return and verify their visit before receiving the approved reward.',
                'form_headline' => 'Welcome back',
                'form_description' => 'Check in during your return visit to unlock the merchant reward.',
                'success_message' => 'Return visit verified. Reward eligibility checked.',
                'quantity_limit' => max(25, $comebackAudience),
                'per_user_limit' => 1,
            ],
            $rewardProposal,
            ['relationship_stage' => ['dormant'], 'minimum_inactivity_risk' => 65, 'cooldown_days' => 30, 'frequency_cap' => 1],
            [
                ['key' => 'high_inactivity', 'label' => 'Dormant or high-risk relationships', 'value' => $comebackAudience, 'direction' => 'opportunity', 'reason' => 'These relationships have the strongest evidence for a controlled comeback invitation.'],
                ['key' => 'average_inactivity_risk', 'label' => 'Average inactivity risk', 'value' => (float)$behavior['average_inactivity_risk'], 'direction' => 'risk', 'reason' => 'The segment is driven by observed recency and relationship history.'],
            ],
            45 + min(25, $comebackAudience * 2) + ((float)$behavior['average_inactivity_risk'] * 0.15),
            $engagement,
            $reward
        );
    }

    $loyalAudience = (int)$behavior['engaged_or_loyal'];
    if ($loyalAudience > 0) {
        $reward = mg_predictive_campaign_choose_reward($activeRewards, ['free_item','perk_upgrade','dollar_credit','discount']);
        $rewardProposal = [
            'title' => 'Loyalty Milestone Reward',
            'description' => 'A reusable milestone reward for repeat visits or purchases.',
            'reward_type' => 'free_item',
            'value_type' => 'free_item',
            'value_amount_cents' => 0,
            'value_percent' => null,
            'currency' => 'USD',
            'redemption_instructions' => 'Unlock after completing the merchant milestone.',
            'expiration_rule' => 'after_issue',
            'expiration_days' => 45,
            'quantity_limit' => max(25, $loyalAudience),
            'per_user_limit' => 1,
            'estimated_unit_cost_cents' => 450,
        ];
        $items[] = mg_predictive_campaign_recommendation(
            'loyalty_milestone', 'customer_segment', 'Turn repeat engagement into a loyalty milestone',
            $loyalAudience . ' engaged or loyal customer relationships can enter a reusable visit-tracking program.',
            'A store-level loyalty campaign avoids creating individual promotions while allowing each customer to accumulate progress against the same merchant rules.',
            'Engaged and loyal customers', $loyalAudience, 'stamp_card_reward',
            [
                'title' => 'Collect visits and unlock a loyalty reward',
                'description' => 'Track repeat visits through the existing campaign and CRM systems.',
                'form_headline' => 'Add a loyalty visit',
                'form_description' => 'Each verified visit moves you closer to the merchant reward.',
                'success_message' => 'Visit recorded. Loyalty progress updated.',
                'quantity_limit' => null,
                'per_user_limit' => 1,
            ],
            $rewardProposal,
            ['relationship_stage' => ['engaged','loyal'], 'required_stamps' => 5, 'cooldown_hours' => 24, 'frequency_cap' => 1],
            [
                ['key' => 'engaged_loyal', 'label' => 'Engaged and loyal relationships', 'value' => $loyalAudience, 'direction' => 'positive', 'reason' => 'Repeat relationships are suitable for one shared loyalty program.'],
                ['key' => 'return_probability', 'label' => 'Average return probability', 'value' => (float)$behavior['average_return_probability'], 'direction' => 'positive', 'reason' => 'Existing behavior profiles estimate repeat-visit likelihood.'],
            ],
            45 + min(30, $loyalAudience * 2) + ((float)$behavior['average_return_probability'] * 0.15),
            max(25, min(80, (float)$behavior['average_campaign_probability'] ?: 45)),
            $reward
        );
    }

    $redeemedAudience = (int)$wallet['redeemed_customers'];
    if ($redeemedAudience > 0) {
        $reward = mg_predictive_campaign_choose_reward($activeRewards, ['perk_upgrade','free_item','discount']);
        $rewardProposal = [
            'title' => 'Feedback Thank-You Reward',
            'description' => 'A small reusable reward for customers who provide post-redemption feedback.',
            'reward_type' => 'perk_upgrade',
            'value_type' => 'custom',
            'value_amount_cents' => 0,
            'value_percent' => null,
            'currency' => 'USD',
            'redemption_instructions' => 'Complete the feedback campaign to unlock the merchant-defined perk.',
            'expiration_rule' => 'after_issue',
            'expiration_days' => 30,
            'quantity_limit' => max(25, $redeemedAudience),
            'per_user_limit' => 1,
            'estimated_unit_cost_cents' => 300,
        ];
        $items[] = mg_predictive_campaign_recommendation(
            'post_redemption_feedback', 'customer_segment', 'Follow redemption with a feedback loop',
            $redeemedAudience . ' identified customers have completed at least one redemption.',
            'Post-redemption feedback improves merchant memory and creates evidence for future retention decisions without issuing anything until the customer completes the approved campaign.',
            'Customers with completed redemptions', $redeemedAudience, 'survey_feedback_reward',
            [
                'title' => 'Tell us about your reward experience',
                'description' => 'Ask customers who redeemed a reward for short structured feedback.',
                'form_headline' => 'How was your experience?',
                'form_description' => 'Share a rating and short note to unlock the merchant-approved thank-you reward.',
                'success_message' => 'Feedback received. Reward eligibility checked.',
                'quantity_limit' => max(25, $redeemedAudience),
                'per_user_limit' => 1,
            ],
            $rewardProposal,
            ['wallet_status' => ['redeemed'], 'lookback_days' => 90, 'frequency_cap' => 1, 'cooldown_days' => 60],
            [
                ['key' => 'redeemed_customers', 'label' => 'Customers with redemption history', 'value' => $redeemedAudience, 'direction' => 'positive', 'reason' => 'Completed redemption creates a clear post-purchase feedback moment.'],
                ['key' => 'redemption_rate', 'label' => 'Observed redemption rate', 'value' => (float)$wallet['redemption_rate'], 'direction' => 'positive', 'reason' => 'Campaign timing is grounded in actual Wallet outcomes.'],
            ],
            45 + min(30, $redeemedAudience * 4) + ((float)$wallet['redemption_rate'] * 0.2),
            max(20, min(75, 30 + ((float)$wallet['redemption_rate'] * 0.45))),
            $reward
        );
    }

    $unclaimedAudience = (int)$wallet['unclaimed_7d'];
    if ($unclaimedAudience > 0) {
        $reward = mg_predictive_campaign_choose_reward($activeRewards, ['discount','dollar_credit','free_item','perk_upgrade']);
        $rewardProposal = [
            'title' => 'Wallet Recovery Reward',
            'description' => 'A controlled offer for customers whose issued rewards remain unclaimed.',
            'reward_type' => 'dollar_credit',
            'value_type' => 'fixed_amount',
            'value_amount_cents' => 500,
            'value_percent' => null,
            'currency' => 'USD',
            'redemption_instructions' => 'Use during a qualifying merchant purchase.',
            'expiration_rule' => 'after_issue',
            'expiration_days' => 21,
            'quantity_limit' => max(25, $unclaimedAudience),
            'per_user_limit' => 1,
            'estimated_unit_cost_cents' => 500,
        ];
        $items[] = mg_predictive_campaign_recommendation(
            'unclaimed_reward_recovery', 'customer_segment', 'Recover customers with aging unclaimed rewards',
            $unclaimedAudience . ' Wallet items have remained issued or viewed for more than seven days.',
            'The predictive studio recommends one merchant campaign for the segment. It does not resend or replace individual Wallet items automatically.',
            'Customers with aging unclaimed Wallet items', $unclaimedAudience, 'agent_offer',
            [
                'title' => 'Choose your next merchant reward',
                'description' => 'Invite customers with aging Wallet activity to re-engage through one merchant-approved offer.',
                'form_headline' => 'Your next reward is waiting',
                'form_description' => 'Tell the merchant what offer interests you and complete the campaign to unlock the approved reward.',
                'success_message' => 'Offer interest recorded. Reward eligibility checked.',
                'quantity_limit' => max(25, $unclaimedAudience),
                'per_user_limit' => 1,
            ],
            $rewardProposal,
            ['wallet_status' => ['issued','viewed'], 'minimum_age_days' => 7, 'frequency_cap' => 1, 'cooldown_days' => 30],
            [
                ['key' => 'unclaimed_7d', 'label' => 'Aging unclaimed Wallet items', 'value' => $unclaimedAudience, 'direction' => 'opportunity', 'reason' => 'Issued or viewed rewards older than seven days signal a recoverable engagement gap.'],
                ['key' => 'claim_rate', 'label' => 'Observed claim rate', 'value' => (float)$wallet['claim_rate'], 'direction' => 'neutral', 'reason' => 'The recommendation is calibrated against current claim behavior.'],
            ],
            45 + min(30, $unclaimedAudience * 3),
            max(12, min(60, 25 + ((float)$wallet['claim_rate'] * 0.25))),
            $reward
        );
    }

    $productAudience = (int)$behavior['product_explorers'];
    if ($productAudience > 0) {
        $reward = mg_predictive_campaign_choose_reward($activeRewards, ['discount','dollar_credit','free_item']);
        $rewardProposal = [
            'title' => 'Product Interest Reward',
            'description' => 'A reusable reward for customers showing repeated product exploration.',
            'reward_type' => 'discount',
            'value_type' => 'percent',
            'value_amount_cents' => 0,
            'value_percent' => 10.0,
            'currency' => 'USD',
            'redemption_instructions' => 'Apply to a qualifying merchant product purchase.',
            'expiration_rule' => 'after_issue',
            'expiration_days' => 14,
            'quantity_limit' => max(25, $productAudience),
            'per_user_limit' => 1,
            'estimated_unit_cost_cents' => 350,
        ];
        $items[] = mg_predictive_campaign_recommendation(
            'product_interest_followup', 'customer_segment', 'Convert product exploration into campaign intent',
            $productAudience . ' customer profiles currently show a product-explorer behavior pattern.',
            'The campaign is created for the segment while product and timing personalization remain customer-specific eligibility inputs.',
            'Product-explorer behavior segment', $productAudience, 'agent_offer',
            [
                'title' => 'Tell us which merchant product interests you',
                'description' => 'Capture product intent and connect the customer to one approved merchant reward.',
                'form_headline' => 'What are you interested in?',
                'form_description' => 'Share your product interest to unlock a relevant merchant offer.',
                'success_message' => 'Product interest recorded. Reward eligibility checked.',
                'quantity_limit' => max(25, $productAudience),
                'per_user_limit' => 1,
            ],
            $rewardProposal,
            ['dominant_pattern' => ['product_explorer'], 'minimum_campaign_probability' => 45, 'frequency_cap' => 1, 'cooldown_days' => 21],
            [
                ['key' => 'product_explorers', 'label' => 'Product-explorer profiles', 'value' => $productAudience, 'direction' => 'positive', 'reason' => 'Repeated product-view behavior supports a product-intent campaign.'],
                ['key' => 'campaign_probability', 'label' => 'Average campaign engagement probability', 'value' => (float)$behavior['average_campaign_probability'], 'direction' => 'positive', 'reason' => 'Behavior profiles provide the timing and eligibility signal.'],
            ],
            42 + min(30, $productAudience * 4) + ((float)$behavior['average_campaign_probability'] * 0.15),
            max(20, min(75, (float)$behavior['average_campaign_probability'] ?: 35)),
            $reward
        );
    }

    return ['snapshot' => $snapshot, 'opportunities' => array_slice($items, 0, 8)];
}

function mg_predictive_campaign_upsert(PDO $pdo, int $merchantId, array $item): void
{
    $dedupeKey = (string)$item['recommendation_type'] . ':' . gmdate('o-W');
    $stmt = $pdo->prepare(
        "INSERT INTO mg_predictive_campaign_recommendations
         (public_id,merchant_user_id,recommendation_type,scope_type,target_customer_user_id,dedupe_key,title,summary,rationale,
          audience_name,audience_count,campaign_type,reward_strategy,reward_template_id,recommended_reward_json,
          recommended_campaign_json,segment_rules_json,evidence_json,projections_json,confidence_score,status,generated_at,created_at,updated_at)
         VALUES (?,?,?,?,NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'new',NOW(),NOW(),NOW())
         ON DUPLICATE KEY UPDATE
          title=IF(status IN ('materialized','dismissed'),title,VALUES(title)),
          summary=IF(status IN ('materialized','dismissed'),summary,VALUES(summary)),
          rationale=IF(status IN ('materialized','dismissed'),rationale,VALUES(rationale)),
          audience_name=IF(status IN ('materialized','dismissed'),audience_name,VALUES(audience_name)),
          audience_count=IF(status IN ('materialized','dismissed'),audience_count,VALUES(audience_count)),
          campaign_type=IF(status IN ('materialized','dismissed'),campaign_type,VALUES(campaign_type)),
          reward_strategy=IF(status IN ('materialized','dismissed'),reward_strategy,VALUES(reward_strategy)),
          reward_template_id=IF(status IN ('materialized','dismissed'),reward_template_id,VALUES(reward_template_id)),
          recommended_reward_json=IF(status IN ('materialized','dismissed'),recommended_reward_json,VALUES(recommended_reward_json)),
          recommended_campaign_json=IF(status IN ('materialized','dismissed'),recommended_campaign_json,VALUES(recommended_campaign_json)),
          segment_rules_json=IF(status IN ('materialized','dismissed'),segment_rules_json,VALUES(segment_rules_json)),
          evidence_json=IF(status IN ('materialized','dismissed'),evidence_json,VALUES(evidence_json)),
          projections_json=IF(status IN ('materialized','dismissed'),projections_json,VALUES(projections_json)),
          confidence_score=IF(status IN ('materialized','dismissed'),confidence_score,VALUES(confidence_score)),
          status=IF(status IN ('materialized','dismissed'),status,'new'),
          generated_at=IF(status IN ('materialized','dismissed'),generated_at,NOW()),
          updated_at=NOW()"
    );
    $stmt->execute([
        mg_merchant_uuid(), $merchantId, $item['recommendation_type'], $item['scope_type'], $dedupeKey,
        $item['title'], $item['summary'], $item['rationale'], $item['audience_name'], $item['audience_count'],
        $item['campaign_type'], $item['reward_strategy'], $item['reward_template_id'],
        mg_predictive_campaign_encode($item['recommended_reward']), mg_predictive_campaign_encode($item['recommended_campaign']),
        mg_predictive_campaign_encode($item['segment_rules']), mg_predictive_campaign_encode($item['evidence']),
        mg_predictive_campaign_encode($item['projections']), $item['confidence_score'],
    ]);
}

function mg_predictive_campaign_generate(PDO $pdo, int $merchantId): array
{
    if (!mg_predictive_campaign_schema_ready($pdo)) {
        throw new RuntimeException('Predictive Campaign Studio setup is incomplete. Import database/predictive_campaign_studio_foundation_v1.sql.');
    }
    $result = mg_predictive_campaign_opportunities($pdo, $merchantId);
    foreach ($result['opportunities'] as $item) mg_predictive_campaign_upsert($pdo, $merchantId, $item);
    return $result;
}

function mg_predictive_campaign_public_row(array $row): array
{
    return [
        'id' => (string)$row['public_id'],
        'recommendation_type' => (string)$row['recommendation_type'],
        'scope_type' => (string)$row['scope_type'],
        'title' => (string)$row['title'],
        'summary' => (string)$row['summary'],
        'rationale' => (string)($row['rationale'] ?? ''),
        'audience_name' => (string)$row['audience_name'],
        'audience_count' => (int)$row['audience_count'],
        'campaign_type' => (string)$row['campaign_type'],
        'campaign_type_label' => mg_campaign_type_label((string)$row['campaign_type']),
        'reward_strategy' => (string)$row['reward_strategy'],
        'reward' => mg_predictive_campaign_json($row['recommended_reward_json'] ?? null),
        'campaign' => mg_predictive_campaign_json($row['recommended_campaign_json'] ?? null),
        'segment_rules' => mg_predictive_campaign_json($row['segment_rules_json'] ?? null),
        'evidence' => array_values(mg_predictive_campaign_json($row['evidence_json'] ?? null)),
        'projections' => mg_predictive_campaign_json($row['projections_json'] ?? null),
        'confidence_score' => (float)$row['confidence_score'],
        'status' => (string)$row['status'],
        'reward_template' => !empty($row['reward_public_id']) ? [
            'id' => (string)$row['reward_public_id'],
            'title' => (string)($row['reward_title'] ?? ''),
            'status' => (string)($row['reward_status'] ?? ''),
        ] : null,
        'materialized_campaign' => !empty($row['campaign_public_id']) ? [
            'id' => (string)$row['campaign_public_id'],
            'title' => (string)($row['campaign_title'] ?? ''),
            'status' => (string)($row['campaign_status'] ?? ''),
        ] : null,
        'generated_at' => $row['generated_at'] ?? null,
        'reviewed_at' => $row['reviewed_at'] ?? null,
        'materialized_at' => $row['materialized_at'] ?? null,
    ];
}

function mg_predictive_campaign_list(PDO $pdo, int $merchantId, string $status = 'open'): array
{
    if (!mg_predictive_campaign_schema_ready($pdo)) return [];
    $sql = "SELECT r.*,rt.public_id reward_public_id,rt.title reward_title,rt.status reward_status,
                   c.public_id campaign_public_id,c.title campaign_title,c.status campaign_status
              FROM mg_predictive_campaign_recommendations r
              LEFT JOIN reward_templates rt ON rt.id=r.reward_template_id
              LEFT JOIN campaigns c ON c.id=r.campaign_id
             WHERE r.merchant_user_id=?";
    $params = [$merchantId];
    if ($status === 'open') {
        $sql .= " AND r.status IN ('new','approved','materialized')";
    } elseif (in_array($status, ['new','approved','materialized','dismissed','expired'], true)) {
        $sql .= ' AND r.status=?';
        $params[] = $status;
    }
    $sql .= " ORDER BY FIELD(r.status,'new','approved','materialized','dismissed','expired'),r.confidence_score DESC,r.generated_at DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('mg_predictive_campaign_public_row', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_predictive_campaign_slug(string $title): string
{
    $slug = strtolower(trim((string)(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? '')));
    return substr(trim($slug, '-') ?: 'campaign', 0, 120);
}

function mg_predictive_campaign_unique_slug(PDO $pdo, int $merchantId, string $title): string
{
    $base = mg_predictive_campaign_slug($title);
    $candidate = $base;
    $suffix = 1;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND public_slug=?');
    while (true) {
        $stmt->execute([$merchantId, $candidate]);
        if ((int)$stmt->fetchColumn() === 0) return $candidate;
        $suffix++;
        $candidate = substr($base, 0, max(1, 120 - strlen((string)$suffix) - 1)) . '-' . $suffix;
    }
}

function mg_predictive_campaign_rules(string $campaignType, string $recommendationId, array $segmentRules): array
{
    $rules = [
        'campaign_type' => $campaignType,
        'version' => 2,
        'registry' => 'predictive_campaign_studio_v1',
        'predictive_recommendation_id' => $recommendationId,
        'predictive_segment_rules' => $segmentRules,
        'merchant_approval_required' => true,
        'automatic_launch' => false,
    ];
    return $rules + match ($campaignType) {
        'newsletter_signup' => ['mode' => 'instant_reward', 'entry_reward_enabled' => true],
        'check_in_reward' => ['mode' => 'geo_check_in', 'browser_location_required' => true, 'merchant_location_match' => true, 'location_required' => true, 'radius_meters' => 150, 'entry_reward_enabled' => true],
        'survey_feedback_reward' => ['mode' => 'survey_feedback', 'prompt' => 'How was your experience?', 'rating_required' => true, 'feedback_required' => false, 'entry_reward_enabled' => true],
        'stamp_card_reward' => ['mode' => 'verified_stamp_card', 'required_count' => max(1, (int)($segmentRules['required_stamps'] ?? 5)), 'stamp_label' => 'Visit', 'cooldown_hours' => max(0, (int)($segmentRules['cooldown_hours'] ?? 24)), 'cashier_verification_required' => false, 'entry_reward_enabled' => true],
        'agent_offer' => ['mode' => 'agent_interest', 'instructions' => 'Tell the merchant which offer or product interests you.', 'entry_reward_enabled' => true],
        default => [],
    };
}

function mg_predictive_campaign_create_reward_draft(PDO $pdo, array $user, int $merchantId, string $recommendationId, array $proposal): int
{
    $usageStmt = $pdo->prepare("SELECT COUNT(*) FROM reward_templates WHERE merchant_user_id=? AND status<>'archived'");
    $usageStmt->execute([$merchantId]);
    mg_package_require_limit_available($pdo, $user, 'max_rewards', (int)$usageStmt->fetchColumn(), 'Reward template limit reached.');

    $rewardType = (string)($proposal['reward_type'] ?? 'custom');
    $valueType = (string)($proposal['value_type'] ?? 'custom');
    if (!in_array($rewardType, ['dollar_credit','free_item','discount','perk_upgrade','event_reward','custom'], true)) $rewardType = 'custom';
    if (!in_array($valueType, ['fixed_amount','percent','free_item','custom'], true)) $valueType = 'custom';
    $title = mb_substr(trim((string)($proposal['title'] ?? 'Predictive Reward Draft')) ?: 'Predictive Reward Draft', 0, 180);
    $description = mb_substr(trim((string)($proposal['description'] ?? 'Merchant-approved predictive reward draft.')), 0, 4000);
    $valueAmount = max(0, (int)($proposal['value_amount_cents'] ?? 0));
    $valuePercent = $valueType === 'percent' ? mg_predictive_campaign_clamp((float)($proposal['value_percent'] ?? 0), 0.01, 100) : null;
    $expirationRule = (string)($proposal['expiration_rule'] ?? 'after_issue');
    if (!in_array($expirationRule, ['none','after_issue','after_claim','fixed_date','event_date'], true)) $expirationRule = 'after_issue';
    $expirationDays = isset($proposal['expiration_days']) ? max(1, (int)$proposal['expiration_days']) : 30;
    $quantityLimit = isset($proposal['quantity_limit']) && $proposal['quantity_limit'] !== null ? max(1, (int)$proposal['quantity_limit']) : null;
    $perUserLimit = max(1, (int)($proposal['per_user_limit'] ?? 1));
    $metadata = [
        'predictive_campaign_studio' => [
            'recommendation_id' => $recommendationId,
            'created_as_draft' => true,
            'merchant_review_required' => true,
            'estimated_unit_cost_cents' => max(0, (int)($proposal['estimated_unit_cost_cents'] ?? 0)),
        ],
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO reward_templates
         (public_id,merchant_user_id,title,description,reward_type,value_type,value_amount_cents,value_percent,currency,
          redemption_instructions,expiration_rule,expiration_days,quantity_limit,per_user_limit,agent_discoverable,
          agent_add_to_wallet_allowed,agent_gift_send_allowed,status,metadata_json,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,0,0,\'draft\',?,NOW(),NOW())'
    );
    $stmt->execute([
        mg_merchant_uuid(), $merchantId, $title, $description, $rewardType, $valueType, $valueAmount, $valuePercent,
        strtoupper((string)($proposal['currency'] ?? 'USD')), mb_substr((string)($proposal['redemption_instructions'] ?? ''), 0, 4000),
        $expirationRule, $expirationDays, $quantityLimit, $perUserLimit, mg_predictive_campaign_encode($metadata),
    ]);
    return (int)$pdo->lastInsertId();
}

function mg_predictive_campaign_materialize(PDO $pdo, array $user, int $merchantId, string $publicId): array
{
    if (strlen($publicId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $publicId) !== 1) mg_fail('Invalid recommendation.', 422);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM mg_predictive_campaign_recommendations WHERE public_id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$publicId, $merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) mg_fail('Recommendation not found.', 404);
        if ((string)$row['status'] === 'dismissed') mg_fail('Dismissed recommendations cannot create drafts.', 409);
        if ((string)$row['status'] === 'materialized' && (int)($row['campaign_id'] ?? 0) > 0) {
            $pdo->commit();
            return mg_predictive_campaign_find($pdo, $merchantId, $publicId);
        }

        $campaignType = (string)$row['campaign_type'];
        if (!mg_campaign_type_is_valid($campaignType, true)) mg_fail('The recommended campaign type is not available.', 422);
        $rewardProposal = mg_predictive_campaign_json($row['recommended_reward_json'] ?? null);
        $campaignProposal = mg_predictive_campaign_json($row['recommended_campaign_json'] ?? null);
        $segmentRules = mg_predictive_campaign_json($row['segment_rules_json'] ?? null);

        $rewardTemplateId = (int)($row['reward_template_id'] ?? 0);
        if ($rewardTemplateId > 0) {
            $verifyReward = $pdo->prepare("SELECT id FROM reward_templates WHERE id=? AND merchant_user_id=? AND status<>'archived' LIMIT 1");
            $verifyReward->execute([$rewardTemplateId, $merchantId]);
            $rewardTemplateId = (int)($verifyReward->fetchColumn() ?: 0);
        }
        if ($rewardTemplateId < 1) {
            $rewardTemplateId = mg_predictive_campaign_create_reward_draft($pdo, $user, $merchantId, $publicId, $rewardProposal);
        }

        $title = mb_substr(trim((string)($campaignProposal['title'] ?? $row['title'])) ?: 'Predictive Campaign Draft', 0, 180);
        $description = mb_substr(trim((string)($campaignProposal['description'] ?? $row['summary'])), 0, 4000);
        $formHeadline = mb_substr(trim((string)($campaignProposal['form_headline'] ?? $title)), 0, 180);
        $formDescription = mb_substr(trim((string)($campaignProposal['form_description'] ?? $description)), 0, 4000);
        $successMessage = mb_substr(trim((string)($campaignProposal['success_message'] ?? 'Campaign response recorded.')), 0, 500);
        $quantityLimit = isset($campaignProposal['quantity_limit']) && $campaignProposal['quantity_limit'] !== null ? max(1, (int)$campaignProposal['quantity_limit']) : null;
        $perUserLimit = max(1, (int)($campaignProposal['per_user_limit'] ?? 1));
        $definition = mg_campaign_type_get($campaignType) ?? [];
        $publicSlug = !empty($definition['public_enabled']) ? mg_predictive_campaign_unique_slug($pdo, $merchantId, $title) : null;
        $rules = mg_predictive_campaign_rules($campaignType, $publicId, $segmentRules);

        $campaignPublicId = mg_merchant_uuid();
        $insert = $pdo->prepare(
            'INSERT INTO campaigns
             (public_id,merchant_user_id,reward_template_id,campaign_type,title,description,form_headline,form_description,
              success_message,status,quantity_limit,per_user_limit,agent_discoverable,public_slug,rules_json,metadata_json,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,\'draft\',?,?,0,?,?,?,NOW(),NOW())'
        );
        $metadata = [
            'predictive_campaign_studio' => [
                'recommendation_id' => $publicId,
                'scope_type' => (string)$row['scope_type'],
                'audience_name' => (string)$row['audience_name'],
                'audience_count' => (int)$row['audience_count'],
                'confidence_score' => (float)$row['confidence_score'],
                'merchant_review_required' => true,
                'automatic_launch' => false,
            ],
        ];
        $insert->execute([
            $campaignPublicId, $merchantId, $rewardTemplateId, $campaignType, $title, $description, $formHeadline,
            $formDescription, $successMessage, $quantityLimit, $perUserLimit, $publicSlug,
            mg_predictive_campaign_encode($rules), mg_predictive_campaign_encode($metadata),
        ]);
        $campaignId = (int)$pdo->lastInsertId();

        $update = $pdo->prepare(
            "UPDATE mg_predictive_campaign_recommendations
                SET reward_template_id=?,campaign_id=?,status='materialized',reviewed_at=NOW(),reviewed_by_user_id=?,materialized_at=NOW(),updated_at=NOW()
              WHERE id=? AND merchant_user_id=?"
        );
        $update->execute([$rewardTemplateId, $campaignId, $merchantId, (int)$row['id'], $merchantId]);
        $pdo->commit();

        mg_audit('merchant.predictive_campaign_materialized', 'campaign', [
            'recommendation_id' => $publicId,
            'campaign_id' => $campaignPublicId,
            'campaign_type' => $campaignType,
            'reward_template_db_id' => $rewardTemplateId,
            'status' => 'draft',
            'automatic_launch' => false,
        ], $merchantId);
        return mg_predictive_campaign_find($pdo, $merchantId, $publicId);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof RuntimeException) throw $error;
        throw new RuntimeException('Unable to create predictive campaign drafts.', 0, $error);
    }
}

function mg_predictive_campaign_find(PDO $pdo, int $merchantId, string $publicId): array
{
    $stmt = $pdo->prepare(
        "SELECT r.*,rt.public_id reward_public_id,rt.title reward_title,rt.status reward_status,
                c.public_id campaign_public_id,c.title campaign_title,c.status campaign_status
           FROM mg_predictive_campaign_recommendations r
           LEFT JOIN reward_templates rt ON rt.id=r.reward_template_id
           LEFT JOIN campaigns c ON c.id=r.campaign_id
          WHERE r.public_id=? AND r.merchant_user_id=? LIMIT 1"
    );
    $stmt->execute([$publicId, $merchantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Recommendation not found.');
    return mg_predictive_campaign_public_row($row);
}

function mg_predictive_campaign_dismiss(PDO $pdo, int $merchantId, int $reviewerId, string $publicId): array
{
    if (strlen($publicId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $publicId) !== 1) mg_fail('Invalid recommendation.', 422);
    $stmt = $pdo->prepare(
        "UPDATE mg_predictive_campaign_recommendations
            SET status='dismissed',reviewed_at=NOW(),reviewed_by_user_id=?,updated_at=NOW()
          WHERE public_id=? AND merchant_user_id=? AND status IN ('new','approved')"
    );
    $stmt->execute([$reviewerId, $publicId, $merchantId]);
    if ($stmt->rowCount() < 1) mg_fail('Recommendation cannot be dismissed.', 409);
    mg_audit('merchant.predictive_campaign_dismissed', 'campaign', ['recommendation_id' => $publicId], $merchantId);
    return mg_predictive_campaign_find($pdo, $merchantId, $publicId);
}

function mg_predictive_campaign_payload(PDO $pdo, int $merchantId, string $status = 'open'): array
{
    return [
        'schema_ready' => mg_predictive_campaign_schema_ready($pdo),
        'snapshot' => mg_predictive_campaign_store_snapshot($pdo, $merchantId),
        'recommendations' => mg_predictive_campaign_list($pdo, $merchantId, $status),
        'active_reward_count' => count(mg_predictive_campaign_active_rewards($pdo, $merchantId)),
        'authority' => [
            'canonical_reward_table' => 'reward_templates',
            'canonical_campaign_table' => 'campaigns',
            'recommendation_table' => MG_PREDICTIVE_CAMPAIGN_TABLE,
            'materializes_draft_rewards_only' => true,
            'materializes_draft_campaigns_only' => true,
            'automatic_launch' => false,
            'automatic_message' => false,
            'automatic_reward_issue' => false,
            'individual_customer_targeting_enabled' => false,
        ],
    ];
}
