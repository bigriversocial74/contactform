<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'examples/local-quest-rewards/database/local_quest_rewards.sql',
    'examples/local-quest-rewards/replay-storage.php',
    'examples/local-quest-rewards/signed-quest-enforcement.php',
    'docs/local-quest-replay-storage.md',
];

$ok = true;
$files = [];
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    $ok = $ok && $exists;
    $files[] = ['path' => $path, 'exists' => $exists];
}

$sql = is_file($root . '/examples/local-quest-rewards/database/local_quest_rewards.sql') ? (string)file_get_contents($root . '/examples/local-quest-rewards/database/local_quest_rewards.sql') : '';
$helper = is_file($root . '/examples/local-quest-rewards/replay-storage.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/replay-storage.php') : '';
$enforcement = is_file($root . '/examples/local-quest-rewards/signed-quest-enforcement.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/signed-quest-enforcement.php') : '';
$doc = is_file($root . '/docs/local-quest-replay-storage.md') ? (string)file_get_contents($root . '/docs/local-quest-replay-storage.md') : '';

$hasTables = str_contains($sql, 'lqr_signed_code_replays') && str_contains($sql, 'lqr_webhook_deliveries');
$hasHelpers = str_contains($helper, 'lqr_sql_replay_seen') && str_contains($helper, 'lqr_sql_mark_replay') && str_contains($helper, 'lqr_sql_webhook_delivery_seen') && str_contains($helper, 'lqr_sql_record_webhook_delivery');
$hasEnforcement = str_contains($enforcement, "require_once __DIR__ . '/replay-storage.php'") && str_contains($enforcement, 'lqr_sql_replay_seen') && str_contains($enforcement, 'lqr_sql_mark_replay');
$hasDocs = str_contains($doc, 'Local Quest replay storage') && str_contains($doc, 'lqr_signed_code_replays') && str_contains($doc, 'lqr_webhook_deliveries');

$ok = $ok && $hasTables && $hasHelpers && $hasEnforcement && $hasDocs;

echo json_encode([
    'ok' => $ok,
    'files' => $files,
    'has_tables' => $hasTables,
    'has_helpers' => $hasHelpers,
    'has_enforcement' => $hasEnforcement,
    'has_docs' => $hasDocs,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($ok ? 0 : 1);
