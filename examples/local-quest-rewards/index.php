<?php
declare(strict_types=1);
require __DIR__ . '/app.php';

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
    $quest = $quests[$questId] ?? null;
    if ($action === 'identify') {
        $message = lqr_action_identify($state, $config, $user);
    } elseif ($action === 'logout') {
        lqr_action_logout($state);
        lqr_save_state($state);
        header('Location: cover.php');
        exit;
    } elseif ($action === 'list_programs') {
        $result = lqr_action_list_programs($state, $config);
        $message = 'Programs response received.';
    } elseif ($action === 'start_account_link') {
        $result = lqr_action_start_account_link($state, $config, $user);
        if (is_array($result['body']) && !empty($result['body']['link_url'])) {
            lqr_save_state($state);
            header('Location: ' . (string)$result['body']['link_url']);
            exit;
        }
        $message = 'Microgifter account link started.';
    } elseif ($action === 'sandbox_link') {
        $result = lqr_action_sandbox_link($state, $config, $user);
        $message = 'Developer sandbox linked account requested.';
    } elseif ($action === 'complete_quest' && is_array($quest)) {
        $message = lqr_action_complete_quest($state, $config, $user, $questId, $quest);
        $state['last_scan'] = ['quest_id'=>$questId,'qr_payload'=>(string)($_POST['qr_payload'] ?? ''),'geo_lat'=>(string)($_POST['geo_lat'] ?? ''),'geo_lng'=>(string)($_POST['geo_lng'] ?? ''),'geo_accuracy'=>(string)($_POST['geo_accuracy'] ?? ''),'geo_captured_at'=>(string)($_POST['geo_captured_at'] ?? ''),'captured_at'=>gmdate('c')];
        lqr_add_event($state, 'quest.scan_context', 'Quest QR/geolocation context captured.', $state['last_scan']);
    } elseif ($action === 'issue_reward' && is_array($quest)) {
        $result = lqr_action_issue_reward($state, $config, $user, $questId, $quest);
        $message = 'Reward issue requested.';
    } elseif ($action === 'check_status' && is_array($quest)) {
        $result = lqr_action_check_status($state, $config, $user, $questId);
        $message = 'Reward status checked.';
    }
    lqr_save_state($state);
    $user = lqr_get_user($state, $config, $userId);
} catch (Throwable $e) {
    $error = $e->getMessage();
    lqr_add_event($state, 'demo.error', $error);
    lqr_save_state($state);
}

$configWarnings = [];
foreach (['base_url','api_key','default_program_id','default_template_id','app_public_url'] as $key) {
    $value = lqr_config_value($config, $key);
    if ($value === '' || str_contains($value, 'replace_me') || str_contains($value, 'replace_with')) $configWarnings[] = $key;
}
$lastResponse = $result ?? ($state['last_response'] ?? null);
$isLinked = trim((string)$user['linked_account_id']) !== '';
$completedCount = count(is_array($user['completed_quests'] ?? null) ? $user['completed_quests'] : []);
$rewardCount = count(is_array($user['rewards'] ?? null) ? $user['rewards'] : []);
$claimCount = 0;
foreach ((array)($user['rewards'] ?? []) as $r) if (is_array($r) && ($r['claim_status'] ?? '') === 'claimed_in_quest_app') $claimCount++;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= lqr_h((string)($config['app_name'] ?? 'Local Quest Rewards')) ?></title><link rel="stylesheet" href="assets/portal.css"></head><body class="lq-portal">
<div class="lq-shell"><header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-icon-btn" href="wallet.php">◉<span class="lq-badge"><?= number_format($rewardCount) ?></span></a><a class="lq-upgrade" href="wallet.php">Reward Wallet</a><span class="lq-user-pill"><span class="lq-avatar"><?= lqr_h(strtoupper(substr((string)$user['display_name'],0,1))) ?></span><?= lqr_h((string)$user['display_name']) ?></span></div></header>
<aside class="lq-sidebar"><a class="lq-side-link active" href="index.php"><span class="lq-side-icon">⌂</span><span class="lq-side-label">Quest board</span></a><a class="lq-side-link" href="wallet.php"><span class="lq-side-icon">◉</span><span class="lq-side-label">Wallet</span></a><a class="lq-side-link" href="cover.php"><span class="lq-side-icon">◇</span><span class="lq-side-label">Cover</span></a><a class="lq-side-link" href="admin.php"><span class="lq-side-icon">⚙</span><span class="lq-side-label">Admin</span></a><span class="lq-side-spacer"></span><form method="post"><button class="lq-side-link" name="action" value="logout" title="Sign out"><span class="lq-side-icon">⇥</span><span class="lq-side-label">Sign out</span></button></form></aside>
<main class="lq-main"><section class="lq-page-head"><span class="lq-eyebrow">User App</span><h1>Quest board</h1><p>Complete QR and location-aware local tasks, earn merchant-approved Microgift rewards, and claim them in your app wallet.</p></section>
<?php if ($message): ?><div class="lq-notice"><?= lqr_h($message) ?></div><?php endif; ?><?php if ($error): ?><div class="lq-notice error"><?= lqr_h($error) ?></div><?php endif; ?><?php if ($configWarnings): ?><div class="lq-notice warn">Config needs values for: <?= lqr_h(implode(', ', $configWarnings)) ?>.</div><?php endif; ?>
<div class="lq-kpis"><div class="lq-kpi"><span>Available quests</span><strong><?= number_format(count($quests)) ?></strong></div><div class="lq-kpi"><span>Completed</span><strong><?= number_format($completedCount) ?></strong></div><div class="lq-kpi"><span>Rewards</span><strong><?= number_format($rewardCount) ?></strong></div><div class="lq-kpi"><span>Claims</span><strong><?= number_format($claimCount) ?></strong></div></div>
<div class="lq-layout"><section class="lq-card"><div class="lq-card-head"><div><h2>Quest tasks</h2><p>Scan a QR code or paste a venue/prize code, capture geolocation, then complete the task.</p></div><span class="lq-pill <?= $isLinked ? 'green' : 'amber' ?>"><?= $isLinked ? 'Microgifter linked' : 'Link required' ?></span></div><div class="lq-stack"><?php foreach ($quests as $questId => $quest): ?><?php $completed=!empty($user['completed_quests'][$questId]); $reward=is_array($user['rewards'][$questId]??null)?$user['rewards'][$questId]:[]; [$canIssue,$issueReason]=lqr_can_issue_reward($user,(string)$questId,$quest,$config); ?><article class="lq-item"><div><h3><?= lqr_h((string)$quest['title']) ?></h3><div class="lq-meta"><?= lqr_h((string)$quest['description']) ?><br><strong><?= lqr_h((string)$quest['reward_label']) ?></strong> · <?= lqr_h((string)$quest['difficulty']) ?><br>Program <code><?= lqr_h(lqr_quest_program_id($quest,$config) ?: 'not configured') ?></code> · Template <code><?= lqr_h(lqr_quest_template_id($quest,$config) ?: 'not configured') ?></code></div><div style="margin-top:10px"><span class="lq-pill <?= $completed ? 'green' : 'amber' ?>"><?= $completed ? 'Completed' : 'Open' ?></span><?php if($reward): ?> <span class="lq-pill pink">Reward <?= lqr_h((string)$reward['status']) ?></span><?php endif; ?></div></div><form method="post" data-lq-geo-box data-lq-qr-box style="min-width:280px"><input type="hidden" name="quest_id" value="<?= lqr_h((string)$questId) ?>"><input type="hidden" name="geo_lat"><input type="hidden" name="geo_lng"><input type="hidden" name="geo_accuracy"><input type="hidden" name="geo_captured_at"><input type="hidden" name="qr_payload"><input class="lq-input" data-lq-qr-manual placeholder="Venue QR / task code"><div class="lq-actions"><button class="lq-btn soft" data-lq-start-qr>Scan QR</button><button class="lq-btn soft" data-lq-capture-geo>Geo</button></div><video class="lq-qr-video" playsinline></video><div class="lq-qr-result" data-lq-qr-result></div><div class="lq-qr-result" data-lq-geo-result></div><div class="lq-actions"><button class="lq-btn green" name="action" value="complete_quest">Complete</button><button class="lq-btn amber" name="action" value="issue_reward" <?= $canIssue ? '' : 'disabled' ?>>Issue reward</button><button class="lq-btn dark" name="action" value="check_status" <?= $reward ? '' : 'disabled' ?>>Status</button></div><small><?= lqr_h($reward ? 'Reward: '.(string)$reward['reward_id'] : $issueReason) ?></small></form></article><?php endforeach; ?></div></section><aside class="lq-side-panel"><section class="lq-panel"><h2>Quick actions</h2><form method="post"><button class="lq-link-row" name="action" value="list_programs">List Microgifter programs <span>→</span></button></form><?php if(!$isLinked): ?><form method="post"><button class="lq-link-row" name="action" value="start_account_link">Connect Microgifter <span>→</span></button></form><?php if(!empty($config['allow_sandbox_shortcut'])): ?><form method="post"><button class="lq-link-row" name="action" value="sandbox_link">Developer sandbox <span>→</span></button></form><?php endif; ?><?php endif; ?><a class="lq-link-row" href="wallet.php">Open wallet <span>→</span></a><a class="lq-link-row" href="webhook-events.log">Webhook log <span>→</span></a></section><section class="lq-panel"><h2>Participant</h2><form method="post"><label class="lq-label">Display name</label><input class="lq-input" name="display_name" value="<?= lqr_h((string)$user['display_name']) ?>"><button class="lq-btn primary" name="action" value="identify" style="width:100%;margin-top:12px">Save profile</button></form><div class="lq-row"><span>Email</span><strong><?= lqr_h((string)$user['email']) ?></strong></div><div class="lq-row"><span>External ID</span><strong><?= lqr_h((string)$user['external_user_id']) ?></strong></div><div class="lq-row"><span>Microgifter</span><strong><?= lqr_h($isLinked ? (string)$user['linked_account_id'] : 'Not connected') ?></strong></div></section></aside></div>
<?php if($lastResponse!==null): ?><section class="lq-card" style="margin-top:22px"><h2>Last API response</h2><pre><?= lqr_h(json_encode($lastResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></section><?php endif; ?></main></div><script src="assets/portal.js"></script></body></html>
