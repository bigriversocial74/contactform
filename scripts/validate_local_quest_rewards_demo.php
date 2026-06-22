<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'examples/local-quest-rewards/README.md',
    'examples/local-quest-rewards/app.php',
    'examples/local-quest-rewards/config.example.php',
    'examples/local-quest-rewards/index.php',
    'examples/local-quest-rewards/quests.php',
    'examples/local-quest-rewards/webhook.php',
    'examples/local-quest-rewards/data/README.md',
    'docs/microgift-permission-system-plan.md',
];

$ok = true;
$rows = [];
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    $ok = $ok && $exists;
    $rows[] = ['path' => $path, 'exists' => $exists];
}

echo json_encode(['ok' => $ok, 'files' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
