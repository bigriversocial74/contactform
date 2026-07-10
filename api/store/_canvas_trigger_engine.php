<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_campaign_recommendations.php';
require_once dirname(__DIR__) . '/merchant-canvas/_trigger_zones.php';

const MG_STORE_TRIGGER_ENGINE_SETTINGS = 'mg_store_trigger_engine_settings';
const MG_STORE_TRIGGER_ENGINE_RULES = 'mg_store_trigger_engine_rules';
const MG_STORE_TRIGGER_ENGINE_EVENTS = 'mg_store_trigger_events';
const MG_STORE_TRIGGER_ENGINE_EVALUATIONS = 'mg_store_trigger_evaluations';

function mg_store_trigger_engine_tables(): array
{
    return [
        MG_STORE_TRIGGER_ENGINE_SETTINGS,
        MG_STORE_TRIGGER_ENGINE_RULES,
        MG_STORE_TRIGGER_ENGINE_EVENTS,
        MG_STORE_TRIGGER_ENGINE_EVALUATIONS,
    ];
}

function mg_store_trigger_engine_required_tables(): array
{
    return array_merge(mg_store_trigger_engine_tables(), [
        'mg_store_sessions',
        'mg_store_session_events',
        'campaigns',
        'reward_templates',
        'wallet_items',
        'mg_merchant_canvas_action_receipts',
    ]);
}

function mg_store_trigger_engine_missing_tables(PDO $pdo): array
{
    return mg_store_canvas_missing_tables($pdo, mg_store_trigger_engine_required_tables());
}

function mg_store_trigger_engine_schema_ready(PDO $pdo): bool
{
    return mg_store_trigger_engine_missing_tables($pdo) === [];
}

function mg_store_trigger_engine_require_schema(PDO $pdo): void
{
    mg_store_canvas_require_tables($pdo, mg_store_trigger_engine_required_tables(), 'Store Canvas server trigger engine');
}

function mg_store_trigger_engine_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_store_trigger_engine_encode(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_store_trigger_engine_uuid(): string
{
    return mg_public_uuid();
}

function mg_store_trigger_engine_clamp(float $value, float $min = 0.0, float $max = 100.0): float
{
    return round(max($min, min($max, $value)), 2);
}

function mg_store_trigger_engine_event_types(): array
{
    return [
        'store_entry' => 'Store entry',
        'return_visit' => 'Return visit',
        'visit_milestone' => 'Visit milestone',
        'campaign_interest' => 'Campaign-interest probability',
        'inactivity_risk' => 'Inactivity-risk probability',
        'product_interest' => 'Product-interest pattern',
        'reward_claimed' => 'Reward claimed',
        'reward_redeemed' => 'Reward redeemed',
    ];
}

function mg_store_trigger_engine_settings(PDO $pdo, int $merchantUserId, bool $create = true): array
{
    mg_store_trigger_engine_require_schema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM mg_store_trigger_engine_settings WHERE merchant_user_id=? LIMIT 1');
    $stmt->execute([$merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row && $create) {
        $publicId = mg_store_trigger_engine_uuid();
        $insert = $pdo->prepare("INSERT INTO mg_store_trigger_engine_settings
            (public_id,merchant_user_id,execution_mode,max_notifications_per_run,default_cooldown_seconds,last_run_status,created_at,updated_at)
            VALUES (?,?,'paused',10,86400,'never',NOW(),NOW())");
        $insert->execute([$publicId, $merchantUserId]);
        $stmt->execute([$merchantUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$row) throw new RuntimeException('Trigger engine settings are unavailable.');
    return $row;
}

function mg_store_trigger_engine_settings_public(array $row): array
{
    return [
        'id' => (string)($row['public_id'] ?? ''),
        'execution_mode' => (string)($row['execution_mode'] ?? 'paused'),
        'max_notifications_per_run' => max(1, min(100, (int)($row['max_notifications_per_run'] ?? 10))),
        'default_cooldown_seconds' => max(300, min(2592000, (int)($row['default_cooldown_seconds'] ?? 86400))),
        'last_run_at' => $row['last_run_at'] ?? null,
        'last_run_status' => (string)($row['last_run_status'] ?? 'never'),
        'last_run_summary' => mg_store_trigger_engine_json($row['last_run_summary_json'] ?? null),
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_store_trigger_engine_update_settings(PDO $pdo, int $merchantUserId, array $input): array
{
    mg_store_trigger_engine_settings($pdo, $merchantUserId, true);
    $mode = strtolower(trim((string)($input['execution_mode'] ?? 'paused')));
    if (!in_array($mode, ['paused','dry_run','notification'], true)) {
        throw new InvalidArgumentException('Invalid trigger engine execution mode.');
    }
    $max = max(1, min(100, (int)($input['max_notifications_per_run'] ?? 10)));
    $cooldown = max(300, min(2592000, (int)($input['default_cooldown_seconds'] ?? 86400)));
    $stmt = $pdo->prepare('UPDATE mg_store_trigger_engine_settings SET execution_mode=?,max_notifications_per_run=?,default_cooldown_seconds=?,updated_at=NOW() WHERE merchant_user_id=?');
    $stmt->execute([$mode, $max, $cooldown, $merchantUserId]);
    return mg_store_trigger_engine_settings_public(mg_store_trigger_engine_settings($pdo, $merchantUserId, false));
}

function mg_store_trigger_engine_campaigns(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare("SELECT c.public_id,c.title,c.campaign_type,c.status,c.starts_at,c.ends_at,c.quantity_limit,c.issued_count,
                                  rt.public_id reward_template_public_id,rt.title reward_template_title,rt.status reward_template_status,
                                  rt.quantity_limit reward_quantity_limit,rt.issued_count reward_issued_count
                           FROM campaigns c
                           LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id AND rt.merchant_user_id=c.merchant_user_id
                           WHERE c.merchant_user_id=? AND c.status IN ('active','paused','draft')
                           ORDER BY FIELD(c.status,'active','paused','draft'),c.updated_at DESC,c.id DESC LIMIT 100");
    $stmt->execute([$merchantUserId]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ready = false;
        $reason = 'Campaign must be active.';
        try {
            mg_store_campaign_recommendation_campaign($pdo, $merchantUserId, (string)$row['public_id']);
            $ready = true;
            $reason = 'Ready for notification-only trigger delivery.';
        } catch (Throwable $error) {
            $reason = $error->getMessage();
        }
        $items[] = [
            'id' => (string)$row['public_id'],
            'title' => (string)$row['title'],
            'campaign_type' => (string)$row['campaign_type'],
            'status' => (string)$row['status'],
            'reward_template_id' => (string)($row['reward_template_public_id'] ?? ''),
            'reward_template_title' => (string)($row['reward_template_title'] ?? ''),
            'ready' => $ready,
            'readiness_reason' => $reason,
        ];
    }
    return $items;
}

function mg_store_trigger_engine_rule_public(array $row): array
{
    return [
        'id' => (string)($row['public_id'] ?? ''),
        'name' => (string)($row['name'] ?? 'Trigger rule'),
        'event_type' => (string)($row['event_type'] ?? 'store_entry'),
        'event_label' => mg_store_trigger_engine_event_types()[(string)($row['event_type'] ?? '')] ?? 'Trigger event',
        'status' => (string)($row['status'] ?? 'paused'),
        'priority' => max(1, min(5, (int)($row['priority'] ?? 3))),
        'campaign_id' => (string)($row['campaign_public_id'] ?? ''),
        'campaign_title' => (string)($row['campaign_title'] ?? ''),
        'campaign_status' => (string)($row['campaign_status'] ?? ''),
        'reward_template_title' => (string)($row['reward_template_title'] ?? ''),
        'trigger_zone_id' => (string)($row['trigger_zone_public_id'] ?? ''),
        'trigger_zone_name' => (string)($row['trigger_zone_name'] ?? ''),
        'minimum_probability' => (float)($row['minimum_probability'] ?? 50),
        'minimum_confidence' => (float)($row['minimum_confidence'] ?? 30),
        'visit_milestone' => $row['visit_milestone'] === null ? null : (int)$row['visit_milestone'],
        'cooldown_seconds' => max(300, (int)($row['cooldown_seconds'] ?? 86400)),
        'max_per_customer_day' => max(1, (int)($row['max_per_customer_day'] ?? 1)),
        'require_active_session' => !empty($row['require_active_session']),
        'notification_note' => (string)($row['notification_note'] ?? ''),
        'audience_rules' => mg_store_trigger_engine_json($row['audience_rules_json'] ?? null),
        'last_evaluated_at' => $row['last_evaluated_at'] ?? null,
        'last_matched_at' => $row['last_matched_at'] ?? null,
        'last_delivered_at' => $row['last_delivered_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_store_trigger_engine_rules(PDO $pdo, int $merchantUserId, bool $includeArchived = false): array
{
    mg_store_trigger_engine_require_schema($pdo);
    $where = $includeArchived ? '' : "AND r.status<>'archived'";
    $stmt = $pdo->prepare("SELECT r.*,c.title campaign_title,c.status campaign_status,rt.title reward_template_title,z.name trigger_zone_name
                           FROM mg_store_trigger_engine_rules r
                           LEFT JOIN campaigns c ON c.public_id=r.campaign_public_id AND c.merchant_user_id=r.merchant_user_id
                           LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id AND rt.merchant_user_id=c.merchant_user_id
                           LEFT JOIN mg_store_trigger_zones z ON z.public_id=r.trigger_zone_public_id AND z.merchant_user_id=r.merchant_user_id
                           WHERE r.merchant_user_id=? {$where}
                           ORDER BY FIELD(r.status,'enabled','paused','archived'),r.priority DESC,r.updated_at DESC,r.id DESC LIMIT 100");
    $stmt->execute([$merchantUserId]);
    return array_map('mg_store_trigger_engine_rule_public', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_store_trigger_engine_save_rule(PDO $pdo, int $merchantUserId, array $input): array
{
    mg_store_trigger_engine_require_schema($pdo);
    $publicId = strtolower(trim((string)($input['rule_id'] ?? $input['id'] ?? '')));
    if ($publicId !== '') $publicId = mg_store_safe_public_id($publicId, 'Trigger rule');
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') $name = 'Store Canvas trigger rule';
    $name = mb_substr($name, 0, 180);
    $eventType = strtolower(trim((string)($input['event_type'] ?? 'store_entry')));
    if (!array_key_exists($eventType, mg_store_trigger_engine_event_types())) {
        throw new InvalidArgumentException('Invalid trigger event type.');
    }
    $status = strtolower(trim((string)($input['status'] ?? 'paused')));
    if (!in_array($status, ['enabled','paused'], true)) $status = 'paused';
    $priority = max(1, min(5, (int)($input['priority'] ?? 3)));
    $minimumProbability = mg_store_trigger_engine_clamp((float)($input['minimum_probability'] ?? 50));
    $minimumConfidence = mg_store_trigger_engine_clamp((float)($input['minimum_confidence'] ?? 30));
    $visitMilestone = $eventType === 'visit_milestone' ? max(2, min(1000, (int)($input['visit_milestone'] ?? 3))) : null;
    $cooldown = max(300, min(2592000, (int)($input['cooldown_seconds'] ?? 86400)));
    $dailyLimit = max(1, min(20, (int)($input['max_per_customer_day'] ?? 1)));
    $requireActive = array_key_exists('require_active_session', $input) ? (!empty($input['require_active_session']) ? 1 : 0) : 1;
    $note = trim((string)($input['notification_note'] ?? ''));
    if (mb_strlen($note) > 1000) throw new InvalidArgumentException('Notification note is too long.');
    $campaignPublicId = mg_store_safe_public_id((string)($input['campaign_id'] ?? ''), 'Campaign');
    mg_store_campaign_recommendation_campaign($pdo, $merchantUserId, $campaignPublicId);

    $zonePublicId = trim((string)($input['trigger_zone_id'] ?? ''));
    if ($zonePublicId !== '') {
        $zone = mg_canvas_trigger_zone_load($pdo, $merchantUserId, $zonePublicId);
        if (!$zone || (string)($zone['status'] ?? '') !== 'active') throw new RuntimeException('Selected trigger zone is not active.');
        $zoneCampaign = trim((string)($zone['campaign_id'] ?? ''));
        if ($zoneCampaign !== '' && $zoneCampaign !== $campaignPublicId) {
            throw new RuntimeException('Selected trigger zone is bound to a different campaign.');
        }
        $zonePublicId = (string)$zone['id'];
    } else {
        $zonePublicId = null;
    }

    $audienceRules = is_array($input['audience_rules'] ?? null) ? $input['audience_rules'] : [];
    $audienceRules['protected_traits_excluded'] = true;
    $audienceRules['individual_customer_selection'] = false;
    $audienceRules['browser_overlap_authority'] = false;
    $audienceRulesJson = mg_store_trigger_engine_encode($audienceRules);
    $metadataJson = mg_store_trigger_engine_encode([
        'authority' => 'server_event_only',
        'delivery' => 'notification_only',
        'reward_issue_policy' => 'campaign_completion_only',
        'wallet_write_allowed' => false,
        'peer_actions_allowed' => false,
    ]);

    if ($publicId === '') {
        $publicId = mg_store_trigger_engine_uuid();
        $stmt = $pdo->prepare("INSERT INTO mg_store_trigger_engine_rules
            (public_id,merchant_user_id,trigger_zone_public_id,campaign_public_id,name,event_type,status,priority,minimum_probability,minimum_confidence,visit_milestone,cooldown_seconds,max_per_customer_day,require_active_session,notification_note,audience_rules_json,metadata_json,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
        $stmt->execute([$publicId,$merchantUserId,$zonePublicId,$campaignPublicId,$name,$eventType,$status,$priority,$minimumProbability,$minimumConfidence,$visitMilestone,$cooldown,$dailyLimit,$requireActive,$note !== '' ? $note : null,$audienceRulesJson,$metadataJson]);
    } else {
        $stmt = $pdo->prepare("UPDATE mg_store_trigger_engine_rules SET trigger_zone_public_id=?,campaign_public_id=?,name=?,event_type=?,status=?,priority=?,minimum_probability=?,minimum_confidence=?,visit_milestone=?,cooldown_seconds=?,max_per_customer_day=?,require_active_session=?,notification_note=?,audience_rules_json=?,metadata_json=?,updated_at=NOW()
                               WHERE public_id=? AND merchant_user_id=? AND status<>'archived'");
        $stmt->execute([$zonePublicId,$campaignPublicId,$name,$eventType,$status,$priority,$minimumProbability,$minimumConfidence,$visitMilestone,$cooldown,$dailyLimit,$requireActive,$note !== '' ? $note : null,$audienceRulesJson,$metadataJson,$publicId,$merchantUserId]);
        if ($stmt->rowCount() < 1) {
            $verify = $pdo->prepare("SELECT 1 FROM mg_store_trigger_engine_rules WHERE public_id=? AND merchant_user_id=? AND status<>'archived' LIMIT 1");
            $verify->execute([$publicId,$merchantUserId]);
            if (!$verify->fetchColumn()) throw new RuntimeException('Trigger rule is not available.');
        }
    }

    foreach (mg_store_trigger_engine_rules($pdo, $merchantUserId) as $rule) {
        if ((string)$rule['id'] === $publicId) return $rule;
    }
    throw new RuntimeException('Unable to load the saved trigger rule.');
}

function mg_store_trigger_engine_archive_rule(PDO $pdo, int $merchantUserId, string $publicId): void
{
    $publicId = mg_store_safe_public_id($publicId, 'Trigger rule');
    $stmt = $pdo->prepare("UPDATE mg_store_trigger_engine_rules SET status='archived',updated_at=NOW() WHERE public_id=? AND merchant_user_id=?");
    $stmt->execute([$publicId,$merchantUserId]);
    if ($stmt->rowCount() < 1) throw new RuntimeException('Trigger rule is not available.');
}

function mg_store_trigger_engine_active_sessions(PDO $pdo, int $merchantUserId, int $limit = 100): array
{
    $limit = max(1, min(200, $limit));
    $hasBehavior = mg_store_canvas_table_exists($pdo, 'mg_merchant_customer_behavior_profiles');
    $behaviorSelect = $hasBehavior
        ? "bp.relationship_stage,bp.dominant_pattern,bp.campaign_engagement_probability,bp.inactivity_risk_probability,bp.confidence_score,bp.sample_size,bp.memory_summary,bp.updated_at behavior_updated_at"
        : "'new' relationship_stage,'early_signal' dominant_pattern,0 campaign_engagement_probability,0 inactivity_risk_probability,0 confidence_score,0 sample_size,'' memory_summary,NULL behavior_updated_at";
    $behaviorJoin = $hasBehavior ? 'LEFT JOIN mg_merchant_customer_behavior_profiles bp ON bp.merchant_user_id=s.merchant_user_id AND bp.customer_user_id=s.customer_user_id' : '';
    $stmt = $pdo->prepare("SELECT s.id,s.public_id,s.merchant_user_id,s.customer_user_id,s.status,s.entered_at,s.last_active_at,s.exited_at,
                                  {$behaviorSelect},
                                  (SELECT COUNT(*) FROM mg_store_sessions s2 WHERE s2.merchant_user_id=s.merchant_user_id AND s2.customer_user_id=s.customer_user_id) visit_count,
                                  (SELECT wi.public_id FROM wallet_items wi WHERE wi.merchant_user_id=s.merchant_user_id AND wi.user_id=s.customer_user_id AND wi.status IN ('claimed','redeemed') ORDER BY COALESCE(wi.claimed_at,wi.updated_at) DESC,wi.id DESC LIMIT 1) claimed_wallet_public_id,
                                  (SELECT MAX(COALESCE(wi.claimed_at,wi.updated_at)) FROM wallet_items wi WHERE wi.merchant_user_id=s.merchant_user_id AND wi.user_id=s.customer_user_id AND wi.status IN ('claimed','redeemed')) last_claimed_at,
                                  (SELECT wi.public_id FROM wallet_items wi WHERE wi.merchant_user_id=s.merchant_user_id AND wi.user_id=s.customer_user_id AND wi.status='redeemed' ORDER BY COALESCE(wi.redeemed_at,wi.updated_at) DESC,wi.id DESC LIMIT 1) redeemed_wallet_public_id,
                                  (SELECT MAX(COALESCE(wi.redeemed_at,wi.updated_at)) FROM wallet_items wi WHERE wi.merchant_user_id=s.merchant_user_id AND wi.user_id=s.customer_user_id AND wi.status='redeemed') last_redeemed_at
                           FROM mg_store_sessions s {$behaviorJoin}
                           WHERE s.merchant_user_id=? AND s.active_key IS NOT NULL AND s.status IN ('entered','active','idle') AND s.exited_at IS NULL
                             AND s.last_active_at>=DATE_SUB(NOW(),INTERVAL " . MG_STORE_EXPIRE_MINUTES . " MINUTE)
                           ORDER BY s.last_active_at DESC,s.id DESC LIMIT {$limit}");
    $stmt->execute([$merchantUserId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_store_trigger_engine_candidate(array $rule, array $session): ?array
{
    $eventType = (string)$rule['event_type'];
    $sessionPublicId = (string)$session['public_id'];
    $today = date('Ymd');
    $probability = 100.0;
    $confidence = 100.0;
    $sourceType = 'store_session';
    $sourcePublicId = $sessionPublicId;
    $evidence = [
        'server_session_id' => $sessionPublicId,
        'visit_count' => (int)($session['visit_count'] ?? 1),
        'relationship_stage' => (string)($session['relationship_stage'] ?? 'new'),
        'dominant_pattern' => (string)($session['dominant_pattern'] ?? 'early_signal'),
        'browser_overlap_used' => false,
        'protected_traits_used' => false,
    ];

    if ($eventType === 'store_entry') {
        $entered = strtotime((string)($session['entered_at'] ?? ''));
        if ($entered === false || $entered < time() - 86400) return null;
        $eventKey = 'store_entry:' . $sessionPublicId;
    } elseif ($eventType === 'return_visit') {
        if ((int)($session['visit_count'] ?? 0) < 2) return null;
        $probability = max(50.0, (float)($session['campaign_engagement_probability'] ?? 0));
        $confidence = max(40.0, (float)($session['confidence_score'] ?? 0));
        $eventKey = 'return_visit:' . $sessionPublicId;
    } elseif ($eventType === 'visit_milestone') {
        $milestone = max(2, (int)($rule['visit_milestone'] ?? 3));
        if ((int)($session['visit_count'] ?? 0) < $milestone) return null;
        $evidence['visit_milestone'] = $milestone;
        $eventKey = 'visit_milestone:' . $sessionPublicId . ':' . $milestone;
    } elseif ($eventType === 'campaign_interest') {
        if ((int)($session['sample_size'] ?? 0) < 1) return null;
        $probability = (float)($session['campaign_engagement_probability'] ?? 0);
        $confidence = (float)($session['confidence_score'] ?? 0);
        $evidence['campaign_engagement_probability'] = $probability;
        $evidence['sample_size'] = (int)($session['sample_size'] ?? 0);
        $eventKey = 'campaign_interest:' . $sessionPublicId . ':' . $today;
        $sourceType = 'behavior_profile';
        $sourcePublicId = $sessionPublicId . ':' . $today;
    } elseif ($eventType === 'inactivity_risk') {
        if ((int)($session['sample_size'] ?? 0) < 1) return null;
        $probability = (float)($session['inactivity_risk_probability'] ?? 0);
        $confidence = (float)($session['confidence_score'] ?? 0);
        $evidence['inactivity_risk_probability'] = $probability;
        $evidence['sample_size'] = (int)($session['sample_size'] ?? 0);
        $eventKey = 'inactivity_risk:' . $sessionPublicId . ':' . $today;
        $sourceType = 'behavior_profile';
        $sourcePublicId = $sessionPublicId . ':' . $today;
    } elseif ($eventType === 'product_interest') {
        if ((string)($session['dominant_pattern'] ?? '') !== 'product_explorer') return null;
        $probability = max(55.0, (float)($session['campaign_engagement_probability'] ?? 0));
        $confidence = (float)($session['confidence_score'] ?? 0);
        $eventKey = 'product_interest:' . $sessionPublicId . ':' . $today;
        $sourceType = 'behavior_profile';
        $sourcePublicId = $sessionPublicId . ':' . $today;
    } elseif ($eventType === 'reward_claimed') {
        $walletId = trim((string)($session['claimed_wallet_public_id'] ?? ''));
        $claimedAt = strtotime((string)($session['last_claimed_at'] ?? ''));
        if ($walletId === '' || $claimedAt === false || $claimedAt < time() - (30 * 86400)) return null;
        $eventKey = 'reward_claimed:' . $walletId;
        $sourceType = 'wallet_item';
        $sourcePublicId = $walletId;
        $evidence['claimed_at'] = $session['last_claimed_at'];
    } elseif ($eventType === 'reward_redeemed') {
        $walletId = trim((string)($session['redeemed_wallet_public_id'] ?? ''));
        $redeemedAt = strtotime((string)($session['last_redeemed_at'] ?? ''));
        if ($walletId === '' || $redeemedAt === false || $redeemedAt < time() - (30 * 86400)) return null;
        $eventKey = 'reward_redeemed:' . $walletId;
        $sourceType = 'wallet_item';
        $sourcePublicId = $walletId;
        $evidence['redeemed_at'] = $session['last_redeemed_at'];
    } else {
        return null;
    }

    return [
        'event_key' => substr($eventKey, 0, 190),
        'event_type' => $eventType,
        'source_type' => $sourceType,
        'source_public_id' => substr($sourcePublicId, 0, 190),
        'event_at' => date('Y-m-d H:i:s'),
        'probability' => mg_store_trigger_engine_clamp($probability),
        'confidence' => mg_store_trigger_engine_clamp($confidence),
        'evidence' => $evidence,
    ];
}

function mg_store_trigger_engine_event(PDO $pdo, int $merchantUserId, array $session, array $candidate): array
{
    $publicId = mg_store_trigger_engine_uuid();
    $payload = $candidate['evidence'] ?? [];
    $payload['probability_score'] = (float)($candidate['probability'] ?? 0);
    $payload['confidence_score'] = (float)($candidate['confidence'] ?? 0);
    $insert = $pdo->prepare("INSERT IGNORE INTO mg_store_trigger_events
        (public_id,merchant_user_id,customer_user_id,store_session_id,store_session_public_id,event_key,event_type,source_type,source_public_id,event_at,payload_json,status,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending',NOW(),NOW())");
    $insert->execute([
        $publicId,$merchantUserId,(int)$session['customer_user_id'],(int)$session['id'],(string)$session['public_id'],
        (string)$candidate['event_key'],(string)$candidate['event_type'],(string)$candidate['source_type'],(string)$candidate['source_public_id'],
        (string)$candidate['event_at'],mg_store_trigger_engine_encode($payload),
    ]);
    $stmt = $pdo->prepare('SELECT * FROM mg_store_trigger_events WHERE merchant_user_id=? AND event_key=? LIMIT 1');
    $stmt->execute([$merchantUserId,(string)$candidate['event_key']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Unable to normalize trigger event.');
    return $row;
}

function mg_store_trigger_engine_existing_evaluation(PDO $pdo, int $eventId, int $ruleId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM mg_store_trigger_evaluations WHERE event_id=? AND rule_id=? LIMIT 1');
    $stmt->execute([$eventId,$ruleId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_store_trigger_engine_delivery_limits(PDO $pdo, int $merchantUserId, int $customerUserId, int $ruleId, int $cooldownSeconds, int $dailyLimit): ?array
{
    $stmt = $pdo->prepare("SELECT created_at FROM mg_store_trigger_evaluations WHERE merchant_user_id=? AND customer_user_id=? AND rule_id=? AND decision='delivered' ORDER BY created_at DESC,id DESC LIMIT 1");
    $stmt->execute([$merchantUserId,$customerUserId,$ruleId]);
    $last = $stmt->fetchColumn();
    if ($last && strtotime((string)$last) > time() - $cooldownSeconds) {
        return ['code'=>'cooldown_active','message'=>'Rule cooldown is still active for this customer.'];
    }
    $daily = $pdo->prepare("SELECT COUNT(*) FROM mg_store_trigger_evaluations WHERE merchant_user_id=? AND customer_user_id=? AND rule_id=? AND decision='delivered' AND created_at>=CURDATE()");
    $daily->execute([$merchantUserId,$customerUserId,$ruleId]);
    if ((int)$daily->fetchColumn() >= $dailyLimit) {
        return ['code'=>'daily_limit','message'=>'Per-customer daily delivery limit reached.'];
    }
    return null;
}

function mg_store_trigger_engine_record_evaluation(PDO $pdo, array $values): array
{
    $publicId = mg_store_trigger_engine_uuid();
    $stmt = $pdo->prepare("INSERT INTO mg_store_trigger_evaluations
        (public_id,merchant_user_id,customer_user_id,event_id,rule_id,campaign_public_id,trigger_zone_public_id,execution_mode,decision,reason_code,reason_text,probability_score,confidence_score,recommendation_id,notification_id,evidence_json,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
    $stmt->execute([
        $publicId,(int)$values['merchant_user_id'],(int)$values['customer_user_id'],(int)$values['event_id'],(int)$values['rule_id'],
        (string)$values['campaign_public_id'],$values['trigger_zone_public_id'] ?: null,(string)$values['execution_mode'],(string)$values['decision'],
        (string)$values['reason_code'],mb_substr((string)$values['reason_text'],0,500),(float)$values['probability_score'],(float)$values['confidence_score'],
        $values['recommendation_id'] ?: null,$values['notification_id'] ?: null,mg_store_trigger_engine_encode($values['evidence'] ?? []),
    ]);
    return ['id'=>$publicId] + $values;
}

function mg_store_trigger_engine_evaluate(PDO $pdo, array $merchantUser, array $ruleRow, array $session, array $event, array $candidate, string $mode, bool $deliveryLimitReached): array
{
    $merchantUserId = (int)$merchantUser['id'];
    $customerUserId = (int)$session['customer_user_id'];
    $probability = (float)($candidate['probability'] ?? 0);
    $confidence = (float)($candidate['confidence'] ?? 0);
    $base = [
        'merchant_user_id'=>$merchantUserId,
        'customer_user_id'=>$customerUserId,
        'event_id'=>(int)$event['id'],
        'rule_id'=>(int)$ruleRow['id'],
        'campaign_public_id'=>(string)$ruleRow['campaign_public_id'],
        'trigger_zone_public_id'=>(string)($ruleRow['trigger_zone_public_id'] ?? ''),
        'execution_mode'=>$mode,
        'probability_score'=>$probability,
        'confidence_score'=>$confidence,
        'recommendation_id'=>'',
        'notification_id'=>'',
        'evidence'=>array_merge($candidate['evidence'] ?? [], [
            'rule_public_id'=>(string)$ruleRow['public_id'],
            'event_public_id'=>(string)$event['public_id'],
            'server_authoritative'=>true,
            'browser_overlap_used'=>false,
            'reward_issued'=>false,
        ]),
    ];

    if ($probability < (float)$ruleRow['minimum_probability']) {
        return mg_store_trigger_engine_record_evaluation($pdo, $base + ['decision'=>'skipped','reason_code'=>'probability_below_threshold','reason_text'=>'Event probability is below the configured threshold.']);
    }
    if ($confidence < (float)$ruleRow['minimum_confidence']) {
        return mg_store_trigger_engine_record_evaluation($pdo, $base + ['decision'=>'skipped','reason_code'=>'confidence_below_threshold','reason_text'=>'Evidence confidence is below the configured threshold.']);
    }

    try {
        mg_store_campaign_recommendation_campaign($pdo, $merchantUserId, (string)$ruleRow['campaign_public_id']);
    } catch (Throwable $error) {
        return mg_store_trigger_engine_record_evaluation($pdo, $base + ['decision'=>'blocked','reason_code'=>'campaign_unavailable','reason_text'=>$error->getMessage()]);
    }

    try {
        mg_store_manual_ops_assert_message_allowed($pdo, $merchantUserId, $customerUserId, true);
    } catch (Throwable $error) {
        return mg_store_trigger_engine_record_evaluation($pdo, $base + ['decision'=>'blocked','reason_code'=>'communication_blocked','reason_text'=>$error->getMessage()]);
    }

    $limit = mg_store_trigger_engine_delivery_limits($pdo,$merchantUserId,$customerUserId,(int)$ruleRow['id'],max(300,(int)$ruleRow['cooldown_seconds']),max(1,(int)$ruleRow['max_per_customer_day']));
    if ($limit) {
        return mg_store_trigger_engine_record_evaluation($pdo, $base + ['decision'=>'blocked','reason_code'=>$limit['code'],'reason_text'=>$limit['message']]);
    }

    if ($mode === 'dry_run') {
        return mg_store_trigger_engine_record_evaluation($pdo, $base + ['decision'=>'matched','reason_code'=>'dry_run_match','reason_text'=>'Rule matched in dry-run mode. No customer notification was sent.']);
    }
    if ($deliveryLimitReached) {
        return mg_store_trigger_engine_record_evaluation($pdo, $base + ['decision'=>'blocked','reason_code'=>'run_delivery_limit','reason_text'=>'The merchant run-level notification limit was reached.']);
    }

    $idempotencyKey = 'trigger-engine-' . substr(hash('sha256',(string)$ruleRow['public_id'] . ':' . (string)$event['public_id']),0,48);
    $note = trim((string)($ruleRow['notification_note'] ?? ''));
    if ($note === '') $note = 'A campaign matched your recent store activity. Open it to review and complete the requirements for the approved reward.';
    try {
        $delivery = mg_store_send_campaign_recommendation_notification(
            $pdo,$merchantUser,(string)$session['public_id'],(string)$ruleRow['campaign_public_id'],$note,$idempotencyKey
        );
        $base['recommendation_id'] = (string)($delivery['recommendation_id'] ?? '');
        $base['notification_id'] = (string)($delivery['notification_id'] ?? '');
        $base['evidence']['delivery_channel'] = 'notification';
        $base['evidence']['campaign_completion_required'] = true;
        return mg_store_trigger_engine_record_evaluation($pdo, $base + ['decision'=>'delivered','reason_code'=>'notification_delivered','reason_text'=>'Governed campaign recommendation notification delivered.']);
    } catch (Throwable $error) {
        $message = $error->getMessage();
        $blocked = str_contains(strtolower($message), 'not message') || str_contains(strtolower($message), 'blocked') || str_contains(strtolower($message), 'cooldown') || str_contains(strtolower($message), 'within the last');
        return mg_store_trigger_engine_record_evaluation($pdo, $base + ['decision'=>$blocked ? 'blocked' : 'error','reason_code'=>$blocked ? 'delivery_blocked' : 'delivery_error','reason_text'=>$message]);
    }
}

function mg_store_trigger_engine_run(PDO $pdo, array $merchantUser, bool $forceDryRun = false): array
{
    mg_store_trigger_engine_require_schema($pdo);
    $merchantUserId = (int)($merchantUser['id'] ?? 0);
    if ($merchantUserId < 1) throw new RuntimeException('Merchant account is required.');
    $settingsRow = mg_store_trigger_engine_settings($pdo,$merchantUserId,true);
    $mode = $forceDryRun ? 'dry_run' : (string)$settingsRow['execution_mode'];
    if ($mode === 'paused') throw new RuntimeException('Trigger engine is paused. Select Dry Run or Notification mode first.');
    if (!in_array($mode,['dry_run','notification'],true)) throw new RuntimeException('Invalid trigger engine mode.');

    $pdo->prepare("UPDATE mg_store_trigger_engine_settings SET last_run_at=NOW(),last_run_status='running',updated_at=NOW() WHERE merchant_user_id=?")->execute([$merchantUserId]);
    $ruleStmt = $pdo->prepare("SELECT * FROM mg_store_trigger_engine_rules WHERE merchant_user_id=? AND status='enabled' ORDER BY priority DESC,updated_at DESC,id DESC LIMIT 100");
    $ruleStmt->execute([$merchantUserId]);
    $rules = $ruleStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $sessions = mg_store_trigger_engine_active_sessions($pdo,$merchantUserId,100);
    $summary = [
        'mode'=>$mode,'rules'=>count($rules),'active_sessions'=>count($sessions),'events'=>0,'evaluations'=>0,
        'matched'=>0,'delivered'=>0,'skipped'=>0,'blocked'=>0,'errors'=>0,'duplicates'=>0,'reward_issued'=>false,
        'browser_overlap_used'=>false,'started_at'=>date('c'),
    ];
    $recent = [];
    $maxDeliveries = max(1,min(100,(int)$settingsRow['max_notifications_per_run']));

    try {
        foreach ($rules as $rule) {
            foreach ($sessions as $session) {
                $candidate = mg_store_trigger_engine_candidate($rule,$session);
                if (!$candidate) continue;
                $event = mg_store_trigger_engine_event($pdo,$merchantUserId,$session,$candidate);
                $summary['events']++;
                if (mg_store_trigger_engine_existing_evaluation($pdo,(int)$event['id'],(int)$rule['id'])) {
                    $summary['duplicates']++;
                    continue;
                }
                $evaluation = mg_store_trigger_engine_evaluate($pdo,$merchantUser,$rule,$session,$event,$candidate,$mode,$summary['delivered'] >= $maxDeliveries);
                $summary['evaluations']++;
                $decision = (string)$evaluation['decision'];
                if ($decision === 'matched') $summary['matched']++;
                elseif ($decision === 'delivered') $summary['delivered']++;
                elseif ($decision === 'skipped') $summary['skipped']++;
                elseif ($decision === 'blocked') $summary['blocked']++;
                elseif ($decision === 'error') $summary['errors']++;
                $recent[] = [
                    'rule_id'=>(string)$rule['public_id'],'rule_name'=>(string)$rule['name'],'event_type'=>(string)$candidate['event_type'],
                    'customer_user_id'=>(int)$session['customer_user_id'],'decision'=>$decision,'reason_code'=>(string)$evaluation['reason_code'],
                    'reason_text'=>(string)$evaluation['reason_text'],'probability'=>(float)$candidate['probability'],'confidence'=>(float)$candidate['confidence'],
                ];
                $pdo->prepare("UPDATE mg_store_trigger_events SET status=?,updated_at=NOW() WHERE id=?")->execute([$decision === 'error' ? 'error' : 'evaluated',(int)$event['id']]);
                $pdo->prepare("UPDATE mg_store_trigger_engine_rules SET last_evaluated_at=NOW(),last_matched_at=IF(? IN ('matched','delivered'),NOW(),last_matched_at),last_delivered_at=IF(?='delivered',NOW(),last_delivered_at),updated_at=NOW() WHERE id=?")
                    ->execute([$decision,$decision,(int)$rule['id']]);
                if ($decision === 'delivered' && !empty($rule['trigger_zone_public_id'])) {
                    $pdo->prepare('UPDATE mg_store_trigger_zones SET last_triggered_at=NOW(),updated_at=NOW() WHERE public_id=? AND merchant_user_id=?')->execute([(string)$rule['trigger_zone_public_id'],$merchantUserId]);
                }
            }
        }
        $summary['completed_at'] = date('c');
        $summary['status'] = $summary['errors'] > 0 ? 'partial' : 'completed';
        $pdo->prepare('UPDATE mg_store_trigger_engine_settings SET last_run_status=?,last_run_summary_json=?,updated_at=NOW() WHERE merchant_user_id=?')
            ->execute([$summary['status'],mg_store_trigger_engine_encode($summary),$merchantUserId]);
    } catch (Throwable $error) {
        $summary['completed_at'] = date('c');
        $summary['status'] = 'failed';
        $summary['failure'] = $error->getMessage();
        $pdo->prepare("UPDATE mg_store_trigger_engine_settings SET last_run_status='failed',last_run_summary_json=?,updated_at=NOW() WHERE merchant_user_id=?")
            ->execute([mg_store_trigger_engine_encode($summary),$merchantUserId]);
        throw $error;
    }

    mg_event('store_canvas.trigger_engine_run', [
        'mode'=>$mode,'rules'=>$summary['rules'],'active_sessions'=>$summary['active_sessions'],'evaluations'=>$summary['evaluations'],
        'delivered'=>$summary['delivered'],'blocked'=>$summary['blocked'],'errors'=>$summary['errors'],'reward_issued'=>false,'browser_overlap_used'=>false,
    ], $merchantUserId);

    return ['summary'=>$summary,'recent'=>array_slice($recent,-50)];
}

function mg_store_trigger_engine_recent(PDO $pdo, int $merchantUserId, int $limit = 50): array
{
    $limit = max(1,min(200,$limit));
    $stmt = $pdo->prepare("SELECT ev.public_id evaluation_id,ev.execution_mode,ev.decision,ev.reason_code,ev.reason_text,ev.probability_score,ev.confidence_score,ev.recommendation_id,ev.notification_id,ev.created_at,
                                  r.public_id rule_public_id,r.name rule_name,r.event_type,c.title campaign_title,
                                  e.public_id event_public_id,e.store_session_public_id,e.customer_user_id,e.source_type,e.source_public_id
                           FROM mg_store_trigger_evaluations ev
                           JOIN mg_store_trigger_engine_rules r ON r.id=ev.rule_id AND r.merchant_user_id=ev.merchant_user_id
                           JOIN mg_store_trigger_events e ON e.id=ev.event_id AND e.merchant_user_id=ev.merchant_user_id
                           LEFT JOIN campaigns c ON c.public_id=ev.campaign_public_id AND c.merchant_user_id=ev.merchant_user_id
                           WHERE ev.merchant_user_id=? ORDER BY ev.id DESC LIMIT {$limit}");
    $stmt->execute([$merchantUserId]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
            'id'=>(string)$row['evaluation_id'],'execution_mode'=>(string)$row['execution_mode'],'decision'=>(string)$row['decision'],
            'reason_code'=>(string)$row['reason_code'],'reason_text'=>(string)$row['reason_text'],'probability'=>(float)$row['probability_score'],
            'confidence'=>(float)$row['confidence_score'],'recommendation_id'=>(string)($row['recommendation_id'] ?? ''),'notification_id'=>(string)($row['notification_id'] ?? ''),
            'created_at'=>$row['created_at'],'rule_id'=>(string)$row['rule_public_id'],'rule_name'=>(string)$row['rule_name'],'event_type'=>(string)$row['event_type'],
            'campaign_title'=>(string)($row['campaign_title'] ?? ''),'event_id'=>(string)$row['event_public_id'],'session_id'=>(string)($row['store_session_public_id'] ?? ''),
            'customer_user_id'=>(int)$row['customer_user_id'],'source_type'=>(string)$row['source_type'],'source_public_id'=>(string)($row['source_public_id'] ?? ''),
        ];
    }
    return $items;
}

function mg_store_trigger_engine_summary(PDO $pdo, int $merchantUserId): array
{
    $counts = ['rules'=>0,'enabled_rules'=>0,'events'=>0,'matched'=>0,'delivered'=>0,'skipped'=>0,'blocked'=>0,'errors'=>0];
    $stmt = $pdo->prepare("SELECT COUNT(*) rules,SUM(status='enabled') enabled_rules FROM mg_store_trigger_engine_rules WHERE merchant_user_id=? AND status<>'archived'");
    $stmt->execute([$merchantUserId]);
    $ruleCounts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $counts['rules'] = (int)($ruleCounts['rules'] ?? 0);
    $counts['enabled_rules'] = (int)($ruleCounts['enabled_rules'] ?? 0);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM mg_store_trigger_events WHERE merchant_user_id=?');
    $stmt->execute([$merchantUserId]);
    $counts['events'] = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT decision,COUNT(*) total FROM mg_store_trigger_evaluations WHERE merchant_user_id=? GROUP BY decision");
    $stmt->execute([$merchantUserId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $decision = (string)$row['decision'];
        if (array_key_exists($decision,$counts)) $counts[$decision] = (int)$row['total'];
    }
    return $counts;
}

function mg_store_trigger_engine_payload(PDO $pdo, int $merchantUserId): array
{
    $missing = mg_store_trigger_engine_missing_tables($pdo);
    if ($missing !== []) {
        return [
            'schema_ready'=>false,'missing_tables'=>$missing,'settings'=>null,'summary'=>[],'rules'=>[],'campaigns'=>[],'zones'=>[],'recent'=>[],
            'event_types'=>mg_store_trigger_engine_event_types(),
            'capabilities'=>['server_event_normalization'=>false,'dry_run'=>false,'notification_delivery'=>false,'browser_overlap_execution'=>false,'reward_issue'=>false],
        ];
    }
    $settings = mg_store_trigger_engine_settings_public(mg_store_trigger_engine_settings($pdo,$merchantUserId,true));
    return [
        'schema_ready'=>true,'missing_tables'=>[],'settings'=>$settings,'summary'=>mg_store_trigger_engine_summary($pdo,$merchantUserId),
        'rules'=>mg_store_trigger_engine_rules($pdo,$merchantUserId),'campaigns'=>mg_store_trigger_engine_campaigns($pdo,$merchantUserId),
        'zones'=>mg_canvas_trigger_zone_schema_ready($pdo) ? mg_canvas_trigger_zone_list($pdo,$merchantUserId) : [],
        'recent'=>mg_store_trigger_engine_recent($pdo,$merchantUserId,50),'event_types'=>mg_store_trigger_engine_event_types(),
        'capabilities'=>[
            'server_event_normalization'=>true,'dry_run'=>true,'notification_delivery'=>true,'scheduled_runner_ready'=>true,
            'browser_overlap_execution'=>false,'automatic_proximity_chat'=>false,'reward_issue'=>false,'wallet_write'=>false,
            'campaign_creation'=>false,'customer_to_customer_actions'=>false,'reward_issue_policy'=>'campaign_completion_only',
        ],
        'authority'=>[
            'events'=>'server_derived_only','campaigns'=>'existing_active_campaigns','rewards'=>'existing_attached_active_reward_templates',
            'delivery'=>'campaign_recommendation_notification','idempotency'=>'existing_merchant_canvas_action_receipts',
            'wallet'=>'campaign_completion_only','inbox_pppm'=>'after_wallet_issue','protected_traits'=>'excluded',
        ],
    ];
}
