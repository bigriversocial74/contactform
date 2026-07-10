<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_analytics.php';

const MG_STORE_BEHAVIOR_PROFILE_TABLE = 'mg_merchant_customer_behavior_profiles';

function mg_store_behavior_schema_ready(PDO $pdo): bool
{
    return mg_store_canvas_table_exists($pdo, MG_STORE_BEHAVIOR_PROFILE_TABLE);
}

function mg_store_behavior_clamp(float $value, float $minimum = 0.0, float $maximum = 100.0): float
{
    return round(max($minimum, min($maximum, $value)), 1);
}

function mg_store_behavior_days_since(mixed $value): ?int
{
    $raw = trim((string)$value);
    if ($raw === '') return null;
    $timestamp = strtotime($raw);
    if ($timestamp === false) return null;
    return max(0, (int)floor((time() - $timestamp) / 86400));
}

function mg_store_behavior_greeting_label(string $mode): string
{
    return match ($mode) {
        'recognized_loyalty' => 'Welcome back — loyalty recognized',
        'returning_engaged' => 'Welcome back — active interest',
        'gentle_reentry' => 'Welcome back — gentle re-entry',
        'exploring_interests' => 'Welcome — exploring interests',
        'test_preview' => 'Test visitor — behavior preview',
        default => 'Welcome — first visit',
    };
}

function mg_store_behavior_movement_explanation(string $mode): string
{
    return match ($mode) {
        'merchant_follow' => 'Visual movement may stay closer to the merchant avatar because repeat engagement is strong.',
        'campaign_interest' => 'Visual movement may lean toward a campaign zone because campaign-response evidence is elevated.',
        'release' => 'Visual movement may drift toward the canvas edge because inactivity risk is elevated.',
        default => 'Visual movement remains exploratory while the profile gathers more evidence.',
    };
}

function mg_store_behavior_public_profile(array $row): array
{
    $evidence = mg_store_analytics_json($row['evidence_json'] ?? null);
    return [
        'schema_ready' => true,
        'is_test' => !empty($row['is_test']),
        'relationship_stage' => (string)($row['relationship_stage'] ?? 'new'),
        'dominant_pattern' => (string)($row['dominant_pattern'] ?? 'early_signal'),
        'memory_summary' => (string)($row['memory_summary'] ?? ''),
        'greeting' => [
            'mode' => (string)($row['greeting_mode'] ?? 'first_visit'),
            'label' => mg_store_behavior_greeting_label((string)($row['greeting_mode'] ?? 'first_visit')),
        ],
        'movement' => [
            'mode' => (string)($row['movement_mode'] ?? 'explore'),
            'follow_state' => (string)($row['follow_state'] ?? 'observe'),
            'release_state' => (string)($row['release_state'] ?? 'hold'),
            'explanation' => mg_store_behavior_movement_explanation((string)($row['movement_mode'] ?? 'explore')),
        ],
        'probabilities' => [
            'return_7d' => (float)($row['return_7d_probability'] ?? 0),
            'campaign_engagement' => (float)($row['campaign_engagement_probability'] ?? 0),
            'reward_claim' => (float)($row['reward_claim_probability'] ?? 0),
            'reward_redeem' => (float)($row['reward_redeem_probability'] ?? 0),
            'inactivity_risk' => (float)($row['inactivity_risk_probability'] ?? 0),
        ],
        'confidence' => (float)($row['confidence_score'] ?? 0),
        'sample_size' => (int)($row['sample_size'] ?? 0),
        'evidence' => is_array($evidence) ? array_values($evidence) : [],
        'last_event_at' => $row['last_event_at'] ?? null,
        'last_calculated_at' => $row['last_calculated_at'] ?? null,
        'safeguards' => [
            'behavioral_evidence_only' => true,
            'protected_traits_excluded' => true,
            'browser_action_authority' => false,
            'automatic_message_authority' => false,
            'automatic_reward_authority' => false,
            'recommendation_requires_merchant_approval' => true,
        ],
    ];
}

function mg_store_behavior_test_profile(array $session): array
{
    $secondsInside = max(0, (int)($session['seconds_inside'] ?? 0));
    $returnProbability = mg_store_behavior_clamp(20 + min(20, $secondsInside / 30));
    $campaignProbability = mg_store_behavior_clamp(18 + min(22, $secondsInside / 25));
    return mg_store_behavior_public_profile([
        'is_test' => true,
        'relationship_stage' => 'test',
        'dominant_pattern' => 'exploring',
        'greeting_mode' => 'test_preview',
        'movement_mode' => 'explore',
        'follow_state' => 'observe',
        'release_state' => 'hold',
        'return_7d_probability' => $returnProbability,
        'campaign_engagement_probability' => $campaignProbability,
        'reward_claim_probability' => 20.0,
        'reward_redeem_probability' => 12.0,
        'inactivity_risk_probability' => 10.0,
        'confidence_score' => 5.0,
        'sample_size' => 1,
        'memory_summary' => 'Test visitor behavior preview. No durable customer profile is written for a test avatar.',
        'evidence_json' => json_encode([
            ['key' => 'test_session', 'label' => 'Test session', 'impact' => 0, 'direction' => 'neutral', 'reason' => 'Preview values are presentation-only and are not persisted.'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'last_event_at' => $session['last_active_at'] ?? null,
        'last_calculated_at' => gmdate('c'),
    ]);
}

function mg_store_behavior_build_profile(PDO $pdo, int $merchantUserId, int $customerUserId): array
{
    if ($merchantUserId < 1 || $customerUserId < 1) {
        throw new InvalidArgumentException('A valid merchant/customer behavior pair is required.');
    }

    $crm = mg_store_manual_ops_crm_get($pdo, $merchantUserId, $customerUserId, false);
    $summary = mg_store_analytics_summary($pdo, $merchantUserId, $customerUserId);
    $journey = mg_store_analytics_journey($pdo, $merchantUserId, $customerUserId);
    $visits = mg_store_analytics_visits($pdo, $merchantUserId, $customerUserId);

    $visitCount = (int)($summary['visit_count'] ?? 0);
    $returnVisits = (int)($summary['return_visit_count'] ?? 0);
    $averageSeconds = (int)($summary['average_session_seconds'] ?? 0);
    $totalSeconds = (int)($summary['total_session_seconds'] ?? 0);
    $messages = (int)($summary['messages_sent'] ?? 0);
    $products = (int)($summary['products_viewed'] ?? 0);
    $issued = (int)($summary['rewards_issued'] ?? 0);
    $claimed = (int)($summary['rewards_claimed'] ?? 0);
    $redeemed = (int)($summary['rewards_redeemed'] ?? 0);
    $eventCount = count($journey);
    $daysSinceActive = mg_store_behavior_days_since($summary['last_active_at'] ?? null);
    $tags = array_values(array_filter((array)($crm['tags'] ?? []), 'is_string'));

    $evidence = [];
    $addEvidence = static function (string $key, string $label, float $impact, string $reason, mixed $value = null) use (&$evidence): void {
        $evidence[] = [
            'key' => $key,
            'label' => $label,
            'impact' => round($impact, 1),
            'direction' => $impact > 0 ? 'positive' : ($impact < 0 ? 'negative' : 'neutral'),
            'reason' => mb_substr($reason, 0, 280),
            'value' => is_scalar($value) || $value === null ? $value : null,
        ];
    };

    $returnProbability = 18.0;
    if ($visitCount > 1) {
        $impact = 12 + min(18, ($visitCount - 2) * 4);
        $returnProbability += $impact;
        $addEvidence('repeat_visits', 'Repeat visits', $impact, 'Multiple merchant visits increase the likelihood of another near-term visit.', $visitCount);
    }
    if ($averageSeconds >= 180) {
        $returnProbability += 8;
        $addEvidence('dwell_time', 'Meaningful store time', 8, 'Average time in the Store Canvas is at least three minutes.', $averageSeconds);
    }
    if ($products >= 2) {
        $impact = min(12, $products * 3);
        $returnProbability += $impact;
        $addEvidence('product_interest', 'Product exploration', $impact, 'Recorded product views indicate continuing merchant interest.', $products);
    }
    if ($redeemed > 0) {
        $impact = min(18, 10 + ($redeemed * 3));
        $returnProbability += $impact;
        $addEvidence('redemption_history', 'Redemption history', $impact, 'Completed reward redemption is a strong repeat-commerce signal.', $redeemed);
    }
    if ($daysSinceActive !== null) {
        if ($daysSinceActive <= 1) {
            $returnProbability += 18;
            $addEvidence('recent_activity', 'Recent activity', 18, 'The customer was active within the last day.', $daysSinceActive);
        } elseif ($daysSinceActive <= 7) {
            $returnProbability += 12;
            $addEvidence('recent_activity', 'Recent activity', 12, 'The customer was active within the last week.', $daysSinceActive);
        } elseif ($daysSinceActive <= 30) {
            $returnProbability += 4;
            $addEvidence('recent_activity', 'Recent activity', 4, 'The customer was active within the last month.', $daysSinceActive);
        } elseif ($daysSinceActive > 60) {
            $returnProbability -= 18;
            $addEvidence('long_inactivity', 'Long inactivity', -18, 'The customer has not been active for more than sixty days.', $daysSinceActive);
        }
    }
    if (in_array('vip', $tags, true)) {
        $returnProbability += 5;
        $addEvidence('merchant_vip_tag', 'Merchant VIP tag', 5, 'The merchant has explicitly marked this CRM relationship as VIP.', 'vip');
    }
    $returnProbability = mg_store_behavior_clamp($returnProbability, 5, 95);

    $campaignProbability = 22.0;
    if ($products > 0) {
        $impact = min(20, $products * 5);
        $campaignProbability += $impact;
        $addEvidence('campaign_product_signal', 'Campaign product signal', $impact, 'Product views increase the evidence for campaign relevance.', $products);
    }
    if ($messages > 0) {
        $impact = min(12, $messages * 4);
        $campaignProbability += $impact;
        $addEvidence('merchant_conversation', 'Merchant conversation', $impact, 'Prior direct merchant communication increases campaign familiarity.', $messages);
    }
    if ($returnVisits > 0) {
        $impact = min(16, $returnVisits * 4);
        $campaignProbability += $impact;
        $addEvidence('campaign_repeat_visit', 'Repeat-visit signal', $impact, 'Returning to the merchant is a campaign-engagement signal.', $returnVisits);
    }
    if ($claimed > 0) {
        $impact = min(18, 10 + ($claimed * 3));
        $campaignProbability += $impact;
        $addEvidence('reward_claim_behavior', 'Reward claim behavior', $impact, 'Prior claims indicate willingness to complete merchant requirements.', $claimed);
    }
    if ($daysSinceActive !== null && $daysSinceActive <= 7) {
        $campaignProbability += 8;
        $addEvidence('campaign_recency', 'Current merchant interest', 8, 'Recent activity improves campaign timing confidence.', $daysSinceActive);
    }
    if (in_array('high_intent', $tags, true)) {
        $campaignProbability += 8;
        $addEvidence('merchant_high_intent_tag', 'Merchant high-intent tag', 8, 'The merchant has explicitly marked this CRM relationship as high intent.', 'high_intent');
    }
    $campaignProbability = mg_store_behavior_clamp($campaignProbability, 5, 95);

    if ($issued > 0) {
        $rewardClaimProbability = (($claimed + 1.2) / ($issued + 2.4)) * 100;
        $rewardRedeemProbability = (($redeemed + 0.7) / ($issued + 3.0)) * 100;
        $addEvidence('reward_sample', 'Reward outcome sample', min(12, $issued * 2), 'Claim and redemption projections use smoothed observed reward outcomes.', $issued);
    } else {
        $rewardClaimProbability = 20 + ($campaignProbability * 0.28);
        $rewardRedeemProbability = 10 + ($campaignProbability * 0.18);
        $addEvidence('reward_no_sample', 'No reward sample yet', 0, 'Reward projections use general engagement evidence until the customer has reward outcomes.', 0);
    }
    $rewardClaimProbability = mg_store_behavior_clamp($rewardClaimProbability, 5, 95);
    $rewardRedeemProbability = mg_store_behavior_clamp($rewardRedeemProbability, 3, 92);

    $inactivityRisk = match (true) {
        $daysSinceActive === null => 70.0,
        $daysSinceActive <= 1 => 8.0,
        $daysSinceActive <= 7 => 15.0,
        $daysSinceActive <= 21 => 30.0,
        $daysSinceActive <= 45 => 50.0,
        $daysSinceActive <= 75 => 72.0,
        default => 88.0,
    };
    $inactivityRisk -= min(12, $returnVisits * 2);
    if ($redeemed > 0) $inactivityRisk -= min(10, $redeemed * 3);
    $inactivityRisk = mg_store_behavior_clamp($inactivityRisk, 5, 95);
    if ($inactivityRisk >= 70) {
        $addEvidence('inactivity_risk', 'Inactivity risk', -$inactivityRisk / 5, 'Recent activity is weak enough to support a gentle release or comeback strategy.', $daysSinceActive);
    }

    $sampleSize = max(1, $visitCount + $eventCount + $issued);
    $confidence = mg_store_behavior_clamp(18 + min(35, $eventCount * 2.5) + min(20, $visitCount * 4) + min(15, $issued * 3), 5, 95);

    $relationshipStage = 'new';
    if ($daysSinceActive !== null && $daysSinceActive > 45) {
        $relationshipStage = 'dormant';
    } elseif (($visitCount >= 5 && ($redeemed >= 1 || $claimed >= 2)) || $visitCount >= 7) {
        $relationshipStage = 'loyal';
    } elseif ($visitCount >= 2 || $products >= 2 || $messages >= 2 || $claimed > 0) {
        $relationshipStage = 'engaged';
    } elseif ($totalSeconds >= 120 || $products > 0) {
        $relationshipStage = 'exploring';
    }

    $dominantPattern = 'early_signal';
    if ($relationshipStage === 'dormant') $dominantPattern = 'inactive';
    elseif ($redeemed >= 2) $dominantPattern = 'redemption_loyal';
    elseif ($issued >= 2 && $rewardClaimProbability >= 55) $dominantPattern = 'reward_responsive';
    elseif ($products >= 3) $dominantPattern = 'product_explorer';
    elseif ($visitCount >= 3) $dominantPattern = 'repeat_visitor';
    elseif ($messages >= 2) $dominantPattern = 'conversation_responsive';
    elseif ($averageSeconds >= 180) $dominantPattern = 'long_browse';

    $greetingMode = match ($relationshipStage) {
        'loyal' => 'recognized_loyalty',
        'engaged' => 'returning_engaged',
        'dormant' => 'gentle_reentry',
        'exploring' => 'exploring_interests',
        default => 'first_visit',
    };

    $followState = 'observe';
    if (!empty($crm['do_not_message'])) {
        $followState = 'observe_only';
    } elseif ($inactivityRisk >= 72) {
        $followState = 'release';
    } elseif ($campaignProbability >= 65 && $confidence >= 35) {
        $followState = 'follow';
    }
    $releaseState = $followState === 'release' ? 'release_recommended' : ($followState === 'follow' ? 'hold_close' : 'gentle_release');
    $movementMode = $followState === 'release'
        ? 'release'
        : (($campaignProbability >= 70 && $relationshipStage !== 'new')
            ? 'campaign_interest'
            : (in_array($relationshipStage, ['engaged', 'loyal'], true) && $followState === 'follow' ? 'merchant_follow' : 'explore'));

    $summaryParts = [];
    $summaryParts[] = ucfirst($relationshipStage) . ' merchant relationship';
    $summaryParts[] = $visitCount . ' recorded visit' . ($visitCount === 1 ? '' : 's');
    if ($products > 0) $summaryParts[] = $products . ' product view' . ($products === 1 ? '' : 's');
    if ($claimed > 0 || $redeemed > 0) $summaryParts[] = $claimed . ' claim' . ($claimed === 1 ? '' : 's') . ' and ' . $redeemed . ' redemption' . ($redeemed === 1 ? '' : 's');
    if ($daysSinceActive !== null) $summaryParts[] = $daysSinceActive === 0 ? 'active today' : 'last active ' . $daysSinceActive . ' day' . ($daysSinceActive === 1 ? '' : 's') . ' ago';
    $memorySummary = mb_substr(implode(' · ', $summaryParts) . '.', 0, 500);

    usort($evidence, static fn(array $a, array $b): int => abs((float)$b['impact']) <=> abs((float)$a['impact']));
    $evidence = array_slice($evidence, 0, 12);
    $lastEventAt = $journey[0]['event_at'] ?? ($summary['last_active_at'] ?? null);

    return [
        'relationship_stage' => $relationshipStage,
        'dominant_pattern' => $dominantPattern,
        'greeting_mode' => $greetingMode,
        'movement_mode' => $movementMode,
        'follow_state' => $followState,
        'release_state' => $releaseState,
        'return_7d_probability' => $returnProbability,
        'campaign_engagement_probability' => $campaignProbability,
        'reward_claim_probability' => $rewardClaimProbability,
        'reward_redeem_probability' => $rewardRedeemProbability,
        'inactivity_risk_probability' => $inactivityRisk,
        'confidence_score' => $confidence,
        'sample_size' => $sampleSize,
        'memory_summary' => $memorySummary,
        'evidence_json' => json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'last_event_at' => $lastEventAt,
        'last_calculated_at' => gmdate('Y-m-d H:i:s'),
    ];
}

function mg_store_behavior_profile_sync(PDO $pdo, int $merchantUserId, int $customerUserId): array
{
    if (!mg_store_behavior_schema_ready($pdo)) {
        throw new RuntimeException('Store Canvas behavior memory setup is incomplete. Import database/merchant_canvas_behavior_memory_predictive_v1.sql.');
    }
    mg_store_analytics_sync_customer($pdo, $merchantUserId, $customerUserId);
    $profile = mg_store_behavior_build_profile($pdo, $merchantUserId, $customerUserId);

    $stmt = $pdo->prepare(
        'INSERT INTO mg_merchant_customer_behavior_profiles
         (public_id,merchant_user_id,customer_user_id,relationship_stage,dominant_pattern,greeting_mode,movement_mode,follow_state,release_state,
          return_7d_probability,campaign_engagement_probability,reward_claim_probability,reward_redeem_probability,inactivity_risk_probability,
          confidence_score,sample_size,memory_summary,evidence_json,last_event_at,last_calculated_at,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE
          relationship_stage=VALUES(relationship_stage),dominant_pattern=VALUES(dominant_pattern),greeting_mode=VALUES(greeting_mode),
          movement_mode=VALUES(movement_mode),follow_state=VALUES(follow_state),release_state=VALUES(release_state),
          return_7d_probability=VALUES(return_7d_probability),campaign_engagement_probability=VALUES(campaign_engagement_probability),
          reward_claim_probability=VALUES(reward_claim_probability),reward_redeem_probability=VALUES(reward_redeem_probability),
          inactivity_risk_probability=VALUES(inactivity_risk_probability),confidence_score=VALUES(confidence_score),sample_size=VALUES(sample_size),
          memory_summary=VALUES(memory_summary),evidence_json=VALUES(evidence_json),last_event_at=VALUES(last_event_at),
          last_calculated_at=VALUES(last_calculated_at),updated_at=NOW()'
    );
    $stmt->execute([
        mg_public_uuid(), $merchantUserId, $customerUserId,
        $profile['relationship_stage'], $profile['dominant_pattern'], $profile['greeting_mode'], $profile['movement_mode'],
        $profile['follow_state'], $profile['release_state'], $profile['return_7d_probability'], $profile['campaign_engagement_probability'],
        $profile['reward_claim_probability'], $profile['reward_redeem_probability'], $profile['inactivity_risk_probability'],
        $profile['confidence_score'], $profile['sample_size'], $profile['memory_summary'], $profile['evidence_json'],
        $profile['last_event_at'], $profile['last_calculated_at'],
    ]);

    $read = $pdo->prepare('SELECT * FROM mg_merchant_customer_behavior_profiles WHERE merchant_user_id=? AND customer_user_id=? LIMIT 1');
    $read->execute([$merchantUserId, $customerUserId]);
    $row = $read->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Unable to read the customer behavior profile.');
    return mg_store_behavior_public_profile($row);
}

function mg_store_behavior_profile_cached(PDO $pdo, int $merchantUserId, int $customerUserId, int $maxAgeSeconds = 900): array
{
    if (!mg_store_behavior_schema_ready($pdo)) {
        return ['schema_ready' => false];
    }
    $stmt = $pdo->prepare('SELECT * FROM mg_merchant_customer_behavior_profiles WHERE merchant_user_id=? AND customer_user_id=? LIMIT 1');
    $stmt->execute([$merchantUserId, $customerUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $calculatedAt = $row ? strtotime((string)($row['last_calculated_at'] ?? '')) : false;
    if (!$row || $calculatedAt === false || $calculatedAt < (time() - max(60, $maxAgeSeconds))) {
        return mg_store_behavior_profile_sync($pdo, $merchantUserId, $customerUserId);
    }
    return mg_store_behavior_public_profile($row);
}

function mg_store_behavior_active_profiles(PDO $pdo, int $merchantUserId): array
{
    if (!mg_store_behavior_schema_ready($pdo)) {
        return ['schema_ready' => false, 'profiles' => []];
    }
    $stmt = $pdo->prepare(
        "SELECT public_id,customer_user_id,status,last_active_at,entered_at,metadata_json,
                TIMESTAMPDIFF(SECOND,entered_at,NOW()) seconds_inside
         FROM mg_store_sessions
         WHERE merchant_user_id=? AND active_key IS NOT NULL AND status IN ('entered','active','idle')
           AND exited_at IS NULL AND last_active_at >= DATE_SUB(NOW(), INTERVAL " . MG_STORE_EXPIRE_MINUTES . " MINUTE)
         ORDER BY last_active_at DESC,id DESC LIMIT 100"
    );
    $stmt->execute([$merchantUserId]);
    $profiles = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $session) {
        $metadata = mg_store_analytics_json($session['metadata_json'] ?? null);
        $isTest = !empty($metadata['test_canvas_avatar']) || (($metadata['source'] ?? '') === 'merchant_canvas_test_seed');
        $customerUserId = (int)($session['customer_user_id'] ?? 0);
        try {
            $profile = $isTest || $customerUserId < 1
                ? mg_store_behavior_test_profile($session)
                : mg_store_behavior_profile_cached($pdo, $merchantUserId, $customerUserId, 900);
        } catch (Throwable $error) {
            mg_security_log('warning', 'merchant_canvas.behavior_profile_refresh_failed', 'Unable to refresh one active customer behavior profile.', ['exception_class' => $error::class], $merchantUserId);
            $profile = ['schema_ready' => true, 'relationship_stage' => 'unknown', 'dominant_pattern' => 'insufficient_data', 'greeting' => ['mode' => 'first_visit', 'label' => 'Welcome'], 'movement' => ['mode' => 'explore', 'follow_state' => 'observe', 'release_state' => 'hold', 'explanation' => 'Visual movement remains exploratory.'], 'probabilities' => [], 'confidence' => 0, 'sample_size' => 0, 'evidence' => []];
        }
        $profiles[(string)$session['public_id']] = $profile;
    }
    return ['schema_ready' => true, 'profiles' => $profiles, 'generated_at' => gmdate('c')];
}
