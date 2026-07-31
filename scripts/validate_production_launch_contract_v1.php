<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$root = dirname(__DIR__);
$manifestPath = $root . '/config/production-launch-certification-v1.php';
$gate = in_array('--gate', $argv, true);
$outputDir = $root . '/build/production-launch-evidence';

foreach ($argv as $index => $argument) {
    if ($argument === '--output-dir' && isset($argv[$index + 1])) {
        $candidate = (string) $argv[$index + 1];
        $outputDir = str_starts_with($candidate, '/') ? $candidate : $root . '/' . ltrim($candidate, '/');
    }
}

if (!is_file($manifestPath)) {
    fwrite(STDERR, "Missing production launch manifest.\n");
    exit(1);
}

/** @var array<string,mixed> $manifest */
$manifest = require $manifestPath;
$checks = [];

$record = static function (string $id, string $label, bool $passed, string $detail = '') use (&$checks): void {
    $checks[] = [
        'id' => $id,
        'label' => $label,
        'passed' => $passed,
        'detail' => $detail,
    ];
};

$requiredTopLevel = [
    'version',
    'name',
    'target_branch',
    'required_files',
    'required_composer_scripts',
    'content_contracts',
    'required_automated_gates',
    'manual_signoffs',
    'scope_exclusions',
];
foreach ($requiredTopLevel as $key) {
    $record('manifest-' . $key, 'Manifest defines ' . $key, array_key_exists($key, $manifest));
}

$requiredFiles = is_array($manifest['required_files'] ?? null) ? $manifest['required_files'] : [];
foreach ($requiredFiles as $path) {
    $path = (string) $path;
    $record(
        'file-' . preg_replace('/[^a-z0-9]+/i', '-', $path),
        'Required launch file exists: ' . $path,
        is_file($root . '/' . $path),
        is_file($root . '/' . $path) ? 'present' : 'missing'
    );
}

$composerPath = $root . '/composer.json';
$composer = [];
if (is_file($composerPath)) {
    try {
        $decoded = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
        $composer = is_array($decoded) ? $decoded : [];
        $record('composer-json', 'composer.json is valid JSON', true);
    } catch (Throwable $error) {
        $record('composer-json', 'composer.json is valid JSON', false, $error->getMessage());
    }
} else {
    $record('composer-json', 'composer.json is valid JSON', false, 'missing');
}

$composerScripts = is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];
$requiredComposerScripts = is_array($manifest['required_composer_scripts'] ?? null) ? $manifest['required_composer_scripts'] : [];
foreach ($requiredComposerScripts as $script) {
    $script = (string) $script;
    $record(
        'composer-script-' . preg_replace('/[^a-z0-9]+/i', '-', $script),
        'Composer launch command exists: ' . $script,
        isset($composerScripts[$script]) && is_string($composerScripts[$script]) && trim($composerScripts[$script]) !== '',
        isset($composerScripts[$script]) ? (string) $composerScripts[$script] : 'missing'
    );
}

$contentContracts = is_array($manifest['content_contracts'] ?? null) ? $manifest['content_contracts'] : [];
foreach ($contentContracts as $path => $tokens) {
    $path = (string) $path;
    $content = is_file($root . '/' . $path) ? (string) file_get_contents($root . '/' . $path) : '';
    foreach ((array) $tokens as $token) {
        $token = (string) $token;
        $record(
            'content-' . preg_replace('/[^a-z0-9]+/i', '-', $path . '-' . $token),
            $path . ' contains launch contract token: ' . $token,
            $content !== '' && stripos($content, $token) !== false,
            $content === '' ? 'file missing or empty' : 'token lookup'
        );
    }
}

$automatedGates = is_array($manifest['required_automated_gates'] ?? null) ? $manifest['required_automated_gates'] : [];
$totalWeight = 0;
foreach ($automatedGates as $id => $definition) {
    $definition = is_array($definition) ? $definition : [];
    $weight = (int) ($definition['weight'] ?? 0);
    $label = trim((string) ($definition['label'] ?? ''));
    $totalWeight += $weight;
    $record('gate-' . (string) $id, 'Automated gate is valid: ' . (string) $id, $weight > 0 && $label !== '', 'weight=' . $weight);
}
$record('gate-weight-total', 'Automated gate weights total 100', $totalWeight === 100, 'total=' . $totalWeight);

$manualSignoffs = is_array($manifest['manual_signoffs'] ?? null) ? $manifest['manual_signoffs'] : [];
$record('manual-signoffs', 'Manual production sign-off catalog is defined', count($manualSignoffs) >= 8, 'count=' . count($manualSignoffs));
foreach ($manualSignoffs as $id => $description) {
    $record(
        'manual-' . (string) $id,
        'Manual sign-off is actionable: ' . (string) $id,
        trim((string) $description) !== '',
        (string) $description
    );
}

$scopeExclusions = array_map('strval', is_array($manifest['scope_exclusions'] ?? null) ? $manifest['scope_exclusions'] : []);
$hasAccessibilityExclusion = false;
foreach ($scopeExclusions as $exclusion) {
    if (stripos($exclusion, 'accessibility') !== false) {
        $hasAccessibilityExclusion = true;
        break;
    }
}
$record('scope-accessibility-excluded', 'Accessibility work is explicitly outside this launch package', $hasAccessibilityExclusion);

$passedCount = count(array_filter($checks, static fn(array $check): bool => $check['passed'] === true));
$failed = array_values(array_filter($checks, static fn(array $check): bool => $check['passed'] !== true));
$totalCount = count($checks);
$score = $totalCount > 0 ? round(($passedCount / $totalCount) * 10, 2) : 0.0;
$status = $failed === [] ? 'passed' : 'failed';

if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Unable to create launch evidence directory.\n");
    exit(1);
}

$payload = [
    'suite' => 'production_launch_contract_v1',
    'version' => (string) ($manifest['version'] ?? 'unknown'),
    'status' => $status,
    'score_out_of_10' => $score,
    'passed_checks' => $passedCount,
    'total_checks' => $totalCount,
    'failed_checks' => $failed,
    'scope_exclusions' => $scopeExclusions,
    'generated_at_utc' => gmdate('c'),
    'checks' => $checks,
];

$jsonPath = rtrim($outputDir, '/') . '/launch-contract-v1.json';
$markdownPath = rtrim($outputDir, '/') . '/launch-contract-v1.md';
file_put_contents($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);

$markdown = [
    '# Production Launch Contract v1',
    '',
    '- Status: **' . strtoupper($status) . '**',
    '- Score: **' . number_format($score, 2) . '/10**',
    '- Checks: **' . $passedCount . '/' . $totalCount . ' passed**',
    '- Generated: `' . $payload['generated_at_utc'] . '`',
    '',
    '## Results',
    '',
];
foreach ($checks as $check) {
    $markdown[] = sprintf('- [%s] **%s**%s', $check['passed'] ? 'x' : ' ', $check['label'], $check['detail'] !== '' ? ' — ' . $check['detail'] : '');
}
$markdown[] = '';
$markdown[] = 'This contract validates that the launch-certification machinery and required evidence paths exist. It does not replace the database, browser, payment-provider, email-delivery, legal, deployment, or manual production sign-offs.';
file_put_contents($markdownPath, implode(PHP_EOL, $markdown) . PHP_EOL);

echo json_encode([
    'status' => $status,
    'score_out_of_10' => $score,
    'evidence_json' => str_replace($root . '/', '', $jsonPath),
    'evidence_markdown' => str_replace($root . '/', '', $markdownPath),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

if ($gate && $status !== 'passed') {
    exit(1);
}
