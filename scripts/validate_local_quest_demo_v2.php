<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'examples/local-quest-rewards/start.php',
    'examples/local-quest-rewards/api-examples.php',
    'examples/local-quest-rewards/admin-developer-readiness.php',
    'examples/local-quest-rewards/developer-starter.php',
    'examples/local-quest-rewards/webhook.php',
    'docs/local-quest-demo-v2.md',
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
$doc = lqdv2_read($root, 'docs/local-quest-demo-v2.md');

$checks[] = [
    'name' => 'launcher guided flow',
    'ok' => str_contains($start, 'Run the full Local Quest API demo')
        && str_contains($start, 'developer-starter.php')
        && str_contains($start, 'api-examples.php')
        && str_contains($start, 'admin-developer-readiness.php')
        && str_contains($start, 'Claim/report from wallet')
        && str_contains($start, 'Verify webhook delivery'),
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
    'name' => 'demo v2 documentation',
    'ok' => str_contains($doc, 'Local Quest Demo Platform v2')
        && str_contains($doc, 'copy-ready API examples')
        && str_contains($doc, 'admin QA view')
        && str_contains($doc, 'Microgifter remains the system of record'),
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
