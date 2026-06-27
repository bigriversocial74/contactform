<?php
declare(strict_types=1);
require __DIR__ . '/app.php';

$config = lqr_config();

function lqrd_mask(string $value): string
{
    $value = trim($value);
    if ($value === '') return 'missing';
    if (strlen($value) <= 10) return str_repeat('•', max(4, strlen($value)));
    return substr($value, 0, 4) . '••••' . substr($value, -4);
}

function lqrd_config_ok(array $config, string $key): bool
{
    $value = lqr_config_value($config, $key);
    return $value !== '' && !str_contains($value, 'replace_me') && !str_contains($value, 'replace_with');
}

function lqrd_check(string $label, bool $ok, string $detail, string $fix = ''): array
{
    return ['label' => $label, 'ok' => $ok, 'detail' => $detail, 'fix' => $fix];
}

$dbOk = false;
$dbDetail = 'Not checked.';
try {
    $pdo = lqr_sql_db($config);
    $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
    $dbOk = true;
    $dbDetail = 'Connected to MySQL/MariaDB ' . $version;
} catch (Throwable $e) {
    $dbDetail = $e->getMessage();
}

$stateOk = false;
$stateDetail = 'Not checked.';
$userCount = 0;
$rewardCount = 0;
$eventCount = 0;
try {
    $state = lqr_load_state();
    $stateOk = true;
    $users = is_array($state['users'] ?? null) ? $state['users'] : [];
    $userCount = count($users);
    foreach ($users as $user) {
        if (!is_array($user)) continue;
        foreach ((is_array($user['rewards'] ?? null) ? $user['rewards'] : []) as $reward) {
            if (is_array($reward)) $rewardCount++;
        }
    }
    $eventCount = count(is_array($state['events'] ?? null) ? $state['events'] : []);
    $stateDetail = 'State loaded with ' . number_format($userCount) . ' users, ' . number_format($rewardCount) . ' rewards, ' . number_format($eventCount) . ' events.';
} catch (Throwable $e) {
    $stateDetail = $e->getMessage();
}

$storage = is_array($config['storage'] ?? null) ? $config['storage'] : [];
$appUrl = rtrim((string)($config['app_public_url'] ?? ''), '/');
$webhookUrl = $appUrl !== '' ? $appUrl . '/webhook.php' : 'missing';
$checks = [
    lqrd_check('SQL storage driver', in_array(strtolower((string)($storage['driver'] ?? '')), ['mysql','mariadb','pdo_mysql'], true), 'Configured driver: ' . (string)($storage['driver'] ?? 'missing'), 'Use the SQL-only runtime driver.'),
    lqrd_check('Database connection', $dbOk, $dbDetail, 'Run install.php or update config.php database credentials.'),
    lqrd_check('App state load', $stateOk, $stateDetail, 'Confirm schema tables exist and database user has permissions.'),
    lqrd_check('App public URL', lqrd_config_ok($config, 'app_public_url'), $appUrl ?: 'missing', 'Set the public URL that external callbacks can reach.'),
    lqrd_check('Microgifter base URL', lqrd_config_ok($config, 'base_url'), (string)($config['base_url'] ?? 'missing'), 'Set base_url to the Microgifter environment.'),
    lqrd_check('Bearer credential', lqrd_config_ok($config, 'api_key'), lqrd_mask((string)($config['api_key'] ?? '')), 'Create a test credential in the merchant Developer API workspace.'),
    lqrd_check('Default Distribution Program', lqrd_config_ok($config, 'default_program_id'), (string)($config['default_program_id'] ?? 'missing'), 'Attach a default program to the developer app.'),
    lqrd_check('Default reward template', lqrd_config_ok($config, 'default_template_id'), (string)($config['default_template_id'] ?? 'missing'), 'Attach a reward template to the default program.'),
    lqrd_check('Webhook signing value', lqrd_config_ok($config, 'webhook_secret'), lqrd_mask((string)($config['webhook_secret'] ?? '')), 'Rotate/copy the webhook signing value from the Developer API workspace.'),
    lqrd_check('Webhook endpoint', $appUrl !== '', $webhookUrl, 'Configure this URL in the merchant Developer API workspace.'),
];
$passed = 0;
foreach ($checks as $check) if (!empty($check['ok'])) $passed++;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Runtime Diagnostics | Local Quest Rewards</title>
<link rel="stylesheet" href="assets/portal.css">
<style>
.lq-diagnostics-hero{background:linear-gradient(135deg,#101828,#211816);color:#fff;border-radius:14px;padding:28px;margin-bottom:22px}.lq-diagnostics-hero h1{font-size:clamp(34px,5vw,58px);line-height:.95;letter-spacing:-.07em;margin:10px 0}.lq-diagnostics-hero p{color:#d9e2f2;max-width:920px}.lq-diagnostic-list{display:grid;gap:12px}.lq-diagnostic-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:center;background:#fff;border:1px solid var(--lq-border);border-radius:12px;padding:15px;box-shadow:var(--lq-shadow)}.lq-diagnostic-row h3{margin:0;font-size:15px}.lq-diagnostic-row p{margin:5px 0 0;color:var(--lq-muted);font-size:13px}.lq-diagnostic-fix{display:block;margin-top:6px;color:#8a6100}.lq-diagnostic-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px}@media(max-width:900px){.lq-diagnostic-row{grid-template-columns:1fr}.lq-diagnostic-metrics{grid-template-columns:1fr 1fr}}@media(max-width:640px){.lq-diagnostic-metrics{grid-template-columns:1fr}}
</style>
</head>
<body class="lq-portal">
<div class="lq-shell">
<header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="start.php">Launcher</a><a class="lq-upgrade" href="webhook-tools.php">Webhook Tools</a></div></header>
<aside class="lq-sidebar"><a class="lq-side-link" href="start.php"><span class="lq-side-icon">▶</span><span class="lq-side-label">Launcher</span></a><a class="lq-side-link active" href="runtime-diagnostics.php"><span class="lq-side-icon">✓</span><span class="lq-side-label">Diagnostics</span></a><a class="lq-side-link" href="api-examples.php"><span class="lq-side-icon">{}</span><span class="lq-side-label">API Examples</span></a><a class="lq-side-link" href="webhook-tools.php"><span class="lq-side-icon">◇</span><span class="lq-side-label">Webhook Tools</span></a><a class="lq-side-link" href="admin-demo-tools.php"><span class="lq-side-icon">DB</span><span class="lq-side-label">Demo Tools</span></a></aside>
<main class="lq-main">
<section class="lq-diagnostics-hero"><span class="lq-eyebrow">Runtime polish</span><h1>Check the demo before handoff.</h1><p>Validate the SQL runtime, state load, API credential presence, default program/template, and callback setup without exposing secret values.</p><div class="lq-actions"><a class="lq-btn primary" href="start.php">Back to launcher</a><a class="lq-btn soft" href="admin-developer-readiness.php">Admin readiness</a></div></section>
<div class="lq-diagnostic-metrics"><div class="lq-kpi"><span>Checks</span><strong><?= number_format($passed) ?>/<?= number_format(count($checks)) ?></strong></div><div class="lq-kpi"><span>Users</span><strong><?= number_format($userCount) ?></strong></div><div class="lq-kpi"><span>Rewards</span><strong><?= number_format($rewardCount) ?></strong></div><div class="lq-kpi"><span>Events</span><strong><?= number_format($eventCount) ?></strong></div></div>
<section class="lq-card"><div class="lq-card-head"><div><h2>Runtime checks</h2><p>These checks are intentionally read-only.</p></div><span class="lq-pill <?= $passed === count($checks) ? 'green' : 'amber' ?>"><?= $passed === count($checks) ? 'Ready' : 'Needs setup' ?></span></div><div class="lq-diagnostic-list"><?php foreach ($checks as $check): ?><article class="lq-diagnostic-row"><div><h3><?= lqr_h($check['label']) ?></h3><p><?= lqr_h($check['detail']) ?></p><?php if (empty($check['ok']) && $check['fix'] !== ''): ?><small class="lq-diagnostic-fix">Fix: <?= lqr_h($check['fix']) ?></small><?php endif; ?></div><span class="lq-pill <?= !empty($check['ok']) ? 'green' : 'amber' ?>"><?= !empty($check['ok']) ? 'Pass' : 'Open' ?></span></article><?php endforeach; ?></div></section>
</main>
</div>
</body>
</html>
