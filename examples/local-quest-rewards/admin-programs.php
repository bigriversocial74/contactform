<?php
declare(strict_types=1);
require __DIR__ . '/app.php';

$config = lqr_config();
$state = lqr_load_state();
$quests = lqr_quests();
if (empty($_SESSION['lqr_admin_authed'])) {
    header('Location: admin.php');
    exit;
}
$message = null;
$error = null;

try {
    $action = (string)($_POST['action'] ?? '');
    $settings = lqr_builder_settings($state, $config, $quests);
    if ($action === 'seed_builder') {
        lqr_builder_save_settings($state, lqr_builder_default_settings($config, $quests));
        lqr_add_event($state, 'program_builder.seeded', 'Program builder defaults seeded.');
        lqr_save_state($state);
        $message = 'Program builder defaults saved.';
    } elseif ($action === 'reset_builder') {
        unset($state['merchant_programs']);
        lqr_add_event($state, 'program_builder.reset', 'Program builder settings reset to defaults.');
        lqr_save_state($state);
        $message = 'Program builder settings reset to defaults.';
    } elseif ($action === 'save_program') {
        $key = (string)($_POST['program_key'] ?? '');
        if ($key === '' || empty($settings['programs'][$key])) throw new RuntimeException('Unknown program.');
        $settings['programs'][$key] = array_replace($settings['programs'][$key], [
            'name' => trim((string)($_POST['name'] ?? '')) ?: $settings['programs'][$key]['name'],
            'status' => in_array((string)($_POST['status'] ?? ''), ['sandbox','live_review','disabled'], true) ? (string)$_POST['status'] : 'sandbox',
            'budget' => trim((string)($_POST['budget'] ?? '')) ?: 'merchant controlled',
            'program_id' => trim((string)($_POST['program_id'] ?? '')),
            'template_id' => trim((string)($_POST['template_id'] ?? '')),
            'issue_limit' => trim((string)($_POST['issue_limit'] ?? '')) ?: '1 reward per action',
            'access_mode' => trim((string)($_POST['access_mode'] ?? '')) ?: 'review',
        ]);
        lqr_builder_save_settings($state, $settings);
        lqr_add_event($state, 'program_builder.program_saved', 'Program builder program saved.', ['program_key'=>$key]);
        lqr_save_state($state);
        $message = 'Program saved.';
    } elseif ($action === 'save_mapping') {
        $questId = (string)($_POST['quest_id'] ?? '');
        if ($questId === '' || empty($settings['mappings'][$questId])) throw new RuntimeException('Unknown quest mapping.');
        $programKey = (string)($_POST['program_key'] ?? '');
        if (empty($settings['programs'][$programKey])) throw new RuntimeException('Choose a valid program.');
        $settings['mappings'][$questId] = array_replace($settings['mappings'][$questId], [
            'program_key' => $programKey,
            'status' => in_array((string)($_POST['status'] ?? ''), ['mapped','draft','disabled'], true) ? (string)$_POST['status'] : 'mapped',
            'template_id' => trim((string)($_POST['template_id'] ?? '')),
            'reward_label' => trim((string)($_POST['reward_label'] ?? '')) ?: 'Microgift reward',
        ]);
        lqr_builder_save_settings($state, $settings);
        lqr_add_event($state, 'program_builder.mapping_saved', 'Program builder mapping saved.', ['quest_id'=>$questId]);
        lqr_save_state($state);
        $message = 'Mapping saved.';
    }
    $state = lqr_load_state();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$settings = lqr_builder_settings($state, $config, $quests);
$programs = is_array($settings['programs'] ?? null) ? $settings['programs'] : [];
$mappings = is_array($settings['mappings'] ?? null) ? $settings['mappings'] : [];
$users = is_array($state['users'] ?? null) ? $state['users'] : [];
$issued = 0; $claimed = 0; $linked = 0; $disabled = 0;
foreach ($programs as $program) if (($program['status'] ?? '') === 'disabled') $disabled++;
foreach ($mappings as $mapping) if (($mapping['status'] ?? '') === 'disabled') $disabled++;
foreach ($users as $user) {
    if (!is_array($user)) continue;
    if (!empty($user['linked_account_id'])) $linked++;
    foreach ((is_array($user['rewards'] ?? null) ? $user['rewards'] : []) as $reward) {
        if (!is_array($reward)) continue;
        $issued++;
        if (($reward['claim_status'] ?? '') === 'claimed_in_quest_app') $claimed++;
    }
}
$qa = [
    ['label'=>'Saved builder settings exist','done'=>!empty($state['merchant_programs'])],
    ['label'=>'At least one active program','done'=>count(array_filter($programs, static fn($p) => is_array($p) && ($p['status'] ?? '') !== 'disabled')) > 0],
    ['label'=>'At least one mapped action','done'=>count(array_filter($mappings, static fn($m) => is_array($m) && ($m['status'] ?? '') === 'mapped')) > 0],
    ['label'=>'Sandbox account link tested','done'=>$linked > 0],
    ['label'=>'Reward issue tested','done'=>$issued > 0],
    ['label'=>'Wallet claim/report tested','done'=>$claimed > 0],
];
$done = 0;
foreach ($qa as $item) if (!empty($item['done'])) $done++;
$statusOptions = ['sandbox'=>'sandbox','live_review'=>'live review','disabled'=>'disabled'];
$mappingOptions = ['mapped'=>'mapped','draft'=>'draft','disabled'=>'disabled'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Merchant Program Builder | Local Quest Rewards</title>
<link rel="stylesheet" href="assets/portal.css">
<style>
.lq-pa-hero{background:linear-gradient(135deg,#101828,#211816 46%,#4b1729);color:#fff;border-radius:18px;padding:32px;margin-bottom:22px;box-shadow:var(--lq-shadow)}.lq-pa-hero h1{font-size:clamp(36px,6vw,68px);line-height:.92;letter-spacing:-.08em;margin:12px 0}.lq-pa-hero p{color:#ffe6ef;max-width:900px}.lq-pa-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px}.lq-pa-panels{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;margin-top:22px}.lq-pa-form{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;align-items:end;border:1px solid var(--lq-border);background:#fff;border-radius:14px;padding:14px;margin-top:12px}.lq-pa-form h3{grid-column:1/-1;margin:0}.lq-pa-form label{font-size:12px;font-weight:900;color:var(--lq-muted);text-transform:uppercase;letter-spacing:.06em}.lq-pa-form input,.lq-pa-form select{width:100%;border:1px solid var(--lq-border);border-radius:10px;padding:10px;background:#fff}.lq-pa-form button{min-height:40px}.lq-pa-check{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;border:1px solid var(--lq-border);background:#fff;border-radius:12px;padding:14px;margin-top:10px}.lq-pa-code{background:#071225;color:#eef6ff;border-radius:10px;padding:14px;overflow:auto;font-size:12px;white-space:pre-wrap}@media(max-width:1100px){.lq-pa-grid,.lq-pa-panels,.lq-pa-form{grid-template-columns:1fr}.lq-pa-form h3{grid-column:auto}}
</style>
</head>
<body class="lq-portal"><div class="lq-shell">
<header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="admin-developer-readiness.php">Readiness</a><a class="lq-upgrade" href="developer-starter.php">Developer Starter</a><a class="lq-upgrade" href="api-examples.php">API Examples</a></div></header>
<aside class="lq-sidebar"><a class="lq-side-link" href="admin.php"><span class="lq-side-icon">⚙</span><span class="lq-side-label">Admin</span></a><a class="lq-side-link active" href="admin-programs.php"><span class="lq-side-icon">BLD</span><span class="lq-side-label">Program Builder</span></a><a class="lq-side-link" href="admin-developer-readiness.php"><span class="lq-side-icon">QA</span><span class="lq-side-label">Readiness</span></a><a class="lq-side-link" href="admin-quest-controls.php"><span class="lq-side-icon">☑</span><span class="lq-side-label">Quest controls</span></a><a class="lq-side-link" href="webhook.php"><span class="lq-side-icon">◷</span><span class="lq-side-label">Webhook status</span></a></aside>
<main class="lq-main">
<section class="lq-pa-hero"><span class="lq-eyebrow">Editable merchant controls</span><h1>Program Builder.</h1><p>Create and edit saved Distribution Program controls, map Local Quest actions to reward templates, and disable programs or actions before the reward issue API call is allowed.</p><div class="lq-actions"><form method="post"><button class="lq-btn primary" name="action" value="seed_builder">Seed defaults</button></form><form method="post"><button class="lq-btn soft" name="action" value="reset_builder">Reset to defaults</button></form><a class="lq-btn soft" href="developer-starter.php">Test issue flow</a></div></section>
<?php if ($message): ?><div class="lq-notice"><?= lqr_h($message) ?></div><?php endif; ?><?php if ($error): ?><div class="lq-notice error"><?= lqr_h($error) ?></div><?php endif; ?>
<div class="lq-pa-grid"><div class="lq-kpi"><span>Programs</span><strong><?= number_format(count($programs)) ?></strong></div><div class="lq-kpi"><span>Mappings</span><strong><?= number_format(count($mappings)) ?></strong></div><div class="lq-kpi"><span>Disabled</span><strong><?= number_format($disabled) ?></strong></div><div class="lq-kpi"><span>QA</span><strong><?= number_format($done) ?>/<?= number_format(count($qa)) ?></strong></div></div>
<section class="lq-card"><div class="lq-card-head"><div><h2>Editable Distribution Programs</h2><p>Saved here and used by reward issue gating.</p></div><span class="lq-pill amber">Saved state</span></div><?php foreach ($programs as $key => $program): ?><form class="lq-pa-form" method="post"><input type="hidden" name="action" value="save_program"><input type="hidden" name="program_key" value="<?= lqr_h((string)$key) ?>"><h3><?= lqr_h((string)($program['name'] ?? $key)) ?></h3><div><label>Name</label><input name="name" value="<?= lqr_h((string)($program['name'] ?? '')) ?>"></div><div><label>Status</label><select name="status"><?php foreach ($statusOptions as $value => $label): ?><option value="<?= lqr_h($value) ?>" <?= ($program['status'] ?? '')===$value?'selected':'' ?>><?= lqr_h($label) ?></option><?php endforeach; ?></select></div><div><label>Budget</label><input name="budget" value="<?= lqr_h((string)($program['budget'] ?? '')) ?>"></div><div><label>Issue limit</label><input name="issue_limit" value="<?= lqr_h((string)($program['issue_limit'] ?? '')) ?>"></div><div><label>Program ID</label><input name="program_id" value="<?= lqr_h((string)($program['program_id'] ?? '')) ?>"></div><div><label>Default template</label><input name="template_id" value="<?= lqr_h((string)($program['template_id'] ?? '')) ?>"></div><div><label>Access mode</label><input name="access_mode" value="<?= lqr_h((string)($program['access_mode'] ?? '')) ?>"></div><button class="lq-btn primary">Save program</button></form><?php endforeach; ?></section>
<section class="lq-pa-panels"><article class="lq-card"><div class="lq-card-head"><div><h2>Action → template mapping</h2><p>Disable an action here to block reward issue.</p></div></div><?php foreach ($mappings as $questId => $map): ?><form class="lq-pa-form" method="post"><input type="hidden" name="action" value="save_mapping"><input type="hidden" name="quest_id" value="<?= lqr_h((string)$questId) ?>"><h3><?= lqr_h((string)($quests[$questId]['title'] ?? $questId)) ?></h3><div><label>Program</label><select name="program_key"><?php foreach ($programs as $key => $program): ?><option value="<?= lqr_h((string)$key) ?>" <?= ($map['program_key'] ?? '')===(string)$key?'selected':'' ?>><?= lqr_h((string)($program['name'] ?? $key)) ?></option><?php endforeach; ?></select></div><div><label>Status</label><select name="status"><?php foreach ($mappingOptions as $value => $label): ?><option value="<?= lqr_h($value) ?>" <?= ($map['status'] ?? '')===$value?'selected':'' ?>><?= lqr_h($label) ?></option><?php endforeach; ?></select></div><div><label>Template ID</label><input name="template_id" value="<?= lqr_h((string)($map['template_id'] ?? '')) ?>"></div><div><label>Reward label</label><input name="reward_label" value="<?= lqr_h((string)($map['reward_label'] ?? '')) ?>"></div><button class="lq-btn primary">Save mapping</button></form><?php endforeach; ?></article><article class="lq-card"><div class="lq-card-head"><div><h2>Program QA checklist</h2><p>Signals that the saved merchant controls are ready.</p></div><span class="lq-pill <?= $done===count($qa)?'green':'amber' ?>"><?= number_format($done) ?>/<?= number_format(count($qa)) ?></span></div><?php foreach ($qa as $item): ?><div class="lq-pa-check"><strong><?= lqr_h($item['label']) ?></strong><span class="lq-pill <?= !empty($item['done'])?'green':'amber' ?>"><?= !empty($item['done'])?'Pass':'Open' ?></span></div><?php endforeach; ?><pre class="lq-pa-code" style="margin-top:16px">Builder updated: <?= lqr_h((string)($settings['updated_at'] ?? 'not saved')) ?>
Issue gate: active mappings allow issue; disabled program/action blocks issue before API request.</pre></article></section>
</main></div></body></html>
