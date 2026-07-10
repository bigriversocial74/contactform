<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_trigger_orchestration.php';

function mg_trigger_orchestration_save_rule(PDO $pdo, int $merchantUserId, array $input): array
{
    mg_trigger_orchestration_require_schema($pdo);
    $eventType = strtolower(trim((string)($input['event_type'] ?? '')));
    if (!array_key_exists($eventType, mg_trigger_orchestration_event_types())) {
        throw new InvalidArgumentException('Invalid orchestration event type.');
    }

    $policyInput = is_array($input['policy'] ?? null) ? $input['policy'] : $input;
    $policyInput['event_type'] = $eventType;
    $policy = mg_trigger_orchestration_save_policy($pdo, $merchantUserId, $policyInput);

    $publicId = strtolower(trim((string)($input['rule_id'] ?? '')));
    if ($publicId !== '') $publicId = mg_store_safe_public_id($publicId, 'Trigger rule');
    $name = trim((string)($input['rule_name'] ?? $input['name'] ?? '')) ?: ($policy['event_label'] . ' campaign rule');
    $name = mb_substr($name, 0, 180);
    $status = strtolower(trim((string)($input['rule_status'] ?? $input['status'] ?? 'paused')));
    if (!in_array($status, ['enabled','paused'], true)) $status = 'paused';
    $priority = max(1, min(5, (int)($input['priority'] ?? 3)));
    $minimumProbability = mg_store_trigger_engine_clamp((float)($input['minimum_probability'] ?? 50));
    $minimumConfidence = mg_store_trigger_engine_clamp((float)($input['minimum_confidence'] ?? 30));
    $visitMilestone = $eventType === 'visit_milestone' ? max(2, min(1000, (int)($input['visit_milestone'] ?? 3))) : null;
    $cooldown = max(300, min(2592000, (int)($input['cooldown_seconds'] ?? 86400)));
    $dailyLimit = max(1, min(20, (int)($input['max_per_customer_day'] ?? 1)));
    $requireActive = array_key_exists('require_active_session', $input)
        ? (!empty($input['require_active_session']) ? 1 : 0)
        : (!empty($policy['require_active_session']) ? 1 : 0);
    $note = trim((string)($input['notification_note'] ?? ''));
    if (mb_strlen($note) > 1000) throw new InvalidArgumentException('Notification note is too long.');

    $campaignPublicId = mg_store_safe_public_id((string)($input['campaign_id'] ?? ''), 'Campaign');
    mg_store_campaign_recommendation_campaign($pdo, $merchantUserId, $campaignPublicId);

    $zonePublicId = trim((string)($input['trigger_zone_id'] ?? ''));
    if ($zonePublicId !== '') {
        $zone = mg_canvas_trigger_zone_load($pdo, $merchantUserId, $zonePublicId);
        if (!$zone || (string)($zone['status'] ?? '') !== 'active') throw new RuntimeException('Selected trigger zone is not active.');
        $zoneCampaign = trim((string)($zone['campaign_id'] ?? ''));
        if ($zoneCampaign !== '' && $zoneCampaign !== $campaignPublicId) throw new RuntimeException('Selected trigger zone is bound to a different campaign.');
        $zonePublicId = (string)$zone['id'];
    } else {
        $zonePublicId = null;
    }

    $audienceRules = is_array($input['audience_rules'] ?? null) ? $input['audience_rules'] : [];
    $audienceRules['protected_traits_excluded'] = true;
    $audienceRules['individual_customer_selection'] = false;
    $audienceRules['browser_overlap_authority'] = false;
    $metadata = [
        'authority' => 'canonical_server_event_only',
        'delivery' => 'notification_only',
        'orchestration_policy_public_id' => (string)$policy['id'],
        'reward_issue_policy' => 'campaign_completion_only',
        'wallet_write_allowed' => false,
        'direct_message_allowed' => false,
        'peer_actions_allowed' => false,
    ];

    if ($publicId === '') {
        $publicId = mg_trigger_orchestration_uuid();
        $stmt = $pdo->prepare('INSERT INTO mg_store_trigger_engine_rules
            (public_id,merchant_user_id,trigger_zone_public_id,orchestration_policy_public_id,campaign_public_id,name,event_type,status,priority,minimum_probability,minimum_confidence,visit_milestone,cooldown_seconds,max_per_customer_day,require_active_session,notification_note,audience_rules_json,metadata_json,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([$publicId,$merchantUserId,$zonePublicId,(string)$policy['id'],$campaignPublicId,$name,$eventType,$status,$priority,$minimumProbability,$minimumConfidence,$visitMilestone,$cooldown,$dailyLimit,$requireActive,$note !== '' ? $note : null,mg_store_trigger_engine_encode($audienceRules),mg_store_trigger_engine_encode($metadata)]);
    } else {
        $stmt = $pdo->prepare("UPDATE mg_store_trigger_engine_rules SET trigger_zone_public_id=?,orchestration_policy_public_id=?,campaign_public_id=?,name=?,event_type=?,status=?,priority=?,minimum_probability=?,minimum_confidence=?,visit_milestone=?,cooldown_seconds=?,max_per_customer_day=?,require_active_session=?,notification_note=?,audience_rules_json=?,metadata_json=?,updated_at=NOW() WHERE public_id=? AND merchant_user_id=? AND status<>'archived'");
        $stmt->execute([$zonePublicId,(string)$policy['id'],$campaignPublicId,$name,$eventType,$status,$priority,$minimumProbability,$minimumConfidence,$visitMilestone,$cooldown,$dailyLimit,$requireActive,$note !== '' ? $note : null,mg_store_trigger_engine_encode($audienceRules),mg_store_trigger_engine_encode($metadata),$publicId,$merchantUserId]);
        if ($stmt->rowCount() < 1) {
            $verify = $pdo->prepare("SELECT 1 FROM mg_store_trigger_engine_rules WHERE public_id=? AND merchant_user_id=? AND status<>'archived' LIMIT 1");
            $verify->execute([$publicId,$merchantUserId]);
            if (!$verify->fetchColumn()) throw new RuntimeException('Trigger rule is not available.');
        }
    }

    foreach (mg_store_trigger_engine_rules($pdo, $merchantUserId) as $rule) {
        if ((string)$rule['id'] === $publicId) {
            $rule['event_label'] = mg_trigger_orchestration_event_types()[$eventType] ?? $rule['event_label'];
            $rule['orchestration_policy_id'] = (string)$policy['id'];
            return ['rule'=>$rule,'policy'=>$policy];
        }
    }
    throw new RuntimeException('Unable to load the saved orchestration rule.');
}
