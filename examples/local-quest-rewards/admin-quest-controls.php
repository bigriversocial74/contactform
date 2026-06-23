<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/quest-controls.php';

$config = lqr_config();
$state = lqr_load_state();
$quests = lqr_quests();
$message = null;
$error = null;

function lqrc_admin_settings(array $config): array
{
    $admin = is_array($config['admin'] ?? null) ? $config['admin'] : [];
    return ['username'=>(string)($admin['username'] ?? 'admin'), 'password'=>(string)($admin['password'] ?? 'change-me-admin-password'), 'password_hash'=>(string)($admin['password_hash'] ?? '')];
}
function lqrc_admin_authed(): bool { return !empty($_SESSION['lqr_admin_authed']); }
function lqrc_password_ok(string $password, array $admin): bool
{
    return ($admin['password_hash'] ?? '') !== '' ? password_verify($password, (string)$admin['password_hash']) : hash_equals((string)$admin['password'], $password);
}

$admin = lqrc_admin_settings($config);
try {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'admin_login') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if (!hash_equals((string)$admin['username'], $username) || !lqrc_password_ok($password, $admin)) throw new RuntimeException('Invalid admin login.');
        $_SESSION['lqr_admin_authed'] = true;
        $_SESSION['lqr_admin_username'] = $username;
        lqr_add_event($state, 'admin.quest_controls_login', 'Quest controls admin signed in.', ['username'=>$username]);
        lqr_save_state($state);
        header('Location: admin-quest-controls.php');
        exit;
    }
    if ($action !== '' && !lqrc_admin_authed()) throw new RuntimeException('Admin login required.');
    if ($action === 'save_controls') {
        $questId = (string)($_POST['quest_id'] ?? '');
        lqr_update_quest_controls_file($questId, [
            'is_active' => !empty($_POST['is_active']),
            'sponsor' => trim((string)($_POST['sponsor'] ?? '')),
            'starts_at' => trim((string)($_POST['starts_at'] ?? '')),
            'ends_at' => trim((string)($_POST['ends_at'] ?? '')),
            'max_total_completions' => max(0, (int)($_POST['max_total_completions'] ?? 0)),
            'max_total_rewards' => max(0, (int)($_POST['max_total_rewards'] ?? 0)),
            'featured' => !empty($_POST['featured']),
            'visibility' => in_array((string)($_POST['visibility'] ?? 'public'), ['public','hidden','invite_only'], true) ? (string)$_POST['visibility'] : 'public',
        ]);
        lqr_add_event($state, 'admin.quest_controls_saved', 'Quest controls updated.', ['quest_id'=>$questId]);
        lqr_save_state($state);
        $quests = lqr_quests();
        $message = 'Quest controls saved.';
    }
} catch (Throwable $e) { $error = $e->getMessage(); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Quest Controls</title><style>body{margin:0;background:#071225;color:#f5f9ff;font-family:Arial,sans-serif}.wrap{width:min(1220px,94%);margin:0 auto;padding:32px 0}.card{background:#0d1b2f;border:1px solid #24415f;border-radius:20px;padding:18px;margin:12px 0}.grid{display:grid;grid-template-columns:1fr 360px;gap:16px}.top{display:flex;justify-content:space-between;gap:16px}.btn,button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 12px;border-radius:12px;background:#172b47;color:#f5f9ff;border:1px solid #24415f;font-weight:800;text-decoration:none;cursor:pointer}.green{background:#4ade80;color:#062113;border:0}.red{background:#fb7185;color:#28050c;border:0}input,select{width:100%;min-height:38px;margin-top:5px;border-radius:10px;border:1px solid #24415f;background:#07192d;color:#f5f9ff;padding:6px 9px}label{display:block;margin-top:9px;font-size:12px;font-weight:800;color:#c8dbef}.row{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.1)}small,p{color:#9db3cc}.notice{padding:10px 12px;border-radius:12px;background:#10243d}.tag{display:inline-flex;padding:5px 9px;border-radius:999px;background:#172b47;font-size:12px}.live{background:rgba(74,222,128,.18);color:#c7ffd9}.off{background:rgba(251,113,133,.18);color:#ffd7df}@media(max-width:900px){.grid,.top{display:block}}</style></head><body>
<?php if (!lqrc_admin_authed()): ?><div class="wrap"><div class="card" style="max-width:480px;margin:10vh auto"><h1>Quest Controls Login</h1><?php if($error):?><p class="notice"><?= lqr_h($error) ?></p><?php endif;?><form method="post"><label>Username<input name="username" required></label><label>Password<input type="password" name="password" required></label><button class="green" name="action" value="admin_login" style="width:100%;margin-top:12px">Sign in</button></form><p><a class="btn" href="cover.php">Back to app</a></p></div></div><?php exit; endif; ?>
<div class="wrap"><header class="top"><div><h1>Quest Controls</h1><p>Run multiple quests at once with active states, schedules, sponsor groups, and global caps.</p></div><p><a class="btn" href="admin.php">Admin home</a> <a class="btn" href="index.php">Quest board</a></p></header><?php if($message):?><p class="notice"><?= lqr_h($message) ?></p><?php endif;?><?php if($error):?><p class="notice"><?= lqr_h($error) ?></p><?php endif;?>
<?php foreach($quests as $questId=>$quest): if(!is_array($quest)) continue; $controls=lqr_quest_controls($quest); [$available,$reason]=lqr_quest_availability($quest,$state,(string)$questId); $metrics=lqr_quest_metrics($state,(string)$questId); ?>
<article class="card grid"><div><span class="tag <?= $available?'live':'off' ?>"><?= lqr_h($reason) ?></span><?php if(!empty($controls['featured'])):?><span class="tag">Featured</span><?php endif;?><h2><?= lqr_h((string)$quest['title']) ?></h2><p><?= lqr_h((string)$quest['description']) ?></p><div class="row"><span>Sponsor</span><strong><?= lqr_h((string)($controls['sponsor'] ?: ($quest['merchant'] ?? ''))) ?></strong></div><div class="row"><span>Completions</span><strong><?= number_format($metrics['completions']) ?><?= (int)$controls['max_total_completions']>0?' / '.number_format((int)$controls['max_total_completions']):'' ?></strong></div><div class="row"><span>Rewards</span><strong><?= number_format($metrics['rewards']) ?><?= (int)$controls['max_total_rewards']>0?' / '.number_format((int)$controls['max_total_rewards']):'' ?></strong></div><div class="row"><span>Claims</span><strong><?= number_format($metrics['claims']) ?></strong></div><div class="row"><span>Window</span><strong><?= lqr_h(($controls['starts_at'] ?: 'now') . ' -> ' . ($controls['ends_at'] ?: 'open')) ?></strong></div></div><form method="post"><input type="hidden" name="quest_id" value="<?= lqr_h((string)$questId) ?>"><label><input type="checkbox" name="is_active" value="1" <?= !empty($controls['is_active'])?'checked':'' ?>> Active</label><label><input type="checkbox" name="featured" value="1" <?= !empty($controls['featured'])?'checked':'' ?>> Featured</label><label>Visibility<select name="visibility"><option value="public" <?= $controls['visibility']==='public'?'selected':'' ?>>Public</option><option value="hidden" <?= $controls['visibility']==='hidden'?'selected':'' ?>>Hidden</option><option value="invite_only" <?= $controls['visibility']==='invite_only'?'selected':'' ?>>Invite only</option></select></label><label>Sponsor / group<input name="sponsor" value="<?= lqr_h((string)$controls['sponsor']) ?>"></label><label>Starts at<input name="starts_at" value="<?= lqr_h((string)$controls['starts_at']) ?>" placeholder="2026-07-01 09:00"></label><label>Ends at<input name="ends_at" value="<?= lqr_h((string)$controls['ends_at']) ?>" placeholder="2026-07-07 23:59"></label><label>Max total completions<input type="number" min="0" name="max_total_completions" value="<?= lqr_h((string)$controls['max_total_completions']) ?>"></label><label>Max total rewards<input type="number" min="0" name="max_total_rewards" value="<?= lqr_h((string)$controls['max_total_rewards']) ?>"></label><button class="green" name="action" value="save_controls" style="width:100%;margin-top:12px">Save controls</button></form></article>
<?php endforeach; ?></div></body></html>
