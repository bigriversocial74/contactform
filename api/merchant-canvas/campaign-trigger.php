<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_messaging.php';
require_once dirname(__DIR__) . '/store/_canvas_rewards.php';
require_once __DIR__ . '/_trigger_zones.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) mg_fail('Merchant access required.', 403);

function mg_canvas_trigger_session(PDO $pdo, int $merchantUserId, string $sessionPublicId): array
{
    mg_store_canvas_require_tables($pdo, ['mg_store_sessions','mg_store_session_events','mg_customer_store_history'], 'Store Canvas');
    $sessionPublicId = mg_store_safe_public_id($sessionPublicId, 'Store session');
    $stmt = $pdo->prepare("SELECT s.*,cp.display_name customer_name FROM mg_store_sessions s LEFT JOIN public_profiles cp ON cp.user_id=s.customer_user_id WHERE s.public_id=? AND s.merchant_user_id=? AND s.active_key IS NOT NULL AND s.status IN ('entered','active','idle') AND s.exited_at IS NULL LIMIT 1");
    $stmt->execute([$sessionPublicId, $merchantUserId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) throw new RuntimeException('Active customer session is not available.');
    return $session;
}

function mg_canvas_trigger_recent(PDO $pdo, array $session, string $key): bool
{
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM mg_store_session_events WHERE store_session_id=? AND event_type='campaign_trigger_zone' AND event_data_json LIKE ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MINUTE) LIMIT 1");
        $stmt->execute([(int)$session['id'], '%' . $key . '%']);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) { return false; }
}

function mg_canvas_trigger_pick_template(array $campaign, array $templates): ?array
{
    $attachedTemplateId = trim((string)($campaign['reward_template_id'] ?? ''));
    if ($attachedTemplateId !== '') {
        foreach ($templates as $template) {
            if (is_array($template) && (string)($template['id'] ?? '') === $attachedTemplateId) return $template;
        }
    }
    return isset($templates[0]) && is_array($templates[0]) ? $templates[0] : null;
}

function mg_canvas_trigger_stamp_debit(PDO $pdo, int $merchantUserId, string $sessionId, string $eventKey, string $triggerLabel, array $metadata): array
{
    return ['debited'=>false,'error'=>'Messages are free.'];
}

try {
    $merchantUserId = (int)$user['id'];
    $sessionId = mg_store_safe_public_id($input['session_id'] ?? '', 'Store session');
    $zoneId = trim((string)($input['trigger_zone_id'] ?? ''));
    $zone = null;
    if ($zoneId !== '') {
        $zoneId = mg_store_safe_public_id($zoneId, 'Trigger zone');
        $zone = mg_canvas_trigger_zone_load($pdo, $merchantUserId, $zoneId);
        if (!$zone || (string)($zone['status'] ?? '') !== 'active') throw new RuntimeException('Trigger zone is not active or available.');
    }
    $triggerKey = mg_canvas_trigger_zone_key($input['trigger_key'] ?? ($zone['trigger_key'] ?? 'in_out_box_zone'));
    $triggerLabel = mg_canvas_trigger_zone_name($input['trigger_label'] ?? ($zone['name'] ?? 'IN/OUT Box Campaign Trigger'));
    $selectedCampaignId = trim((string)($input['campaign_id'] ?? ''));
    if ($selectedCampaignId === '' && is_array($zone)) $selectedCampaignId = trim((string)($zone['campaign_public_id'] ?? ''));
    if ($selectedCampaignId !== '') $selectedCampaignId = mg_store_safe_public_id($selectedCampaignId, 'Campaign');
    $priority = is_array($zone) ? max(1, min(5, (int)$zone['priority'])) : max(1, min(5, (int)($input['priority'] ?? 3)));

    mg_rate_limit('merchant_canvas.campaign_trigger', 'user:' . $merchantUserId, 80, 60);
    $session = mg_canvas_trigger_session($pdo, $merchantUserId, $sessionId);
    $customerName = trim((string)($session['customer_name'] ?? '')) ?: 'there';
    $firstName = preg_split('/\s+/u', $customerName, -1, PREG_SPLIT_NO_EMPTY)[0] ?? $customerName;
    $eventKey = ($zoneId !== '' ? $zoneId : $triggerKey) . ':' . $sessionId . ':' . ($selectedCampaignId !== '' ? $selectedCampaignId : 'default');

    if (mg_canvas_trigger_recent($pdo, $session, $eventKey)) {
        mg_ok(['triggered'=>false,'recent'=>true,'trigger_zone_id'=>$zoneId ?: null,'priority'=>$priority,'campaign_id'=>$selectedCampaignId ?: null], 'Campaign trigger recently fired.');
        return;
    }

    $messageResult = null;
    $rewardResult = null;
    $stampDebit = ['debited'=>false,'error'=>'Messages are free.'];
    $campaign = null;
    $template = null;

    try {
        $messageBody = 'Hi ' . $firstName . ' — you entered the ' . $triggerLabel . ' zone. I sent this to your IN/OUT Box so you can review the offer or ask questions.';
        $messageResult = mg_store_send_direct_message_via_messaging($pdo, $merchantUserId, $sessionId, $messageBody);
        $stampDebit = mg_canvas_trigger_stamp_debit($pdo, $merchantUserId, $sessionId, $eventKey, $triggerLabel, [
            'trigger_zone_id' => $zoneId ?: null,
            'trigger_key' => $triggerKey,
            'trigger_priority' => $priority,
            'store_session_id' => $sessionId,
            'campaign_id' => $selectedCampaignId ?: null,
            'message_id' => is_array($messageResult) ? ($messageResult['id'] ?? null) : null,
            'source_system' => 'store_canvas',
            'source_channel' => 'merchant_canvas_trigger',
        ]);
    } catch (Throwable $messageError) {
        mg_security_log('error', 'merchant_canvas.trigger_message_failed', 'Campaign trigger message failed.', ['exception_class'=>$messageError::class], $merchantUserId);
        $stampDebit = ['debited'=>false,'error'=>'Automated message failed.'];
    }

    try {
        $options = mg_store_reward_options($pdo, $merchantUserId);
        $campaigns = is_array($options['campaigns'] ?? null) ? $options['campaigns'] : [];
        $templates = is_array($options['templates'] ?? null) ? $options['templates'] : [];
        if ($selectedCampaignId !== '') {
            foreach ($campaigns as $candidate) {
                if (is_array($candidate) && (string)($candidate['id'] ?? '') === $selectedCampaignId) { $campaign = $candidate; break; }
            }
            if (!$campaign) throw new RuntimeException('Selected trigger campaign is not active or available.');
        } else {
            $campaign = isset($campaigns[0]) && is_array($campaigns[0]) ? $campaigns[0] : null;
        }
        if (is_array($campaign)) $template = mg_canvas_trigger_pick_template($campaign, $templates);
        if (is_array($campaign) && is_array($template) && !empty($campaign['id']) && !empty($template['id'])) {
            $rewardResult = mg_store_reward_issue($pdo, $user, $sessionId, (string)$campaign['id'], (string)$template['id'], 'Triggered by ' . $triggerLabel . ' on the Merchant Store Canvas.', null, null, 'canvas-trigger:' . hash('sha256', $merchantUserId . '|' . $sessionId . '|' . $triggerKey . '|' . ($zoneId ?: 'zone') . '|' . (string)$campaign['id'] . '|' . gmdate('YmdHi')));
            if (is_array($rewardResult) && is_array($rewardResult['stamp_ledger'] ?? null)) {
                $stampDebit = ['debited'=>true,'entry_id'=>(string)($rewardResult['stamp_ledger']['entry']['entry_id'] ?? ''),'result'=>$rewardResult['stamp_ledger']];
            }
        }
    } catch (Throwable $rewardError) {
        mg_security_log('error', 'merchant_canvas.trigger_reward_failed', 'Campaign trigger reward failed.', ['exception_class'=>$rewardError::class,'selected_campaign_id'=>$selectedCampaignId,'trigger_zone_id'=>$zoneId], $merchantUserId);
    }

    if ($zoneId !== '') mg_canvas_trigger_zone_touch($pdo, $merchantUserId, $zoneId);
    mg_store_log_event($pdo, $session, 'campaign_trigger_zone', $triggerLabel, [
        'trigger_key'=>$triggerKey,
        'trigger_zone_id'=>$zoneId ?: null,
        'trigger_priority'=>$priority,
        'event_key'=>$eventKey,
        'selected_campaign_id'=>$selectedCampaignId ?: null,
        'source_system'=>'store_canvas',
        'source_channel'=>'merchant_canvas_motion',
        'message_sent'=>is_array($messageResult),
        'reward_sent'=>is_array($rewardResult),
        'stamp_debited'=>!empty($stampDebit['debited']),
        'stamp_ledger_entry_id'=>(string)($stampDebit['entry_id'] ?? ''),
        'stamp_action_key'=>!empty($stampDebit['debited'])?'direct_reward_send':null,
        'stamp_debit_error'=>(string)($stampDebit['error'] ?? ''),
        'message_id'=>is_array($messageResult) ? ($messageResult['id'] ?? null) : null,
        'wallet_item_id'=>is_array($rewardResult) ? ($rewardResult['wallet_item_id'] ?? null) : null,
        'campaign_id'=>is_array($rewardResult) ? ($rewardResult['campaign_id'] ?? null) : ($campaign['id'] ?? null),
        'campaign_title'=>is_array($campaign) ? ($campaign['title'] ?? null) : null,
        'reward_template_id'=>is_array($rewardResult) ? ($rewardResult['reward_template_id'] ?? null) : ($template['id'] ?? null),
    ]);

    mg_event('store_canvas.campaign_trigger_zone', ['session_id'=>$sessionId,'trigger_zone_id'=>$zoneId ?: null,'priority'=>$priority,'campaign_id'=>is_array($campaign) ? ($campaign['id'] ?? null) : null,'message_sent'=>is_array($messageResult),'reward_sent'=>is_array($rewardResult),'stamp_debited'=>!empty($stampDebit['debited'])], $merchantUserId);
    mg_ok(['triggered'=>true,'trigger_key'=>$triggerKey,'trigger_zone_id'=>$zoneId ?: null,'priority'=>$priority,'campaign_id'=>is_array($campaign) ? ($campaign['id'] ?? null) : null,'campaign_title'=>is_array($campaign) ? ($campaign['title'] ?? null) : null,'message_sent'=>is_array($messageResult),'reward_sent'=>is_array($rewardResult),'stamp_debited'=>!empty($stampDebit['debited']),'stamp_ledger_entry_id'=>(string)($stampDebit['entry_id'] ?? ''),'stamp_debit_error'=>(string)($stampDebit['error'] ?? ''),'message'=>$messageResult,'reward'=>$rewardResult], 'Campaign trigger fired.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.campaign_trigger_failed', 'Merchant canvas campaign trigger failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to fire campaign trigger.', 500);
}
