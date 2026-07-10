<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_trigger_engine_runner.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
$pdo = mg_db();
$merchantUserId = (int)$user['id'];

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

try {
    if ($method === 'GET') {
        mg_rate_limit('merchant_canvas.trigger_engine.read', 'user:' . $merchantUserId, 180, 60);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok(mg_store_trigger_engine_payload($pdo, $merchantUserId), 'Store Canvas trigger engine loaded.');
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);
    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_rate_limit('merchant_canvas.trigger_engine.write', 'user:' . $merchantUserId, 30, 300);
    $action = strtolower(trim((string)($input['action'] ?? '')));

    if (!mg_store_trigger_engine_schema_ready($pdo)) {
        mg_fail('Store Canvas trigger engine setup is incomplete. Import database/store_canvas_server_trigger_engine_v1.sql.', 503, [
            'missing_tables' => mg_store_trigger_engine_missing_tables($pdo),
        ]);
    }

    if ($action === 'save_settings') {
        $settings = mg_store_trigger_engine_update_settings($pdo, $merchantUserId, $input);
        mg_audit('merchant.store_trigger_engine_settings_saved', 'store_trigger_engine', [
            'execution_mode'=>$settings['execution_mode'],
            'max_notifications_per_run'=>$settings['max_notifications_per_run'],
            'default_cooldown_seconds'=>$settings['default_cooldown_seconds'],
            'browser_overlap_execution'=>false,
            'reward_issue'=>false,
        ], $merchantUserId);
        mg_ok(['settings'=>$settings,'payload'=>mg_store_trigger_engine_payload($pdo,$merchantUserId)], 'Trigger engine settings saved.');
    }

    if ($action === 'save_rule') {
        $rule = mg_store_trigger_engine_save_rule($pdo, $merchantUserId, $input);
        mg_audit('merchant.store_trigger_engine_rule_saved', 'store_trigger_engine_rule', [
            'rule_id'=>$rule['id'],'event_type'=>$rule['event_type'],'campaign_id'=>$rule['campaign_id'],'status'=>$rule['status'],
            'delivery'=>'notification_only','browser_overlap_execution'=>false,'reward_issue'=>false,
        ], $merchantUserId);
        mg_ok(['rule'=>$rule,'payload'=>mg_store_trigger_engine_payload($pdo,$merchantUserId)], 'Trigger rule saved.', 201);
    }

    if ($action === 'archive_rule') {
        $ruleId = trim((string)($input['rule_id'] ?? ''));
        mg_store_trigger_engine_archive_rule($pdo, $merchantUserId, $ruleId);
        mg_audit('merchant.store_trigger_engine_rule_archived', 'store_trigger_engine_rule', ['rule_id'=>$ruleId], $merchantUserId);
        mg_ok(['payload'=>mg_store_trigger_engine_payload($pdo,$merchantUserId)], 'Trigger rule archived.');
    }

    if ($action === 'preview') {
        $run = mg_store_trigger_engine_run_authorized($pdo, $user, true);
        mg_audit('merchant.store_trigger_engine_previewed', 'store_trigger_engine', [
            'mode'=>'dry_run','summary'=>$run['summary'],'notification_delivery'=>false,'reward_issue'=>false,
            'preview_consumes_notification'=>false,
        ], $merchantUserId);
        mg_ok(['run'=>$run,'payload'=>mg_store_trigger_engine_payload($pdo,$merchantUserId)], 'Dry-run evaluation completed.');
    }

    if ($action === 'run') {
        $settings = mg_store_trigger_engine_settings_public(mg_store_trigger_engine_settings($pdo,$merchantUserId,true));
        if ($settings['execution_mode'] === 'notification' && empty($input['confirm_notification_delivery'])) {
            mg_fail('Confirm notification delivery before running the engine in Notification mode.', 422);
        }
        $run = mg_store_trigger_engine_run_authorized($pdo, $user, false);
        mg_audit('merchant.store_trigger_engine_ran', 'store_trigger_engine', [
            'mode'=>$run['summary']['mode'] ?? $settings['execution_mode'],'summary'=>$run['summary'],
            'notification_delivery'=>($run['summary']['mode'] ?? '') === 'notification','reward_issue'=>false,
            'preview_consumes_notification'=>false,
        ], $merchantUserId);
        mg_ok(['run'=>$run,'payload'=>mg_store_trigger_engine_payload($pdo,$merchantUserId)], 'Server trigger evaluation completed.');
    }

    mg_fail('Invalid trigger engine action.', 422);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_security_log('warning','merchant_canvas.trigger_engine_blocked','Store Canvas trigger engine request was blocked.',[
        'action'=>$action ?? $method,'exception_class'=>$error::class,'message'=>$error->getMessage(),
    ],$merchantUserId);
    mg_fail($error->getMessage(), 409);
} catch (Throwable $error) {
    mg_security_log('error','merchant_canvas.trigger_engine_failed','Store Canvas trigger engine request failed.',[
        'action'=>$action ?? $method,'exception_class'=>$error::class,'message'=>$error->getMessage(),
    ],$merchantUserId);
    mg_fail('Unable to process the Store Canvas trigger engine request.', 500);
}
