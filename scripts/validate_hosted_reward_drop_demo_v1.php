<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$source = $root . '/examples/hosted-game-reward-drop-demo';
$zipPath = $argv[1] ?? null;
$errors = [];
$requiredFiles = [
    'index.html',
    'game.css',
    'game.js',
    'game.json',
    'assets/cover.svg',
    'assets/icon.svg',
];

foreach ($requiredFiles as $relativePath) {
    if (!is_file($source . '/' . $relativePath)) {
        $errors[] = "Missing demo source file: {$relativePath}";
    }
}

$manifestRaw = @file_get_contents($source . '/game.json');
$manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
if (!is_array($manifest) || json_last_error() !== JSON_ERROR_NONE) {
    $errors[] = 'game.json must contain valid JSON.';
} else {
    $expected = [
        'schema' => 'microgifter.hosted-game/v1',
        'name' => 'Reward Drop SDK Demo',
        'version' => '2.0.0',
        'entry' => 'index.html',
        'category' => 'arcade',
    ];
    foreach ($expected as $key => $value) {
        if (($manifest[$key] ?? null) !== $value) {
            $errors[] = "Manifest field {$key} must equal {$value}.";
        }
    }
    foreach (['player','runs','events','state','scores','leaderboard','inbox'] as $capability) {
        if (!in_array($capability, $manifest['capabilities'] ?? [], true)) {
            $errors[] = "Manifest is missing capability: {$capability}";
        }
    }
    foreach (['game_loaded','run_started','level_started','score_updated','level_completed','player_qualified','run_completed','run_abandoned','runtime_error'] as $event) {
        if (!in_array($event, $manifest['events'] ?? [], true)) {
            $errors[] = "Manifest is missing event: {$event}";
        }
    }
    if (($manifest['qualification']['mode'] ?? null) !== 'game_reported') {
        $errors[] = 'Reward Drop demo must use game_reported qualification.';
    }
    if (($manifest['network']['connect'] ?? null) !== []) {
        $errors[] = 'Reward Drop demo must not request external network access.';
    }
}

$gameJs = (string) @file_get_contents($source . '/game.js');
foreach ([
    'MicrogifterGame.ready()',
    'MicrogifterGame.getPlayer()',
    'MicrogifterGame.getProgram()',
    'MicrogifterGame.getReward()',
    'MicrogifterGame.startRun(',
    'MicrogifterGame.levelStarted(',
    'MicrogifterGame.updateScore(',
    'MicrogifterGame.levelCompleted(',
    'MicrogifterGame.submitScore(',
    'MicrogifterGame.qualify(',
    'MicrogifterGame.complete(',
    'MicrogifterGame.saveState(',
    'MicrogifterGame.loadState(',
    'MicrogifterGame.getLeaderboard(',
    'MicrogifterGame.openInbox()',
    'MicrogifterGame.abandonRun(',
    'MicrogifterGame.reportError(',
    'response?.simulated',
    'response?.run?.test_mode',
] as $needle) {
    if (!str_contains($gameJs, $needle)) {
        $errors[] = "game.js is missing SDK contract: {$needle}";
    }
}
foreach (['/games/reward-drop/api/', 'window.RewardDropConfig', 'MG_REWARD_DROP_', 'fetch('] as $forbidden) {
    if (str_contains($gameJs, $forbidden)) {
        $errors[] = "game.js contains forbidden standalone-game contract: {$forbidden}";
    }
}

$index = (string) @file_get_contents($source . '/index.html');
foreach (['data-reward-drop','data-mode-badge','data-sdk-version','data-arena','data-player','data-program','data-reward','data-leaderboard'] as $needle) {
    if (!str_contains($index, $needle)) {
        $errors[] = "index.html is missing UI contract: {$needle}";
    }
}
if (str_contains($index, '<?php') || str_contains($index, '/games/reward-drop/api/')) {
    $errors[] = 'The uploadable demo must be static and SDK-driven.';
}

if (is_string($zipPath) && $zipPath !== '') {
    if (!class_exists('ZipArchive')) {
        $errors[] = 'The PHP Zip extension is required to validate the package.';
    } elseif (!is_file($zipPath)) {
        $errors[] = 'Generated package ZIP was not found.';
    } else {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            $errors[] = 'Generated package ZIP could not be opened.';
        } else {
            $actual = [];
            for ($indexNumber = 0; $indexNumber < $zip->numFiles; $indexNumber++) {
                $stat = $zip->statIndex($indexNumber);
                if (is_array($stat) && isset($stat['name']) && !str_ends_with((string) $stat['name'], '/')) {
                    $actual[] = (string) $stat['name'];
                }
            }
            sort($actual);
            $expectedFiles = $requiredFiles;
            sort($expectedFiles);
            if ($actual !== $expectedFiles) {
                $errors[] = 'Generated package ZIP contents do not match the approved source list.';
            }
            $zipManifest = $zip->getFromName('game.json');
            if (!is_string($zipManifest) || trim($zipManifest) !== trim((string) $manifestRaw)) {
                $errors[] = 'Generated package manifest does not match source game.json.';
            }
            $zip->close();
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Hosted Reward Drop SDK Demo v1 validation failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "Hosted Reward Drop SDK Demo v1 validation passed.\n";
