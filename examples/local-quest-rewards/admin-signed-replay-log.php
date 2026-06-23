<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/admin-auth.php';

$config = lqr_config();
$state = lqr_load_state();
$error = '';
$rows = [];

try {
    lqr_admin_require($state, $config);
    $pdo = lqr_sql_db($config);
    $rows = $pdo->query('SELECT replay_key, quest_key, code_type, nonce, first_seen_at FROM lqr_signed_code_replays ORDER BY first_seen_at DESC, id DESC LIMIT 100')->fetchAll();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Signed QR Replay Log</title><style>body{margin:0;background:#071225;color:#f5f9ff;font-family:Arial,sans-serif}.wrap{width:min(1100px,94%);margin:0 auto;padding:32px 0}.card{background:#0d1b2f;border:1px solid #24415f;border-radius:18px;padding:18px;margin:12px 0}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 12px;border-radius:12px;background:#172b47;color:#f5f9ff;border:1px solid #24415f;font-weight:800;text-decoration:none}.notice{padding:10px 12px;border-radius:12px;background:#10243d}.error{background:rgba(251,113,133,.16);color:#ffd7df}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:9px;border-bottom:1px solid rgba(255,255,255,.1);text-align:left;font-size:13px}code{font-size:11px;word-break:break-all}p{color:#9db3cc}@media(max-width:860px){.table{display:block;overflow:auto}}</style></head><body><main class="wrap"><header><h1>Signed QR Replay Log</h1><p><a class="btn" href="admin.php">Admin home</a> <a class="btn" href="admin-signed-codes.php">Signed codes</a> <a class="btn" href="admin-signed-code-controls.php">Signed QR controls</a></p></header><?php if($error):?><p class="notice error"><?= lqr_h($error) ?></p><?php endif; ?><section class="card"><h2>Accepted signed QR payloads</h2><p>Accepted signed QR payloads are recorded here to prevent re-use.</p><table class="table"><thead><tr><th>Seen</th><th>Quest</th><th>Type</th><th>Nonce</th><th>Replay key</th></tr></thead><tbody><?php if(!$rows): ?><tr><td colspan="5">No signed QR replay records yet.</td></tr><?php endif; ?><?php foreach($rows as $row): ?><tr><td><?= lqr_h((string)$row['first_seen_at']) ?></td><td><?= lqr_h((string)$row['quest_key']) ?></td><td><?= lqr_h((string)$row['code_type']) ?></td><td><code><?= lqr_h((string)$row['nonce']) ?></code></td><td><code><?= lqr_h((string)$row['replay_key']) ?></code></td></tr><?php endforeach; ?></tbody></table></section></main></body></html>
