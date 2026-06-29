<?php
 declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$warnings = [];
$ok = [];

$phpFiles = [
    'examples/local-quest-rewards/training-lab.php',
    'examples/local-quest-rewards/training-campaigns.php',
    'examples/local-quest-rewards/training-campaign-detail.php',
    'examples/local-quest-rewards/training-sequence.php',
    'examples/local-quest-rewards/training-proof-upload.php',
    'examples/local-quest-rewards/admin-training-review.php',
    'examples/local-quest-rewards/admin-training-receipts.php',
    'examples/local-quest-rewards/training-rewards.php',
    'examples/local-quest-rewards/training-profile-wallet.php',
    'examples/local-quest-rewards/training-campaign-data.php',
    'examples/local-quest-rewards/training-storage.php',
    'examples/local-quest-rewards/training-receipt-service.php',
    'examples/local-quest-rewards/training-reward-service.php',
    'scripts/validate_training_campaign_lab.php',
];

function qa_path(string $root, string $path): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function qa_can_shell_exec(): bool
{
    if (!function_exists('shell_exec')) return false;
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    return !in_array('shell_exec', $disabled, true) && !in_array('exec', $disabled, true);
}

foreach ($phpFiles as $file) {
    $full = qa_path($root, $file);
    if (!is_file($full)) {
        $errors[] = "Missing PHP file: {$file}";
        continue;
    }
    $ok[] = "Found {$file}";

    if (qa_can_shell_exec()) {
        $cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($full) . ' 2>&1';
        $output = (string)shell_exec($cmd);
        if (stripos($output, 'No syntax errors detected') === false) {
            $errors[] = "PHP lint failed for {$file}: " . trim($output);
        } else {
            $ok[] = "PHP lint passed for {$file}";
        }
    }
}

if (!qa_can_shell_exec()) {
    $warnings[] = 'shell_exec/exec is unavailable, so PHP lint checks were skipped. Run php -l manually for each training file.';
}

$runtimeDirs = [
    'examples/local-quest-rewards/data',
    'examples/local-quest-rewards/uploads/training-proof',
];

foreach ($runtimeDirs as $dir) {
    $full = qa_path($root, $dir);
    if (!is_dir($full)) {
        if (!@mkdir($full, 0775, true) && !is_dir($full)) {
            $errors[] = "Runtime directory missing and could not be created: {$dir}";
            continue;
        }
    }
    if (!is_writable($full)) {
        $warnings[] = "Runtime directory is not writable: {$dir}";
    } else {
        $ok[] = "Runtime directory writable: {$dir}";
    }
}

$routes = [
    '/training-lab.php',
    '/training-campaigns.php',
    '/training-campaign-detail.php?campaign=5-day-movement-challenge',
    '/training-sequence.php?campaign=5-day-movement-challenge',
    '/training-proof-upload.php?campaign=5-day-movement-challenge&task=warm-up',
    '/admin-training-review.php',
    '/admin-training-receipts.php',
    '/training-rewards.php?campaign=5-day-movement-challenge',
    '/training-profile-wallet.php?campaign=5-day-movement-challenge',
];

echo "Training Campaign Lab local QA\n";
echo str_repeat('=', 31) . "\n\n";
echo "Manual browser routes:\n";
foreach ($routes as $route) echo "  http://127.0.0.1:8090{$route}\n";

echo "\nPassed checks: " . count($ok) . "\n";
foreach ($ok as $line) echo "  [OK] {$line}\n";

if ($warnings) {
    echo "\nWarnings: " . count($warnings) . "\n";
    foreach ($warnings as $line) echo "  [WARN] {$line}\n";
}

if ($errors) {
    echo "\nErrors: " . count($errors) . "\n";
    foreach ($errors as $line) echo "  [FAIL] {$line}\n";
    exit(1);
}

echo "\nLocal QA helper passed file/directory checks. Now run the browser flow.\n";
exit(0);
