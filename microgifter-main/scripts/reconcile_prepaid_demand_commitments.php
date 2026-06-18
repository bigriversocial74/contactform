<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }
require_once dirname(__DIR__) . '/api/demand/_prepaid.php';

$pdo = mg_db();
$limit = max(1, min((int)($argv[1] ?? 500), 1000));
$updatedAfter = trim((string)($argv[2] ?? ''));
$filters = [];
if ($updatedAfter !== '') {
    try { $filters['updated_after'] = (new DateTimeImmutable($updatedAfter, new DateTimeZone('UTC')))->format('Y-m-d H:i:s'); }
    catch (Throwable) { fwrite(STDERR, "Invalid updated-after timestamp.\n"); exit(2); }
}
try {
    $pdo->beginTransaction();
    $result = mg_prepaid_demand_reconcile_batch($pdo, $filters, $limit);
    $pdo->commit();
    echo json_encode(['ok'=>true,'suite'=>'prepaid_demand_reconciliation','result'=>$result], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
