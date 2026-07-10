<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$communications = file_get_contents($root . '/api/communications/_communications.php') ?: '';
$liveAcceptance = file_get_contents($root . '/scripts/validate_message_delivery_bridges.php') ?: '';
$schema = file_get_contents($root . '/database/stage_5h_notifications_messaging_alerts.sql') ?: '';

$checks = [
    'public notification signature preserved' => str_contains($communications, 'function mg_create_notification(PDO $pdo, int $userId, string $type, string $title, ?string $body = null, ?string $actionUrl = null, array $context = []): string'),
    'queue helper signature preserved' => str_contains($communications, 'function mg_queue_notification_deliveries(PDO $pdo, int $notificationId, int $userId, string $type, ?array $preference = null, bool $refreshExternal = false, array $context = []): void'),
    'in app remains available' => str_contains($communications, "if (\$channel === 'in_app') return true;") && str_contains($communications, "'in_app_enabled' => 1"),
    'external channels fail closed' => str_contains($communications, "'email_enabled' => 0") && str_contains($communications, "'sms_enabled' => 0") && str_contains($communications, "'push_enabled' => 0"),
    'new foundation detected safely' => str_contains($communications, 'mg_notification_delivery_foundation_ready') && str_contains($communications, "column_name IN ('job_key','max_attempts','source_type','metadata_json')"),
    'legacy schema fallback retained' => str_contains($communications, 'INSERT INTO notification_delivery_jobs (public_id,notification_id,user_id,channel,status,next_attempt_at,sent_at,delivered_at,created_at,updated_at)'),
    'legacy status supports delivered' => str_contains($schema, "status ENUM('queued','processing','sent','delivered','failed','cancelled','suppressed')"),
    'event aggregation remains idempotent' => str_contains($communications, 'ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)') && str_contains($communications, 'occurrence_count=occurrence_count+1'),
    'message bridge still calls notification authority' => str_contains($liveAcceptance, 'mg_create_notification(') && str_contains($liveAcceptance, 'mg_message_delivery_validate('),
    'live acceptance retained for deployment' => str_contains($liveAcceptance, "in_array('--commit', \$argv, true)") && str_contains($liveAcceptance, "'mode' => \$commitMode ? 'committed' : 'rolled_back'"),
];

$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) $failed[] = $name;
}
if ($failed !== []) {
    fwrite(STDERR, 'Delivery communications regression failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo "Delivery communications compatibility regression passed.\n";
