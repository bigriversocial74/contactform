<?php
declare(strict_types=1);

require_once __DIR__ . '/_email_worker.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission('admin.users.view');
$pdo = mg_db();

if ($method === 'GET') {
    mg_delivery_install_schema($pdo);
    $stmt = $pdo->query("SELECT status,COUNT(*) jobs FROM message_delivery_jobs WHERE channel='email' GROUP BY status ORDER BY status");
    mg_ok(['queue'=>$stmt->fetchAll(PDO::FETCH_ASSOC)], 'Email delivery queue loaded.');
}

if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input();
mg_require_csrf_for_write($input);
$limit = max(1, min(50, (int)($input['limit'] ?? 10)));
$result = mg_delivery_run_email_worker($pdo, $limit);
mg_audit('communications.email_worker_run','message_delivery_jobs',['limit'=>$limit,'processed'=>$result['processed_count']],(int)$user['id']);
mg_ok($result, 'Email delivery worker completed.');
