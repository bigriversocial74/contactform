<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$requiredFiles = [
    'includes/hosted-game-standard-v1.php',
    'includes/hosted-game-standard-core.php',
    'includes/hosted-game-standard-security.php',
    'includes/hosted-game-standard-upload.php',
    'api/hosted-games/document.php',
    'api/hosted-games/runtime.php',
    'assets/js/hosted-game-shell.js',
    'assets/js/hosted-game-child-bridge.js',
    'hosted-game.php',
    'api/admin/hosted-game-upload.php',
    'api/merchant/hosted-game-upload.php',
    'docs/hosted-game-standard-v1.md',
    'docs/hosted-games-package-guide.md',
    'examples/hosted-game-starter/game.json',
    'examples/hosted-game-starter/game.js',
];
foreach ($requiredFiles as $path) {
    if (!is_file($root . '/' . $path)) $errors[] = "Missing required file: {$path}";
}

$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);
    return is_string($content) ? $content : '';
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

$mustContain('includes/hosted-game-standard-core.php', [
    'microgifter.hosted-game/v1',
    'MG_HOSTED_GAME_STANDARD_SDK_VERSION',
    'mg_hosted_game_standard_normalize_manifest',
    'game_loaded',
    'run_started',
    'score_updated',
    'player_qualified',
    'run_completed',
    'run_abandoned',
    'runtime_error',
    'server_review',
    'max_duration_seconds',
    'network.connect accepts only',
]);
$mustContain('includes/hosted-game-standard-security.php', [
    'mg_hosted_game_standard_iframe_sandbox',
    'mg_hosted_game_standard_iframe_allow',
    'mg_hosted_game_standard_csp',
    'mg_hosted_game_standard_bridge_token',
]);
$mustContain('includes/hosted-game-standard-upload.php', [
    'mg_hosted_game_standard_preflight_upload',
    'mg_hosted_game_standard_finalize_release',
    'hosted_game.standard_v1.release_validated',
]);

foreach (['api/admin/hosted-game-upload.php', 'api/merchant/hosted-game-upload.php'] as $path) {
    $mustContain($path, [
        'hosted-game-standard-v1.php',
        'mg_hosted_game_standard_preflight_upload',
        'mg_hosted_game_standard_finalize_release',
    ]);
}

$mustContain('hosted-game.php', [
    'hosted-game-standard-v1.php',
    'mg_hosted_game_standard_bridge_token',
    'mg_hosted_game_standard_iframe_sandbox',
    'mg_hosted_game_standard_iframe_allow',
    'bridgeToken',
    'Hosted Game Standard v1',
    'hosted-game-shell.js?v=1.1.0',
]);
$mustNotContain('hosted-game.php', ['allow-same-origin']);

$mustContain('api/hosted-games/document.php', [
    'mg_hosted_game_standard_valid_bridge_token',
    'mg_hosted_game_standard_manifest_from_game',
    'mg_hosted_game_standard_csp',
    'bridgeToken',
    'hosted-game-child-bridge.js?v=1.1.0',
    'Permissions-Policy',
]);
$mustNotContain('api/hosted-games/document.php', ['allow-same-origin']);

$mustContain('assets/js/hosted-game-shell.js', [
    'const bridgeToken',
    "String(message.bridgeToken || '') !== bridgeToken",
    "'abandon'",
    "'event'",
    'sdk_version',
    'shell-ready',
    'JSON.stringify(message).length <= 131072',
]);
$mustContain('assets/js/hosted-game-child-bridge.js', [
    "version: '1.1.0'",
    "standardVersion: '1.0.0'",
    'getManifest',
    'getProgram',
    'getReward',
    'getActiveRun',
    'updateScore',
    'emitEvent',
    'levelStarted',
    'levelCompleted',
    'qualify',
    'complete',
    'abandonRun',
    'reportError',
    'completeRun',
    "String(message.bridgeToken || '') !== bridgeToken",
]);

$mustContain('api/hosted-games/runtime.php', [
    'mg_hosted_game_standard_manifest_from_game',
    'mg_hosted_game_standard_public_manifest',
    'max_duration_seconds',
    "if (\$action === 'event')",
    "if (\$action === 'abandon')",
    'standard.run_started',
    'standard.run_completed',
    'standard.run_abandoned',
    'server-side qualification endpoint',
    'Superseded by a new game run',
    'hosted_game_standard',
]);

$manifestRaw = $read('examples/hosted-game-starter/game.json');
$manifest = json_decode($manifestRaw, true);
if (!is_array($manifest) || json_last_error() !== JSON_ERROR_NONE) {
    $errors[] = 'Starter game.json is not valid JSON.';
} else {
    if (($manifest['schema'] ?? null) !== 'microgifter.hosted-game/v1') $errors[] = 'Starter manifest does not declare Standard v1.';
    if (($manifest['entry'] ?? null) !== 'index.html') $errors[] = 'Starter manifest entry must be index.html.';
    foreach (['player','runs','events','state','scores','leaderboard','inbox'] as $capability) {
        if (!in_array($capability, $manifest['capabilities'] ?? [], true)) $errors[] = "Starter manifest is missing capability: {$capability}";
    }
    foreach (['game_loaded','run_started','score_updated','player_qualified','run_completed','run_abandoned','runtime_error'] as $event) {
        if (!in_array($event, $manifest['events'] ?? [], true)) $errors[] = "Starter manifest is missing event: {$event}";
    }
}

$mustContain('examples/hosted-game-starter/game.js', [
    'MicrogifterGame.startRun',
    'MicrogifterGame.updateScore',
    'MicrogifterGame.qualify',
    'MicrogifterGame.complete',
    'MicrogifterGame.abandonRun',
    'MicrogifterGame.reportError',
]);
$mustContain('docs/hosted-game-standard-v1.md', [
    'microgifter.hosted-game/v1',
    'MicrogifterGame.updateScore',
    'MicrogifterGame.emitEvent',
    'MicrogifterGame.abandonRun',
    'allow-same-origin',
    'server_review',
]);

if ($errors !== []) {
    fwrite(STDERR, "Hosted Game Standard v1 validation failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "Hosted Game Standard v1 validation passed.\n";
