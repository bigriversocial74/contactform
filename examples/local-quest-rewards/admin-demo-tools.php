<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/admin-auth.php';
require __DIR__ . '/webhook-storage.php';

$config = lqr_config();
$state = lqr_load_state();
$admin = lqr_admin_current($state, $config);
if (!lqr_admin_is_authed() || !$admin) {
    header('Location: admin-credentials.php');
    exit;
}

$message = null;
$error = null;
$demoQuestKey = 'downtown-coffee-checkin';
$demoDeliveryId = 'del_partner_demo_seed';

function lqadt_demo_user(string $questKey): array
{
    $userId = 'demo_user_partner_handoff';
    return [
        'id' => $userId,
        'display_name' => 'Partner Demo User',
        'email' => 'partner-demo@example.test',
        'password_hash' => password_hash('local-quest-demo-2026', PASSWORD_DEFAULT),
        'external_user_id' => 'lqr_partner_demo_user',
        'linked_account_id' => 'sandbox_linked_partner_demo',
        'link_status' => 'sandbox_linked',
        'linked_at' => gmdate('c'),
        'completed_quests' => [$questKey => gmdate('c')],
        'rewards' => [$questKey => [
            'reward_id' => 'reward_partner_demo_1001',
            'external_event_id' => $questKey . ':lqr_partner_demo_user',
            'status' => 'delivered',
            'item_id' => 'item_partner_demo_1001',
            'item_status' => 'delivered',
            'claim_status' => 'available_in_app',
            'claim_report_status' => 'not_reported',
            'microgifter_event_id' => '',
            'response' => ['reward_id' => 'reward_partner_demo_1001', 'status' => 'queued', 'pppm_item_id' => 'item_partner_demo_1001'],
            'status_response' => ['id' => 'reward_partner_demo_1001', 'status' => 'delivered'],
            'claim_report_response' => [],
            'issued_at' => gmdate('c'),
            'last_checked_at' => gmdate('c'),
            'claimed_at' => '',
        ]],
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
    ];
}

$pdo = lqr_sql_db($config);
try {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'seed_demo') {
        $user = lqadt_demo_user($demoQuestKey);
        lqr_put_user($state, $user);
        lqr_add_event($state, 'demo.seeded', 'Partner demo data seeded.', ['user_id' => $user['id'], 'reward_id' => 'reward_partner_demo_1001']);
        lqr_save_state($state);
        lqr_webhook_store_delivery($pdo, [
            'delivery_id' => $demoDeliveryId,
            'event_type' => 'webhook.test',
            'verified' => true,
            'reconciled' => true,
            'reward_id' => 'reward_partner_demo_1001',
            'item_id' => 'item_partner_demo_1001',
            'payload' => ['message' => 'Seed webhook evidence for Local Quest demo handoff.'],
        ]);
        $message = 'Seeded a participant, linked account, completed quest, reward, and SQL webhook evidence.';
        $state = lqr_load_state();
    } elseif ($action === 'reset_demo') {
        if (trim((string)($_POST['confirm'] ?? '')) !== 'RESET LOCAL QUEST DEMO') throw new RuntimeException('Type RESET LOCAL QUEST DEMO to confirm reset.');
        unset($state['users']['demo_user_partner_handoff'], $state['users_by_email']['partner-demo@example.test']);
        lqr_add_event($state, 'demo.reset', 'Partner demo seed data removed.');
        lqr_save_state($state);
        $stmt = $pdo->prepare('DELETE FROM lqr_webhook_deliveries WHERE delivery_id=?');
        $stmt->execute([$demoDeliveryId]);
        $message = 'Removed only the deterministic partner demo user, reward, and webhook evidence.';
        $state = lqr_load_state();
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$users = is_array($state['users'] ?? null) ? $state['users'] : [];
$demoExists = isset($users['demo_user_partner_handoff']);
$webhookEntries = lqr_webhook_delivery_count($pdo);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Admin Demo Tools | Local Quest Rewards</title><link rel="stylesheet" href="assets/portal.css"><style>.lq-demo-tools-hero{background:linear-gradient(135deg,#0f2f25,#18583f);color:#fff;border-radius:18px;padding:30px;margin-bottom:22px}.lq-demo-tools-hero h1{font-size:clamp(34px,5vw,58px);line-height:.95;letter-spacing:-.07em;margin:10px 0}.lq-demo-tools-hero p{color:#d9e7df;max-width:920px}.lq-demo-tool-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.lq-danger{border-color:#fecdd3;background:#fff1f2}.lq-danger h2{color:#be123c}@media(max-width:900px){.lq-demo-tool-grid{grid-template-columns:1fr}}</style></head><body class="lq-portal"><div class="lq-shell"><header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="admin.php">Admin</a><a class="lq-upgrade" href="admin-developer-readiness.php">Readiness</a></div></header><aside class="lq-sidebar"><a class="lq-side-link" href="admin.php"><span class="lq-side-icon">⚙</span><span class="lq-side-label">Admin</span></a><a class="lq-side-link" href="admin-developer-readiness.php"><span class="lq-side-icon">QA</span><span class="lq-side-label">Readiness</span></a><a class="lq-side-link active" href="admin-demo-tools.php"><span class="lq-side-icon">DB</span><span class="lq-side-label">Demo tools</span></a></aside><main class="lq-main"><section class="lq-demo-tools-hero"><span class="lq-eyebrow">Admin-only utility</span><h1>Seed a clean partner demonstration.</h1><p>Create one deterministic test participant, linked account, completed quest, reward record, and database-backed webhook event without touching real users.</p></section><?php if($message): ?><div class="lq-notice"><?= lqr_h($message) ?></div><?php endif; ?><?php if($error): ?><div class="lq-notice error"><?= lqr_h($error) ?></div><?php endif; ?><div class="lq-kpis"><div class="lq-kpi"><span>Demo user</span><strong><?= $demoExists ? 'Yes' : 'No' ?></strong></div><div class="lq-kpi"><span>Total users</span><strong><?= number_format(count($users)) ?></strong></div><div class="lq-kpi"><span>Quest key</span><strong><?= lqr_h($demoQuestKey) ?></strong></div><div class="lq-kpi"><span>Webhook rows</span><strong><?= number_format($webhookEntries) ?></strong></div></div><section class="lq-demo-tool-grid"><article class="lq-card"><h2>Seed partner demo</h2><p>Adds only the deterministic test account and evidence.</p><form method="post"><button class="lq-btn primary" name="action" value="seed_demo" style="width:100%">Seed demo data</button></form><div class="lq-row"><span>Email</span><strong>partner-demo@example.test</strong></div><div class="lq-row"><span>Password</span><strong>local-quest-demo-2026</strong></div></article><article class="lq-card lq-danger"><h2>Reset seeded demo</h2><p>Removes only the deterministic test records.</p><form method="post"><label class="lq-label">Type confirmation</label><input class="lq-input" name="confirm" placeholder="RESET LOCAL QUEST DEMO"><button class="lq-btn dark" name="action" value="reset_demo" style="width:100%;margin-top:12px">Reset seeded demo</button></form></article></section></main></div></body></html>
