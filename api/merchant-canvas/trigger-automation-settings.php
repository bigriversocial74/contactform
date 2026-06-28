<?php
declare(strict_types=1);

require_once __DIR__ . '/_trigger_zones.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) mg_fail('Merchant access required.', 403);

function mg_canvas_auto_column_exists(PDO $pdo, string $column): bool
{
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $stmt->execute(['mg_store_trigger_zones', $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) { return false; }
}

function mg_canvas_auto_stamp_preview(PDO $pdo, int $merchantUserId): array
{
    $preview = ['schema_ready'=>false,'action_key'=>'store_canvas_auto_message_send','stamp_cost'=>1,'balance'=>0,'can_auto_message'=>false,'message'=>'Stamp ledger is not ready.'];
    try {
        foreach (['stamp_debit_actions','account_stamp_balances','stamp_ledger_entries'] as $table) {
            if (!mg_store_canvas_table_exists($pdo, $table)) return $preview;
        }
        $preview['schema_ready'] = true;
        $stmt = $pdo->prepare("SELECT stamp_value,label FROM stamp_debit_actions WHERE action_key='store_canvas_auto_message_send' AND status='active' LIMIT 1");
        $stmt->execute();
        $action = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $preview['stamp_cost'] = max(1, (int)($action['stamp_value'] ?? 1));
        $preview['label'] = (string)($action['label'] ?? 'Store Canvas automated message');
        $period = date('Y-m');
        $balance = $pdo->prepare('SELECT balance,current_period_key FROM account_stamp_balances WHERE account_user_id=? AND current_period_key=? LIMIT 1');
        $balance->execute([$merchantUserId, $period]);
        $row = $balance->fetch(PDO::FETCH_ASSOC) ?: [];
        $preview['balance'] = (int)($row['balance'] ?? 0);
        $preview['period'] = (string)($row['current_period_key'] ?? $period);
        $preview['can_auto_message'] = $preview['balance'] >= $preview['stamp_cost'];
        $preview['message'] = $preview['can_auto_message'] ? 'Automated messages are ready.' : 'Not enough Stamps for automated messages.';
    } catch (Throwable $error) {
        $preview['message'] = 'Stamp preview unavailable.';
    }
    return $preview;
}

try {
    mg_rate_limit('merchant_canvas.trigger_automation_settings', 'user:' . (int)$user['id'], 180, 60);
    $schemaReady = mg_canvas_trigger_zone_schema_ready($pdo) && mg_canvas_auto_column_exists($pdo, 'automation_action');
    $zones = [];
    if ($schemaReady) {
        $stmt = $pdo->prepare("SELECT public_id,automation_action,cooldown_policy,cooldown_seconds,auto_message_text,fallback_action,crm_segment_name,notify_merchant FROM mg_store_trigger_zones WHERE merchant_user_id=? AND status<>'archived' ORDER BY updated_at DESC,id DESC LIMIT 80");
        $stmt->execute([(int)$user['id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $zones[(string)$row['public_id']] = [
                'id'=>(string)$row['public_id'],
                'automation_action'=>(string)($row['automation_action'] ?? 'message_and_reward'),
                'cooldown_policy'=>(string)($row['cooldown_policy'] ?? 'fifteen_minutes'),
                'cooldown_seconds'=>(int)($row['cooldown_seconds'] ?? 900),
                'auto_message_text'=>(string)($row['auto_message_text'] ?? ''),
                'fallback_action'=>(string)($row['fallback_action'] ?? 'notify_only'),
                'crm_segment_name'=>(string)($row['crm_segment_name'] ?? ''),
                'notify_merchant'=>((int)($row['notify_merchant'] ?? 1)) === 1,
            ];
        }
    }
    mg_ok([
        'schema_ready'=>$schemaReady,
        'zones'=>$zones,
        'actions'=>['message_and_reward','message_only','reward_only','notify_only','follow_up','crm_segment','analytics_only'],
        'cooldowns'=>['five_minutes','fifteen_minutes','one_hour','once_per_visit','once_per_customer_day'],
        'fallbacks'=>['notify_only','analytics_only','skip'],
        'stamp_preview'=>mg_canvas_auto_stamp_preview($pdo, (int)$user['id']),
    ]);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.trigger_automation_settings_failed', 'Store Canvas automation settings failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to load automation settings.', 500);
}
