<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$files = [
    'database/hosted_games_analytics_diagnostics_v1.sql',
    'includes/hosted-game-observability.php',
    'includes/hosted-game-analytics-report.php',
    'includes/hosted-game-diagnostics-export.php',
    'includes/hosted-game-analytics-view.php',
    'api/hosted-games/telemetry.php',
    'api/hosted-games/webhook.php',
    'api/merchant/hosted-game-analytics.php',
    'api/admin/hosted-game-analytics.php',
    'api/merchant/hosted-game-diagnostics-export.php',
    'api/admin/hosted-game-diagnostics-export.php',
    'merchant-game-analytics.php',
    'admin/hosted-game-analytics.php',
    'assets/css/hosted-game-analytics.css',
    'assets/js/hosted-game-analytics.js',
    'assets/js/hosted-games-analytics-links.js',
    'assets/js/hosted-game-child-bridge.js',
    'assets/js/hosted-game-shell.js',
    'docs/hosted-game-analytics-diagnostics-v1.md',
];
foreach ($files as $file) {
    if (!is_file($root . '/' . $file)) $errors[] = "Missing required file: {$file}";
}

$read = static function (string $file) use ($root): string {
    $content = @file_get_contents($root . '/' . $file);
    return is_string($content) ? $content : '';
};
$mustContain = static function (string $file, array $needles) use (&$errors, $read): void {
    $content = $read($file);
    foreach ($needles as $needle) {
        if (!str_contains($content,$needle)) $errors[] = "{$file} is missing contract: {$needle}";
    }
};
$mustNotContain = static function (string $file, array $needles) use (&$errors, $read): void {
    $content = $read($file);
    foreach ($needles as $needle) {
        if (str_contains($content,$needle)) $errors[] = "{$file} contains forbidden contract: {$needle}";
    }
};

$mustContain('database/hosted_games_analytics_diagnostics_v1.sql',[
    'CREATE TABLE IF NOT EXISTS hosted_game_run_observability',
    'CREATE TABLE IF NOT EXISTS hosted_game_diagnostic_groups',
    'CREATE TABLE IF NOT EXISTS hosted_game_diagnostic_occurrences',
    'release_public_id CHAR(36)',
    "status ENUM('open','resolved','ignored')",
    'occurrence_count BIGINT UNSIGNED',
    'merchant.hosted_games.analytics.view',
    'merchant.hosted_games.diagnostics.manage',
    'admin.hosted_games.analytics.view',
    'admin.hosted_games.diagnostics.manage',
]);
$mustContain('config/migrations.php',[
    "'hosted_games_management_v1.sql'",
    "'hosted_games_analytics_diagnostics_v1.sql'",
]);
$migrations = $read('config/migrations.php');
if (strpos($migrations,"'hosted_games_analytics_diagnostics_v1.sql'") < strpos($migrations,"'hosted_games_management_v1.sql'")) {
    $errors[] = 'Analytics diagnostics migration must follow Hosted Games management migration.';
}

$mustContain('includes/hosted-game-observability.php',[
    'mg_hosted_game_observability_schema_ready',
    'mg_hosted_game_observability_client',
    'mg_hosted_game_observability_event',
    'mg_hosted_game_observability_diagnostic',
    'mg_hosted_game_observability_run_start',
    'mg_hosted_game_observability_run_update',
    'mg_hosted_game_observability_resolve',
    "hash('sha256'",
    'release_public_id',
    'browser_family',
]);
$mustContain('includes/hosted-game-analytics-report.php',[
    'game_loads',
    'unique_players',
    'connected_players',
    'runs_started',
    'runs_completed',
    'qualification_rate',
    'abandonment_rate',
    'average_play_duration_ms',
    'average_score',
    'highest_score',
    'repeat_player_rate',
    'inventory_consumed',
    'cost_per_qualified_player_cents',
    'distribution_allocations',
    'distribution_issuance_jobs',
    'pppm_items',
    'mg_hosted_game_analytics_timeseries',
    'mg_hosted_game_analytics_breakdowns',
    'mg_hosted_game_analytics_funnels',
    'mg_hosted_game_analytics_releases',
    'mg_hosted_game_analytics_diagnostics',
    'mg_hosted_game_analytics_health',
]);

$mustContain('api/hosted-games/telemetry.php',[
    'mg_require_csrf_for_write',
    'mg_rate_limit',
    'Valid game run telemetry authorization is required.',
    'run_token_hash',
    'game_loaded',
    'game_startup',
    'asset_load_failed',
    'sdk_request_failed',
    'runtime_error',
    'manifest_warning',
    'reward_failed',
    'mg_hosted_game_observability_diagnostic',
]);
$mustContain('assets/js/hosted-game-child-bridge.js',[
    "sendTelemetry('game_loaded'",
    "sendTelemetry('game_startup'",
    "sendTelemetry('asset_load_failed'",
    "sendTelemetry('sdk_request_failed'",
    "sendTelemetry('sdk_request_slow'",
    "sendTelemetry('run_started'",
    "sendTelemetry('player_qualified'",
    "sendTelemetry('run_completed'",
    "sendTelemetry('run_abandoned'",
    "sendTelemetry('runtime_error'",
    "window.addEventListener('unhandledrejection'",
    'session_id',
    'viewport_width',
]);
$mustContain('assets/js/hosted-game-shell.js',[
    "'telemetry'",
    'telemetryUrl',
    '/api/hosted-games/telemetry.php',
    "action === 'telemetry'",
]);
$mustContain('hosted-game.php',[
    "'telemetryUrl'=>'/api/hosted-games/telemetry.php'",
    "'releaseId'",
    "'releaseVersion'",
    'hosted-game-shell.js?v=1.2.0',
]);

$mustContain('api/merchant/hosted-game-analytics.php',[
    "merchant.hosted_games.analytics.view",
    "merchant.hosted_games.diagnostics.manage",
    'mg_hosted_game_for_merchant',
    'mg_require_csrf_for_write',
]);
$mustContain('api/admin/hosted-game-analytics.php',[
    "admin.hosted_games.analytics.view",
    "admin.hosted_games.diagnostics.manage",
    'mg_admin_permission_user_has',
    'mg_require_csrf_for_write',
]);
$mustContain('includes/hosted-game-diagnostics-export.php',[
    'summary.json',
    'diagnostic-groups.csv',
    'diagnostic-occurrences.csv',
    'Content-Disposition: attachment',
    'API credentials, webhook secrets, database credentials',
]);

$mustContain('includes/hosted-game-analytics-view.php',[
    'data-hosted-game-analytics',
    'Game loads',
    'Qualification rate',
    'Abandonment rate',
    'Reward lifecycle',
    'Device mix',
    'Standard event funnel',
    'Release comparison',
    'Runtime health',
    'Developer diagnostics',
    'Export diagnostics ZIP',
]);
$mustContain('assets/js/hosted-game-analytics.js',[
    'renderSummary',
    'renderChart',
    'renderFunnels',
    'renderReleases',
    'renderHealth',
    'renderDiagnostics',
    'data-hga-diagnostic-action',
]);
$mustContain('assets/js/hosted-games-analytics-links.js',[
    '/merchant-game-analytics.php?game=',
    '/admin/hosted-game-analytics.php?game=',
]);
$mustContain('merchant-games.php',['hosted-games-analytics-links.js']);
$mustContain('admin/hosted-games.php',['hosted-games-analytics-links.js']);

$mustContain('api/hosted-games/webhook.php',[
    'mg_hosted_game_observability_diagnostic',
    "'category'=>'reward_failed'",
    "'category'=>'webhook_failed'",
]);

foreach ([
    'includes/hosted-game-observability.php',
    'includes/hosted-game-analytics-report.php',
    'includes/hosted-game-diagnostics-export.php',
    'api/hosted-games/telemetry.php',
    'api/merchant/hosted-game-analytics.php',
    'api/admin/hosted-game-analytics.php',
] as $file) {
    $mustNotContain($file,[
        'MG_REWARD_DROP_API_KEY',
        'MG_REWARD_DROP_WEBHOOK_SECRET',
        'api_credential_ciphertext',
        'webhook_secret_ciphertext',
        'password_ciphertext',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR',
    ]);
}

if ($errors !== []) {
    fwrite(STDERR,"Hosted Games analytics and diagnostics validation failed:\n- " . implode("\n- ",$errors) . "\n");
    exit(1);
}

echo "Hosted Games analytics and diagnostics validation passed.\n";
