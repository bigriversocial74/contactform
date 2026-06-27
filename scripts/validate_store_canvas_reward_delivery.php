<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$requiredFiles = [
    'api/store/_canvas_rewards.php',
    'api/merchant-canvas/reward-options.php',
    'api/merchant-canvas/send-reward.php',
    'api/account/wallet-source-metadata.php',
    'assets/js/merchant-canvas-rewards.js',
    'assets/css/merchant-canvas-rewards.css',
    'merchant-canvas.php',
];

$failures = [];
foreach ($requiredFiles as $path) {
    if (!is_file($root . '/' . $path)) $failures[] = "Missing {$path}";
}

function require_contains(string $path, string $needle, array &$failures): void
{
    $source = file_get_contents(dirname(__DIR__) . '/' . $path);
    if (!is_string($source) || !str_contains($source, $needle)) {
        $failures[] = "{$path} missing marker: {$needle}";
    }
}

require_contains('api/store/_canvas_rewards.php', 'wallet_items', $failures);
require_contains('api/store/_canvas_rewards.php', 'campaign_events', $failures);
require_contains('api/store/_canvas_rewards.php', 'reward_sent', $failures);
require_contains('api/store/_canvas_rewards.php', 'source_system\' => \'store_canvas', $failures);
require_contains('api/store/_canvas_rewards.php', 'mg_create_notification', $failures);
require_contains('api/merchant-canvas/send-reward.php', 'mg_require_csrf_for_write', $failures);
require_contains('api/merchant-canvas/send-reward.php', 'mg_user_has_merchant_access', $failures);
require_contains('api/merchant-canvas/reward-options.php', 'mg_store_reward_options', $failures);
require_contains('api/account/wallet-source-metadata.php', 'source_system', $failures);
require_contains('assets/js/merchant-canvas-rewards.js', '/api/merchant-canvas/send-reward.php', $failures);
require_contains('assets/js/merchant-canvas-rewards.js', '/api/merchant-canvas/reward-options.php', $failures);
require_contains('merchant-canvas.php', 'merchant-canvas-rewards.js', $failures);
require_contains('merchant-canvas.php', 'merchant-canvas-rewards.css', $failures);

if ($failures !== []) {
    fwrite(STDERR, "Store Canvas reward delivery validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Store Canvas reward delivery validation passed.\n";
