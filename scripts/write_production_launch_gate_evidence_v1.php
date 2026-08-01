<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$options = getopt('', [
    'gate:',
    'status:',
    'output:',
    'label::',
    'details::',
    'database::',
    'runtime::',
    'artifact::',
]);

$gate = trim((string) ($options['gate'] ?? ''));
$status = strtolower(trim((string) ($options['status'] ?? '')));
$output = trim((string) ($options['output'] ?? ''));

if ($gate === '' || !preg_match('/^[a-z0-9_]+$/', $gate)) {
    fwrite(STDERR, "A valid --gate identifier is required.\n");
    exit(2);
}
if (!in_array($status, ['passed', 'failed'], true)) {
    fwrite(STDERR, "--status must be passed or failed.\n");
    exit(2);
}
if ($output === '') {
    fwrite(STDERR, "--output is required.\n");
    exit(2);
}

$root = dirname(__DIR__);
if (!str_starts_with($output, '/')) {
    $output = $root . '/' . ltrim($output, '/');
}
$directory = dirname($output);
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    fwrite(STDERR, "Unable to create evidence directory.\n");
    exit(1);
}

$commit = trim((string) getenv('MG_CERTIFIED_HEAD_SHA'));
if ($commit === '') {
    $commit = trim((string) getenv('GITHUB_SHA'));
}
if ($commit === '') {
    $resolved = [];
    $code = 1;
    exec('git rev-parse HEAD 2>/dev/null', $resolved, $code);
    if ($code === 0 && isset($resolved[0])) {
        $commit = trim((string) $resolved[0]);
    }
}

$payload = [
    'suite' => 'production_launch_gate_v1',
    'gate' => $gate,
    'status' => $status,
    'label' => trim((string) ($options['label'] ?? '')),
    'details' => trim((string) ($options['details'] ?? '')),
    'database' => trim((string) ($options['database'] ?? '')),
    'runtime' => trim((string) ($options['runtime'] ?? PHP_VERSION)),
    'artifact' => trim((string) ($options['artifact'] ?? '')),
    'commit_sha' => $commit,
    'workflow_run_id' => trim((string) getenv('GITHUB_RUN_ID')),
    'workflow_run_attempt' => trim((string) getenv('GITHUB_RUN_ATTEMPT')),
    'generated_at_utc' => gmdate('c'),
];

file_put_contents($output, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
echo $output . PHP_EOL;
