<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$source = $root . '/examples/hosted-game-reward-drop-demo';
$output = $argv[1] ?? ($root . '/examples/packages/reward-drop-sdk-demo-v2.0.0.zip');
$files = [
    'index.html',
    'game.css',
    'game.js',
    'game.json',
    'assets/cover.svg',
    'assets/icon.svg',
];

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "The PHP Zip extension is required.\n");
    exit(1);
}

foreach ($files as $relativePath) {
    if (!is_file($source . '/' . $relativePath)) {
        fwrite(STDERR, "Missing package source file: {$relativePath}\n");
        exit(1);
    }
}

$directory = dirname($output);
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    fwrite(STDERR, "Unable to create ZIP output directory.\n");
    exit(1);
}
if (is_file($output) && !unlink($output)) {
    fwrite(STDERR, "Unable to replace the existing ZIP.\n");
    exit(1);
}

$zip = new ZipArchive();
$result = $zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE);
if ($result !== true) {
    fwrite(STDERR, "Unable to create Reward Drop demo ZIP.\n");
    exit(1);
}

foreach ($files as $relativePath) {
    if (!$zip->addFile($source . '/' . $relativePath, $relativePath)) {
        $zip->close();
        @unlink($output);
        fwrite(STDERR, "Unable to add {$relativePath} to the ZIP.\n");
        exit(1);
    }
}
$zip->setArchiveComment('Microgifter Reward Drop Hosted Game SDK Demo v2.0.0');
$zip->close();

$bytes = filesize($output);
$checksum = hash_file('sha256', $output);
if ($bytes === false || !is_string($checksum)) {
    fwrite(STDERR, "Unable to verify the generated ZIP.\n");
    exit(1);
}

echo $output . PHP_EOL;
echo 'bytes=' . $bytes . PHP_EOL;
echo 'sha256=' . $checksum . PHP_EOL;
