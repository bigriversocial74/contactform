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
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reward Wallet | Local Quest Rewards</title>
<style>
:root{--bg:#071225;--panel:#0d1b2f;--card:#10243d;--line:#24415f;--text:#f5f9ff;--muted:#9db3cc;--blue:#5aa7ff;--green:#4ade80;--amber:#fbbf24;--red:#fb7185}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 18% 0,rgba(90,167,255,.23),transparent 32%),var(--bg);color:var(--text);font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{width:min(1180px,94%);margin:0 auto;padding:36px 0 70px}.hero,.grid{display:grid;gap:18px}.hero{grid-template-columns:minmax(0,1.2fr) 320px;align-items:stretch}.panel,.card{background:rgba(13,27,47,.92);border:1px solid rgba(148,180,213,.22);border-radius:24px;box-shadow:0 24px 70px rgba(0,0,0,.28);padding:24px}.eyebrow{display:inline-flex;border:1px solid rgba(90,167,255,.45);border-radius:999px;padding:8px 12px;color:#b9dcff;background:rgba(90,167,255,.12);font-size:12px;font-weight:950;text-transform:uppercase;letter-spacing:.08em}h1{margin:18px 0 0;font-size:clamp(42px,7vw,76px);line-height:.92;letter-spacing:-.08em}h2{margin:0 0 12px;font-size:25px;letter-spacing:-.04em}h3{margin:0 0 8px}p{color:var(--muted);line-height:1.6}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.btn,button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 14px;border:0;border-radius:13px;background:var(--blue);color:#06111f;font-weight:950;text-decoration:none;cursor:pointer}.dark{background:#172b47;color:var(--text);border:1px solid var(--line)}.green{background:var(--green);color:#062113}.amber{background:var(--amber);color:#241700}.notice{margin-top:12px;padding:12px 14px;border-radius:16px;border:1px solid rgba(90,167,255,.32);background:rgba(90,167,255,.11);color:#cfe7ff}.error{border-color:rgba(251,113,133,.45);background:rgba(251,113,133,.12);color:#ffd7df}.wallet{display:grid;gap:14px;margin-top:18px}.reward{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(280px,.9fr);gap:14px;align-items:stretch}.meta{display:grid;gap:8px}.row{display:flex;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.08)}.row span{color:var(--muted)}.row strong{text-align:right;word-break:break-word}.tag{display:inline-flex;min-height:26px;align-items:center;border-radius:999px;padding:0 10px;background:rgba(255,255,255,.08);color:#cfe0f4;font-size:12px;font-weight:850}.tag.claimed{background:rgba(74,222,128,.15);color:#c7ffd9}.tag.pending{background:rgba(251,191,36,.15);color:#ffe6a6}.empty{padding:22px;border-radius:18px;background:rgba(255,255,255,.055);border:1px solid rgba(255,255,255,.08)}pre{overflow:auto;margin:0;padding:16px;border-radius:16px;background:#050b14;color:#d9ecff;font-size:12px;line-height:1.55;white-space:pre-wrap}@media(max-width:900px){.hero,.reward{grid-template-columns:1fr}h1{font-size:44px}}
</style>
</head>
<body>
<div class="wrap">
  <section class="hero">
    <div class="panel">
      <span class="eyebrow">Quest wallet</span>
      <h1>Your rewards</h1>
      <p>Participants should see issued Microgift rewards inside the Quest app. Claim actions report back to Microgifter so ownership, claim, and redemption stay centralized.</p>
      <div class="actions"><a class="btn dark" href="index.php">Back to quests</a><a class="btn dark" href="webhook-events.log">Webhook log</a></div>
      <?php if ($message): ?><div class="notice"><?= lqr_h($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="notice error"><?= lqr_h($error) ?></div><?php endif; ?>
    </div>
    <aside class="panel">
      <h2>Connected identity</h2>
      <div class="row"><span>Local user</span><strong><?= lqr_h((string)$user['email']) ?></strong></div>
      <div class="row"><span>External user</span><strong><?= lqr_h((string)$user['external_user_id']) ?></strong></div>
      <div class="row"><span>Linked account</span><strong><?= lqr_h((string)($user['linked_account_id'] ?: 'Not connected')) ?></strong></div>
    </aside>
  </section>
  <section class="wallet">
    <?php if (!$wallet): ?>
      <div class="empty"><h2>No rewards yet</h2><p>Complete a quest and issue a reward first. Then return here to refresh status and claim it in the Quest app.</p></div>
    <?php endif; ?>
    <?php foreach ($wallet as $reward): ?>
      <?php $claimed = $reward['claim_status'] === 'claimed_in_quest_app'; ?>
      <article class="card reward">
        <div>
          <span class="tag <?= $claimed ? 'claimed' : 'pending' ?>"><?= lqr_h($claimed ? 'Claimed in Quest' : 'Available') ?></span>
          <h2><?= lqr_h($reward['reward_label']) ?></h2>
          <p><?= lqr_h($reward['quest_title']) ?></p>
          <div class="actions">
            <form method="post"><input type="hidden" name="quest_id" value="<?= lqr_h($reward['quest_id']) ?>"><button class="dark" name="action" value="refresh_status">Refresh from Microgifter</button></form>
            <form method="post"><input type="hidden" name="quest_id" value="<?= lqr_h($reward['quest_id']) ?>"><button class="green" name="action" value="claim_reward" <?= $claimed ? 'disabled' : '' ?>>Claim in Quest app</button></form>
          </div>
        </div>
        <div class="meta">
          <div class="row"><span>Reward ID</span><strong><?= lqr_h($reward['reward_id']) ?></strong></div>
          <div class="row"><span>Microgifter status</span><strong><?= lqr_h($reward['status']) ?></strong></div>
          <div class="row"><span>PPPM item</span><strong><?= lqr_h($reward['item_id'] ?: 'Refresh status after issuance') ?></strong></div>
          <div class="row"><span>Claim report</span><strong><?= lqr_h($reward['claim_report_status']) ?></strong></div>
          <div class="row"><span>Issued</span><strong><?= lqr_h($reward['issued_at']) ?></strong></div>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
  <?php if ($result !== null): ?><section class="card" style="margin-top:18px"><h2>Last API response</h2><pre><?= lqr_h(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></section><?php endif; ?>
</div>
</body>
</html>
