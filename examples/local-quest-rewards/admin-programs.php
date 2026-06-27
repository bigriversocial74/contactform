<?php
declare(strict_types=1);
require __DIR__ . '/app.php';

$config = lqr_config();
$state = lqr_load_state();
if (empty($_SESSION['lqr_admin_authed'])) {
    header('Location: admin.php');
    exit;
}

$users = is_array($state['users'] ?? null) ? $state['users'] : [];
$issued = 0;
$claimed = 0;
$linked = 0;
foreach ($users as $user) {
    if (!is_array($user)) continue;
    if (!empty($user['linked_account_id'])) $linked++;
    foreach ((is_array($user['rewards'] ?? null) ? $user['rewards'] : []) as $reward) {
        if (!is_array($reward)) continue;
        $issued++;
        if (($reward['claim_status'] ?? '') === 'claimed_in_quest_app') $claimed++;
    }
}

$defaultProgram = (string)($config['default_program_id'] ?? 'not configured');
$defaultTemplate = (string)($config['default_template_id'] ?? 'not configured');
$webhookUrl = rtrim((string)($config['app_public_url'] ?? ''), '/') . '/webhook.php';

$programs = [
    ['name'=>'Local Quest Demo Program','status'=>'sandbox','budget'=>'$500 demo cap','template'=>$defaultTemplate,'limit'=>'1 reward per quest action','mode'=>'Sandbox testing'],
    ['name'=>'Downtown Coffee Rewards','status'=>'draft','budget'=>'Merchant controlled','template'=>'coffee_checkin reward template','limit'=>'1 per participant per day','mode'=>'Ready for mapping'],
    ['name'=>'Venue Night Rewards','status'=>'draft','budget'=>'Merchant controlled','template'=>'venue_checkin reward template','limit'=>'1 per ticket or QR scan','mode'=>'Needs merchant approval'],
];
$mappings = [
    ['action'=>'coffee_checkin','reward'=>'$5 Coffee Microgift','program'=>'Local Quest Demo Program','status'=>'mapped'],
    ['action'=>'venue_checkin','reward'=>'Free Appetizer Microgift','program'=>'Venue Night Rewards','status'=>'draft'],
    ['action'=>'food_crawl_complete','reward'=>'$10 Dining Microgift','program'=>'Downtown Food Crawl','status'=>'draft'],
];
$qa = [
    ['label'=>'Default Distribution Program selected','done'=>$defaultProgram !== '' && $defaultProgram !== 'not configured'],
    ['label'=>'Default reward template selected','done'=>$defaultTemplate !== '' && $defaultTemplate !== 'not configured'],
    ['label'=>'Sandbox account link tested','done'=>$linked > 0],
    ['label'=>'Reward issue tested','done'=>$issued > 0],
    ['label'=>'Wallet claim/report tested','done'=>$claimed > 0],
    ['label'=>'Webhook URL available','done'=>trim($webhookUrl) !== '/webhook.php'],
];
$done = 0;
foreach ($qa as $item) if (!empty($item['done'])) $done++;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Merchant Program Admin | Local Quest Rewards</title>
<link rel="stylesheet" href="assets/portal.css">
<style>
.lq-pa-hero{background:linear-gradient(135deg,#101828,#211816 46%,#4b1729);color:#fff;border-radius:18px;padding:32px;margin-bottom:22px;box-shadow:var(--lq-shadow)}.lq-pa-hero h1{font-size:clamp(36px,6vw,68px);line-height:.92;letter-spacing:-.08em;margin:12px 0}.lq-pa-hero p{color:#ffe6ef;max-width:900px}.lq-pa-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px}.lq-pa-table{width:100%;border-collapse:separate;border-spacing:0 10px}.lq-pa-table td,.lq-pa-table th{background:#fff;border-top:1px solid var(--lq-border);border-bottom:1px solid var(--lq-border);padding:14px;text-align:left;vertical-align:top}.lq-pa-table th{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:var(--lq-muted)}.lq-pa-table td:first-child,.lq-pa-table th:first-child{border-left:1px solid var(--lq-border);border-radius:10px 0 0 10px}.lq-pa-table td:last-child,.lq-pa-table th:last-child{border-right:1px solid var(--lq-border);border-radius:0 10px 10px 0}.lq-pa-panels{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;margin-top:22px}.lq-pa-check{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;border:1px solid var(--lq-border);background:#fff;border-radius:12px;padding:14px;margin-top:10px}.lq-pa-code{background:#071225;color:#eef6ff;border-radius:10px;padding:14px;overflow:auto;font-size:12px;white-space:pre-wrap}@media(max-width:980px){.lq-pa-grid,.lq-pa-panels{grid-template-columns:1fr}.lq-pa-table,.lq-pa-table tbody,.lq-pa-table tr,.lq-pa-table td,.lq-pa-table th{display:block;width:100%}.lq-pa-table th{display:none}.lq-pa-table td{border:1px solid var(--lq-border);border-radius:0}.lq-pa-table td:first-child{border-radius:10px 10px 0 0}.lq-pa-table td:last-child{border-radius:0 0 10px 10px}}
</style>
</head>
<body class="lq-portal"><div class="lq-shell">
<header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="admin-developer-readiness.php">Readiness</a><a class="lq-upgrade" href="developer-starter.php">Developer Starter</a><a class="lq-upgrade" href="api-examples.php">API Examples</a></div></header>
<aside class="lq-sidebar"><a class="lq-side-link" href="admin.php"><span class="lq-side-icon">⚙</span><span class="lq-side-label">Admin</span></a><a class="lq-side-link active" href="admin-programs.php"><span class="lq-side-icon">PRG</span><span class="lq-side-label">Programs</span></a><a class="lq-side-link" href="admin-developer-readiness.php"><span class="lq-side-icon">QA</span><span class="lq-side-label">Readiness</span></a><a class="lq-side-link" href="admin-quest-controls.php"><span class="lq-side-icon">☑</span><span class="lq-side-label">Quest controls</span></a><a class="lq-side-link" href="webhook.php"><span class="lq-side-icon">◷</span><span class="lq-side-label">Webhook status</span></a></aside>
<main class="lq-main">
<section class="lq-pa-hero"><span class="lq-eyebrow">Merchant controls</span><h1>Distribution Program Admin.</h1><p>Control which Local Quest actions can distribute merchant-approved Microgift rewards, which template is used, and whether the app is in sandbox, live review, or disabled mode.</p><div class="lq-actions"><a class="lq-btn primary" href="developer-starter.php">Test API flow</a><a class="lq-btn soft" href="admin-developer-readiness.php">Review QA</a><a class="lq-btn soft" href="webhook-tools.php">Webhook tools</a></div></section>
<div class="lq-pa-grid"><div class="lq-kpi"><span>Programs</span><strong><?= number_format(count($programs)) ?></strong></div><div class="lq-kpi"><span>Mappings</span><strong><?= number_format(count($mappings)) ?></strong></div><div class="lq-kpi"><span>Issued</span><strong><?= number_format($issued) ?></strong></div><div class="lq-kpi"><span>QA</span><strong><?= number_format($done) ?>/<?= number_format(count($qa)) ?></strong></div></div>
<section class="lq-card"><div class="lq-card-head"><div><h2>Distribution Programs</h2><p>Merchant-facing control surface for app access, budgets, templates, and issue limits.</p></div><span class="lq-pill amber">Admin review</span></div><table class="lq-pa-table"><thead><tr><th>Program</th><th>Status</th><th>Budget</th><th>Template</th><th>Limit</th></tr></thead><tbody><?php foreach ($programs as $program): ?><tr><td><strong><?= lqr_h($program['name']) ?></strong><br><small><?= lqr_h($program['mode']) ?></small></td><td><span class="lq-pill <?= $program['status']==='sandbox'?'green':'amber' ?>"><?= lqr_h($program['status']) ?></span></td><td><?= lqr_h($program['budget']) ?></td><td><?= lqr_h($program['template']) ?></td><td><?= lqr_h($program['limit']) ?></td></tr><?php endforeach; ?></tbody></table></section>
<section class="lq-pa-panels"><article class="lq-card"><div class="lq-card-head"><div><h2>Reward template mapping</h2><p>Map Local Quest actions to merchant-approved Microgift rewards.</p></div></div><?php foreach ($mappings as $map): ?><div class="lq-pa-check"><div><strong><?= lqr_h($map['action']) ?></strong><p><?= lqr_h($map['reward']) ?> · <?= lqr_h($map['program']) ?></p></div><span class="lq-pill <?= $map['status']==='mapped'?'green':'amber' ?>"><?= lqr_h($map['status']) ?></span></div><?php endforeach; ?></article><article class="lq-card"><div class="lq-card-head"><div><h2>Developer app access</h2><p>Current handoff details for the Local Quest app.</p></div></div><pre class="lq-pa-code">Default program: <?= lqr_h($defaultProgram) ?>
Default template: <?= lqr_h($defaultTemplate) ?>
Webhook URL: <?= lqr_h($webhookUrl) ?>
Allowed flow: link → issue → status → claim → webhook</pre></article></section>
<section class="lq-card" style="margin-top:22px"><div class="lq-card-head"><div><h2>Program QA checklist</h2><p>Signals that the merchant distribution setup is ready for outside-app use.</p></div><span class="lq-pill <?= $done===count($qa)?'green':'amber' ?>"><?= number_format($done) ?>/<?= number_format(count($qa)) ?></span></div><?php foreach ($qa as $item): ?><div class="lq-pa-check"><strong><?= lqr_h($item['label']) ?></strong><span class="lq-pill <?= !empty($item['done'])?'green':'amber' ?>"><?= !empty($item['done'])?'Pass':'Open' ?></span></div><?php endforeach; ?></section>
</main></div></body></html>
