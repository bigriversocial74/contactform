<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/wallet-actions.php';
$config = lqr_config();
$quests = lqr_quests();
$state = lqr_load_state();
$userId = lqr_current_user_id($config);
$user = lqr_get_user($state, $config, $userId);
$message = null;
$error = null;
$result = null;
if (!lqr_is_authenticated() || empty($user['email'])) {
    header('Location: cover.php');
    exit;
}
try {
    $action = (string)($_POST['action'] ?? '');
    $questId = (string)($_POST['quest_id'] ?? '');
    if ($action === 'refresh_status') {
        $result = lqr_action_check_status($state, $config, $user, $questId);
        $message = 'Reward status refreshed from Microgifter.';
    } elseif ($action === 'claim_reward') {
        $message = lqr_action_claim_reward_reported($state, $config, $user, $questId, $quests);
    }
    lqr_save_state($state);
    $user = lqr_get_user($state, $config, $userId);
} catch (Throwable $e) {
    $error = $e->getMessage();
    lqr_add_event($state, 'wallet.error', $error);
    lqr_save_state($state);
}
$wallet = lqr_wallet_rewards($user, $quests);
$claimed = 0;
foreach ($wallet as $r) if (($r['claim_status'] ?? '') === 'claimed_in_quest_app') $claimed++;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reward Wallet | Local Quest Rewards</title><link rel="stylesheet" href="assets/portal.css"></head><body class="lq-portal">
<div class="lq-shell"><header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-icon-btn" href="wallet.php">◉<span class="lq-badge"><?= number_format(count($wallet)) ?></span></a><a class="lq-upgrade" href="index.php">Quest Board</a><span class="lq-user-pill"><span class="lq-avatar"><?= lqr_h(strtoupper(substr((string)$user['display_name'],0,1))) ?></span><?= lqr_h((string)$user['display_name']) ?></span></div></header>
<aside class="lq-sidebar"><a class="lq-side-link" href="index.php"><span class="lq-side-icon">⌂</span><span class="lq-side-label">Quest board</span></a><a class="lq-side-link active" href="wallet.php"><span class="lq-side-icon">◉</span><span class="lq-side-label">Wallet</span></a><a class="lq-side-link" href="cover.php"><span class="lq-side-icon">◇</span><span class="lq-side-label">Cover</span></a><a class="lq-side-link" href="admin.php"><span class="lq-side-icon">⚙</span><span class="lq-side-label">Admin</span></a><span class="lq-side-spacer"></span><a class="lq-side-link" href="webhook-events.log"><span class="lq-side-icon">◷</span><span class="lq-side-label">Webhook log</span></a></aside>
<main class="lq-main"><section class="lq-page-head"><span class="lq-eyebrow">User Wallet</span><h1>Your rewards</h1><p>See issued Microgift rewards, refresh status from Microgifter, scan claim/prize QR codes, capture geolocation, and report claims back to the platform.</p></section>
<?php if ($message): ?><div class="lq-notice"><?= lqr_h($message) ?></div><?php endif; ?><?php if ($error): ?><div class="lq-notice error"><?= lqr_h($error) ?></div><?php endif; ?>
<div class="lq-kpis"><div class="lq-kpi"><span>Total rewards</span><strong><?= number_format(count($wallet)) ?></strong></div><div class="lq-kpi"><span>Claimed</span><strong><?= number_format($claimed) ?></strong></div><div class="lq-kpi"><span>Linked</span><strong><?= trim((string)$user['linked_account_id'])!==''?'1':'0' ?></strong></div><div class="lq-kpi"><span>Mode</span><strong><?= lqr_h((string)($config['mode'] ?? 'test')) ?></strong></div></div>
<div class="lq-layout"><section class="lq-card"><div class="lq-card-head"><div><h2>Reward wallet</h2><p>Claiming a reward sends QR/geolocation context in the Microgifter claim report metadata.</p></div><span class="lq-pill pink">Wallet</span></div><div class="lq-stack"><?php if(!$wallet): ?><div class="lq-item"><div><h3>No rewards yet</h3><div class="lq-meta">Complete a quest and issue a reward first. Then return here to refresh status and claim it.</div></div><a class="lq-btn primary" href="index.php">Start questing</a></div><?php endif; ?><?php foreach($wallet as $reward): ?><?php $isClaimed=$reward['claim_status']==='claimed_in_quest_app'; ?><article class="lq-item"><div><h3><?= lqr_h($reward['reward_label']) ?></h3><div class="lq-meta"><?= lqr_h($reward['quest_title']) ?><br>Reward <code><?= lqr_h($reward['reward_id']) ?></code><br>Microgifter: <?= lqr_h($reward['status']) ?> · Claim report: <?= lqr_h($reward['claim_report_status']) ?><br>PPPM item: <code><?= lqr_h($reward['item_id'] ?: 'refresh status after issuance') ?></code></div><div style="margin-top:10px"><span class="lq-pill <?= $isClaimed?'green':'amber' ?>"><?= lqr_h($isClaimed?'Claimed in Quest':'Available') ?></span></div></div><form method="post" data-lq-geo-box data-lq-qr-box style="min-width:300px"><input type="hidden" name="quest_id" value="<?= lqr_h($reward['quest_id']) ?>"><input type="hidden" name="geo_lat"><input type="hidden" name="geo_lng"><input type="hidden" name="geo_accuracy"><input type="hidden" name="geo_captured_at"><input type="hidden" name="qr_payload"><input class="lq-input" data-lq-qr-manual placeholder="Prize QR / merchant code"><div class="lq-actions"><button class="lq-btn soft" data-lq-start-qr>Scan QR</button><button class="lq-btn soft" data-lq-capture-geo>Geo</button></div><video class="lq-qr-video" playsinline></video><div class="lq-qr-result" data-lq-qr-result></div><div class="lq-qr-result" data-lq-geo-result></div><div class="lq-actions"><button class="lq-btn dark" name="action" value="refresh_status">Refresh</button><button class="lq-btn green" name="action" value="claim_reward" <?= $isClaimed ? 'disabled' : '' ?>>Claim</button></div></form></article><?php endforeach; ?></div></section><aside class="lq-side-panel"><section class="lq-panel"><h2>Connected identity</h2><div class="lq-row"><span>Local user</span><strong><?= lqr_h((string)$user['email']) ?></strong></div><div class="lq-row"><span>External user</span><strong><?= lqr_h((string)$user['external_user_id']) ?></strong></div><div class="lq-row"><span>Linked account</span><strong><?= lqr_h((string)($user['linked_account_id'] ?: 'Not connected')) ?></strong></div></section><section class="lq-panel"><h2>Quick actions</h2><a class="lq-link-row" href="index.php">Quest board <span>→</span></a><a class="lq-link-row" href="webhook-events.log">Webhook log <span>→</span></a><a class="lq-link-row" href="admin.php?section=claims">Admin claims <span>→</span></a></section></aside></div>
<?php if($result!==null): ?><section class="lq-card" style="margin-top:22px"><h2>Last API response</h2><pre><?= lqr_h(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></section><?php endif; ?></main></div><script src="assets/portal.js"></script></body></html>
