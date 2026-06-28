<?php
declare(strict_types=1);

require_once __DIR__ . '/_trigger_zones.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) mg_fail('Merchant access required.', 403);

function mg_canvas_auto_save_column_exists(PDO $pdo, string $column): bool
{
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $stmt->execute(['mg_store_trigger_zones', $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) { return false; }
}

function mg_canvas_auto_value(string $value, array $allowed, string $default): string
{
    return in_array($value, $allowed, true) ? $value : $default;
}

try {
    mg_rate_limit('merchant_canvas.trigger_automation_save', 'user:' . (int)$user['id'], 120, 60);
    if (!mg_canvas_trigger_zone_schema_ready($pdo) || !mg_canvas_auto_save_column_exists($pdo, 'automation_action')) {
        throw new RuntimeException('Store Canvas automation rule migration is required.');
    }
    $zoneId = mg_store_safe_public_id($input['id'] ?? '', 'Trigger zone');
    $automationAction = mg_canvas_auto_value(trim((string)($input['automation_action'] ?? 'message_and_reward')), ['message_and_reward','message_only','reward_only','notify_only','follow_up','crm_segment','analytics_only'], 'message_and_reward');
    $cooldownPolicy = mg_canvas_auto_value(trim((string)($input['cooldown_policy'] ?? 'fifteen_minutes')), ['five_minutes','fifteen_minutes','one_hour','once_per_visit','once_per_customer_day'], 'fifteen_minutes');
    $cooldownSeconds = match ($cooldownPolicy) {
        'five_minutes' => 300,
        'one_hour' => 3600,
        'once_per_visit' => 0,
        'once_per_customer_day' => 86400,
        default => 900,
    };
    $fallbackAction = mg_canvas_auto_value(trim((string)($input['fallback_action'] ?? 'notify_only')), ['notify_only','analytics_only','skip'], 'notify_only');
    $autoMessageText = trim((string)($input['auto_message_text'] ?? ''));
    if ($autoMessageText === '') $autoMessageText = null;
    if ($autoMessageText !== null && mb_strlen($autoMessageText) > 1000) $autoMessageText = mb_substr($autoMessageText, 0, 1000);
    $crmSegmentName = trim((string)($input['crm_segment_name'] ?? ''));
    if ($crmSegmentName === '') $crmSegmentName = null;
    if ($crmSegmentName !== null && mb_strlen($crmSegmentName) > 160) $crmSegmentName = mb_substr($crmSegmentName, 0, 160);
    $notifyMerchant = !empty($input['notify_merchant']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE mg_store_trigger_zones SET automation_action=?,cooldown_policy=?,cooldown_seconds=?,auto_message_text=?,fallback_action=?,crm_segment_name=?,notify_merchant=?,updated_at=NOW() WHERE public_id=? AND merchant_user_id=? AND status<>'archived'");
    $stmt->execute([$automationAction,$cooldownPolicy,$cooldownSeconds,$autoMessageText,$fallbackAction,$crmSegmentName,$notifyMerchant,$zoneId,(int)$user['id']]);
    if ($stmt->rowCount() < 1) throw new RuntimeException('Trigger zone is not available.');
    mg_ok(['id'=>$zoneId,'automation_action'=>$automationAction,'cooldown_policy'=>$cooldownPolicy,'cooldown_seconds'=>$cooldownSeconds,'fallback_action'=>$fallbackAction,'crm_segment_name'=>$crmSegmentName ?: '','notify_merchant'=>$notifyMerchant === 1], 'Automation settings saved.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.trigger_automation_save_failed', 'Store Canvas automation save failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to save automation settings.', 500);
}
