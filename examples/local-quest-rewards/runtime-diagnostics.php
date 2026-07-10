<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/webhook-storage.php';

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

$requiredTables = ['lqr_admin_users','lqr_users','lqr_link_states','lqr_quests','lqr_quest_completions','lqr_rewards','lqr_reward_claims','lqr_signed_code_replays','lqr_webhook_deliveries','lqr_admin_audit_events','lqr_events','lqr_app_state','lqr_admin_password_resets','lqr_schema_versions'];
$dbOk = false;
$dbDetail = 'Not checked.';
$schemaOk = false;
$schemaDetail = 'Not checked.';
$stateOk = false;
$stateDetail = 'Not checked.';
$userCount = 0;
$rewardCount = 0;
$eventCount = 0;
$webhookCount = 0;
$schemaVersion = '';

try {
    $pdo = lqr_sql_db($config);
    $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
    $dbOk = true;
    $dbDetail = 'Connected to MySQL/MariaDB ' . $version;
    $missing = [];
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    foreach ($requiredTables as $table) {
        $stmt->execute([$table]);
        if ((int)$stmt->fetchColumn() < 1) $missing[] = $table;
    }
    $schemaOk = $missing === [];
    $schemaDetail = $schemaOk ? 'All required production-foundation tables are present.' : 'Missing: ' . implode(', ', $missing);
    if ($schemaOk) {
        $schemaVersion = (string)$pdo->query('SELECT version_key FROM lqr_schema_versions ORDER BY applied_at DESC,id DESC LIMIT 1')->fetchColumn();
        $webhookCount = lqr_webhook_delivery_count($pdo);
    }
} catch (Throwable $e) {
    $dbDetail = $e->getMessage();
    $schemaDetail = $e->getMessage();
}

try {
    $state = lqr_load_state();
    $stateOk = true;
    $users = is_array($state['users'] ?? null) ? $state['users'] : [];
    $userCount = count($users);
    foreach ($users as $user) {
        if (!is_array($user)) continue;
        $rewardCount += count(is_array($user['rewards'] ?? null) ? $user['rewards'] : []);
    }
    $eventCount = count(is_array($state['events'] ?? null) ? $state['events'] : []);
    $stateDetail = 'Loaded ' . number_format($userCount) . ' users, ' . number_format($rewardCount) . ' rewards, and ' . number_format($eventCount) . ' recent events.';
} catch (Throwable $e) {
    $stateDetail = $e->getMessage();
}

$storage = is_array($config['storage'] ?? null) ? $config['storage'] : [];
$appUrl = rtrim((string)($config['app_public_url'] ?? ''), '/');
$mode = (string)($config['mode'] ?? 'test');
$checks = [
    lqrd_check('SQL-only storage driver', in_array(strtolower((string)($storage['driver'] ?? '')), ['mysql','mariadb','pdo_mysql'], true), 'Configured driver: ' . (string)($storage['driver'] ?? 'missing'), 'Run the production installer or set storage.driver to mysql.'),
    lqrd_check('Database connection', $dbOk, $dbDetail, 'Verify database host, database name, user, and password.'),
    lqrd_check('Production schema', $schemaOk, $schemaDetail, 'Run install.php or import database/local_quest_production_foundation_v1.sql.'),
    lqrd_check('Schema version', $schemaVersion !== '', $schemaVersion ?: 'No schema version recorded.', 'Apply the production foundation schema migration.'),
    lqrd_check('Application state', $stateOk, $stateDetail, 'Confirm every required table exists and the database user can read/write it.'),
    lqrd_check('Installer locked', is_file(__DIR__ . '/.installed.lock'), is_file(__DIR__ . '/.installed.lock') ? '.installed.lock is present.' : 'Installer lock is missing.', 'Complete installation and remove any temporary .install-unlock file.'),
    lqrd_check('Install unlock removed', !is_file(__DIR__ . '/.install-unlock'), is_file(__DIR__ . '/.install-unlock') ? '.install-unlock is still present.' : 'No temporary installer unlock exists.', 'Remove .install-unlock immediately after intentional maintenance.'),
    lqrd_check('Config protection', is_file(__DIR__ . '/config.php') && !is_readable(__DIR__ . '/config.php') === false, 'config.php exists; filesystem mode should be 0600 when supported.', 'Confirm the web server can read config.php but it is not publicly served.'),
    lqrd_check('Public application URL', lqrd_config_ok($config, 'app_public_url'), $appUrl ?: 'missing', 'Set the public HTTPS URL used for callbacks.'),
    lqrd_check('HTTPS in live mode', $mode !== 'live' || str_starts_with($appUrl, 'https://'), $mode === 'live' ? ($appUrl ?: 'missing') : 'Not required in test mode.', 'Use HTTPS before switching to live mode.'),
    lqrd_check('Microgifter base URL', lqrd_config_ok($config, 'base_url'), (string)($config['base_url'] ?? 'missing'), 'Set the target Microgifter environment.'),
    lqrd_check('Bearer credential', lqrd_config_ok($config, 'api_key'), lqrd_mask((string)($config['api_key'] ?? '')), 'Create a Developer API credential and keep it server-side.'),
    lqrd_check('Distribution Program', lqrd_config_ok($config, 'default_program_id'), (string)($config['default_program_id'] ?? 'missing'), 'Attach an approved Distribution Program.'),
    lqrd_check('Reward template', lqrd_config_ok($config, 'default_template_id'), (string)($config['default_template_id'] ?? 'missing'), 'Choose an approved reward template.'),
    lqrd_check('Webhook signing value', lqrd_config_ok($config, 'webhook_secret'), lqrd_mask((string)($config['webhook_secret'] ?? '')), 'Rotate and copy the webhook signing value.'),
    lqrd_check('Webhook SQL storage', $schemaOk, number_format($webhookCount) . ' delivery records stored.', 'Apply the production schema.'),
];
$passed = count(array_filter($checks, static fn(array $check): bool => !empty($check['ok'])));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Runtime Diagnostics | Local Quest Rewards</title><link rel="stylesheet" href="assets/portal.css"><style>.lq-diagnostics-hero{background:linear-gradient(135deg,#0f2f25,#18583f);color:#fff;border-radius:18px;padding:30px;margin-bottom:22px}.lq-diagnostics-hero h1{font-size:clamp(34px,5vw,58px);line-height:.95;letter-spacing:-.07em;margin:10px 0}.lq-diagnostics-hero p{color:#d5e8de;max-width:920px}.lq-diagnostic-list{display:grid;gap:12px}.lq-diagnostic-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:center;background:#fff;border:1px solid var(--lq-border);border-radius:12px;padding:15px;box-shadow:var(--lq-shadow)}.lq-diagnostic-row h3{margin:0;font-size:15px}.lq-diagnostic-row p{margin:5px 0 0;color:var(--lq-muted);font-size:13px}.lq-diagnostic-fix{display:block;margin-top:6px;color:#8a6100}.lq-diagnostic-metrics{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:22px}@media(max-width:1000px){.lq-diagnostic-metrics{grid-template-columns:1fr 1fr}.lq-diagnostic-row{grid-template-columns:1fr}}@media(max-width:640px){.lq-diagnostic-metrics{grid-template-columns:1fr}}</style></head><body class="lq-portal"><div class="lq-shell"><header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="start.php">Launcher</a><a class="lq-upgrade" href="admin-developer-readiness.php">Readiness</a><a class="lq-upgrade" href="cover.php">Public site</a></div></header><aside class="lq-sidebar"><a class="lq-side-link" href="start.php"><span class="lq-side-icon">▶</span><span class="lq-side-label">Launcher</span></a><a class="lq-side-link active" href="runtime-diagnostics.php"><span class="lq-side-icon">✓</span><span class="lq-side-label">Diagnostics</span></a><a class="lq-side-link" href="webhook.php"><span class="lq-side-icon">◷</span><span class="lq-side-label">Webhooks</span></a><a class="lq-side-link" href="admin.php"><span class="lq-side-icon">⚙</span><span class="lq-side-label">Admin</span></a></aside><main class="lq-main"><section class="lq-diagnostics-hero"><span class="lq-eyebrow">Production diagnostics</span><h1>Check the complete application foundation.</h1><p>Validate installation lockdown, SQL schema, application state, API credentials, public callback configuration, and protected webhook storage without exposing secret values.</p><div class="lq-actions"><a class="lq-btn primary" href="start.php">Back to launcher</a><a class="lq-btn soft" href="admin-developer-readiness.php">Admin readiness</a></div></section><div class="lq-diagnostic-metrics"><div class="lq-kpi"><span>Checks</span><strong><?= number_format($passed) ?>/<?= number_format(count($checks)) ?></strong></div><div class="lq-kpi"><span>Users</span><strong><?= number_format($userCount) ?></strong></div><div class="lq-kpi"><span>Rewards</span><strong><?= number_format($rewardCount) ?></strong></div><div class="lq-kpi"><span>Events</span><strong><?= number_format($eventCount) ?></strong></div><div class="lq-kpi"><span>Webhooks</span><strong><?= number_format($webhookCount) ?></strong></div></div><section class="lq-card"><div class="lq-card-head"><div><h2>Runtime checks</h2><p>These checks are read-only.</p></div><span class="lq-pill <?= $passed === count($checks) ? 'green' : 'amber' ?>"><?= $passed === count($checks) ? 'Ready' : 'Needs attention' ?></span></div><div class="lq-diagnostic-list"><?php foreach($checks as $check): ?><article class="lq-diagnostic-row"><div><h3><?= lqr_h($check['label']) ?></h3><p><?= lqr_h($check['detail']) ?></p><?php if(empty($check['ok']) && $check['fix'] !== ''): ?><small class="lq-diagnostic-fix">Fix: <?= lqr_h($check['fix']) ?></small><?php endif; ?></div><span class="lq-pill <?= !empty($check['ok']) ? 'green' : 'amber' ?>"><?= !empty($check['ok']) ? 'Pass' : 'Open' ?></span></article><?php endforeach; ?></div></section></main></div></body></html>
