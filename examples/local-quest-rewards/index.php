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

try {
    $action = (string)($_POST['action'] ?? '');
    $questId = (string)($_POST['quest_id'] ?? '');
    $quest = $quests[$questId] ?? null;

    if ($action === 'identify') {
        $message = lqr_action_identify($state, $config, $user);
    } elseif ($action === 'list_programs') {
        $result = lqr_action_list_programs($state, $config);
        $message = 'Programs response received.';
    } elseif ($action === 'sandbox_link') {
        $result = lqr_action_sandbox_link($state, $config, $user);
        $message = 'Sandbox linked account requested.';
    } elseif ($action === 'complete_quest' && is_array($quest)) {
        $message = lqr_action_complete_quest($state, $config, $user, $questId, $quest);
    } elseif ($action === 'issue_reward' && is_array($quest)) {
        $result = lqr_action_issue_reward($state, $config, $user, $questId, $quest);
        $message = 'Reward issue requested.';
    } elseif ($action === 'check_status' && is_array($quest)) {
        $result = lqr_action_check_status($state, $config, $user, $questId);
        $message = 'Reward status checked.';
    } elseif ($action === 'reset_user') {
        unset($state['users'][$userId]);
        $user = lqr_default_user($config, $userId);
        lqr_put_user($state, $user);
        lqr_add_event($state, 'demo.reset', 'Current demo user was reset.');
        $message = 'Demo user reset.';
    }
    lqr_save_state($state);
    $user = lqr_get_user($state, $config, $userId);
} catch (Throwable $e) {
    $error = $e->getMessage();
    lqr_add_event($state, 'demo.error', $error);
    lqr_save_state($state);
}

$configWarnings = [];
foreach (['base_url','api_key','default_program_id','default_template_id'] as $key) {
    $value = lqr_config_value($config, $key);
    if ($value === '' || str_contains($value, 'replace_me') || str_contains($value, 'replace_with')) {
        $configWarnings[] = $key;
    }
}
$lastResponse = $result ?? ($state['last_response'] ?? null);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= lqr_h((string)($config['app_name'] ?? 'Local Quest Rewards')) ?></title>
<style>
:root{--bg:#071225;--panel:#0d1b2f;--card:#10243d;--line:#24415f;--text:#f5f9ff;--muted:#9db3cc;--blue:#5aa7ff;--green:#4ade80;--amber:#fbbf24;--red:#fb7185;--white:#fff}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 10% 0,rgba(90,167,255,.25),transparent 28%),radial-gradient(circle at 85% 10%,rgba(74,222,128,.14),transparent 30%),var(--bg);color:var(--text);font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{width:min(1240px,94%);margin:0 auto;padding:34px 0 70px}.hero{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:18px;align-items:stretch}.panel,.card{background:rgba(13,27,47,.92);border:1px solid rgba(148,180,213,.22);border-radius:24px;box-shadow:0 24px 70px rgba(0,0,0,.28)}.panel{padding:30px}.eyebrow{display:inline-flex;border:1px solid rgba(90,167,255,.45);border-radius:999px;padding:8px 12px;color:#b9dcff;background:rgba(90,167,255,.12);font-size:12px;font-weight:950;text-transform:uppercase;letter-spacing:.08em}h1{margin:18px 0 0;font-size:clamp(42px,7vw,78px);line-height:.92;letter-spacing:-.08em}h2{margin:0 0 12px;font-size:26px;letter-spacing:-.04em}h3{margin:0 0 8px;font-size:18px}p{color:var(--muted);line-height:1.6}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.btn,button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 14px;border:0;border-radius:13px;background:var(--blue);color:#06111f;font-weight:950;text-decoration:none;cursor:pointer}.btn-dark,button.dark{background:#172b47;color:var(--text);border:1px solid var(--line)}.btn-green,button.green{background:var(--green);color:#062113}.btn-amber,button.amber{background:var(--amber);color:#241700}.btn-red,button.red{background:var(--red);color:#28050c}.grid{display:grid;grid-template-columns:360px minmax(0,1fr);gap:18px;margin-top:18px}.quest-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.card{padding:20px}.mini{display:grid;gap:8px;margin-top:12px}.stat{padding:12px;border-radius:16px;background:rgba(255,255,255,.055);border:1px solid rgba(255,255,255,.08)}.stat strong{display:block;color:var(--white);font-size:13px}.stat span{display:block;margin-top:4px;color:var(--muted);font-size:13px;word-break:break-word}label{display:block;margin-top:12px;color:#c8dbef;font-size:12px;font-weight:950}input{width:100%;min-height:42px;margin-top:6px;padding:0 12px;border-radius:12px;border:1px solid var(--line);background:#07192d;color:var(--text);font:inherit}.notice{margin-top:12px;padding:12px 14px;border-radius:16px;border:1px solid rgba(90,167,255,.32);background:rgba(90,167,255,.11);color:#cfe7ff}.error{border-color:rgba(251,113,133,.45);background:rgba(251,113,133,.12);color:#ffd7df}.warning{border-color:rgba(251,191,36,.45);background:rgba(251,191,36,.12);color:#ffe6a6}.tag{display:inline-flex;align-items:center;min-height:26px;border-radius:999px;padding:0 10px;background:rgba(255,255,255,.08);color:#cfe0f4;font-size:12px;font-weight:850}.tag.done{background:rgba(74,222,128,.15);color:#c7ffd9}.tag.wait{background:rgba(251,191,36,.15);color:#ffe6a6}.tag.fail{background:rgba(251,113,133,.15);color:#ffd3dc}.quest-card{display:flex;flex-direction:column;gap:12px}.quest-card p{margin:0}.quest-card form{display:grid;gap:8px;margin-top:auto}.quest-meta{display:flex;gap:8px;flex-wrap:wrap}.reward{padding:12px;border-radius:16px;background:rgba(90,167,255,.09);border:1px solid rgba(90,167,255,.18)}pre{overflow:auto;margin:0;padding:16px;border-radius:16px;background:#050b14;color:#d9ecff;font-size:12px;line-height:1.55;white-space:pre-wrap}.events{display:grid;gap:10px;max-height:430px;overflow:auto}.event{padding:12px;border-radius:16px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08)}.event strong{display:block}.event small{color:var(--muted)}.footer-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px}@media(max-width:1040px){.quest-grid{grid-template-columns:1fr 1fr}.hero,.grid,.footer-grid{grid-template-columns:1fr}}@media(max-width:680px){.quest-grid{grid-template-columns:1fr}h1{font-size:42px}.wrap{width:92%}}
</style>
</head>
<body>
<div class="wrap">
  <section class="hero">
    <div class="panel">
      <span class="eyebrow">Microgifter Demo Ecosystem</span>
      <h1>Local Quest Rewards</h1>
      <p>Complete local quests, map each action to a merchant-approved Microgift reward rule, issue the reward through the Public Distribution API, and verify webhook delivery.</p>
      <div class="actions">
        <form method="post"><button name="action" value="list_programs">List Microgifter programs</button></form>
        <form method="post"><button class="dark" name="action" value="sandbox_link">Create sandbox linked account</button></form>
        <a class="btn btn-dark" href="webhook-events.log">Webhook log</a>
      </div>
      <?php if ($message): ?><div class="notice"><?= lqr_h($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="notice error"><?= lqr_h($error) ?></div><?php endif; ?>
      <?php if ($configWarnings): ?><div class="notice warning">Config needs values for: <?= lqr_h(implode(', ', $configWarnings)) ?>. Copy <code>config.example.php</code> to <code>config.php</code> and add real sandbox values.</div><?php endif; ?>
    </div>
    <aside class="panel">
      <h2>Demo user</h2>
      <form method="post">
        <label>Display name</label>
        <input name="display_name" value="<?= lqr_h((string)$user['display_name']) ?>">
        <label>External user ID</label>
        <input name="external_user_id" value="<?= lqr_h((string)$user['external_user_id']) ?>">
        <div class="actions">
          <button name="action" value="identify" class="green">Save user</button>
          <button name="action" value="reset_user" class="red">Reset</button>
        </div>
      </form>
      <div class="mini">
        <div class="stat"><strong>Local app user</strong><span><?= lqr_h((string)$user['id']) ?></span></div>
        <div class="stat"><strong>Microgifter linked account</strong><span><?= lqr_h((string)($user['linked_account_id'] ?: 'Not linked yet')) ?></span></div>
        <div class="stat"><strong>Mode</strong><span><?= lqr_h((string)($config['mode'] ?? 'sandbox')) ?></span></div>
      </div>
    </aside>
  </section>

  <section class="grid">
    <aside class="card">
      <h2>Permission model</h2>
      <p>The demo app has a local permission decision before it calls Microgifter. Microgifter still performs the final permission and capacity checks.</p>
      <div class="mini">
        <div class="stat"><strong>Local permission</strong><span>Quest completed, linked account exists, one reward per quest/user.</span></div>
        <div class="stat"><strong>Microgifter permission</strong><span>Credential scope, app mode, program access, template membership, limits, and idempotency.</span></div>
        <div class="stat"><strong>Reward rule</strong><span>Each quest maps to a program ID and template ID.</span></div>
      </div>
    </aside>

    <main>
      <div class="quest-grid">
        <?php foreach ($quests as $questId => $quest): ?>
          <?php
            $completed = !empty($user['completed_quests'][$questId]);
            $reward = is_array($user['rewards'][$questId] ?? null) ? $user['rewards'][$questId] : [];
            [$canIssue, $issueReason] = lqr_can_issue_reward($user, (string)$questId, $quest, $config);
          ?>
          <article class="card quest-card">
            <div class="quest-meta">
              <span class="tag <?= $completed ? 'done' : 'wait' ?>"><?= $completed ? 'Completed' : 'Open quest' ?></span>
              <span class="tag"><?= lqr_h((string)$quest['difficulty']) ?></span>
            </div>
            <h3><?= lqr_h((string)$quest['title']) ?></h3>
            <p><?= lqr_h((string)$quest['description']) ?></p>
            <div class="reward">
              <strong><?= lqr_h((string)$quest['reward_label']) ?></strong>
              <p>Program: <code><?= lqr_h(lqr_quest_program_id($quest, $config) ?: 'not configured') ?></code></p>
              <p>Template: <code><?= lqr_h(lqr_quest_template_id($quest, $config) ?: 'not configured') ?></code></p>
            </div>
            <?php if ($reward): ?>
              <div class="stat"><strong>Reward</strong><span><?= lqr_h((string)$reward['reward_id']) ?> — <?= lqr_h((string)$reward['status']) ?></span></div>
            <?php else: ?>
              <div class="stat"><strong>Permission</strong><span><?= lqr_h($issueReason) ?></span></div>
            <?php endif; ?>
            <form method="post">
              <input type="hidden" name="quest_id" value="<?= lqr_h((string)$questId) ?>">
              <button name="action" value="complete_quest" class="green">Complete quest</button>
              <button name="action" value="issue_reward" class="amber" <?= $canIssue ? '' : 'disabled' ?>>Issue reward</button>
              <button name="action" value="check_status" class="dark" <?= $reward ? '' : 'disabled' ?>>Check status</button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    </main>
  </section>

  <section class="footer-grid">
    <div class="card">
      <h2>Last API response</h2>
      <?php if ($lastResponse !== null): ?>
        <pre><?= lqr_h(json_encode($lastResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
      <?php else: ?>
        <p>No API response yet. Start by listing programs or creating a sandbox linked account.</p>
      <?php endif; ?>
    </div>
    <div class="card">
      <h2>Event log</h2>
      <div class="events">
        <?php foreach (($state['events'] ?? []) as $event): ?>
          <div class="event">
            <strong><?= lqr_h((string)$event['type']) ?></strong>
            <small><?= lqr_h((string)$event['at']) ?></small>
            <p><?= lqr_h((string)$event['message']) ?></p>
          </div>
        <?php endforeach; ?>
        <?php if (empty($state['events'])): ?><p>No events yet.</p><?php endif; ?>
      </div>
    </div>
  </section>
</div>
</body>
</html>
