<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'examples/local-quest-rewards/admin-signed-replay-log.php',
    'examples/local-quest-rewards/database/local_quest_rewards.sql',
    'docs/local-quest-replay-storage.md',
];

$ok = true;
$files = [];
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    $ok = $ok && $exists;
    $files[] = ['path' => $path, 'exists' => $exists];
}

$page = is_file($root . '/examples/local-quest-rewards/admin-signed-replay-log.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/admin-signed-replay-log.php') : '';
$sql = is_file($root . '/examples/local-quest-rewards/database/local_quest_rewards.sql') ? (string)file_get_contents($root . '/examples/local-quest-rewards/database/local_quest_rewards.sql') : '';
$doc = is_file($root . '/docs/local-quest-replay-storage.md') ? (string)file_get_contents($root . '/docs/local-quest-replay-storage.md') : '';

$hasPage = str_contains($page, 'Signed QR Replay Log') && str_contains($page, 'lqr_signed_code_replays') && str_contains($page, 'replay_key');
$hasSql = str_contains($sql, 'lqr_signed_code_replays') && str_contains($sql, 'uq_lqr_signed_replay_key');
$hasDocs = str_contains($doc, 'Admin replay log') && str_contains($doc, 'admin-signed-replay-log.php');

$ok = $ok && $hasPage && $hasSql && $hasDocs;

echo json_encode([
    'ok' => $ok,
    'files' => $files,
    'has_page' => $hasPage,
    'has_sql' => $hasSql,
    'has_docs' => $hasDocs,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($ok ? 0 : 1);
