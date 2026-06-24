<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/stamps/_renewals.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$period = '';
$limit = 500;
foreach (($argv ?? []) as $arg) {
    if (str_starts_with($arg, '--period=')) $period = substr($arg, 9);
    if (str_starts_with($arg, '--limit=')) $limit = max(1, (int)substr($arg, 8));
}

$pdo = mg_db();
$pdo->beginTransaction();
try {
    $result = mg_stamp_run_monthly_renewals($pdo, null, $period, $limit, $dryRun);
    if ($dryRun) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Monthly Stamp renewal failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
