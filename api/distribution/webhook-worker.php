<?php
declare(strict_types=1);
require_once __DIR__ . '/_developer_webhooks.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission('distribution.allocations.manage');
$pdo = mg_db();

if ($method === 'GET') {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 25)));
    $queue = $pdo->query("SELECT status,COUNT(*) AS events FROM developer_webhook_events GROUP BY status ORDER BY status")->fetchAll();
    $next = mg_dev_webhook_claimable($pdo, $limit);
    mg_ok(['queue'=>$queue,'claimable_events'=>$next,'claimable_count'=>count($next)]);
}

if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input();
mg_require_csrf_for_write($input);
$limit = max(1, min(100, (int)($input['limit'] ?? 25)));
$workerId = trim((string)($input['worker_id'] ?? 'manual-developer-webhook-worker')) ?: 'manual-developer-webhook-worker';
if (mb_strlen($workerId) > 120) mg_fail('Invalid worker ID.',422);
$result = mg_dev_webhook_run($pdo, $limit, $workerId);
mg_audit('developer_webhooks.worker_run','developer_webhook_event',['limit'=>$limit,'claimed'=>$result['claimed']],(int)$user['id']);
mg_ok($result, 'Developer webhook worker completed.');
