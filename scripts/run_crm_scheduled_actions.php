<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/merchant-crm-scheduled-actions.php';

$limit = 100;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) $limit = max(1, min(500, (int)substr($arg, 8)));
}

$pdo = mg_db();
if (!mg_crm_scheduled_ready($pdo)) {
    fwrite(STDERR, "CRM scheduled action schema is not installed.\n");
    exit(2);
}

$merchantStmt = $pdo->prepare("SELECT DISTINCT merchant_user_id FROM crm_scheduled_actions WHERE status='scheduled' AND scheduled_at<=NOW() ORDER BY merchant_user_id ASC LIMIT 250");
$merchantStmt->execute();
$merchantIds = array_map('intval', $merchantStmt->fetchAll(PDO::FETCH_COLUMN));
$total = ['selected' => 0, 'processed' => 0, 'issued' => 0, 'invited' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0, 'duplicates' => 0];

foreach ($merchantIds as $merchantId) {
    try {
        $result = mg_crm_scheduled_process_due($pdo, $merchantId, $limit);
        $summary = $result['summary'] ?? [];
        foreach ($total as $key => $value) {
            $total[$key] += (int)($summary[$key] ?? 0);
        }
        echo 'merchant=' . $merchantId . ' processed=' . (int)($summary['processed'] ?? 0) . ' failed=' . (int)($summary['failed'] ?? 0) . PHP_EOL;
    } catch (Throwable $error) {
        $total['failed']++;
        if (function_exists('mg_security_log')) {
            mg_security_log('error', 'scripts.crm_scheduled_actions.failed', 'Scheduled CRM action script failed for merchant.', ['merchant_user_id' => $merchantId, 'exception_class' => $error::class, 'message' => $error->getMessage()]);
        }
        fwrite(STDERR, 'merchant=' . $merchantId . ' error=' . $error->getMessage() . PHP_EOL);
    }
}

echo 'summary=' . json_encode($total, JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($total['failed'] > 0 ? 1 : 0);
