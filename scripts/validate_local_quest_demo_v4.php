<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'examples/local-quest-rewards/demo.php',
    'examples/local-quest-rewards/how-it-works.php',
    'examples/local-quest-rewards/deployment-checklist.php',
    'examples/local-quest-rewards/cover.php',
    'examples/local-quest-rewards/start.php',
];

function lqdv4_read(string $root, string $path): string
{
    $full = $root . '/' . $path;
    return is_file($full) ? (string)file_get_contents($full) : '';
}

$checks = [];
foreach ($files as $file) {
    $checks[] = ['name' => 'file:' . $file, 'ok' => is_file($root . '/' . $file)];
}

$demo = lqdv4_read($root, 'examples/local-quest-rewards/demo.php');
$how = lqdv4_read($root, 'examples/local-quest-rewards/how-it-works.php');
$checklist = lqdv4_read($root, 'examples/local-quest-rewards/deployment-checklist.php');
$cover = lqdv4_read($root, 'examples/local-quest-rewards/cover.php');
$start = lqdv4_read($root, 'examples/local-quest-rewards/start.php');

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
    'name' => 'how it works lifecycle page',
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
    'name' => 'cover links presentation pages',
    'ok' => str_contains($cover, 'demo.php')
        && str_contains($cover, 'start.php')
        && str_contains($cover, 'how-it-works.php')
        && str_contains($cover, 'deployment-checklist.php')
        && str_contains($cover, 'api-examples.php'),
];

$checks[] = [
    'name' => 'launcher links presentation pages',
    'ok' => str_contains($start, 'demo.php')
        && str_contains($start, 'how-it-works.php')
        && str_contains($start, 'deployment-checklist.php')
        && str_contains($start, 'Review public demo overview'),
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
