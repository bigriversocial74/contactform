<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/quest-controls.php';

$config = lqr_config();
$quests = lqr_quests();
$state = lqr_load_state();
$userId = lqr_current_user_id($config);
$user = lqr_get_user($state, $config, $userId);

if (!lqr_is_authenticated() || empty($user['email'])) {
    header('Location: cover.php');
    exit;
}

$history = lqr_user_quest_history($user, $quests);
$events = array_values(array_filter((array)($state['events'] ?? []), static function($event) use ($user): bool {
    if (!is_array($event)) return false;
    $context = is_array($event['context'] ?? null) ? $event['context'] : [];
    return empty($context['user_id']) || (string)$context['user_id'] === (string)$user['id'];
}));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Quest History</title><link rel="stylesheet" href="assets/portal.css"><style>.history-table{width:100%;border-collapse:collapse}.history-table th,.history-table td{padding:10px;border-bottom:1px solid rgba(255,255,255,.1);text-align:left}.history-table code{font-size:11px;word-break:break-all}.timeline{display:grid;gap:10px}.timeline-item{padding:12px;border-radius:14px;background:#0d1b2f;border:1px solid #24415f}.muted{color:#9db3cc}@media(max-width:860px){.history-table{display:block;overflow:auto}}</style></head><body class="lq-portal"><div class="lq-shell"><header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="wallet.php">Wallet</a><a class="lq-upgrade" href="index.php">Quest board</a></div></header><aside class="lq-sidebar"><a class="lq-side-link" href="index.php"><span class="lq-side-icon">⌂</span><span class="lq-side-label">Quest board</span></a><a class="lq-side-link" href="wallet.php"><span class="lq-side-icon">◉</span><span class="lq-side-label">Wallet</span></a><a class="lq-side-link active" href="history.php"><span class="lq-side-icon">≡</span><span class="lq-side-label">History</span></a><a class="lq-side-link" href="cover.php"><span class="lq-side-icon">◇</span><span class="lq-side-label">Cover</span></a></aside><main class="lq-main"><section class="lq-page-head"><span class="lq-eyebrow">Participant</span><h1>Quest history</h1><p>Review completed quests, issued Microgift rewards, claim status, and recent app events.</p></section><section class="lq-card"><h2>Reward timeline</h2><table class="history-table"><thead><tr><th>Quest</th><th>Completed</th><th>Reward</th><th>Status</th><th>Claim</th><th>Report</th></tr></thead><tbody><?php if(!$history): ?><tr><td colspan="6">No quest history yet.</td></tr><?php endif; ?><?php foreach($history as $row): ?><tr><td><?= lqr_h($row['quest_title']) ?></td><td><?= lqr_h($row['completed_at'] ?: '—') ?></td><td><code><?= lqr_h($row['reward_id'] ?: '—') ?></code></td><td><?= lqr_h($row['reward_status'] ?: '—') ?></td><td><?= lqr_h($row['claim_status'] ?: '—') ?></td><td><?= lqr_h($row['claim_report_status'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></section><section class="lq-card" style="margin-top:18px"><h2>Recent events</h2><div class="timeline"><?php if(!$events): ?><p>No events yet.</p><?php endif; ?><?php foreach(array_slice($events,0,20) as $event): ?><article class="timeline-item"><strong><?= lqr_h((string)($event['type'] ?? 'event')) ?></strong><p><?= lqr_h((string)($event['message'] ?? '')) ?></p><small class="muted"><?= lqr_h((string)($event['at'] ?? '')) ?></small></article><?php endforeach; ?></div></section></main></div><script src="assets/portal.js"></script></body></html>
