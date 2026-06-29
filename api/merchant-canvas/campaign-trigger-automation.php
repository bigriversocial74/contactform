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

function mg_sc_auto_column_exists(PDO $pdo, string $column): bool
{
    try { $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'); $stmt->execute(['mg_store_trigger_zones', $column]); return (bool)$stmt->fetchColumn(); }
    catch (Throwable) { return false; }
}

function mg_sc_auto_session(PDO $pdo, int $merchantUserId, string $sessionPublicId): array
{
    mg_store_canvas_require_tables($pdo, ['mg_store_sessions','mg_store_session_events','mg_customer_store_history'], 'Store Canvas');
    $sessionPublicId = mg_store_safe_public_id($sessionPublicId, 'Store session');
    $stmt = $pdo->prepare("SELECT s.*,cp.display_name customer_name FROM mg_store_sessions s LEFT JOIN public_profiles cp ON cp.user_id=s.customer_user_id WHERE s.public_id=? AND s.merchant_user_id=? AND s.active_key IS NOT NULL AND s.status IN ('entered','active','idle') AND s.exited_at IS NULL LIMIT 1");
    $stmt->execute([$sessionPublicId, $merchantUserId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) throw new RuntimeException('Active customer session is not available.');
    return $session;
}

function mg_sc_auto_load_zone(PDO $pdo, int $merchantUserId, string $zoneId): array
{
    if (!mg_canvas_trigger_zone_schema_ready($pdo) || !mg_sc_auto_column_exists($pdo, 'automation_action')) throw new RuntimeException('Store Canvas automation rule migration is required.');
    $zoneId = mg_store_safe_public_id($zoneId, 'Trigger zone');
    $campaignJoin = mg_store_canvas_table_exists($pdo, 'campaigns') ? 'LEFT JOIN campaigns c ON c.public_id=z.campaign_public_id AND c.merchant_user_id=z.merchant_user_id' : '';
    $campaignTitle = $campaignJoin !== '' ? 'c.title campaign_title' : 'NULL campaign_title';
    $stmt = $pdo->prepare("SELECT z.*,{$campaignTitle} FROM mg_store_trigger_zones z {$campaignJoin} WHERE z.public_id=? AND z.merchant_user_id=? AND z.status='active' LIMIT 1");
    $stmt->execute([$zoneId, $merchantUserId]);
    $zone = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$zone) throw new RuntimeException('Trigger zone is not active or available.');
    return $zone;
}

function mg_sc_auto_cooldown_active(PDO $pdo, array $session, array $zone, string $eventKey): bool
{
    $policy = (string)($zone['cooldown_policy'] ?? 'fifteen_minutes');
    $zoneId = (string)($zone['public_id'] ?? '');
    try {
        if ($policy === 'once_per_visit') {
            $stmt = $pdo->prepare("SELECT 1 FROM mg_store_session_events WHERE store_session_id=? AND event_type='campaign_trigger_zone' AND event_data_json LIKE ? LIMIT 1");
            $stmt->execute([(int)$session['id'], '%' . $zoneId . '%']);
            return (bool)$stmt->fetchColumn();
        }
        if ($policy === 'once_per_customer_day') {
            $stmt = $pdo->prepare("SELECT 1 FROM mg_store_session_events WHERE merchant_user_id=? AND customer_user_id=? AND event_type='campaign_trigger_zone' AND event_data_json LIKE ? AND created_at >= CURDATE() LIMIT 1");
            $stmt->execute([(int)$session['merchant_user_id'], (int)$session['customer_user_id'], '%' . $zoneId . '%']);
            return (bool)$stmt->fetchColumn();
        }
        $seconds = match ($policy) { 'five_minutes' => 300, 'one_hour' => 3600, default => max(60, (int)($zone['cooldown_seconds'] ?? 900)) };
        $stmt = $pdo->prepare("SELECT 1 FROM mg_store_session_events WHERE store_session_id=? AND event_type='campaign_trigger_zone' AND event_data_json LIKE ? AND created_at >= DATE_SUB(NOW(), INTERVAL {$seconds} SECOND) LIMIT 1");
        $stmt->execute([(int)$session['id'], '%' . $eventKey . '%']);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) { return false; }
}

function mg_sc_auto_pick_template(array $campaign, array $templates): ?array
{
    $attachedTemplateId = trim((string)($campaign['reward_template_id'] ?? ''));
    if ($attachedTemplateId !== '') foreach ($templates as $template) if (is_array($template) && (string)($template['id'] ?? '') === $attachedTemplateId) return $template;
    return isset($templates[0]) && is_array($templates[0]) ? $templates[0] : null;
}

function mg_sc_auto_render_message(string $template, string $firstName, string $triggerLabel, string $campaignTitle): string
{
    $template = trim($template) ?: 'Hi {first_name} — you entered the {trigger_name} zone. I sent this to your IN/OUT Box so you can review the offer or ask questions.';
    return strtr($template, ['{first_name}'=>$firstName,'{trigger_name}'=>$triggerLabel,'{campaign_title}'=>$campaignTitle]);
}

try {
    $merchantUserId = (int)$user['id'];
    $sessionId = mg_store_safe_public_id($input['session_id'] ?? '', 'Store session');
    $zoneId = mg_store_safe_public_id($input['trigger_zone_id'] ?? '', 'Trigger zone');
    $zone = mg_sc_auto_load_zone($pdo, $merchantUserId, $zoneId);
    $session = mg_sc_auto_session($pdo, $merchantUserId, $sessionId);
    $action = (string)($zone['automation_action'] ?? 'message_and_reward');
    $triggerKey = (string)($zone['trigger_key'] ?? 'store_canvas_zone');
    $triggerLabel = (string)($zone['name'] ?? 'IN/OUT Box Trigger');
    $campaignId = trim((string)($zone['campaign_public_id'] ?? ''));
    $priority = max(1, min(5, (int)($zone['priority'] ?? 3)));
    $eventKey = $zoneId . ':' . $sessionId . ':' . ($campaignId !== '' ? $campaignId : 'default') . ':' . $action;

    mg_rate_limit('merchant_canvas.campaign_trigger_automation', 'user:' . $merchantUserId, 100, 60);
    if (mg_sc_auto_cooldown_active($pdo, $session, $zone, $eventKey)) {
        mg_ok(['triggered'=>false,'cooldown'=>true,'trigger_zone_id'=>$zoneId,'automation_action'=>$action,'cooldown_policy'=>(string)($zone['cooldown_policy'] ?? 'fifteen_minutes')], 'Trigger cooldown active.');
        return;
    }

    $sendMessage = in_array($action, ['message_and_reward','message_only'], true);
    $sendReward = in_array($action, ['message_and_reward','reward_only'], true);
    $followUp = $action === 'follow_up';
    $crmSegment = $action === 'crm_segment';
    $notifyOnly = $action === 'notify_only';
    $analyticsOnly = $action === 'analytics_only';
    $fallbackApplied = false;
    $stampDebit = ['debited'=>false,'error'=>'Messages are free.'];
    $messageResult = null;
    $rewardResult = null;
    $campaign = null;
    $template = null;

    if ($campaignId !== '') {
        $options = mg_store_reward_options($pdo, $merchantUserId);
        foreach ((array)($options['campaigns'] ?? []) as $candidate) if (is_array($candidate) && (string)($candidate['id'] ?? '') === $campaignId) { $campaign = $candidate; break; }
        if (is_array($campaign)) $template = mg_sc_auto_pick_template($campaign, (array)($options['templates'] ?? []));
    }
    $campaignTitle = is_array($campaign) ? (string)($campaign['title'] ?? '') : (string)($zone['campaign_title'] ?? '');

    if ($sendMessage) {
        $customerName = trim((string)($session['customer_name'] ?? '')) ?: 'there';
        $firstName = preg_split('/\s+/u', $customerName, -1, PREG_SPLIT_NO_EMPTY)[0] ?? $customerName;
        $messageBody = mg_sc_auto_render_message((string)($zone['auto_message_text'] ?? ''), $firstName, $triggerLabel, $campaignTitle);
        $messageResult = mg_store_send_direct_message_via_messaging($pdo, $merchantUserId, $sessionId, $messageBody);
    }

    if ($sendReward && is_array($campaign) && is_array($template) && !empty($campaign['id']) && !empty($template['id'])) {
        $rewardResult = mg_store_reward_issue($pdo, $user, $sessionId, (string)$campaign['id'], (string)$template['id'], 'Triggered by ' . $triggerLabel . ' on the Merchant Store Canvas.', null, null, 'canvas-trigger-auto:' . hash('sha256', $merchantUserId . '|' . $sessionId . '|' . $zoneId . '|' . (string)$campaign['id'] . '|' . gmdate('YmdHi')));
        if (is_array($rewardResult) && is_array($rewardResult['stamp_ledger'] ?? null)) {
            $stampDebit = ['debited'=>true,'entry_id'=>(string)($rewardResult['stamp_ledger']['entry']['entry_id'] ?? ''),'result'=>$rewardResult['stamp_ledger']];
        }
    }

    mg_canvas_trigger_zone_touch($pdo, $merchantUserId, $zoneId);
    mg_store_log_event($pdo, $session, 'campaign_trigger_zone', $triggerLabel, [
        'trigger_key'=>$triggerKey,
        'trigger_zone_id'=>$zoneId,
        'trigger_priority'=>$priority,
        'automation_action'=>$action,
        'cooldown_policy'=>(string)($zone['cooldown_policy'] ?? 'fifteen_minutes'),
        'event_key'=>$eventKey,
        'selected_campaign_id'=>$campaignId ?: null,
        'fallback_applied'=>$fallbackApplied,
        'notify_merchant'=>$notifyOnly || !empty($zone['notify_merchant']),
        'follow_up_created'=>$followUp,
        'crm_segment_added'=>$crmSegment,
        'crm_segment_name'=>(string)($zone['crm_segment_name'] ?? ''),
        'analytics_only'=>$analyticsOnly,
        'source_system'=>'store_canvas',
        'source_channel'=>'merchant_canvas_automation',
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

    mg_ok(['triggered'=>true,'trigger_zone_id'=>$zoneId,'priority'=>$priority,'automation_action'=>$action,'fallback_applied'=>$fallbackApplied,'message_sent'=>is_array($messageResult),'reward_sent'=>is_array($rewardResult),'notify_merchant'=>$notifyOnly,'follow_up_created'=>$followUp,'crm_segment_added'=>$crmSegment,'analytics_only'=>$analyticsOnly,'stamp_debited'=>!empty($stampDebit['debited']),'stamp_ledger_entry_id'=>(string)($stampDebit['entry_id'] ?? ''),'stamp_debit_error'=>(string)($stampDebit['error'] ?? ''),'campaign_id'=>is_array($campaign) ? ($campaign['id'] ?? null) : null,'campaign_title'=>is_array($campaign) ? ($campaign['title'] ?? null) : null], 'Automation trigger fired.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.campaign_trigger_automation_failed', 'Store Canvas automation trigger failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], (int)$user['id']);
    mg_fail('Unable to fire automation trigger.', 500);
}
