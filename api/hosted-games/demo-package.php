<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');
$user = mg_require_api_user();
$userId = (int) $user['id'];
if (function_exists('mg_rate_limit')) {
    mg_rate_limit('hosted_game.demo_package', 'user:' . $userId, 20, 3600);
}

$root = dirname(__DIR__, 2);
$source = $root . '/examples/hosted-game-reward-drop-demo';
$files = [
    'index.html',
    'game.css',
    'game.js',
    'game.json',
    'assets/cover.svg',
    'assets/icon.svg',
];

if (!class_exists('ZipArchive')) {
    mg_fail('The server Zip extension is required to build the demo package.', 503);
}
foreach ($files as $relativePath) {
    if (!is_file($source . '/' . $relativePath)) {
        mg_fail('The Reward Drop demo package source is incomplete.', 503);
    }
}

$tempBase = tempnam(sys_get_temp_dir(), 'mg-reward-drop-');
if (!is_string($tempBase) || $tempBase === '') {
    mg_fail('Unable to prepare the demo package.', 500);
}
@unlink($tempBase);
$tempPath = $tempBase . '.zip';
register_shutdown_function(static function () use ($tempPath): void {
    if (is_file($tempPath)) @unlink($tempPath);
});

$zip = new ZipArchive();
if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    mg_fail('Unable to build the demo package.', 500);
}
foreach ($files as $relativePath) {
    if (!$zip->addFile($source . '/' . $relativePath, $relativePath)) {
        $zip->close();
        mg_fail('Unable to build the demo package.', 500);
    }
}
$zip->setArchiveComment('Microgifter Reward Drop Hosted Game SDK Demo v2.0.0');
$zip->close();

$bytes = filesize($tempPath);
if ($bytes === false || $bytes < 1) {
    mg_fail('The generated demo package is invalid.', 500);
}

try {
    mg_audit('hosted_game.demo_package_downloaded', 'hosted_game_demo', [
        'package' => 'reward-drop-sdk-demo-v2.0.0.zip',
        'byte_size' => $bytes,
        'sha256' => hash_file('sha256', $tempPath),
    ], $userId);
} catch (Throwable) {
    // A completed package build must remain downloadable if audit logging is unavailable.
}

header('Content-Type: application/zip');
header('Content-Length: ' . (string) $bytes);
header('Content-Disposition: attachment; filename="reward-drop-sdk-demo-v2.0.0.zip"');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
readfile($tempPath);
exit;
