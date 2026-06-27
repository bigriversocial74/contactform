<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'examples/local-quest-rewards/demo.php',
    'examples/local-quest-rewards/how-it-works.php',
    'examples/local-quest-rewards/checklist.php',
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
$checklist = lqdv4_read($root, 'examples/local-quest-rewards/checklist.php');

$checks[] = [
    'name' => 'demo overview page',
    'ok' => str_contains($demo, 'Local Quest Rewards demo')
        && str_contains($demo, 'Start Demo')
        && str_contains($demo, 'how-it-works.php')
        && str_contains($demo, 'checklist')
        && str_contains($demo, 'runtime-diagnostics.php')
        && str_contains($demo, 'admin-developer-readiness.php'),
];

$checks[] = [
    'name' => 'how it works lifecycle page',
    'ok' => str_contains($how, 'How the outside app and Microgifter work together')
        && str_contains($how, 'Lifecycle stages')
        && str_contains($how, 'Copy API examples')
        && str_contains($how, 'api-examples.php'),
];

$checks[] = [
    'name' => 'checklist page',
    'ok' => str_contains($checklist, 'Demo checklist')
        && str_contains($checklist, 'Run launcher')
        && str_contains($checklist, 'admin-developer-readiness.php')
        && str_contains($checklist, 'webhook-tools.php'),
];

$failed = array_values(array_filter($checks, static fn(array $check): bool => empty($check['ok'])));
$result = ['ok' => count($failed) === 0, 'checks' => $checks, 'failed' => $failed, 'generated_at' => gmdate('c')];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
