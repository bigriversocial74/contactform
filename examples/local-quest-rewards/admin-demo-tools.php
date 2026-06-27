<?php
declare(strict_types=1);
require __DIR__ . '/app.php';

$config = lqr_config();
if (empty($_SESSION['lqr_admin_authed'])) {
    header('Location: admin.php');
    exit;
}

$message = null;
$error = null;
$state = lqr_load_state();

function lqadt_demo_user(array $config): array
{
    $userId = 'demo_user_partner_handoff';
    return [
        'id' => $userId,
        'display_name' => 'Partner Demo User',
        'email' => 'partner-demo@example.test',
        'password_hash' => password_hash('local-quest-demo', PASSWORD_DEFAULT),
        'external_user_id' => 'lqr_partner_demo_user',
        'linked_account_id' => 'sandbox_linked_partner_demo',
        'link_status' => 'sandbox_linked',
        'linked_at' => gmdate('c'),
        'completed_quests' => [
            'coffee_checkin' => gmdate('c'),
        ],
        'rewards' => [
            'coffee_checkin' => [
                'reward_id' => 'reward_partner_demo_1001',
                'external_event_id' => 'coffee_checkin:lqr_partner_demo_user',
                'status' => 'delivered',
                'item_id' => 'item_partner_demo_1001',
                'item_status' => 'delivered',
                'claim_status' => 'available_in_app',
                'claim_report_status' => 'not_reported',
                'microgifter_event_id' => '',
                'response' => ['reward_id' => 'reward_partner_demo_1001', 'status' => 'queued', 'pppm_item_id' => 'item_partner_demo_1001'],
                'status_response' => ['id' => 'reward_partner_demo_1001', 'status' => 'delivered', 'jobs' => [['pppm_item_id' => 'item_partner_demo_1001']]],
                'claim_report_response' => [],
                'issued_at' => gmdate('c'),
                'last_checked_at' => gmdate('c'),
                'claimed_at' => '',
            ],
        ],
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
    ];
}

function lqadt_seed_webhook_log(): void
{
    $entry = [
        'received_at' => gmdate('c'),
        'verified' => true,
        'event' => 'webhook.test',
        'delivery' => 'del_partner_demo_seed',
        'timestamp' => (string)time(),
        'signature_version' => 'v1',
        'body' => [
            'event' => 'webhook.test',
            'message' => 'Seed webhook evidence for Local Quest demo handoff.',
            'reward_id' => 'reward_partner_demo_1001',
            'item_id' => 'item_partner_demo_1001',
        ],
    ];
    file_put_contents(__DIR__ . '/webhook-events.log', json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

try {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'seed_demo') {
        $user = lqadt_demo_user($config);
        lqr_put_user($state, $user);
        lqr_add_event($state, 'demo.seeded', 'Partner demo data seeded.', ['user_id' => $user['id'], 'reward_id' => 'reward_partner_demo_1001']);
        lqr_save_state($state);
        lqadt_seed_webhook_log();
        $message = 'Seeded partner demo participant, linked account, completed quest, reward, and webhook log evidence.';
        $state = lqr_load_state();
    } elseif ($action === 'reset_demo') {
        $confirm = trim((string)($_POST['confirm'] ?? ''));
        if ($confirm !== 'RESET LOCAL QUEST DEMO') throw new RuntimeException('Type RESET LOCAL QUEST DEMO to confirm reset.');
        unset($state['users']['demo_user_partner_handoff']);
        if (isset($state['users_by_email']['partner-demo@example.test'])) unset($state['users_by_email']['partner-demo@example.test']);
        lqr_add_event($state, 'demo.reset', 'Partner demo seed data removed.');
        lqr_save_state($state);
        $message = 'Removed partner demo seed user and reward records. Existing real participant records were not touched.';
        $state = lqr_load_state();
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$users = is_array($state['users'] ?? null) ? $state['users'] : [];
$demoExists = isset($users['demo_user_partner_handoff']);
$eventCount = count(is_array($state['events'] ?? null) ? $state['events'] : []);
$webhookEntries = is_file(__DIR__ . '/webhook-events.log') ? count(file(__DIR__ . '/webhook-events.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : 0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Demo Tools | Local Quest Rewards</title>
<link rel="stylesheet" href="assets/portal.css">
<style>
.lq-demo-tools-hero{background:linear-gradient(135deg,#101828,#211816);color:#fff;border-radius:14px;padding:28px;margin-bottom:22px}.lq-demo-tools-hero h1{font-size:clamp(34px,5vw,58px);line-height:.95;letter-spacing:-.07em;margin:10px 0}.lq-demo-tools-hero p{color:#d9e2f2;max-width:920px}.lq-demo-tool-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.lq-danger{border-color:#fecdd3;background:#fff1f2}.lq-danger h2{color:#be123c}@media(max-width:900px){.lq-demo-tool-grid{grid-template-columns:1fr}}
</style>
</head>
<body class="lq-portal">
<div class="lq-shell">
<header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="admin.php">Admin</a><a class="lq-upgrade" href="start.php">Launcher</a></div></header>
<aside class="lq-sidebar"><a class="lq-side-link" href="admin.php"><span class="lq-side-icon">⚙</span><span class="lq-side-label">Admin</span></a><a class="lq-side-link" href="admin-developer-readiness.php"><span class="lq-side-icon">QA</span><span class="lq-side-label">Readiness</span></a><a class="lq-side-link active" href="admin-demo-tools.php"><span class="lq-side-icon">DB</span><span class="lq-side-label">Demo Tools</span></a><a class="lq-side-link" href="runtime-diagnostics.php"><span class="lq-side-icon">✓</span><span class="lq-side-label">Diagnostics</span></a></aside>
<main class="lq-main">
<section class="lq-demo-tools-hero"><span class="lq-eyebrow">Admin utilities</span><h1>Seed a clean partner demo.</h1><p>Create a predictable participant, linked account, completed quest, reward record, and webhook log evidence for sales demos or developer handoff reviews.</p><div class="lq-actions"><a class="lq-btn primary" href="admin-developer-readiness.php">Review readiness</a><a class="lq-btn soft" href="runtime-diagnostics.php">Diagnostics</a></div></section>
<?php if ($message): ?><div class="lq-notice"><?= lqr_h($message) ?></div><?php endif; ?><?php if ($error): ?><div class="lq-notice error"><?= lqr_h($error) ?></div><?php endif; ?>
<div class="lq-kpis"><div class="lq-kpi"><span>Demo user</span><strong><?= $demoExists ? 'Yes' : 'No' ?></strong></div><div class="lq-kpi"><span>Total users</span><strong><?= number_format(count($users)) ?></strong></div><div class="lq-kpi"><span>Events</span><strong><?= number_format($eventCount) ?></strong></div><div class="lq-kpi"><span>Webhook log</span><strong><?= number_format($webhookEntries) ?></strong></div></div>
<section class="lq-demo-tool-grid"><article class="lq-card"><div class="lq-card-head"><div><h2>Seed partner demo</h2><p>Adds one deterministic demo user with one completed quest and one delivered reward. Existing users remain untouched.</p></div><span class="lq-pill <?= $demoExists ? 'green' : 'amber' ?>"><?= $demoExists ? 'Seeded' : 'Ready' ?></span></div><form method="post"><button class="lq-btn primary" name="action" value="seed_demo" style="width:100%">Seed demo data</button></form><div class="lq-row"><span>Email</span><strong>partner-demo@example.test</strong></div><div class="lq-row"><span>Password</span><strong>local-quest-demo</strong></div><div class="lq-row"><span>Reward</span><strong>reward_partner_demo_1001</strong></div></article><article class="lq-card lq-danger"><div class="lq-card-head"><div><h2>Reset seeded demo only</h2><p>Removes the deterministic seed user. It does not clear real participants or production test records.</p></div><span class="lq-pill amber">Guarded</span></div><form method="post"><label class="lq-label">Type confirmation</label><input class="lq-input" name="confirm" placeholder="RESET LOCAL QUEST DEMO"><button class="lq-btn dark" name="action" value="reset_demo" style="width:100%;margin-top:12px">Reset seeded demo</button></form></article></section>
</main>
</div>
</body>
</html>
