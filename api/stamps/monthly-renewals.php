<?php
declare(strict_types=1);
require_once __DIR__ . '/_renewals.php';
$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$pdo = mg_db();
$period = trim((string)($_GET['period'] ?? ''));
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 500;

if ($method === 'GET') {
    mg_ok(mg_stamp_monthly_renewal_preview($pdo, $period, $limit));
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$period = trim((string)($input['period'] ?? $period));
$limit = isset($input['limit']) ? (int)$input['limit'] : $limit;
$dryRun = !empty($input['dry_run']);

try {
    $pdo->beginTransaction();
    $result = mg_stamp_run_monthly_renewals($pdo, (int)$user['id'], $period, $limit, $dryRun);
    if ($dryRun) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }
    mg_audit('stamps.monthly_renewals_run', 'stamp_ledger', ['period'=>$result['period'], 'dry_run'=>$dryRun, 'credited_count'=>$result['credited_count'] ?? 0, 'pending_count'=>$result['pending_count'] ?? 0], (int)$user['id']);
    mg_ok($result, $dryRun ? 'Monthly Stamp renewal preview generated.' : 'Monthly Stamp renewals processed.', 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.monthly_renewals_failed','Unable to process monthly Stamp renewals.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to process monthly Stamp renewals.', 500);
}
