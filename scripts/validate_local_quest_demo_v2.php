<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'examples/local-quest-rewards/start.php',
    'examples/local-quest-rewards/api-examples.php',
    'examples/local-quest-rewards/admin-developer-readiness.php',
    'examples/local-quest-rewards/developer-starter.php',
    'examples/local-quest-rewards/webhook.php',
    'examples/local-quest-rewards/runtime-diagnostics.php',
    'examples/local-quest-rewards/webhook-tools.php',
    'examples/local-quest-rewards/admin-demo-tools.php',
    'examples/local-quest-rewards/demo.php',
    'examples/local-quest-rewards/how-it-works.php',
    'examples/local-quest-rewards/deployment-checklist.php',
    'docs/local-quest-demo-v2.md',
    'docs/local-quest-developer-handoff.md',
];

function lqdv2_read(string $root, string $path): string
{
    $full = $root . '/' . $path;
    return is_file($full) ? (string)file_get_contents($full) : '';
}

$checks = [];
foreach ($files as $file) {
    $checks[] = ['name' => 'file:' . $file, 'ok' => is_file($root . '/' . $file)];
}

$start = lqdv2_read($root, 'examples/local-quest-rewards/start.php');
$examples = lqdv2_read($root, 'examples/local-quest-rewards/api-examples.php');
$readiness = lqdv2_read($root, 'examples/local-quest-rewards/admin-developer-readiness.php');
$diagnostics = lqdv2_read($root, 'examples/local-quest-rewards/runtime-diagnostics.php');
$webhookTools = lqdv2_read($root, 'examples/local-quest-rewards/webhook-tools.php');
$demoTools = lqdv2_read($root, 'examples/local-quest-rewards/admin-demo-tools.php');
$demo = lqdv2_read($root, 'examples/local-quest-rewards/demo.php');
$how = lqdv2_read($root, 'examples/local-quest-rewards/how-it-works.php');
$checklist = lqdv2_read($root, 'examples/local-quest-rewards/deployment-checklist.php');
$doc = lqdv2_read($root, 'docs/local-quest-demo-v2.md');
$handoff = lqdv2_read($root, 'docs/local-quest-developer-handoff.md');

$checks[] = [
    'name' => 'launcher guided flow',
    'ok' => str_contains($start, 'Run the full Local Quest API demo')
        && str_contains($start, 'developer-starter.php')
        && str_contains($start, 'api-examples.php')
        && str_contains($start, 'admin-developer-readiness.php')
        && str_contains($start, 'Claim/report from wallet')
        && str_contains($start, 'Verify webhook delivery')
        && str_contains($start, 'runtime-diagnostics.php')
        && str_contains($start, 'webhook-tools.php')
        && str_contains($start, 'admin-demo-tools.php')
        && str_contains($start, 'demo.php')
        && str_contains($start, 'how-it-works.php')
        && str_contains($start, 'deployment-checklist.php'),
];

$checks[] = [
    'name' => 'copy-ready api examples',
    'ok' => str_contains($examples, '/api/public/v1/programs/index.php')
        && str_contains($examples, '/api/public/v1/sandbox/linked-account.php')
        && str_contains($examples, '/api/public/v1/account-links/start.php')
        && str_contains($examples, '/api/public/v1/rewards/issue.php')
        && str_contains($examples, '/api/public/v1/rewards/status.php')
        && str_contains($examples, '/api/public/v1/rewards/claim.php')
        && str_contains($examples, 'X-Idempotency-Key'),
];

$checks[] = [
    'name' => 'admin developer readiness',
    'ok' => str_contains($readiness, 'Admin Developer Readiness')
        && str_contains($readiness, 'lqr_admin_authed')
        && str_contains($readiness, 'Verified webhooks received')
        && str_contains($readiness, 'Claims reported')
        && str_contains($readiness, 'admin-developer-readiness.php'),
];

$checks[] = [
    'name' => 'runtime diagnostics',
    'ok' => str_contains($diagnostics, 'Runtime Diagnostics')
        && str_contains($diagnostics, 'lqr_sql_db')
        && str_contains($diagnostics, 'lqr_load_state')
        && str_contains($diagnostics, 'Bearer credential')
        && str_contains($diagnostics, 'Webhook endpoint'),
];

$checks[] = [
    'name' => 'webhook test tools',
    'ok' => str_contains($webhookTools, 'Webhook Test Tools')
        && str_contains($webhookTools, 'hash_hmac')
        && str_contains($webhookTools, 'X-Microgifter-Signature')
        && str_contains($webhookTools, 'payload.json')
        && str_contains($webhookTools, 'webhook.php'),
];

$checks[] = [
    'name' => 'admin demo tools',
    'ok' => str_contains($demoTools, 'Admin Demo Tools')
        && str_contains($demoTools, 'lqr_admin_authed')
        && str_contains($demoTools, 'seed_demo')
        && str_contains($demoTools, 'reset_demo')
        && str_contains($demoTools, 'RESET LOCAL QUEST DEMO')
        && str_contains($demoTools, 'partner-demo@example.test'),
];

$checks[] = [
    'name' => 'demo overview page',
    'ok' => str_contains($demo, 'Local Quest Rewards demo')
        && str_contains($demo, 'Start Demo')
        && str_contains($demo, 'how-it-works.php')
        && str_contains($demo, 'deployment-checklist.php')
        && str_contains($demo, 'runtime-diagnostics.php')
        && str_contains($demo, 'admin-developer-readiness.php'),
];

$checks[] = [
    'name' => 'how it works page',
    'ok' => str_contains($how, 'How the outside app and Microgifter work together')
        && str_contains($how, 'Lifecycle stages')
        && str_contains($how, 'Endpoint map')
        && str_contains($how, '/api/public/v1/rewards/issue.php')
        && str_contains($how, '/api/public/v1/rewards/claim.php'),
];

$checks[] = [
    'name' => 'deployment checklist page',
    'ok' => str_contains($checklist, 'Demo environment checklist')
        && str_contains($checklist, 'Environment')
        && str_contains($checklist, 'API handoff')
        && str_contains($checklist, 'Webhook readiness')
        && str_contains($checklist, 'Demo evidence'),
];

$checks[] = [
    'name' => 'demo v2 documentation',
    'ok' => str_contains($doc, 'Local Quest Demo Platform v2')
        && str_contains($doc, 'copy-ready API examples')
        && str_contains($doc, 'admin QA view')
        && str_contains($doc, 'Microgifter remains the system of record'),
];

$checks[] = [
    'name' => 'partner developer handoff documentation',
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
