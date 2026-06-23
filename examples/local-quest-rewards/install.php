<?php
declare(strict_types=1);
require __DIR__ . '/app.php';

$config = lqr_config();
$checks = [];

function lqr_install_check(array &$checks, string $name, bool $ok, string $detail): void
{
    $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
}

lqr_install_check($checks, 'PHP version', version_compare(PHP_VERSION, '8.1.0', '>='), 'Current: ' . PHP_VERSION . ' / Required: 8.1+');
lqr_install_check($checks, 'PDO extension', extension_loaded('pdo'), extension_loaded('pdo') ? 'PDO is loaded.' : 'PDO is missing.');
lqr_install_check($checks, 'PDO MySQL driver', in_array('mysql', PDO::getAvailableDrivers(), true), 'Available drivers: ' . implode(', ', PDO::getAvailableDrivers()));
lqr_install_check($checks, 'cURL or stream HTTP', function_exists('curl_init') || ini_get('allow_url_fopen'), function_exists('curl_init') ? 'cURL is available.' : 'Using stream HTTP fallback.');

$baseUrl = lqr_config_value($config, 'base_url');
$apiKey = lqr_config_value($config, 'api_key');
$appPublicUrl = lqr_config_value($config, 'app_public_url');
$programId = lqr_config_value($config, 'default_program_id');
$templateId = lqr_config_value($config, 'default_template_id');
$webhookSecret = lqr_config_value($config, 'webhook_secret');

lqr_install_check($checks, 'Microgifter base URL', $baseUrl !== '', $baseUrl !== '' ? $baseUrl : 'Missing base URL.');
lqr_install_check($checks, 'App public URL', $appPublicUrl !== '', $appPublicUrl !== '' ? $appPublicUrl : 'Missing app public URL.');
lqr_install_check($checks, 'API key configured', $apiKey !== '' && !str_contains($apiKey, 'replace_with'), $apiKey !== '' ? 'API key appears configured.' : 'Missing API key.');
lqr_install_check($checks, 'Default program ID', $programId !== '' && !str_contains($programId, 'replace_me'), $programId !== '' ? $programId : 'Missing default program ID.');
lqr_install_check($checks, 'Default template ID', $templateId !== '' && !str_contains($templateId, 'replace_me'), $templateId !== '' ? $templateId : 'Missing default template ID.');
lqr_install_check($checks, 'Webhook signing value', $webhookSecret !== '' && !str_contains($webhookSecret, 'replace_with'), $webhookSecret !== '' ? 'Webhook signing value appears configured.' : 'Missing webhook signing value.');

$dbOk = false;
$dbDetail = '';
$missingTables = [];
try {
    $pdo = lqr_sql_db($config);
    $dbOk = true;
    $dbDetail = 'Database connection succeeded.';
    $requiredTables = [
        'lqr_admin_users',
        'lqr_users',
        'lqr_link_states',
        'lqr_quests',
        'lqr_quest_completions',
        'lqr_rewards',
        'lqr_reward_claims',
        'lqr_admin_audit_events',
        'lqr_events',
        'lqr_app_state',
        'lqr_admin_password_resets',
    ];
    foreach ($requiredTables as $table) {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        if (!$stmt->fetchColumn()) $missingTables[] = $table;
    }
} catch (Throwable $e) {
    $dbDetail = $e->getMessage();
}
lqr_install_check($checks, 'Database connection', $dbOk, $dbDetail);
lqr_install_check($checks, 'Schema tables', $dbOk && empty($missingTables), empty($missingTables) ? 'Required tables are present.' : 'Missing: ' . implode(', ', $missingTables));

$allOk = array_reduce($checks, static fn(bool $carry, array $check): bool => $carry && (bool)$check['ok'], true);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Local Quest Installer</title>
<style>
body{margin:0;background:#071225;color:#f5f9ff;font-family:Arial,sans-serif}.wrap{width:min(980px,94%);margin:0 auto;padding:40px 0}.card{background:#0d1b2f;border:1px solid #24415f;border-radius:18px;padding:18px;margin:12px 0}.row{display:grid;grid-template-columns:220px 90px 1fr;gap:12px;align-items:start;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.1)}.ok{color:#4ade80}.bad{color:#fb7185}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 12px;border-radius:12px;background:#172b47;color:#f5f9ff;border:1px solid #24415f;font-weight:800;text-decoration:none}p{color:#9db3cc}@media(max-width:760px){.row{grid-template-columns:1fr}.wrap{padding:22px 0}}
</style>
</head>
<body><main class="wrap"><h1>Local Quest Installer</h1><p>This screen verifies the SQL-only starter foundation before use. Remove or protect this file after deployment.</p><section class="card"><h2><?= $allOk ? 'Ready' : 'Needs attention' ?></h2><?php foreach ($checks as $check): ?><div class="row"><strong><?= lqr_h((string)$check['name']) ?></strong><strong class="<?= $check['ok'] ? 'ok' : 'bad' ?>"><?= $check['ok'] ? 'PASS' : 'FAIL' ?></strong><span><?= lqr_h((string)$check['detail']) ?></span></div><?php endforeach; ?></section><p><a class="btn" href="cover.php">Open app</a> <a class="btn" href="admin.php">Open admin</a></p></main></body></html>
