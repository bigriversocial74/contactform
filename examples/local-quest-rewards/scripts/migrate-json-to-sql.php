<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app.php';

$config = lqr_config();
if (!lqr_storage_uses_sql($config)) {
    fwrite(STDERR, "Set storage.driver to mysql in config.php before running this migration.\n");
    exit(1);
}
$jsonPath = lqr_state_path();
if (!is_file($jsonPath)) {
    fwrite(STDERR, "No JSON state file found at {$jsonPath}. Nothing to migrate.\n");
    exit(0);
}
$state = json_decode((string)file_get_contents($jsonPath), true);
if (!is_array($state)) {
    fwrite(STDERR, "JSON state file is invalid.\n");
    exit(1);
}
$state = array_replace_recursive(lqr_default_state(), $state);
lqr_sql_save_state($config, $state);
$loaded = lqr_sql_load_state($config);
$result = [
    'ok' => true,
    'migrated_from' => $jsonPath,
    'users' => count((array)($loaded['users'] ?? [])),
    'events' => count((array)($loaded['events'] ?? [])),
    'admin_users' => count((array)($loaded['admin_users'] ?? [])),
    'link_states' => count((array)($loaded['link_states'] ?? [])),
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
