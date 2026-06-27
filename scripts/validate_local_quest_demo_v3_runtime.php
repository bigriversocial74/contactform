<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'examples/local-quest-rewards/runtime-diagnostics.php',
    'examples/local-quest-rewards/webhook-tools.php',
    'examples/local-quest-rewards/admin-demo-tools.php',
    'examples/local-quest-rewards/start.php',
    'docs/local-quest-developer-handoff.md',
];

function lqdv3_read(string $root, string $path): string
{
    $full = $root . '/' . $path;
    return is_file($full) ? (string)file_get_contents($full) : '';
}

$checks = [];
foreach ($files as $file) {
    $checks[] = ['name' => 'file:' . $file, 'ok' => is_file($root . '/' . $file)];
}

$diagnostics = lqdv3_read($root, 'examples/local-quest-rewards/runtime-diagnostics.php');
$webhookTools = lqdv3_read($root, 'examples/local-quest-rewards/webhook-tools.php');
$demoTools = lqdv3_read($root, 'examples/local-quest-rewards/admin-demo-tools.php');
$start = lqdv3_read($root, 'examples/local-quest-rewards/start.php');
$handoff = lqdv3_read($root, 'docs/local-quest-developer-handoff.md');

$checks[] = [
    'name' => 'runtime diagnostics coverage',
    'ok' => str_contains($diagnostics, 'Runtime Diagnostics')
        && str_contains($diagnostics, 'lqr_sql_db')
        && str_contains($diagnostics, 'lqr_load_state')
        && str_contains($diagnostics, 'Bearer credential')
        && str_contains($diagnostics, 'Webhook endpoint')
        && str_contains($diagnostics, 'lqrd_mask'),
];

$checks[] = [
    'name' => 'webhook signed payload tools',
    'ok' => str_contains($webhookTools, 'Webhook Test Tools')
        && str_contains($webhookTools, 'hash_hmac')
        && str_contains($webhookTools, 'X-Microgifter-Signature')
        && str_contains($webhookTools, 'payload.json')
        && str_contains($webhookTools, 'webhook.php'),
];

$checks[] = [
    'name' => 'admin demo seed reset tools',
    'ok' => str_contains($demoTools, 'Admin Demo Tools')
        && str_contains($demoTools, 'lqr_admin_authed')
        && str_contains($demoTools, 'seed_demo')
        && str_contains($demoTools, 'reset_demo')
        && str_contains($demoTools, 'RESET LOCAL QUEST DEMO')
        && str_contains($demoTools, 'partner-demo@example.test'),
];

$checks[] = [
    'name' => 'launcher links runtime tools',
    'ok' => str_contains($start, 'runtime-diagnostics.php')
        && str_contains($start, 'webhook-tools.php')
        && str_contains($start, 'admin-demo-tools.php')
        && str_contains($start, 'Run runtime diagnostics')
        && str_contains($start, 'Generate webhook test payload'),
];

$checks[] = [
    'name' => 'partner handoff docs',
    'ok' => str_contains($handoff, 'Local Quest Partner Developer Handoff')
        && str_contains($handoff, 'runtime-diagnostics.php')
        && str_contains($handoff, 'webhook-tools.php')
        && str_contains($handoff, 'admin-demo-tools.php')
        && str_contains($handoff, 'Minimum handoff pass'),
];

$failed = array_values(array_filter($checks, static fn(array $check): bool => empty($check['ok'])));
$result = [
    'ok' => count($failed) === 0,
    'checks' => $checks,
    'failed' => $failed,
    'generated_at' => gmdate('c'),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
