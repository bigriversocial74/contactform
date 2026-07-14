<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
$failures = [];

function hg_file(string $path): string
{
    global $root, $failures;
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) {
        $failures[] = "Missing required file: {$path}";
        return '';
    }
    $value = file_get_contents($full);
    if (!is_string($value)) {
        $failures[] = "Unable to read required file: {$path}";
        return '';
    }
    return $value;
}

function hg_has(string $path, string $needle, string $label): void
{
    global $checks, $failures;
    $checks++;
    $content = hg_file($path);
    if ($content === '' || !str_contains($content, $needle)) {
        $failures[] = "{$label}: {$path} is missing {$needle}";
    }
}

function hg_not_has(string $path, string $needle, string $label): void
{
    global $checks, $failures;
    $checks++;
    $content = hg_file($path);
    if ($content !== '' && str_contains($content, $needle)) {
        $failures[] = "{$label}: {$path} must not contain {$needle}";
    }
}

function hg_regex(string $path, string $pattern, string $label): void
{
    global $checks, $failures;
    $checks++;
    $content = hg_file($path);
    if ($content === '' || preg_match($pattern, $content) !== 1) {
        $failures[] = "{$label}: {$path} failed pattern {$pattern}";
    }
}

$requiredFiles = [
    'database/hosted_games_management_v1.sql',
    'includes/hosted-games.php',
    'api/merchant/hosted-games.php',
    'api/merchant/hosted-game-upload.php',
    'api/admin/hosted-games.php',
    'api/hosted-games/runtime.php',
    'api/hosted-games/asset.php',
    'api/hosted-games/document.php',
    'api/hosted-games/webhook.php',
    'merchant-games.php',
    'admin/hosted-games.php',
    'hosted-game.php',
    'includes/merchant-hosted-games-view.php',
    'assets/js/merchant-hosted-games.js',
    'assets/js/admin-hosted-games.js',
    'assets/js/hosted-game-shell.js',
    'assets/js/hosted-game-child-bridge.js',
    'assets/css/hosted-games-management.css',
    'assets/css/hosted-game-shell.css',
    'docs/hosted-games-package-guide.md',
    'examples/hosted-game-starter/game.json',
    'examples/hosted-game-starter/index.html',
    'examples/hosted-game-starter/game.js',
    'examples/hosted-game-starter/game.css',
];
foreach ($requiredFiles as $path) {
    $checks++;
    if (!is_file($root . '/' . $path)) $failures[] = "Missing required file: {$path}";
}

foreach ([
    'hosted_games',
    'hosted_game_releases',
    'hosted_game_secrets',
    'hosted_game_database_connections',
    'hosted_game_runs',
    'hosted_game_events',
] as $table) {
    hg_has('database/hosted_games_management_v1.sql', "CREATE TABLE IF NOT EXISTS {$table}", "Schema table {$table}");
}
hg_has('database/hosted_games_management_v1.sql', 'api_credential_ciphertext', 'Encrypted API credential column');
hg_has('database/hosted_games_management_v1.sql', 'username_ciphertext', 'Encrypted database username column');
hg_has('database/hosted_games_management_v1.sql', 'password_ciphertext', 'Encrypted database password column');
hg_has('database/hosted_games_management_v1.sql', 'program_public_id CHAR(36) NOT NULL', 'Run program snapshot');
hg_has('database/hosted_games_management_v1.sql', 'campaign_public_id CHAR(36) NOT NULL', 'Run campaign snapshot');
hg_has('database/hosted_games_management_v1.sql', 'template_public_id CHAR(36) NOT NULL', 'Run reward snapshot');
hg_has('database/hosted_games_management_v1.sql', 'uq_hosted_game_database_target', 'Isolated database uniqueness');
hg_has('config/migrations.php', "'hosted_games_management_v1.sql'", 'Migration manifest');

hg_has('api/merchant/hosted-game-upload.php', 'ZipArchive', 'ZIP processing');
hg_has('api/merchant/hosted-game-upload.php', 'is_uploaded_file', 'Real upload validation');
hg_has('api/merchant/hosted-game-upload.php', 'mg_hosted_game_zip_symlink', 'Symlink rejection');
hg_has('api/merchant/hosted-game-upload.php', "if (\$part === '..')", 'Traversal rejection');
hg_has('api/merchant/hosted-game-upload.php', 'MG_HOSTED_GAME_MAX_ZIP_BYTES', 'ZIP size cap');
hg_has('api/merchant/hosted-game-upload.php', 'MG_HOSTED_GAME_MAX_EXTRACTED_BYTES', 'Extracted size cap');
hg_has('api/merchant/hosted-game-upload.php', "'wasm'", 'WASM support');
hg_has('api/merchant/hosted-game-upload.php', "'unityweb'", 'Unity WebGL support');
hg_not_has('api/merchant/hosted-game-upload.php', "'php'", 'Executable PHP must not be allowlisted');

hg_has('hosted-game.php', 'sandbox="allow-scripts', 'Sandboxed game frame');
hg_not_has('hosted-game.php', 'allow-same-origin', 'Uploaded game must have opaque origin');
hg_has('api/hosted-games/document.php', 'hosted-game-child-bridge.js', 'Child bridge injection');
hg_has('assets/js/hosted-game-child-bridge.js', "Object.defineProperty(window, 'MicrogifterGame'", 'Immutable game SDK global');
hg_has('assets/js/hosted-game-child-bridge.js', 'startHandshake', 'Durable child handshake');
hg_has('assets/js/hosted-game-shell.js', "'state_save'", 'Approved state action');
hg_has('assets/js/hosted-game-shell.js', "'score_submit'", 'Approved score action');
hg_has('assets/js/hosted-game-shell.js', 'event.source !== iframe.contentWindow', 'Message source validation');

foreach (['session','connect','start','complete','status','state_load','state_save','score_submit','leaderboard','track'] as $action) {
    hg_has('api/hosted-games/runtime.php', "\$action === '{$action}'", "Runtime action {$action}");
}
hg_has('api/hosted-games/runtime.php', 'X-Idempotency-Key:', 'Reward idempotency');
hg_has('api/hosted-games/runtime.php', 'run_token_hash', 'One-time run token');
hg_has('api/hosted-games/runtime.php', 'campaign_id', 'Campaign included in reward metadata');
hg_has('api/hosted-games/webhook.php', 'hash_hmac', 'Signed webhook validation');
hg_has('api/hosted-games/webhook.php', 'abs(time()-(int)$timestamp) <= 300', 'Webhook replay window');

hg_has('includes/hosted-games.php', 'MG_HOSTED_GAMES_BASE_URL', 'Trusted hosted-games base URL');
hg_has('includes/hosted-games.php', 'mg_integration_encrypt_secret', 'Existing encryption foundation reuse');
hg_has('includes/hosted-games.php', 'microgifter_game_player_state', 'Standard game state table');
hg_has('includes/hosted-games.php', 'microgifter_game_scores', 'Standard game score table');
hg_has('includes/hosted-games.php', "'distribution:rewards.issue'", 'Reward issue scope');
hg_has('includes/hosted-games.php', "'distribution:rewards.status'", 'Reward status scope');
hg_has('includes/hosted-games.php', '$webhookReady', 'Webhook readiness gate');
hg_has('includes/hosted-games.php', '$databaseReady', 'Database readiness gate');

hg_has('api/admin/hosted-games.php', "\$action === 'save_database'", 'Admin database save');
hg_has('api/admin/hosted-games.php', "\$action === 'test_database'", 'Admin database test');
hg_has('api/admin/hosted-games.php', "\$action === 'disable_database'", 'Admin database disable');
hg_has('api/admin/hosted-games.php', 'mg_hosted_game_encrypt_secret($username)', 'Username encryption');
hg_has('api/admin/hosted-games.php', 'mg_hosted_game_encrypt_secret($password)', 'Password encryption');
hg_not_has('api/admin/hosted-games.php', "'password'=>", 'Admin API must not return password');
hg_has('admin/hosted-games.php', 'Database username', 'Admin username form');
hg_has('admin/hosted-games.php', 'Database password', 'Admin password form');

hg_has('api/merchant/hosted-games.php', "\$action === 'configure_integration'", 'Merchant integration setup');
hg_has('api/merchant/hosted-games.php', 'mg_hosted_game_ensure_runtime_integration', 'Automatic game credential setup');
hg_has('includes/merchant-hosted-games-view.php', 'Upload game release', 'Merchant ZIP workflow');
hg_has('includes/merchant-hosted-games-view.php', 'Configure game integration', 'Merchant campaign/reward workflow');
hg_not_has('includes/merchant-hosted-games-view.php', 'Game Rules', 'No generic game rules UI');
hg_has('includes/merchant-navigation.php', "'hosted_games'", 'Merchant navigation');
hg_has('includes/admin-sidebar.php', "'hosted-games'", 'Admin navigation');
hg_has('includes/admin-permission-matrix.php', "'admin.hosted_games'", 'Admin permission matrix');

hg_has('.htaccess', 'RewriteRule ^games/([A-Za-z0-9-]+)/?$', 'Hosted game route');
hg_has('.htaccess', 'RewriteCond %{REQUEST_FILENAME} !-d', 'Physical game directory preservation');
hg_not_has('.htaccess', 'MG_REWARD_DROP_API_KEY', 'No game API secrets in htaccess');
hg_not_has('.htaccess', 'MG_HOSTED_GAME', 'No hosted-game secrets in htaccess');

hg_has('docs/hosted-games-package-guide.md', 'Merchant-uploaded PHP is not executed', 'Static package security boundary');
hg_has('docs/hosted-games-package-guide.md', 'MicrogifterGame.completeRun', 'Developer reward bridge documentation');
hg_has('docs/hosted-games-package-guide.md', 'isolated MySQL database', 'Per-game database documentation');
hg_has('examples/hosted-game-starter/game.js', 'MicrogifterGame.startRun', 'Starter run integration');
hg_has('examples/hosted-game-starter/game.js', 'MicrogifterGame.completeRun', 'Starter reward integration');

if ($failures !== []) {
    fwrite(STDERR, "Hosted Games Management v1 validation failed:\n- " . implode("\n- ", array_values(array_unique($failures))) . "\n");
    exit(1);
}

echo "Hosted Games Management v1 validation passed ({$checks} checks).\n";
