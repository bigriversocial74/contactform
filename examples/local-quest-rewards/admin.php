<?php
declare(strict_types=1);
require __DIR__ . '/app.php';

$config = lqr_config();
$state = lqr_load_state();
$message = null;
$error = null;
$section = preg_replace('/[^a-z_]/', '', (string)($_GET['section'] ?? 'dashboard')) ?: 'dashboard';

function lqr_admin_config(array $config): array
{
    $admin = is_array($config['admin'] ?? null) ? $config['admin'] : [];
    return [
        'username' => (string)($admin['username'] ?? 'admin'),
        'password' => (string)($admin['password'] ?? 'change-me-admin-password'),
        'password_hash' => (string)($admin['password_hash'] ?? ''),
    ];
}

function lqr_admin_is_authed(): bool
{
    return !empty($_SESSION['lqr_admin_authed']);
}

function lqr_admin_password_ok(string $password, array $admin): bool
{
    if (($admin['password_hash'] ?? '') !== '') return password_verify($password, (string)$admin['password_hash']);
    return hash_equals((string)($admin['password'] ?? ''), $password);
}

function lqr_admin_flash(array &$state, string $type, string $message, array $context = []): void
{
    lqr_add_event($state, 'admin.' . $type, $message, $context);
}

function lqr_admin_write_quests(array $quests): void
{
    ksort($quests);
    $content = "<?php\nreturn " . var_export($quests, true) . ";\n";
    file_put_contents(__DIR__ . '/quests.php', $content, LOCK_EX);
}

function lqr_admin_post_array(string $key): array
{
    $value = $_POST[$key] ?? [];
    if (is_string($value)) $value = preg_split('/[\s,]+/', $value) ?: [];
    if (!is_array($value)) return [];
    return array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $value), static fn(string $v): bool => $v !== ''));
}

function lqr_admin_stats(array $state, array $quests): array
{
    $users = is_array($state['users'] ?? null) ? $state['users'] : [];
    $linked = 0; $completed = 0; $rewards = 0; $claimed = 0;
    foreach ($users as $user) {
        if (!is_array($user)) continue;
        if (!empty($user['linked_account_id'])) $linked++;
        $completed += count(is_array($user['completed_quests'] ?? null) ? $user['completed_quests'] : []);
        foreach ((is_array($user['rewards'] ?? null) ? $user['rewards'] : []) as $reward) {
            if (!is_array($reward)) continue;
            $rewards++;
            if (($reward['claim_status'] ?? '') === 'claimed_in_quest_app') $claimed++;
        }
    }
    return ['users'=>count($users),'linked'=>$linked,'quests'=>count($quests),'completions'=>$completed,'rewards'=>$rewards,'claimed'=>$claimed,'events'=>count(is_array($state['events'] ?? null) ? $state['events'] : [])];
}

$admin = lqr_admin_config($config);
try {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'admin_login') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if (!hash_equals((string)$admin['username'], $username) || !lqr_admin_password_ok($password, $admin)) throw new RuntimeException('Invalid admin login.');
        $_SESSION['lqr_admin_authed'] = true;
        $_SESSION['lqr_admin_username'] = $username;
        lqr_admin_flash($state, 'login', 'Admin signed in.', ['username'=>$username]);
        lqr_save_state($state);
        header('Location: admin.php');
        exit;
    }
    if ($action === 'admin_logout') {
        unset($_SESSION['lqr_admin_authed'], $_SESSION['lqr_admin_username']);
        lqr_admin_flash($state, 'logout', 'Admin signed out.');
        lqr_save_state($state);
        header('Location: admin.php');
        exit;
    }
    if ($action !== '' && !lqr_admin_is_authed()) throw new RuntimeException('Admin login required.');

    if ($action === 'save_quest') {
        $quests = lqr_quests();
        $key = strtolower(trim((string)($_POST['quest_key'] ?? '')));
        $key = preg_replace('/[^a-z0-9_-]/', '-', $key) ?: '';
        if ($key === '') throw new RuntimeException('Quest key is required.');
        $requiresCompletion = !empty($_POST['requires_completion']);
        $requiresLinkedAccount = !empty($_POST['requires_linked_account']);
        $maxRewards = max(1, (int)($_POST['max_rewards_per_user'] ?? 1));
        $allowedModes = lqr_admin_post_array('allowed_modes') ?: ['test','live'];
        $quests[$key] = [
            'title' => trim((string)($_POST['title'] ?? '')) ?: $key,
            'merchant' => trim((string)($_POST['merchant'] ?? '')),
            'location' => trim((string)($_POST['location'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'event_type' => trim((string)($_POST['event_type'] ?? 'quest.completed')) ?: 'quest.completed',
            'program_id' => trim((string)($_POST['program_id'] ?? '')) ?: null,
            'template_id' => trim((string)($_POST['template_id'] ?? '')) ?: null,
            'reward_label' => trim((string)($_POST['reward_label'] ?? 'Microgift reward')) ?: 'Microgift reward',
            'difficulty' => trim((string)($_POST['difficulty'] ?? 'Easy')) ?: 'Easy',
            'permission' => [
                'requires_completion' => $requiresCompletion,
                'requires_linked_account' => $requiresLinkedAccount,
                'max_rewards_per_user' => $maxRewards,
                'allowed_modes' => $allowedModes,
                'consent_label' => trim((string)($_POST['consent_label'] ?? 'Send one reward for this completed quest.')),
            ],
        ];
        lqr_admin_write_quests($quests);
        lqr_admin_flash($state, 'quest_saved', 'Quest saved.', ['quest_key'=>$key]);
        lqr_save_state($state);
        $message = 'Quest saved and published to quests.php.';
        $section = 'quests';
    } elseif ($action === 'delete_quest') {
        $quests = lqr_quests();
        $key = (string)($_POST['quest_key'] ?? '');
        unset($quests[$key]);
        lqr_admin_write_quests($quests);
        lqr_admin_flash($state, 'quest_deleted', 'Quest deleted.', ['quest_key'=>$key]);
        lqr_save_state($state);
        $message = 'Quest deleted.';
        $section = 'quests';
    } elseif ($action === 'unlink_user') {
        $userId = (string)($_POST['user_id'] ?? '');
        if (!empty($state['users'][$userId]) && is_array($state['users'][$userId])) {
            $state['users'][$userId]['linked_account_id'] = '';
            $state['users'][$userId]['link_status'] = 'admin_unlinked';
            lqr_admin_flash($state, 'user_unlinked', 'User Microgifter link cleared.', ['user_id'=>$userId]);
            lqr_save_state($state);
            $message = 'User Microgifter link cleared.';
        }
        $section = 'users';
    } elseif ($action === 'clear_user_rewards') {
        $userId = (string)($_POST['user_id'] ?? '');
        if (!empty($state['users'][$userId]) && is_array($state['users'][$userId])) {
            $state['users'][$userId]['rewards'] = [];
            lqr_admin_flash($state, 'user_rewards_cleared', 'User wallet cleared.', ['user_id'=>$userId]);
            lqr_save_state($state);
            $message = 'User wallet cleared.';
        }
        $section = 'users';
    } elseif ($action === 'mark_claim_reported') {
        $userId = (string)($_POST['user_id'] ?? '');
        $questId = (string)($_POST['quest_id'] ?? '');
        if (!empty($state['users'][$userId]['rewards'][$questId]) && is_array($state['users'][$userId]['rewards'][$questId])) {
            $state['users'][$userId]['rewards'][$questId]['claim_report_status'] = 'admin_marked_reported';
            $state['users'][$userId]['rewards'][$questId]['claim_status'] = 'claimed_in_quest_app';
            $state['users'][$userId]['rewards'][$questId]['claimed_at'] = gmdate('c');
            lqr_admin_flash($state, 'claim_marked_reported', 'Claim marked reported by admin.', ['user_id'=>$userId,'quest_id'=>$questId]);
            lqr_save_state($state);
            $message = 'Claim marked reported.';
        }
        $section = 'claims';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$quests = lqr_quests();
$stats = lqr_admin_stats($state, $quests);
$editQuestKey = (string)($_GET['edit'] ?? '');
$editQuest = is_array($quests[$editQuestKey] ?? null) ? $quests[$editQuestKey] : null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin | <?= lqr_h((string)($config['app_name'] ?? 'Local Quest Rewards')) ?></title>
<style>
:root{--bg:#071225;--panel:#0d1b2f;--card:#10243d;--line:#24415f;--text:#f5f9ff;--muted:#9db3cc;--blue:#5aa7ff;--green:#4ade80;--amber:#fbbf24;--red:#fb7185}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 12% 0,rgba(90,167,255,.22),transparent 28%),var(--bg);color:var(--text);font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{width:min(1280px,94%);margin:0 auto;padding:32px 0 70px}.top{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:18px}.eyebrow{display:inline-flex;border:1px solid rgba(90,167,255,.45);border-radius:999px;padding:8px 12px;color:#b9dcff;background:rgba(90,167,255,.12);font-size:12px;font-weight:950;text-transform:uppercase;letter-spacing:.08em}h1{margin:12px 0 0;font-size:clamp(36px,5vw,62px);line-height:.95;letter-spacing:-.07em}h2{margin:0 0 12px;font-size:24px;letter-spacing:-.04em}h3{margin:0 0 8px}.panel,.card{background:rgba(13,27,47,.92);border:1px solid rgba(148,180,213,.22);border-radius:22px;box-shadow:0 24px 70px rgba(0,0,0,.24);padding:20px}p,small{color:var(--muted);line-height:1.55}.nav{display:flex;flex-wrap:wrap;gap:8px;margin:18px 0}.nav a,.btn,button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 12px;border:0;border-radius:12px;background:#172b47;color:var(--text);border:1px solid var(--line);font-weight:900;text-decoration:none;cursor:pointer}.nav a.active,.primary{background:var(--blue);color:#06111f;border-color:transparent}.green{background:var(--green);color:#062113;border-color:transparent}.red{background:var(--red);color:#28050c;border-color:transparent}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.two{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:14px}.row{display:flex;justify-content:space-between;gap:14px;padding:11px 0;border-bottom:1px solid rgba(255,255,255,.08)}.row span{color:var(--muted)}.row strong{text-align:right;word-break:break-word}.notice{margin:12px 0;padding:12px 14px;border-radius:14px;background:rgba(90,167,255,.12);border:1px solid rgba(90,167,255,.34);color:#d7ecff}.error{background:rgba(251,113,133,.12);border-color:rgba(251,113,133,.4);color:#ffd7df}label{display:block;margin-top:10px;color:#c8dbef;font-size:12px;font-weight:950}input,textarea,select{width:100%;min-height:40px;margin-top:6px;border-radius:12px;border:1px solid var(--line);background:#07192d;color:var(--text);font:inherit;padding:8px 10px}textarea{min-height:86px}.table{display:grid;gap:10px}.item{padding:14px;border-radius:16px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08)}.item-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.kpi strong{display:block;font-size:30px;letter-spacing:-.04em}.kpi span{color:var(--muted);font-size:13px}.login{width:min(480px,94%);margin:10vh auto}.muted{color:var(--muted)}code{background:rgba(255,255,255,.08);padding:2px 5px;border-radius:6px}@media(max-width:980px){.grid,.two{grid-template-columns:1fr}.top{display:block}}
</style>
</head>
<body>
<?php if (!lqr_admin_is_authed()): ?>
<div class="login panel"><span class="eyebrow">Quest admin</span><h1>Admin login</h1><p>Use the admin credentials from <code>config.php</code>.</p><?php if ($error): ?><div class="notice error"><?= lqr_h($error) ?></div><?php endif; ?><form method="post"><label>Username<input name="username" required></label><label>Password<input name="password" type="password" required></label><button class="primary" name="action" value="admin_login" style="width:100%;margin-top:14px">Sign in</button></form><p><a class="btn" href="cover.php">Back to app</a></p></div>
<?php exit; endif; ?>
<div class="wrap">
  <header class="top"><div><span class="eyebrow">Quest admin backend</span><h1>Local Quest Control Center</h1><p>Manage quest rules, users, wallets, claims, and integration events.</p></div><form method="post"><button class="red" name="action" value="admin_logout">Sign out</button></form></header>
  <nav class="nav"><?php foreach(['dashboard'=>'Dashboard','quests'=>'Quests','users'=>'Users','wallets'=>'Wallets','claims'=>'Claims','events'=>'Events','settings'=>'Settings'] as $key=>$label): ?><a class="<?= $section===$key?'active':'' ?>" href="admin.php?section=<?= lqr_h($key) ?>"><?= lqr_h($label) ?></a><?php endforeach; ?><a href="index.php">Quest board</a><a href="wallet.php">User wallet</a></nav>
  <?php if ($message): ?><div class="notice"><?= lqr_h($message) ?></div><?php endif; ?><?php if ($error): ?><div class="notice error"><?= lqr_h($error) ?></div><?php endif; ?>

  <?php if ($section === 'dashboard'): ?>
    <section class="grid"><?php foreach($stats as $label=>$value): ?><div class="card kpi"><span><?= lqr_h(ucwords(str_replace('_',' ',$label))) ?></span><strong><?= number_format((int)$value) ?></strong></div><?php endforeach; ?></section>
    <section class="card" style="margin-top:14px"><h2>Admin scope</h2><p>This backend controls the third-party Quest app layer: app users, quest definitions, wallet records, app-side claim state, and integration event logs. Microgifter still owns product approval, Distribution Programs, reward ownership, claim/report truth, redemption, and merchant audit history.</p></section>
  <?php elseif ($section === 'quests'): ?>
    <section class="two"><div class="card"><h2>Quest catalog</h2><div class="table"><?php foreach($quests as $key=>$quest): ?><div class="item"><div class="item-head"><div><h3><?= lqr_h((string)$quest['title']) ?></h3><small><?= lqr_h($key) ?> · <?= lqr_h((string)$quest['event_type']) ?></small><p><?= lqr_h((string)$quest['description']) ?></p><p><strong><?= lqr_h((string)$quest['reward_label']) ?></strong> · Program <code><?= lqr_h((string)($quest['program_id'] ?: 'default')) ?></code> · Template <code><?= lqr_h((string)($quest['template_id'] ?: 'default')) ?></code></p></div><div><a class="btn" href="admin.php?section=quests&edit=<?= urlencode((string)$key) ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this quest?')"><input type="hidden" name="quest_key" value="<?= lqr_h((string)$key) ?>"><button class="red" name="action" value="delete_quest">Delete</button></form></div></div></div><?php endforeach; ?></div></div><aside class="card"><h2><?= $editQuest ? 'Edit quest' : 'Create quest' ?></h2><form method="post"><label>Quest key<input name="quest_key" value="<?= lqr_h($editQuestKey) ?>" required></label><label>Title<input name="title" value="<?= lqr_h((string)($editQuest['title'] ?? '')) ?>" required></label><label>Merchant<input name="merchant" value="<?= lqr_h((string)($editQuest['merchant'] ?? '')) ?>"></label><label>Location<input name="location" value="<?= lqr_h((string)($editQuest['location'] ?? '')) ?>"></label><label>Description<textarea name="description"><?= lqr_h((string)($editQuest['description'] ?? '')) ?></textarea></label><label>Event type<input name="event_type" value="<?= lqr_h((string)($editQuest['event_type'] ?? 'quest.completed')) ?>"></label><label>Program ID<input name="program_id" value="<?= lqr_h((string)($editQuest['program_id'] ?? '')) ?>" placeholder="blank = default config"></label><label>Template ID<input name="template_id" value="<?= lqr_h((string)($editQuest['template_id'] ?? '')) ?>" placeholder="blank = default config"></label><label>Reward label<input name="reward_label" value="<?= lqr_h((string)($editQuest['reward_label'] ?? 'Microgift reward')) ?>"></label><label>Difficulty<input name="difficulty" value="<?= lqr_h((string)($editQuest['difficulty'] ?? 'Easy')) ?>"></label><label>Allowed modes<input name="allowed_modes" value="<?= lqr_h(implode(',', (array)($editQuest['permission']['allowed_modes'] ?? ['test','live']))) ?>"></label><label>Max rewards per user<input name="max_rewards_per_user" type="number" min="1" value="<?= lqr_h((string)($editQuest['permission']['max_rewards_per_user'] ?? 1)) ?>"></label><label><input type="checkbox" name="requires_completion" value="1" <?= !empty($editQuest['permission']['requires_completion']) || !$editQuest ? 'checked' : '' ?>> Requires completion</label><label><input type="checkbox" name="requires_linked_account" value="1" <?= !empty($editQuest['permission']['requires_linked_account']) || !$editQuest ? 'checked' : '' ?>> Requires Microgifter link</label><label>Consent label<textarea name="consent_label"><?= lqr_h((string)($editQuest['permission']['consent_label'] ?? 'Send one reward for this completed quest.')) ?></textarea></label><button class="green" name="action" value="save_quest" style="width:100%;margin-top:14px">Save quest</button></form></aside></section>
  <?php elseif ($section === 'users'): ?>
    <section class="card"><h2>User accounts</h2><div class="table"><?php foreach((array)$state['users'] as $userId=>$user): if(!is_array($user)) continue; ?><div class="item"><div class="item-head"><div><h3><?= lqr_h((string)$user['display_name']) ?></h3><small><?= lqr_h((string)$user['email']) ?> · <?= lqr_h((string)$userId) ?></small><p>External: <code><?= lqr_h((string)$user['external_user_id']) ?></code><br>Microgifter: <code><?= lqr_h((string)($user['linked_account_id'] ?: 'not linked')) ?></code> · <?= lqr_h((string)$user['link_status']) ?></p></div><div class="actions"><form method="post"><input type="hidden" name="user_id" value="<?= lqr_h((string)$userId) ?>"><button class="btn" name="action" value="unlink_user">Unlink</button></form><form method="post" onsubmit="return confirm('Clear this user wallet?')"><input type="hidden" name="user_id" value="<?= lqr_h((string)$userId) ?>"><button class="red" name="action" value="clear_user_rewards">Clear wallet</button></form></div></div></div><?php endforeach; ?></div></section>
  <?php elseif ($section === 'wallets' || $section === 'claims'): ?>
    <section class="card"><h2><?= $section === 'claims' ? 'Reward claims' : 'User wallets' ?></h2><div class="table"><?php foreach((array)$state['users'] as $userId=>$user): if(!is_array($user)) continue; foreach((array)($user['rewards'] ?? []) as $questId=>$reward): if(!is_array($reward)) continue; $quest=$quests[$questId]??[]; ?><div class="item"><div class="item-head"><div><h3><?= lqr_h((string)($quest['title'] ?? $questId)) ?></h3><small><?= lqr_h((string)($user['email'] ?? $userId)) ?> · <?= lqr_h((string)($reward['issued_at'] ?? '')) ?></small><p>Reward: <code><?= lqr_h((string)($reward['reward_id'] ?? '')) ?></code><br>Status: <?= lqr_h((string)($reward['status'] ?? 'unknown')) ?> · Claim: <?= lqr_h((string)($reward['claim_status'] ?? 'available')) ?> · Report: <?= lqr_h((string)($reward['claim_report_status'] ?? 'not_reported')) ?></p></div><form method="post"><input type="hidden" name="user_id" value="<?= lqr_h((string)$userId) ?>"><input type="hidden" name="quest_id" value="<?= lqr_h((string)$questId) ?>"><button class="green" name="action" value="mark_claim_reported">Mark reported</button></form></div></div><?php endforeach; endforeach; ?></div></section>
  <?php elseif ($section === 'events'): ?>
    <section class="card"><h2>Event log</h2><div class="table"><?php foreach((array)$state['events'] as $event): if(!is_array($event)) continue; ?><div class="item"><strong><?= lqr_h((string)$event['type']) ?></strong><br><small><?= lqr_h((string)$event['at']) ?></small><p><?= lqr_h((string)$event['message']) ?></p></div><?php endforeach; ?></div></section>
  <?php elseif ($section === 'settings'): ?>
    <section class="grid"><div class="card"><h2>Storage</h2><div class="row"><span>Driver</span><strong><?= lqr_h((string)($config['storage']['driver'] ?? 'json')) ?></strong></div><div class="row"><span>JSON state</span><strong>data/state.json</strong></div><div class="row"><span>SQL schema</span><strong>database/local_quest_rewards.sql</strong></div></div><div class="card"><h2>Microgifter</h2><div class="row"><span>Base URL</span><strong><?= lqr_h((string)$config['base_url']) ?></strong></div><div class="row"><span>Mode</span><strong><?= lqr_h((string)$config['mode']) ?></strong></div><div class="row"><span>Program</span><strong><?= lqr_h((string)$config['default_program_id']) ?></strong></div><div class="row"><span>Template</span><strong><?= lqr_h((string)$config['default_template_id']) ?></strong></div></div><div class="card"><h2>Admin security</h2><p>Change the demo admin password in <code>config.php</code> before exposing this app. For production, use a password hash or move admin identity into <code>lqr_admin_users</code>.</p></div></section>
  <?php endif; ?>
</div>
</body>
</html>
