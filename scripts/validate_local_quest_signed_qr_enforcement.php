<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'examples/local-quest-rewards/admin-signed-code-controls.php',
    'examples/local-quest-rewards/admin-signed-codes.php',
    'examples/local-quest-rewards/signed-code-print.php',
    'examples/local-quest-rewards/signed-quest-enforcement.php',
    'examples/local-quest-rewards/quest-controls.php',
    'examples/local-quest-rewards/index.php',
    'docs/local-quest-signed-codes.md',
];

$ok = true;
$files = [];
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    $ok = $ok && $exists;
    $files[] = ['path' => $path, 'exists' => $exists];
}

$controlsPage = is_file($root . '/examples/local-quest-rewards/admin-signed-code-controls.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/admin-signed-code-controls.php') : '';
$generator = is_file($root . '/examples/local-quest-rewards/admin-signed-codes.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/admin-signed-codes.php') : '';
$print = is_file($root . '/examples/local-quest-rewards/signed-code-print.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/signed-code-print.php') : '';
$enforcement = is_file($root . '/examples/local-quest-rewards/signed-quest-enforcement.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/signed-quest-enforcement.php') : '';
$controls = is_file($root . '/examples/local-quest-rewards/quest-controls.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/quest-controls.php') : '';
$index = is_file($root . '/examples/local-quest-rewards/index.php') ? (string)file_get_contents($root . '/examples/local-quest-rewards/index.php') : '';
$doc = is_file($root . '/docs/local-quest-signed-codes.md') ? (string)file_get_contents($root . '/docs/local-quest-signed-codes.md') : '';

$hasControlDefaults = str_contains($controls, 'requires_signed_code') && str_contains($controls, 'signed_code_type');
$hasAdminControls = str_contains($controlsPage, 'save_signed_controls') && str_contains($controlsPage, 'Require signed QR') && str_contains($controlsPage, 'signed_code_type');
$hasEnforcement = str_contains($enforcement, 'lqr_enforce_signed_quest_code') && str_contains($enforcement, 'lqr_verify_signed_payload') && str_contains($enforcement, 'lqr_replay_seen') && str_contains($enforcement, 'lqr_mark_replay');
$hasIndexWiring = str_contains($index, "require __DIR__ . '/signed-quest-enforcement.php'") && str_contains($index, 'lqr_enforce_signed_quest_code') && str_contains($index, 'Signed QR required');
$hasPrintFlow = str_contains($generator, 'signed-code-print.php') && str_contains($print, 'api.qrserver.com') && str_contains($print, 'window.print');
$hasDocs = str_contains($doc, 'Enforcement controls') && str_contains($doc, 'requires_signed_code') && str_contains($doc, 'lqr_enforce_signed_quest_code');

$ok = $ok && $hasControlDefaults && $hasAdminControls && $hasEnforcement && $hasIndexWiring && $hasPrintFlow && $hasDocs;

echo json_encode([
    'ok' => $ok,
    'files' => $files,
    'has_control_defaults' => $hasControlDefaults,
    'has_admin_controls' => $hasAdminControls,
    'has_enforcement' => $hasEnforcement,
    'has_index_wiring' => $hasIndexWiring,
    'has_print_flow' => $hasPrintFlow,
    'has_docs' => $hasDocs,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($ok ? 0 : 1);
