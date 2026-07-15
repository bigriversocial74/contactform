<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$files = [
    'includes/hosted-game-runtime-toggle.php',
    'api/admin/hosted-game-runtime.php',
    'api/merchant/hosted-game-runtime.php',
    'assets/js/admin-hosted-games-runtime-toggle.js',
    'assets/js/merchant-hosted-games-runtime-toggle.js',
    'assets/css/hosted-games-runtime-toggle.css',
    'admin/hosted-games.php',
    'merchant-games.php',
    'includes/merchant-hosted-games-view.php',
];

foreach ($files as $file) {
    if (!is_file($root . '/' . $file)) $errors[] = "Missing required file: {$file}";
}

$read = static function (string $path) use ($root): string {
    $value = @file_get_contents($root . '/' . $path);
    return is_string($value) ? $value : '';
};
$mustContain = static function (string $path, array $needles) use (&$errors, $read): void {
    $content = $read($path);
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) $errors[] = "{$path} is missing contract: {$needle}";
    }
};
$mustNotContain = static function (string $path, array $needles) use (&$errors, $read): void {
    $content = $read($path);
    foreach ($needles as $needle) {
        if (str_contains($content, $needle)) $errors[] = "{$path} contains forbidden contract: {$needle}";
    }
};

$mustContain('includes/hosted-game-runtime-toggle.php', [
    'mg_hosted_game_runtime_can_enable',
    'mg_hosted_game_managed_runtime_state',
    'mg_hosted_game_set_runtime_enabled',
    "status='active'",
    "status='paused'",
    'credentials_managed',
    'configuration_source',
    'distribution_program',
    'api_credential_configured',
    'webhook_secret_configured',
    'state_secret_configured',
]);
$mustNotContain('includes/hosted-game-runtime-toggle.php', [
    'MG_REWARD_DROP_API_KEY',
    'MG_REWARD_DROP_PROGRAM_ID',
    'MG_REWARD_DROP_TEMPLATE_ID',
    'MG_REWARD_DROP_WEBHOOK_SECRET',
]);

$mustContain('api/admin/hosted-game-runtime.php', [
    "admin.hosted_games.manage",
    'mg_require_csrf_for_write',
    'mg_hosted_game_by_public_id',
    'mg_hosted_game_set_runtime_enabled',
    'credentials_preserved',
]);
$mustContain('api/merchant/hosted-game-runtime.php', [
    "merchant.hosted_games.manage",
    'mg_require_csrf_for_write',
    'mg_hosted_game_for_merchant',
    'mg_hosted_game_set_runtime_enabled',
    'credentials_preserved',
]);

$mustContain('assets/js/admin-hosted-games-runtime-toggle.js', [
    'role="switch"',
    'aria-checked',
    '/api/admin/hosted-game-runtime.php',
    'Raw credentials are never displayed',
    'api_credential_ready',
    'webhook_secret_ready',
]);
$mustContain('assets/js/merchant-hosted-games-runtime-toggle.js', [
    'role="switch"',
    'aria-checked',
    '/api/merchant/hosted-game-runtime.php',
    'No manual environment values',
    'api_credential_ready',
    'webhook_secret_ready',
]);

$mustContain('admin/hosted-games.php', [
    'hosted-games-runtime-toggle.css',
    'admin-hosted-games-runtime-toggle.js',
    'No per-game environment values are required',
    'Game enabled switch',
]);
$mustContain('merchant-games.php', [
    'hosted-games-runtime-toggle.css',
    'merchant-hosted-games-runtime-toggle.js',
]);
$mustContain('includes/merchant-hosted-games-view.php', [
    'No per-game environment values are required',
    'Game enabled switch',
]);

$hostedGames = $read('includes/hosted-games.php');
if (!str_contains($hostedGames, "AND hg.status='active'")) {
    $errors[] = 'Public hosted-game lookup must keep the active-status runtime gate.';
}
if (!str_contains($hostedGames, 'api_credential_ciphertext') || !str_contains($hostedGames, 'webhook_secret_ciphertext')) {
    $errors[] = 'Hosted Games must retain encrypted per-game credential storage.';
}

if ($errors !== []) {
    fwrite(STDERR, "Hosted Games managed runtime validation failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "Hosted Games managed runtime validation passed.\n";
