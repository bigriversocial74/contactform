<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/quest-controls.php';

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

function lqds_configured(array $config, string $key): bool
{
    $value = lqr_config_value($config, $key);
    return $value !== '' && !str_contains($value, 'replace_me') && !str_contains($value, 'replace_with');
}

function lqds_latest_webhooks(int $limit = 5): array
{
    $path = __DIR__ . '/webhook-events.log';
    if (!is_file($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return [];
    $lines = array_reverse(array_slice($lines, -1 * max(1, $limit)));
    $entries = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        $entries[] = is_array($decoded) ? $decoded : ['body' => $line];
    }
    return $entries;
}

function lqds_api_endpoints(): array
{
    return [
        ['label' => 'List programs', 'method' => 'GET', 'path' => '/api/public/v1/programs/index.php', 'scope' => 'distribution:programs.read', 'status' => 'wired'],
        ['label' => 'Sandbox linked account', 'method' => 'POST', 'path' => '/api/public/v1/sandbox/linked-account.php', 'scope' => 'distribution:rewards.issue', 'status' => 'wired'],
        ['label' => 'Production account link', 'method' => 'POST', 'path' => '/api/public/v1/account-links/start.php', 'scope' => 'distribution:rewards.issue', 'status' => 'wired'],
        ['label' => 'Issue reward', 'method' => 'POST', 'path' => '/api/public/v1/rewards/issue.php', 'scope' => 'distribution:rewards.issue', 'status' => 'wired'],
        ['label' => 'Reward status', 'method' => 'GET', 'path' => '/api/public/v1/rewards/status.php?id={reward_id}', 'scope' => 'distribution:rewards.status', 'status' => 'wired'],
        ['label' => 'Claim report', 'method' => 'POST', 'path' => '/api/public/v1/rewards/claim.php', 'scope' => 'distribution:rewards.issue', 'status' => 'wallet action'],
        ['label' => 'Signed webhook receiver', 'method' => 'POST', 'path' => '/webhook.php', 'scope' => 'webhook signing value', 'status' => 'local receiver'],
    ];
}

function lqds_launch_steps(array $config, array $user, array $quests, array $state): array
{
    $completedCount = count(is_array($user['completed_quests'] ?? null) ? $user['completed_quests'] : []);
    $rewardCount = count(is_array($user['rewards'] ?? null) ? $user['rewards'] : []);
    $claimReported = false;
    foreach ((array)($user['rewards'] ?? []) as $reward) {
        if (is_array($reward) && in_array((string)($reward['claim_report_status'] ?? ''), ['reported_to_microgifter','confirmed_by_microgifter_webhook'], true)) {
            $claimReported = true;
            break;
        }
    }
    $storage = is_array($config['storage'] ?? null) ? $config['storage'] : [];
    $driver = strtolower((string)($storage['driver'] ?? ''));
    $webhooks = lqds_latest_webhooks(1);
    return [
        ['label' => 'SQL runtime configured', 'ok' => in_array($driver, ['mysql','mariadb','pdo_mysql'], true), 'detail' => 'Local Quest now expects MySQL or MariaDB through PDO.'],
        ['label' => 'Public API base URL configured', 'ok' => lqds_configured($config, 'base_url'), 'detail' => (string)($config['base_url'] ?? '')],
        ['label' => 'Bearer API credential configured', 'ok' => lqds_configured($config, 'api_key'), 'detail' => 'Server-side only. Do not expose in browser JavaScript.'],
        ['label' => 'Default Distribution Program configured', 'ok' => lqds_configured($config, 'default_program_id'), 'detail' => (string)($config['default_program_id'] ?? '')],
        ['label' => 'Default reward template configured', 'ok' => lqds_configured($config, 'default_template_id'), 'detail' => (string)($config['default_template_id'] ?? '')],
        ['label' => 'Webhook signing value rotated', 'ok' => lqds_configured($config, 'webhook_secret'), 'detail' => 'Required before trusting lifecycle callbacks.'],
        ['label' => 'Local Quest account created', 'ok' => !empty($user['email']), 'detail' => (string)($user['email'] ?? '')],
        ['label' => 'Microgifter account linked', 'ok' => trim((string)($user['linked_account_id'] ?? '')) !== '', 'detail' => trim((string)($user['linked_account_id'] ?? '')) ?: 'Use sandbox or production link.'],
        ['label' => 'Quest catalog loaded', 'ok' => count($quests) > 0, 'detail' => number_format(count($quests)) . ' quests available in quests.php.'],
        ['label' => 'Quest completion captured', 'ok' => $completedCount > 0, 'detail' => number_format($completedCount) . ' local completions.'],
        ['label' => 'Reward issue tested', 'ok' => $rewardCount > 0, 'detail' => number_format($rewardCount) . ' rewards in local wallet state.'],
        ['label' => 'Claim report tested', 'ok' => $claimReported, 'detail' => 'Wallet claim report posts to /api/public/v1/rewards/claim.php.'],
        ['label' => 'Webhook received', 'ok' => count($webhooks) > 0, 'detail' => count($webhooks) > 0 ? 'Latest delivery recorded in webhook-events.log.' : 'Send webhook.test from the merchant Developer API workspace.'],
    ];
}

function lqds_step_count(array $steps): array
{
    $done = 0;
    foreach ($steps as $step) if (!empty($step['ok'])) $done++;
    return [$done, count($steps)];
}

try {
    $action = (string)($_POST['action'] ?? '');
    $questId = (string)($_POST['quest_id'] ?? '');
    $quest = $quests[$questId] ?? null;
    if ($action === 'list_programs') {
        $result = lqr_action_list_programs($state, $config);
        $message = 'Program list requested from Microgifter.';
    } elseif ($action === 'sandbox_link') {
        $result = lqr_action_sandbox_link($state, $config, $user);
        $message = 'Sandbox linked account requested.';
    } elseif ($action === 'start_account_link') {
        $result = lqr_action_start_account_link($state, $config, $user);
        if (is_array($result['body']) && !empty($result['body']['link_url'])) {
            lqr_save_state($state);
            header('Location: ' . (string)$result['body']['link_url']);
            exit;
        }
        $message = 'Production account-link request started.';
    } elseif ($action === 'issue_reward' && is_array($quest)) {
        [$available, $reason] = lqr_quest_availability($quest, $state, $questId);
        if (!$available) throw new RuntimeException('Quest is not playable: ' . $reason);
        $result = lqr_action_issue_reward($state, $config, $user, $questId, $quest);
        $message = 'Reward issue requested through the Public Distribution API.';
    } elseif ($action === 'check_status' && is_array($quest)) {
        $result = lqr_action_check_status($state, $config, $user, $questId);
        $message = 'Reward status checked.';
    }
    lqr_save_state($state);
    $state = lqr_load_state();
    $user = lqr_get_user($state, $config, $userId);
} catch (Throwable $e) {
    $error = $e->getMessage();
    lqr_add_event($state, 'developer_starter.error', $error);
    lqr_save_state($state);
}

$wallet = lqr_wallet_rewards($user, $quests);
$steps = lqds_launch_steps($config, $user, $quests, $state);
[$stepsDone, $stepsTotal] = lqds_step_count($steps);
$latestWebhooks = lqds_latest_webhooks(5);
$lastResponse = $result ?? ($state['last_response'] ?? null);
$isLinked = trim((string)($user['linked_account_id'] ?? '')) !== '';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Developer Starter Portal | Local Quest Rewards</title><link rel="stylesheet" href="assets/portal.css"><style>.lq-dev-tabs{display:block}.lq-dev-tab-input{position:absolute;opacity:0;pointer-events:none}.lq-dev-tabbar{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 16px}.lq-dev-tabbar label{min-height:38px;border:1px solid var(--lq-border);background:#fff;border-radius:999px;padding:0 14px;display:inline-flex;align-items:center;font-size:13px;font-weight:950;cursor:pointer;color:#344054}.lq-dev-panel{display:none}.lq-dev-panels{display:grid;gap:16px}#lq-tab-setup:checked~.lq-dev-tabbar label[for=lq-tab-setup],#lq-tab-api:checked~.lq-dev-tabbar label[for=lq-tab-api],#lq-tab-link:checked~.lq-dev-tabbar label[for=lq-tab-link],#lq-tab-quest:checked~.lq-dev-tabbar label[for=lq-tab-quest],#lq-tab-wallet:checked~.lq-dev-tabbar label[for=lq-tab-wallet],#lq-tab-webhooks:checked~.lq-dev-tabbar label[for=lq-tab-webhooks],#lq-tab-launch:checked~.lq-dev-tabbar label[for=lq-tab-launch]{background:var(--lq-pink);border-color:var(--lq-pink);color:#fff}#lq-tab-setup:checked~.lq-dev-panels .lq-panel-setup,#lq-tab-api:checked~.lq-dev-panels .lq-panel-api,#lq-tab-link:checked~.lq-dev-panels .lq-panel-link,#lq-tab-quest:checked~.lq-dev-panels .lq-panel-quest,#lq-tab-wallet:checked~.lq-dev-panels .lq-panel-wallet,#lq-tab-webhooks:checked~.lq-dev-panels .lq-panel-webhooks,#lq-tab-launch:checked~.lq-dev-panels .lq-panel-launch{display:block}.lq-dev-status-list{display:grid;gap:10px}.lq-dev-status{display:flex;justify-content:space-between;gap:14px;border:1px solid var(--lq-border);border-radius:8px;padding:12px 14px;background:#fff}.lq-dev-status strong{font-size:14px}.lq-dev-status small{display:block;color:var(--lq-muted);margin-top:4px}.lq-dev-code{background:#101828;color:#f8fafc;border-radius:8px;padding:14px;overflow:auto;font-size:12px;line-height:1.55;white-space:pre-wrap}.lq-dev-table{display:grid;gap:10px}.lq-dev-endpoint{display:grid;grid-template-columns:120px minmax(0,1fr) 220px 130px;gap:10px;align-items:center;border:1px solid var(--lq-border);border-radius:8px;padding:12px;background:#fff}.lq-dev-endpoint code{word-break:break-all}.lq-dev-method{font-weight:950;color:var(--lq-pink)}@media(max-width:900px){.lq-dev-endpoint{grid-template-columns:1fr}.lq-dev-status{display:block}}</style></head><body class="lq-portal">
<div class="lq-shell"><header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-icon-btn" href="wallet.php">◉<span class="lq-badge"><?= number_format(count($wallet)) ?></span></a><a class="lq-upgrade" href="index.php">Quest Board</a><span class="lq-user-pill"><span class="lq-avatar"><?= lqr_h(strtoupper(substr((string)$user['display_name'],0,1))) ?></span><?= lqr_h((string)$user['display_name']) ?></span></div></header>
<aside class="lq-sidebar"><a class="lq-side-link" href="index.php"><span class="lq-side-icon">⌂</span><span class="lq-side-label">Quest board</span></a><a class="lq-side-link" href="wallet.php"><span class="lq-side-icon">◉</span><span class="lq-side-label">Wallet</span></a><a class="lq-side-link active" href="developer-starter.php"><span class="lq-side-icon">API</span><span class="lq-side-label">Developer Starter</span></a><a class="lq-side-link" href="webhook.php"><span class="lq-side-icon">◷</span><span class="lq-side-label">Webhook status</span></a><a class="lq-side-link" href="admin.php"><span class="lq-side-icon">⚙</span><span class="lq-side-label">Admin</span></a></aside>
<main class="lq-main"><section class="lq-page-head"><span class="lq-eyebrow">Developer Starter Portal</span><h1>Full Public API demo flow</h1><p>Use this page as the outside-developer proof: configure credentials, link a user, complete a quest, issue a reward, claim from the wallet, verify webhooks, and clear launch QA.</p></section>
<?php if ($message): ?><div class="lq-notice"><?= lqr_h($message) ?></div><?php endif; ?><?php if ($error): ?><div class="lq-notice error"><?= lqr_h($error) ?></div><?php endif; ?>
<div class="lq-kpis"><div class="lq-kpi"><span>Launch checks</span><strong><?= number_format($stepsDone) ?>/<?= number_format($stepsTotal) ?></strong></div><div class="lq-kpi"><span>Linked account</span><strong><?= $isLinked ? 'Yes' : 'No' ?></strong></div><div class="lq-kpi"><span>Wallet rewards</span><strong><?= number_format(count($wallet)) ?></strong></div><div class="lq-kpi"><span>Mode</span><strong><?= lqr_h((string)($config['mode'] ?? 'test')) ?></strong></div></div>
<section class="lq-card lq-dev-tabs">
<input class="lq-dev-tab-input" type="radio" name="lq_dev_tab" id="lq-tab-setup" checked><input class="lq-dev-tab-input" type="radio" name="lq_dev_tab" id="lq-tab-api"><input class="lq-dev-tab-input" type="radio" name="lq_dev_tab" id="lq-tab-link"><input class="lq-dev-tab-input" type="radio" name="lq_dev_tab" id="lq-tab-quest"><input class="lq-dev-tab-input" type="radio" name="lq_dev_tab" id="lq-tab-wallet"><input class="lq-dev-tab-input" type="radio" name="lq_dev_tab" id="lq-tab-webhooks"><input class="lq-dev-tab-input" type="radio" name="lq_dev_tab" id="lq-tab-launch">
<div class="lq-dev-tabbar"><label for="lq-tab-setup">Setup</label><label for="lq-tab-api">API Map</label><label for="lq-tab-link">Account Link</label><label for="lq-tab-quest">Quest Issue</label><label for="lq-tab-wallet">Wallet Claim</label><label for="lq-tab-webhooks">Webhooks</label><label for="lq-tab-launch">Launch QA</label></div>
<div class="lq-dev-panels">
<section class="lq-dev-panel lq-panel-setup"><div class="lq-card-head"><div><h2>Setup snapshot</h2><p>These values should be copied from the merchant Developer API workspace after the test app, credential, webhook URL, and signing value exist.</p></div><span class="lq-pill <?= $stepsDone === $stepsTotal ? 'green' : 'amber' ?>"><?= $stepsDone === $stepsTotal ? 'Ready' : 'In progress' ?></span></div><div class="lq-grid-2"><div class="lq-panel"><h2>Config</h2><div class="lq-row"><span>Base URL</span><strong><?= lqr_h((string)($config['base_url'] ?? '')) ?></strong></div><div class="lq-row"><span>Program</span><strong><?= lqr_h((string)($config['default_program_id'] ?? '')) ?></strong></div><div class="lq-row"><span>Template</span><strong><?= lqr_h((string)($config['default_template_id'] ?? '')) ?></strong></div><div class="lq-row"><span>Webhook secret</span><strong><?= lqds_configured($config,'webhook_secret') ? 'Configured' : 'Missing' ?></strong></div></div><div class="lq-panel"><h2>Recommended scopes</h2><p>Keep these enabled for the test app before cloning to live.</p><div class="lq-dev-code">distribution:programs.read
distribution:rewards.issue
distribution:rewards.status
distribution:webhooks.manage</div></div></div></section>
<section class="lq-dev-panel lq-panel-api"><div class="lq-card-head"><div><h2>Current Public API endpoint map</h2><p>This upgrades Local Quest to match the current docs script and makes the full third-party flow visible in one place.</p></div><form method="post"><button class="lq-btn primary" name="action" value="list_programs">List programs</button></form></div><div class="lq-dev-table"><?php foreach (lqds_api_endpoints() as $endpoint): ?><div class="lq-dev-endpoint"><span class="lq-dev-method"><?= lqr_h($endpoint['method']) ?></span><code><?= lqr_h($endpoint['path']) ?></code><span><?= lqr_h($endpoint['scope']) ?></span><span class="lq-pill green"><?= lqr_h($endpoint['status']) ?></span></div><?php endforeach; ?></div></section>
<section class="lq-dev-panel lq-panel-link"><div class="lq-card-head"><div><h2>Account linking</h2><p>Sandbox linking is for developer validation. Production linking sends the user through Microgifter consent and returns to link-callback.php.</p></div><span class="lq-pill <?= $isLinked ? 'green' : 'amber' ?>"><?= $isLinked ? 'Linked' : 'Not linked' ?></span></div><div class="lq-grid-2"><div class="lq-panel"><h2>Current user</h2><div class="lq-row"><span>Email</span><strong><?= lqr_h((string)$user['email']) ?></strong></div><div class="lq-row"><span>External user ID</span><strong><?= lqr_h((string)$user['external_user_id']) ?></strong></div><div class="lq-row"><span>Linked account</span><strong><?= lqr_h((string)($user['linked_account_id'] ?: 'Not connected')) ?></strong></div></div><div class="lq-panel"><h2>Link actions</h2><form method="post"><button class="lq-link-row" name="action" value="start_account_link">Start production consent link <span>→</span></button></form><?php if(!empty($config['allow_sandbox_shortcut'])): ?><form method="post"><button class="lq-link-row" name="action" value="sandbox_link">Create sandbox linked account <span>→</span></button></form><?php endif; ?></div></div></section>
<section class="lq-dev-panel lq-panel-quest"><div class="lq-card-head"><div><h2>Quest action → reward issue</h2><p>Use existing quest rules, signed QR controls, completion state, idempotency, and Public API reward issue.</p></div><a class="lq-btn soft" href="index.php">Open quest board</a></div><div class="lq-stack"><?php foreach ($quests as $questId => $quest): ?><?php if(!is_array($quest)) continue; $reward=is_array($user['rewards'][$questId]??null)?$user['rewards'][$questId]:[]; [$canIssue,$issueReason]=lqr_can_issue_reward($user,(string)$questId,$quest,$config); ?><article class="lq-item"><div><h3><?= lqr_h((string)($quest['title'] ?? $questId)) ?></h3><div class="lq-meta">Event type: <code><?= lqr_h((string)($quest['event_type'] ?? '')) ?></code><br>Program <code><?= lqr_h(lqr_quest_program_id($quest,$config) ?: 'not configured') ?></code> · Template <code><?= lqr_h(lqr_quest_template_id($quest,$config) ?: 'not configured') ?></code><br><?= lqr_h($reward ? 'Reward '.$reward['reward_id'].' · '.$reward['status'] : $issueReason) ?></div></div><form method="post"><input type="hidden" name="quest_id" value="<?= lqr_h((string)$questId) ?>"><div class="lq-actions"><button class="lq-btn amber" name="action" value="issue_reward" <?= $canIssue ? '' : 'disabled' ?>>Issue reward</button><button class="lq-btn dark" name="action" value="check_status" <?= $reward ? '' : 'disabled' ?>>Status</button></div></form></article><?php endforeach; ?></div></section>
<section class="lq-dev-panel lq-panel-wallet"><div class="lq-card-head"><div><h2>Wallet claim integration</h2><p>The wallet posts claim QR/geolocation evidence to the current claim endpoint and stores the Microgifter response in local SQL state.</p></div><a class="lq-btn primary" href="wallet.php">Open wallet</a></div><div class="lq-stack"><?php if(!$wallet): ?><div class="lq-item"><div><h3>No wallet rewards yet</h3><div class="lq-meta">Complete a quest and issue a reward first.</div></div></div><?php endif; ?><?php foreach($wallet as $reward): ?><div class="lq-item"><div><h3><?= lqr_h($reward['reward_label']) ?></h3><div class="lq-meta">Reward <code><?= lqr_h($reward['reward_id']) ?></code><br>Item <code><?= lqr_h($reward['item_id'] ?: 'refresh status') ?></code><br>Claim report: <?= lqr_h($reward['claim_report_status']) ?></div></div><span class="lq-pill <?= $reward['claim_status']==='claimed_in_quest_app' ? 'green' : 'amber' ?>"><?= lqr_h($reward['claim_status']) ?></span></div><?php endforeach; ?></div></section>
<section class="lq-dev-panel lq-panel-webhooks"><div class="lq-card-head"><div><h2>Webhook receiver</h2><p>Point the merchant Developer API webhook URL at this app's webhook.php endpoint and rotate the signing value into config.php.</p></div><a class="lq-btn soft" href="webhook.php">Webhook status</a></div><div class="lq-dev-code">Webhook URL: <?= lqr_h(rtrim((string)($config['app_public_url'] ?? ''), '/')) ?>/webhook.php
Signature base string: &lt;timestamp&gt;.&lt;raw request body&gt;
Required headers: X-Microgifter-Event, X-Microgifter-Delivery, X-Microgifter-Timestamp, X-Microgifter-Signature, X-Microgifter-Signature-Version</div><div class="lq-stack" style="margin-top:14px"><?php if(!$latestWebhooks): ?><div class="lq-item"><div><h3>No webhook deliveries recorded</h3><div class="lq-meta">Send webhook.test from the merchant Developer API workspace.</div></div></div><?php endif; ?><?php foreach($latestWebhooks as $hook): ?><div class="lq-item"><div><h3><?= lqr_h((string)($hook['event'] ?? 'webhook')) ?></h3><div class="lq-meta">Delivery <?= lqr_h((string)($hook['delivery'] ?? '')) ?><br>Verified: <?= !empty($hook['verified']) ? 'yes' : 'no' ?><br>Received <?= lqr_h((string)($hook['received_at'] ?? '')) ?></div></div><span class="lq-pill <?= !empty($hook['verified']) ? 'green' : 'amber' ?>"><?= !empty($hook['verified']) ? 'Verified' : 'Rejected' ?></span></div><?php endforeach; ?></div></section>
<section class="lq-dev-panel lq-panel-launch"><div class="lq-card-head"><div><h2>Launch QA checklist</h2><p>This mirrors the current public launch checklist so the demo platform can prove sandbox readiness before cloning to a live app.</p></div><span class="lq-pill <?= $stepsDone === $stepsTotal ? 'green' : 'amber' ?>"><?= number_format($stepsDone) ?>/<?= number_format($stepsTotal) ?></span></div><div class="lq-dev-status-list"><?php foreach($steps as $step): ?><div class="lq-dev-status"><div><strong><?= lqr_h($step['label']) ?></strong><small><?= lqr_h($step['detail']) ?></small></div><span class="lq-pill <?= $step['ok'] ? 'green' : 'amber' ?>"><?= $step['ok'] ? 'Pass' : 'Needs work' ?></span></div><?php endforeach; ?></div></section>
</div></section>
<?php if($lastResponse!==null): ?><section class="lq-card" style="margin-top:22px"><h2>Last API response</h2><pre><?= lqr_h(json_encode($lastResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></section><?php endif; ?>
</main></div></body></html>
