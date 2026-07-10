<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/api/store/_canvas_trigger_engine_runner.php';

$pdo = mg_db();
if (!mg_store_trigger_engine_schema_ready($pdo)) {
    fwrite(STDERR, 'Store Canvas trigger engine schema is incomplete: ' . implode(', ', mg_store_trigger_engine_missing_tables($pdo)) . PHP_EOL);
    exit(2);
}

$limit = 100;
foreach ($argv as $argument) {
    if (preg_match('/^--limit=(\d+)$/', (string)$argument, $match)) {
        $limit = max(1, min(500, (int)$match[1]));
    }
}

$stmt = $pdo->query("SELECT merchant_user_id FROM mg_store_trigger_engine_settings WHERE execution_mode='notification' ORDER BY updated_at ASC,id ASC LIMIT {$limit}");
$merchantIds = array_map('intval', $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : []);
$totals = ['merchants'=>0,'evaluations'=>0,'delivered'=>0,'blocked'=>0,'errors'=>0,'failed_merchants'=>0];

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
        $result = mg_store_trigger_engine_run_authorized($pdo, $merchant, false);
        $summary = $result['summary'] ?? [];
        $totals['merchants']++;
        $totals['evaluations'] += (int)($summary['evaluations'] ?? 0);
        $totals['delivered'] += (int)($summary['delivered'] ?? 0);
        $totals['blocked'] += (int)($summary['blocked'] ?? 0);
        $totals['errors'] += (int)($summary['errors'] ?? 0);
        fwrite(STDOUT, sprintf(
            "Merchant %d: %d evaluations, %d delivered, %d blocked, %d errors.%s",
            $merchantUserId,
            (int)($summary['evaluations'] ?? 0),
            (int)($summary['delivered'] ?? 0),
            (int)($summary['blocked'] ?? 0),
            (int)($summary['errors'] ?? 0),
            PHP_EOL
        ));
    } catch (Throwable $error) {
        $totals['failed_merchants']++;
        fwrite(STDERR, "Merchant {$merchantUserId}: {$error->getMessage()}" . PHP_EOL);
    }
}

fwrite(STDOUT, 'Store Canvas trigger engine run complete: ' . json_encode($totals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
exit($totals['failed_merchants'] > 0 ? 1 : 0);
