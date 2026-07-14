<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = rd_require_user_json();
$runId = trim((string)($_GET['run_id'] ?? ''));
if ($runId === '') rd_json(['ok' => false, 'message' => 'Game run ID is required.'], 422);

$pdo = mg_db();
$run = rd_find_run($pdo, $runId, (int)$user['id']);
if (!$run) rd_json(['ok' => false, 'message' => 'Game run not found.'], 404);

if ((string)$run['status'] === 'started' && strtotime((string)$run['expires_at']) < time()) {
    $pdo->prepare("UPDATE reward_drop_runs SET status='expired',error_message='Game run expired.',updated_at=NOW() WHERE id=? AND status='started'")
        ->execute([(int)$run['id']]);
    $run = rd_find_run($pdo, $runId, (int)$user['id']) ?: $run;
}

rd_json(['ok' => true, 'run' => rd_run_payload($run)]);
