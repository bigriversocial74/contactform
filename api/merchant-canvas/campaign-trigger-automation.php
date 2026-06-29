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

function mg_sc_auto_require_delivery_ledger(PDO $pdo): void
{
    mg_store_canvas_require_tables($pdo, ['mg_store_trigger_deliveries'], 'Store Canvas trigger delivery ledger');
}

function mg_sc_auto_session(PDO $pdo, int $merchantUserId, string $sessionPublicId): array
{
    mg_store_canvas_require_tables($pdo, ['mg_store_sessions','mg_store_session_events','mg_customer_store_history'], 'Store Canvas');
    mg_sc_auto_require_delivery_ledger($pdo);
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

function mg_sc_auto_cooldown_seconds(array $zone): int
{
    $policy = (string)($zone['cooldown_policy'] ?? 'fifteen_minutes');
    if ($policy === 'five_minutes') return 300;
    if ($policy === 'one_hour') return 3600;
    return max(60, min(86400, (int)($zone['cooldown_seconds'] ?? 900)));
}

function mg_sc_auto_cooldown_window(array $zone): array
{
    $policy = (string)($zone['cooldown_policy'] ?? 'fifteen_minutes');
    if ($policy === 'once_per_visit') {
        return ['cooldown_policy'=>$policy,'cooldown_started'=>true,'cooldown_locked'=>true,'cooldown_remaining_seconds'=>86400,'cooldown_until'=>null,'cooldown_until_sql'=>null,'cooldown_seconds'=>0];
    }
    if ($policy === 'once_per_customer_day') {
        $until = strtotime('tomorrow');
        $remaining = $until !== false ? max(60, $until - time()) : 86400;
        return ['cooldown_policy'=>$policy,'cooldown_started'=>true,'cooldown_locked'=>false,'cooldown_remaining_seconds'=>$remaining,'cooldown_until'=>$until !== false ? date('c', $until) : null,'cooldown_until_sql'=>$until !== false ? date('Y-m-d H:i:s', $until) : null,'cooldown_seconds'=>$remaining];
    }
    $seconds = mg_sc_auto_cooldown_seconds($zone);
    $until = time() + $seconds;
    return ['cooldown_policy'=>$policy,'cooldown_started'=>true,'cooldown_locked'=>false,'cooldown_remaining_seconds'=>$seconds,'cooldown_until'=>date('c', $until),'cooldown_until_sql'=>date('Y-m-d H:i:s', $until),'cooldown_seconds'=>$seconds];
}

function mg_sc_auto_delivery_state_from_row(array $row, string $policy): array
{
    $cooldownUntil = trim((string)($row['cooldown_until'] ?? ''));
    $cooldownAt = $cooldownUntil !== '' ? strtotime($cooldownUntil) : false;
    $remaining = $cooldownAt !== false ? max(1, $cooldownAt - time()) : 0;
    if ($policy === 'once_per_visit') $remaining = 86400;
    if ($remaining < 1 && (string)($row['status'] ?? '') === 'pending') $remaining = 60;
    return [
        'active'=>true,
        'cooldown'=>true,
        'cooldown_policy'=>$policy,
        'cooldown_locked'=>$policy === 'once_per_visit',
        'cooldown_remaining_seconds'=>$remaining,
        'cooldown_until'=>$cooldownAt !== false ? date('c', $cooldownAt) : null,
        'last_triggered_at'=>(string)($row['fired_at'] ?: $row['created_at'] ?: ''),
        'delivery_id'=>(string)($row['public_id'] ?? ''),
        'delivery_status'=>(string)($row['status'] ?? ''),
        'message_sent'=>!empty($row['message_sent']),
        'reward_sent'=>!empty($row['reward_sent']),
        'fallback_applied'=>!empty($row['fallback_applied']),
    ];
}

function mg_sc_auto_cooldown_state(PDO $pdo, array $session, array $zone): array
{
    $policy = (string)($zone['cooldown_policy'] ?? 'fifteen_minutes');
    $zoneId = (string)($zone['public_id'] ?? '');
    $empty = ['active'=>false,'cooldown'=>false,'cooldown_policy'=>$policy,'cooldown_locked'=>false,'cooldown_remaining_seconds'=>0,'cooldown_until'=>null,'last_triggered_at'=>null];
    if ($zoneId === '') return $empty;

    try {
        if ($policy === 'once_per_visit') {
            $stmt = $pdo->prepare("SELECT * FROM mg_store_trigger_deliveries WHERE store_session_id=? AND trigger_zone_public_id=? AND status IN ('pending','fired','skipped','failed') ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([(int)$session['id'], $zoneId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? mg_sc_auto_delivery_state_from_row($row, $policy) : $empty;
        }

        if ($policy === 'once_per_customer_day') {
            $stmt = $pdo->prepare("SELECT * FROM mg_store_trigger_deliveries WHERE merchant_user_id=? AND customer_user_id=? AND trigger_zone_public_id=? AND status IN ('pending','fired','skipped','failed') AND (cooldown_until > NOW() OR fired_at >= CURDATE() OR created_at >= CURDATE()) ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([(int)$session['merchant_user_id'], (int)$session['customer_user_id'], $zoneId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? mg_sc_auto_delivery_state_from_row($row, $policy) : $empty;
        }

        $stmt = $pdo->prepare("SELECT * FROM mg_store_trigger_deliveries WHERE store_session_id=? AND trigger_zone_public_id=? AND status IN ('pending','fired','skipped','failed') AND cooldown_until > NOW() ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([(int)$session['id'], $zoneId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? mg_sc_auto_delivery_state_from_row($row, $policy) : $empty;
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant_canvas.auto_cooldown_check_failed', 'Store Canvas trigger delivery cooldown check failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], (int)($session['merchant_user_id'] ?? 0));
        return $empty;
    }
}

function mg_sc_auto_idempotency_key(int $merchantUserId, array $session, array $zone, string $campaignId, string $action): string
{
    $policy = (string)($zone['cooldown_policy'] ?? 'fifteen_minutes');
    $zoneId = (string)($zone['public_id'] ?? '');
    if ($policy === 'once_per_visit') {
        $window = 'visit:' . (string)($session['public_id'] ?? $session['id']);
    } elseif ($policy === 'once_per_customer_day') {
        $window = 'day:' . date('Ymd');
    } else {
        $seconds = mg_sc_auto_cooldown_seconds($zone);
        $window = 'bucket:' . (string)floor(time() / max(60, $seconds));
    }
    return 'store-canvas-trigger:' . hash('sha256', $merchantUserId . '|' . (int)$session['customer_user_id'] . '|' . (int)$session['id'] . '|' . $zoneId . '|' . $campaignId . '|' . $action . '|' . $policy . '|' . $window);
}

function mg_sc_auto_load_delivery_by_key(PDO $pdo, string $idempotencyKey): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM mg_store_trigger_deliveries WHERE idempotency_key=? LIMIT 1');
    $stmt->execute([$idempotencyKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_sc_auto_create_delivery(PDO $pdo, array $session, array $zone, string $campaignId, string $campaignTitle, ?array $template, string $action, string $eventKey, string $idempotencyKey, array $cooldownWindow): array
{
    $publicId = mg_public_uuid();
    $metadata = [
        'event_key'=>$eventKey,
        'source_system'=>'store_canvas',
        'source_channel'=>'merchant_canvas_automation',
    ];
    try {
        $stmt = $pdo->prepare("INSERT INTO mg_store_trigger_deliveries (
            public_id,merchant_user_id,customer_user_id,store_session_id,store_session_public_id,
            trigger_zone_id,trigger_zone_public_id,trigger_key,trigger_label,trigger_priority,
            campaign_public_id,campaign_title,reward_template_id,automation_action,cooldown_policy,cooldown_seconds,cooldown_until,
            idempotency_key,status,crm_segment_name,metadata_json,created_at,updated_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
        $stmt->execute([
            $publicId,
            (int)$session['merchant_user_id'],
            (int)$session['customer_user_id'],
            (int)$session['id'],
            (string)$session['public_id'],
            (int)($zone['id'] ?? 0) ?: null,
            (string)$zone['public_id'],
            (string)($zone['trigger_key'] ?? 'store_canvas_zone'),
            (string)($zone['name'] ?? 'IN/OUT Box Trigger'),
            max(1, min(5, (int)($zone['priority'] ?? 3))),
            $campaignId !== '' ? $campaignId : null,
            $campaignTitle !== '' ? $campaignTitle : null,
            is_array($template) ? (string)($template['id'] ?? '') : null,
            $action,
            (string)($zone['cooldown_policy'] ?? 'fifteen_minutes'),
            (int)($cooldownWindow['cooldown_seconds'] ?? 0),
            $cooldownWindow['cooldown_until_sql'] ?? null,
            $idempotencyKey,
            'pending',
            trim((string)($zone['crm_segment_name'] ?? '')) ?: null,
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        return ['created'=>true,'row'=>mg_sc_auto_load_delivery_by_key($pdo, $idempotencyKey) ?: ['public_id'=>$publicId,'id'=>(int)$pdo->lastInsertId(),'status'=>'pending']];
    } catch (PDOException $error) {
        if ((string)$error->getCode() === '23000') {
            $existing = mg_sc_auto_load_delivery_by_key($pdo, $idempotencyKey);
            if ($existing) return ['created'=>false,'row'=>$existing];
        }
        throw $error;
    }
}

function mg_sc_auto_update_delivery(PDO $pdo, int $deliveryId, array $fields): void
{
    if ($deliveryId < 1 || !$fields) return;
    $allowed = [
        'status','fallback_applied','skipped_reason','notify_merchant','follow_up_created','crm_segment_added','crm_segment_name','analytics_only',
        'message_sent','reward_sent','stamp_debited','stamp_ledger_entry_id','stamp_action_key','stamp_debit_error','message_id','wallet_item_id',
        'message_error','reward_error','campaign_public_id','campaign_title','reward_template_id','metadata_json','fired_at'
    ];
    $sets = [];
    $params = [];
    foreach ($fields as $key => $value) {
        if (!in_array($key, $allowed, true)) continue;
        $sets[] = $key . '=?';
        if ($key === 'metadata_json' && is_array($value)) $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $params[] = $value;
    }
    if (!$sets) return;
    $sets[] = 'updated_at=NOW()';
    $params[] = $deliveryId;
    $stmt = $pdo->prepare('UPDATE mg_store_trigger_deliveries SET ' . implode(',', $sets) . ' WHERE id=?');
    $stmt->execute($params);
}

function mg_sc_auto_stamp_preview(PDO $pdo, int $merchantUserId): array
{
    $preview = ['ready'=>false,'cost'=>1,'balance'=>0,'can_debit'=>false,'error'=>'Stamp ledger unavailable.'];
    try {
        foreach (['stamp_debit_actions','account_stamp_balances','stamp_ledger_entries'] as $table) if (!mg_store_canvas_table_exists($pdo, $table)) return $preview;
        $action = $pdo->query("SELECT stamp_value FROM stamp_debit_actions WHERE action_key='store_canvas_auto_message_send' AND status='active' LIMIT 1")->fetchColumn();
        $preview['cost'] = max(1, (int)($action ?: 1));
        $period = date('Y-m');
        $stmt = $pdo->prepare('SELECT balance FROM account_stamp_balances WHERE account_user_id=? AND current_period_key=? LIMIT 1');
        $stmt->execute([$merchantUserId, $period]);
        $preview['balance'] = (int)($stmt->fetchColumn() ?: 0);
        $preview['ready'] = true;
        $preview['can_debit'] = $preview['balance'] >= $preview['cost'];
        $preview['error'] = $preview['can_debit'] ? '' : 'Insufficient Stamps for automated message.';
    } catch (Throwable) {}
    return $preview;
}

function mg_sc_auto_debit_stamp(PDO $pdo, int $merchantUserId, string $eventKey, string $zoneId, string $triggerLabel, array $metadata): array
{
    try {
        require_once dirname(__DIR__) . '/stamps/_stamps.php';
        $result = mg_stamp_debit_send($pdo, $merchantUserId, $merchantUserId, 'store_canvas_auto_message_send', $eventKey . ':message', [
            'source_type'=>'store_canvas_trigger_message',
            'source_id'=>$zoneId,
            'reference'=>$eventKey,
            'reason_code'=>'store_canvas_trigger_automated_message',
            'note'=>'Automated Store Canvas trigger message: ' . $triggerLabel,
            'metadata'=>$metadata,
        ]);
        return ['debited'=>true,'entry_id'=>(string)($result['entry']['entry_id'] ?? ''),'result'=>$result];
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant_canvas.auto_stamp_debit_failed', 'Store Canvas automation stamp debit failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], $merchantUserId);
        return ['debited'=>false,'error'=>'Stamp debit failed.'];
    }
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

function mg_sc_auto_log_fire(PDO $pdo, array $session, string $triggerLabel, array $metadata): void
{
    $zoneId = (string)($metadata['trigger_zone_id'] ?? '');
    if ($zoneId !== '') mg_canvas_trigger_zone_touch($pdo, (int)$session['merchant_user_id'], $zoneId);
    mg_store_log_event($pdo, $session, 'campaign_trigger_zone', $triggerLabel, $metadata);
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
    $cooldown = mg_sc_auto_cooldown_state($pdo, $session, $zone);
    if (!empty($cooldown['active'])) {
        mg_ok(array_merge(['triggered'=>false,'trigger_zone_id'=>$zoneId,'automation_action'=>$action], $cooldown), 'Trigger cooldown active.');
        return;
    }

    $sendMessage = in_array($action, ['message_and_reward','message_only'], true);
    $sendReward = in_array($action, ['message_and_reward','reward_only'], true);
    $followUp = $action === 'follow_up';
    $crmSegment = $action === 'crm_segment';
    $notifyOnly = $action === 'notify_only';
    $analyticsOnly = $action === 'analytics_only';
    $fallbackApplied = false;
    $stampDebit = ['debited'=>false,'error'=>'No automated message configured.'];
    $messageResult = null;
    $rewardResult = null;
    $campaign = null;
    $template = null;
    $messageError = '';
    $rewardError = '';

    if ($campaignId !== '') {
        try {
            $options = mg_store_reward_options($pdo, $merchantUserId);
            foreach ((array)($options['campaigns'] ?? []) as $candidate) if (is_array($candidate) && (string)($candidate['id'] ?? '') === $campaignId) { $campaign = $candidate; break; }
            if (is_array($campaign)) $template = mg_sc_auto_pick_template($campaign, (array)($options['templates'] ?? []));
        } catch (Throwable $error) {
            mg_security_log('warning', 'merchant_canvas.auto_campaign_lookup_failed', 'Store Canvas trigger campaign lookup failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage(),'trigger_zone_id'=>$zoneId], $merchantUserId);
        }
    }
    $campaignTitle = is_array($campaign) ? (string)($campaign['title'] ?? '') : (string)($zone['campaign_title'] ?? '');
    if ($sendReward && (!is_array($campaign) || !is_array($template) || empty($campaign['id']) || empty($template['id']))) {
        $rewardError = 'No active reward campaign/template assigned.';
    }

    $cooldownWindow = mg_sc_auto_cooldown_window($zone);
    $idempotencyKey = mg_sc_auto_idempotency_key($merchantUserId, $session, $zone, $campaignId, $action);
    $deliveryResult = mg_sc_auto_create_delivery($pdo, $session, $zone, $campaignId, $campaignTitle, $template, $action, $eventKey, $idempotencyKey, $cooldownWindow);
    $delivery = is_array($deliveryResult['row'] ?? null) ? $deliveryResult['row'] : [];
    if (empty($deliveryResult['created'])) {
        mg_ok(array_merge(['triggered'=>false,'duplicate'=>true,'trigger_zone_id'=>$zoneId,'automation_action'=>$action], mg_sc_auto_delivery_state_from_row($delivery, (string)($zone['cooldown_policy'] ?? 'fifteen_minutes'))), 'Trigger delivery already exists for this cooldown window.');
        return;
    }
    $deliveryId = (int)($delivery['id'] ?? 0);
    $deliveryPublicId = (string)($delivery['public_id'] ?? '');

    if ($sendMessage) {
        $preview = mg_sc_auto_stamp_preview($pdo, $merchantUserId);
        if (empty($preview['can_debit'])) {
            $fallbackApplied = true;
            $fallback = (string)($zone['fallback_action'] ?? 'notify_only');
            $stampDebit = ['debited'=>false,'error'=>(string)($preview['error'] ?? 'Insufficient Stamps.')];
            if ($fallback === 'skip') {
                $metadata = [
                    'delivery_id'=>$deliveryPublicId,
                    'trigger_key'=>$triggerKey,
                    'trigger_zone_id'=>$zoneId,
                    'trigger_priority'=>$priority,
                    'automation_action'=>$action,
                    'cooldown_policy'=>(string)($zone['cooldown_policy'] ?? 'fifteen_minutes'),
                    'event_key'=>$eventKey,
                    'selected_campaign_id'=>$campaignId ?: null,
                    'fallback_applied'=>true,
                    'skipped'=>true,
                    'skip_reason'=>'stamp_unavailable',
                    'notify_merchant'=>false,
                    'follow_up_created'=>false,
                    'crm_segment_added'=>false,
                    'crm_segment_name'=>(string)($zone['crm_segment_name'] ?? ''),
                    'analytics_only'=>false,
                    'source_system'=>'store_canvas',
                    'source_channel'=>'merchant_canvas_automation',
                    'message_sent'=>false,
                    'reward_sent'=>false,
                    'stamp_debited'=>false,
                    'stamp_action_key'=>'store_canvas_auto_message_send',
                    'stamp_debit_error'=>$stampDebit['error'],
                    'campaign_id'=>$campaignId ?: null,
                    'campaign_title'=>$campaignTitle ?: null,
                ];
                mg_sc_auto_update_delivery($pdo, $deliveryId, [
                    'status'=>'skipped',
                    'fallback_applied'=>1,
                    'skipped_reason'=>'stamp_unavailable',
                    'notify_merchant'=>0,
                    'message_sent'=>0,
                    'reward_sent'=>0,
                    'stamp_debited'=>0,
                    'stamp_action_key'=>'store_canvas_auto_message_send',
                    'stamp_debit_error'=>$stampDebit['error'],
                    'metadata_json'=>$metadata,
                    'fired_at'=>date('Y-m-d H:i:s'),
                ]);
                mg_sc_auto_log_fire($pdo, $session, $triggerLabel, $metadata);
                mg_ok(array_merge(['triggered'=>false,'skipped'=>true,'fallback_applied'=>true,'trigger_zone_id'=>$zoneId,'delivery_id'=>$deliveryPublicId,'automation_action'=>$action,'stamp_debit_error'=>$stampDebit['error']], $cooldownWindow), 'Trigger skipped because automated message Stamps are unavailable.');
                return;
            }
            $sendMessage = false;
            $sendReward = false;
            $rewardError = '';
            $notifyOnly = $fallback === 'notify_only';
            $analyticsOnly = $fallback === 'analytics_only';
        }
    }

    if ($sendMessage) {
        try {
            $customerName = trim((string)($session['customer_name'] ?? '')) ?: 'there';
            $firstName = preg_split('/\s+/u', $customerName, -1, PREG_SPLIT_NO_EMPTY)[0] ?? $customerName;
            $messageBody = mg_sc_auto_render_message((string)($zone['auto_message_text'] ?? ''), $firstName, $triggerLabel, $campaignTitle);
            $messageResult = mg_store_send_direct_message_via_messaging($pdo, $merchantUserId, $sessionId, $messageBody);
            $stampDebit = mg_sc_auto_debit_stamp($pdo, $merchantUserId, $eventKey, $zoneId, $triggerLabel, ['delivery_id'=>$deliveryPublicId,'trigger_zone_id'=>$zoneId,'trigger_key'=>$triggerKey,'trigger_priority'=>$priority,'store_session_id'=>$sessionId,'campaign_id'=>$campaignId ?: null,'message_id'=>is_array($messageResult) ? ($messageResult['id'] ?? null) : null,'automation_action'=>$action,'source_system'=>'store_canvas']);
        } catch (Throwable $error) {
            $messageError = 'Automated message failed.';
            $stampDebit = ['debited'=>false,'error'=>'Automated message failed before stamp debit.'];
            mg_security_log('error', 'merchant_canvas.auto_message_failed', 'Store Canvas automation message failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage(),'trigger_zone_id'=>$zoneId,'delivery_id'=>$deliveryPublicId], $merchantUserId);
        }
    }

    if ($sendReward && $rewardError === '') {
        try {
            $rewardResult = mg_store_reward_issue($pdo, $user, $sessionId, (string)$campaign['id'], (string)$template['id'], 'Triggered by ' . $triggerLabel . ' on the Merchant Store Canvas.', null, null, 'canvas-trigger-auto:' . hash('sha256', $merchantUserId . '|' . $sessionId . '|' . $zoneId . '|' . (string)$campaign['id'] . '|' . $deliveryPublicId));
        } catch (Throwable $error) {
            $rewardError = 'Automated reward failed.';
            mg_security_log('error', 'merchant_canvas.auto_reward_failed', 'Store Canvas automation reward failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage(),'trigger_zone_id'=>$zoneId,'campaign_id'=>$campaignId,'delivery_id'=>$deliveryPublicId], $merchantUserId);
        }
    }

    $messageSent = is_array($messageResult);
    $rewardSent = is_array($rewardResult);
    $hadRequestedDelivery = $sendMessage || $sendReward;
    $allRequestedDeliveryFailed = $hadRequestedDelivery && !$messageSent && !$rewardSent && ($messageError !== '' || $rewardError !== '' || !empty($stampDebit['error']));
    $status = $allRequestedDeliveryFailed ? 'failed' : 'fired';

    $metadata = [
        'delivery_id'=>$deliveryPublicId,
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
        'message_sent'=>$messageSent,
        'reward_sent'=>$rewardSent,
        'stamp_debited'=>!empty($stampDebit['debited']),
        'stamp_ledger_entry_id'=>(string)($stampDebit['entry_id'] ?? ''),
        'stamp_action_key'=>'store_canvas_auto_message_send',
        'stamp_debit_error'=>(string)($stampDebit['error'] ?? ''),
        'message_error'=>$messageError ?: null,
        'reward_error'=>$rewardError ?: null,
        'message_id'=>$messageSent ? ($messageResult['id'] ?? null) : null,
        'wallet_item_id'=>$rewardSent ? ($rewardResult['wallet_item_id'] ?? null) : null,
        'campaign_id'=>$rewardSent ? ($rewardResult['campaign_id'] ?? null) : ($campaign['id'] ?? ($campaignId ?: null)),
        'campaign_title'=>is_array($campaign) ? ($campaign['title'] ?? null) : ($campaignTitle ?: null),
        'reward_template_id'=>$rewardSent ? ($rewardResult['reward_template_id'] ?? null) : ($template['id'] ?? null),
    ];

    mg_sc_auto_update_delivery($pdo, $deliveryId, [
        'status'=>$status,
        'fallback_applied'=>$fallbackApplied ? 1 : 0,
        'notify_merchant'=>($notifyOnly || !empty($zone['notify_merchant'])) ? 1 : 0,
        'follow_up_created'=>$followUp ? 1 : 0,
        'crm_segment_added'=>$crmSegment ? 1 : 0,
        'crm_segment_name'=>trim((string)($zone['crm_segment_name'] ?? '')) ?: null,
        'analytics_only'=>$analyticsOnly ? 1 : 0,
        'message_sent'=>$messageSent ? 1 : 0,
        'reward_sent'=>$rewardSent ? 1 : 0,
        'stamp_debited'=>!empty($stampDebit['debited']) ? 1 : 0,
        'stamp_ledger_entry_id'=>(string)($stampDebit['entry_id'] ?? '') ?: null,
        'stamp_action_key'=>'store_canvas_auto_message_send',
        'stamp_debit_error'=>(string)($stampDebit['error'] ?? '') ?: null,
        'message_id'=>$messageSent ? (string)($messageResult['id'] ?? '') : null,
        'wallet_item_id'=>$rewardSent ? (string)($rewardResult['wallet_item_id'] ?? '') : null,
        'message_error'=>$messageError ?: null,
        'reward_error'=>$rewardError ?: null,
        'campaign_public_id'=>is_array($campaign) ? (string)($campaign['id'] ?? $campaignId) : ($campaignId ?: null),
        'campaign_title'=>is_array($campaign) ? (string)($campaign['title'] ?? '') : ($campaignTitle ?: null),
        'reward_template_id'=>is_array($template) ? (string)($template['id'] ?? '') : null,
        'metadata_json'=>$metadata,
        'fired_at'=>date('Y-m-d H:i:s'),
    ]);
    mg_sc_auto_log_fire($pdo, $session, $triggerLabel, $metadata);

    mg_ok(array_merge(['triggered'=>true,'trigger_zone_id'=>$zoneId,'delivery_id'=>$deliveryPublicId,'delivery_status'=>$status,'priority'=>$priority,'automation_action'=>$action,'fallback_applied'=>$fallbackApplied,'message_sent'=>$messageSent,'reward_sent'=>$rewardSent,'notify_merchant'=>$notifyOnly,'follow_up_created'=>$followUp,'crm_segment_added'=>$crmSegment,'analytics_only'=>$analyticsOnly,'stamp_debited'=>!empty($stampDebit['debited']),'stamp_ledger_entry_id'=>(string)($stampDebit['entry_id'] ?? ''),'stamp_debit_error'=>(string)($stampDebit['error'] ?? ''),'message_error'=>$messageError,'reward_error'=>$rewardError,'campaign_id'=>is_array($campaign) ? ($campaign['id'] ?? null) : null,'campaign_title'=>is_array($campaign) ? ($campaign['title'] ?? null) : null], $cooldownWindow), 'Automation trigger fired.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.campaign_trigger_automation_failed', 'Store Canvas automation trigger failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], (int)$user['id']);
    mg_fail('Unable to fire automation trigger.', 500);
}
