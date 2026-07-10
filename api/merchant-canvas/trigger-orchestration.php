<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_trigger_orchestration_rules.php';
require_once dirname(__DIR__) . '/store/_canvas_trigger_orchestration_runner.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
$pdo = mg_db();
$merchantUserId = (int)$user['id'];

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

function mg_trigger_orchestration_merchant_payload(PDO $pdo, int $merchantUserId): array
{
    $payload = mg_trigger_orchestration_payload($pdo, $merchantUserId);
    if (empty($payload['schema_ready'])) return $payload;
    $payload['campaigns'] = mg_store_trigger_engine_campaigns($pdo, $merchantUserId);
    $payload['rules'] = mg_store_trigger_engine_rules($pdo, $merchantUserId);
    $payload['zones'] = mg_canvas_trigger_zone_schema_ready($pdo) ? mg_canvas_trigger_zone_list($pdo, $merchantUserId) : [];
    return $payload;
}

try {
    if ($method === 'GET') {
        mg_rate_limit('merchant_canvas.trigger_orchestration.read', 'user:' . $merchantUserId, 180, 60);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok(mg_trigger_orchestration_merchant_payload($pdo, $merchantUserId), 'Trigger ingestion and orchestration loaded.');
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_rate_limit('merchant_canvas.trigger_orchestration.write', 'user:' . $merchantUserId, 40, 300);
    $action = strtolower(trim((string)($input['action'] ?? '')));

    if (!mg_trigger_orchestration_schema_ready($pdo)) {
        mg_fail('Trigger ingestion and orchestration setup is incomplete. Import database/trigger_event_ingestion_orchestration_v1.sql.', 503, [
            'missing_tables' => mg_trigger_orchestration_missing_tables($pdo),
        ]);
    }

    if ($action === 'save_settings') {
        $settings = mg_trigger_orchestration_update_settings($pdo, $merchantUserId, $input);
        mg_audit('merchant.trigger_orchestration_settings_saved', 'trigger_orchestration', [
            'emergency_pause'=>$settings['emergency_pause'],
            'ingestion_enabled'=>$settings['ingestion_enabled'],
            'scheduler_enabled'=>$settings['scheduler_enabled'],
            'timezone'=>$settings['timezone'],
            'quiet_hours_start'=>$settings['quiet_hours_start'],
            'quiet_hours_end'=>$settings['quiet_hours_end'],
            'notification_only'=>true,
            'reward_issue'=>false,
            'browser_overlap_execution'=>false,
        ], $merchantUserId);
        mg_ok(['settings'=>$settings,'payload'=>mg_trigger_orchestration_merchant_payload($pdo,$merchantUserId)], 'Orchestration settings saved.');
    }

    if ($action === 'save_policy_rule') {
        $saved = mg_trigger_orchestration_save_rule($pdo, $merchantUserId, $input);
        mg_audit('merchant.trigger_orchestration_policy_rule_saved', 'trigger_orchestration_rule', [
            'rule_id'=>$saved['rule']['id'] ?? null,
            'policy_id'=>$saved['policy']['id'] ?? null,
            'event_type'=>$saved['rule']['event_type'] ?? null,
            'campaign_id'=>$saved['rule']['campaign_id'] ?? null,
            'rule_status'=>$saved['rule']['status'] ?? null,
            'policy_status'=>$saved['policy']['status'] ?? null,
            'notification_only'=>true,
            'reward_issue'=>false,
            'direct_message'=>false,
        ], $merchantUserId);
        mg_ok(['saved'=>$saved,'payload'=>mg_trigger_orchestration_merchant_payload($pdo,$merchantUserId)], 'Orchestration policy and trigger rule saved.', 201);
    }

    if ($action === 'run_ingestion') {
        mg_rate_limit('merchant_canvas.trigger_orchestration.ingestion', 'user:' . $merchantUserId, 12, 300);
        $summary = mg_trigger_ingestion_run($pdo, $user, max(1,min(1000,(int)($input['limit_per_source'] ?? 250))));
        mg_audit('merchant.trigger_ingestion_ran', 'trigger_orchestration', [
            'summary'=>$summary,'notification_delivery'=>false,'reward_issue'=>false,
        ], $merchantUserId);
        mg_ok(['ingestion'=>$summary,'payload'=>mg_trigger_orchestration_merchant_payload($pdo,$merchantUserId)], 'Canonical event ingestion completed.');
    }

    if ($action === 'preview_queue') {
        mg_rate_limit('merchant_canvas.trigger_orchestration.preview', 'user:' . $merchantUserId, 12, 300);
        $summary = mg_trigger_orchestration_process_queue_authorized($pdo, $user, true, max(1,min(500,(int)($input['limit'] ?? 100))));
        mg_audit('merchant.trigger_orchestration_previewed', 'trigger_orchestration', [
            'summary'=>$summary,'mode'=>'dry_run','notification_delivery'=>false,'reward_issue'=>false,
            'preview_consumes_notification'=>false,
        ], $merchantUserId);
        mg_ok(['orchestration'=>$summary,'payload'=>mg_trigger_orchestration_merchant_payload($pdo,$merchantUserId)], 'Queue dry preview completed.');
    }

    if ($action === 'run_queue') {
        mg_rate_limit('merchant_canvas.trigger_orchestration.run', 'user:' . $merchantUserId, 12, 300);
        $settings = mg_trigger_orchestration_settings($pdo,$merchantUserId);
        if ($settings['execution_mode'] === 'notification' && empty($input['confirm_notification_delivery'])) {
            mg_fail('Confirm notification delivery before processing the orchestration queue in Notification mode.', 422);
        }
        $summary = mg_trigger_orchestration_process_queue_authorized($pdo, $user, false, max(1,min(500,(int)($input['limit'] ?? 100))));
        mg_audit('merchant.trigger_orchestration_ran', 'trigger_orchestration', [
            'summary'=>$summary,
            'mode'=>$summary['mode'] ?? $settings['execution_mode'],
            'notification_delivery'=>($summary['mode'] ?? '') === 'notification',
            'reward_issue'=>false,
        ], $merchantUserId);
        mg_ok(['orchestration'=>$summary,'payload'=>mg_trigger_orchestration_merchant_payload($pdo,$merchantUserId)], 'Orchestration queue processed.');
    }

    if ($action === 'run_full') {
        mg_rate_limit('merchant_canvas.trigger_orchestration.full', 'user:' . $merchantUserId, 8, 300);
        $settings = mg_trigger_orchestration_settings($pdo,$merchantUserId);
        if ($settings['execution_mode'] === 'notification' && empty($input['confirm_notification_delivery'])) {
            mg_fail('Confirm notification delivery before running ingestion and orchestration in Notification mode.', 422);
        }
        $ingestion = mg_trigger_ingestion_run($pdo, $user, max(1,min(1000,(int)($input['limit_per_source'] ?? 250))));
        $orchestration = null;
        if ($settings['execution_mode'] !== 'paused') {
            $orchestration = mg_trigger_orchestration_process_queue_authorized($pdo, $user, false, max(1,min(500,(int)($input['limit'] ?? 100))));
        }
        mg_audit('merchant.trigger_orchestration_full_run', 'trigger_orchestration', [
            'ingestion'=>$ingestion,'orchestration'=>$orchestration,
            'notification_delivery'=>is_array($orchestration) && (($orchestration['mode'] ?? '') === 'notification'),
            'reward_issue'=>false,
            'preview_consumes_notification'=>false,
        ], $merchantUserId);
        mg_ok(['ingestion'=>$ingestion,'orchestration'=>$orchestration,'payload'=>mg_trigger_orchestration_merchant_payload($pdo,$merchantUserId)], 'Trigger ingestion and orchestration run completed.');
    }

    if ($action === 'retry_event') {
        $eventId = trim((string)($input['event_id'] ?? ''));
        mg_trigger_orchestration_retry_event($pdo,$merchantUserId,$eventId);
        mg_audit('merchant.trigger_orchestration_event_requeued', 'trigger_event', [
            'event_id'=>$eventId,'reward_issue'=>false,'notification_delivery'=>false,
        ], $merchantUserId);
        mg_ok(['payload'=>mg_trigger_orchestration_merchant_payload($pdo,$merchantUserId)], 'Trigger event requeued.');
    }

    mg_fail('Invalid trigger orchestration action.', 422);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_security_log('warning','merchant_canvas.trigger_orchestration_blocked','Trigger ingestion or orchestration request was blocked.',[
        'action'=>$action ?? $method,'exception_class'=>$error::class,'message'=>$error->getMessage(),
    ],$merchantUserId);
    mg_fail($error->getMessage(), 409);
} catch (Throwable $error) {
    mg_security_log('error','merchant_canvas.trigger_orchestration_failed','Trigger ingestion or orchestration request failed.',[
        'action'=>$action ?? $method,'exception_class'=>$error::class,'message'=>$error->getMessage(),
    ],$merchantUserId);
    mg_fail('Unable to process the trigger ingestion and orchestration request.', 500);
}
