<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/api/store/_canvas_trigger_orchestration_rules.php';

$pdo = mg_db();
if (!mg_trigger_orchestration_schema_ready($pdo)) {
    fwrite(STDERR, 'Trigger ingestion and orchestration schema is incomplete: ' . implode(', ', mg_trigger_orchestration_missing_tables($pdo)) . PHP_EOL);
    exit(2);
}

$merchantLimit = 100;
$sourceLimit = 250;
$queueLimit = 100;
foreach ($argv as $argument) {
    if (preg_match('/^--merchant-limit=(\d+)$/', (string)$argument, $match)) {
        $merchantLimit = max(1, min(500, (int)$match[1]));
    } elseif (preg_match('/^--source-limit=(\d+)$/', (string)$argument, $match)) {
        $sourceLimit = max(1, min(1000, (int)$match[1]));
    } elseif (preg_match('/^--queue-limit=(\d+)$/', (string)$argument, $match)) {
        $queueLimit = max(1, min(500, (int)$match[1]));
    }
}

$stmt = $pdo->query("SELECT merchant_user_id FROM mg_store_trigger_engine_settings WHERE ingestion_enabled=1 AND scheduler_enabled=1 ORDER BY COALESCE(last_scheduler_heartbeat_at,'1970-01-01 00:00:00') ASC,id ASC LIMIT {$merchantLimit}");
$merchantIds = array_map('intval', $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : []);
$totals = [
    'merchants'=>0,'ingested'=>0,'evaluated'=>0,'delivered'=>0,'blocked'=>0,
    'retried'=>0,'dead_lettered'=>0,'errors'=>0,'paused'=>0,'failed_merchants'=>0,
];

$userStmt = $pdo->prepare('SELECT u.*,pp.display_name profile_display_name FROM users u LEFT JOIN public_profiles pp ON pp.user_id=u.id WHERE u.id=? LIMIT 1');
foreach ($merchantIds as $merchantUserId) {
    $userStmt->execute([$merchantUserId]);
    $merchant = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$merchant) {
        $totals['failed_merchants']++;
        fwrite(STDERR, "Merchant {$merchantUserId}: account not found." . PHP_EOL);
        continue;
    }
    if (trim((string)($merchant['profile_display_name'] ?? '')) !== '') {
        $merchant['display_name'] = (string)$merchant['profile_display_name'];
    }

    try {
        $settings = mg_trigger_orchestration_settings($pdo,$merchantUserId);
        $ingestion = mg_trigger_ingestion_run($pdo,$merchant,$sourceLimit);
        $orchestration = null;
        if ($settings['execution_mode'] !== 'paused') {
            $orchestration = mg_trigger_orchestration_process_queue($pdo,$merchant,false,$queueLimit);
        } else {
            $totals['paused']++;
        }
        $totals['merchants']++;
        $totals['ingested'] += (int)($ingestion['events_queued'] ?? 0);
        if (is_array($orchestration)) {
            $totals['evaluated'] += (int)($orchestration['events_evaluated'] ?? 0);
            $totals['delivered'] += (int)($orchestration['notifications_delivered'] ?? 0);
            $totals['blocked'] += (int)($orchestration['events_blocked'] ?? 0);
            $totals['retried'] += (int)($orchestration['events_retried'] ?? 0);
            $totals['dead_lettered'] += (int)($orchestration['events_dead_lettered'] ?? 0);
            $totals['errors'] += (int)($orchestration['errors'] ?? 0);
            if (($orchestration['status'] ?? '') === 'paused') $totals['paused']++;
        }
        fwrite(STDOUT, sprintf(
            "Merchant %d: %d queued, %d evaluated, %d delivered, %d retried, %d dead-lettered.%s",
            $merchantUserId,
            (int)($ingestion['events_queued'] ?? 0),
            (int)($orchestration['events_evaluated'] ?? 0),
            (int)($orchestration['notifications_delivered'] ?? 0),
            (int)($orchestration['events_retried'] ?? 0),
            (int)($orchestration['events_dead_lettered'] ?? 0),
            PHP_EOL
        ));
    } catch (Throwable $error) {
        $totals['failed_merchants']++;
        fwrite(STDERR, "Merchant {$merchantUserId}: {$error->getMessage()}" . PHP_EOL);
    }
}

fwrite(STDOUT, 'Trigger ingestion and orchestration run complete: ' . json_encode($totals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
exit($totals['failed_merchants'] > 0 ? 1 : 0);
