<?php
declare(strict_types=1);
require_once __DIR__ . '/_issuance_worker.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission('distribution.allocations.manage');
$pdo = mg_db();

if ($method === 'GET') {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 25)));
    $stmt = $pdo->query("SELECT status,COUNT(*) AS jobs FROM distribution_issuance_jobs GROUP BY status ORDER BY status");
    $next = mg_distribution_worker_claimable_jobs($pdo, $limit);
    mg_ok(['queue'=>$stmt->fetchAll(),'claimable_jobs'=>$next,'claimable_count'=>count($next)]);
}

if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input();
mg_require_csrf_for_write($input);
$limit = max(1, min(100, (int)($input['limit'] ?? 25)));
$workerId = trim((string)($input['worker_id'] ?? 'manual-distribution-worker')) ?: 'manual-distribution-worker';
if (mb_strlen($workerId) > 120) mg_fail('Invalid worker ID.',422);
$result = mg_distribution_worker_run($pdo, $limit, $workerId);
mg_audit('distribution.issuance_worker_run','distribution_issuance_job',['limit'=>$limit,'claimed'=>$result['claimed']],(int)$user['id']);
mg_ok($result, 'Distribution issuance worker completed.');
