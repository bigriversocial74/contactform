<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$options = getopt('', [
    'evidence-dir:',
    'output::',
    'expected-head::',
    'manual-evidence::',
    'gate',
]);

$root = dirname(__DIR__);
$manifest = require $root . '/config/production-launch-certification-v1.php';
$evidenceDirectory = trim((string) ($options['evidence-dir'] ?? ''));
if ($evidenceDirectory === '') {
    fwrite(STDERR, "--evidence-dir is required.\n");
    exit(2);
}
if (!str_starts_with($evidenceDirectory, '/')) {
    $evidenceDirectory = $root . '/' . ltrim($evidenceDirectory, '/');
}
if (!is_dir($evidenceDirectory)) {
    fwrite(STDERR, "Evidence directory does not exist: {$evidenceDirectory}\n");
    exit(1);
}

$output = trim((string) ($options['output'] ?? 'build/production-launch-evidence/production-launch-certification-v1.json'));
if (!str_starts_with($output, '/')) {
    $output = $root . '/' . ltrim($output, '/');
}
$outputDirectory = dirname($output);
if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
    fwrite(STDERR, "Unable to create summary directory.\n");
    exit(1);
}

$expectedHead = trim((string) ($options['expected-head'] ?? getenv('MG_CERTIFIED_HEAD_SHA') ?: getenv('GITHUB_SHA')));
$gateMode = array_key_exists('gate', $options);
$discovered = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($evidenceDirectory, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'json') {
        continue;
    }
    try {
        $payload = json_decode((string) file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        continue;
    }
    if (!is_array($payload) || ($payload['suite'] ?? '') !== 'production_launch_gate_v1') {
        continue;
    }
    $gateId = trim((string) ($payload['gate'] ?? ''));
    if ($gateId === '') {
        continue;
    }
    $payload['_source'] = str_replace($root . '/', '', $file->getPathname());
    $discovered[$gateId] = $payload;
}

$requiredGates = is_array($manifest['required_automated_gates'] ?? null) ? $manifest['required_automated_gates'] : [];
$results = [];
$earned = 0;
$possible = 0;
foreach ($requiredGates as $gateId => $definition) {
    $definition = is_array($definition) ? $definition : [];
    $weight = (int) ($definition['weight'] ?? 0);
    $possible += $weight;
    $evidence = $discovered[$gateId] ?? null;
    $statusPassed = is_array($evidence) && ($evidence['status'] ?? '') === 'passed';
    $evidenceHead = is_array($evidence) ? trim((string) ($evidence['commit_sha'] ?? '')) : '';
    $headMatches = $expectedHead === '' || $evidenceHead === '' || hash_equals($expectedHead, $evidenceHead);
    $passed = $statusPassed && $headMatches;
    if ($passed) {
        $earned += $weight;
    }
    $results[$gateId] = [
        'label' => (string) ($definition['label'] ?? $gateId),
        'weight' => $weight,
        'passed' => $passed,
        'status' => is_array($evidence) ? (string) ($evidence['status'] ?? 'unknown') : 'missing',
        'commit_sha' => $evidenceHead,
        'head_matches' => $headMatches,
        'source' => is_array($evidence) ? (string) ($evidence['_source'] ?? '') : '',
        'details' => is_array($evidence) ? (string) ($evidence['details'] ?? '') : 'Required gate evidence was not found.',
    ];
}

$automatedPassed = $possible === 100 && $earned === $possible;
$automatedScore = $possible > 0 ? round(($earned / $possible) * 10, 2) : 0.0;

$manualDefinitions = is_array($manifest['manual_signoffs'] ?? null) ? $manifest['manual_signoffs'] : [];
$manualResults = [];
$manualEvidencePath = trim((string) ($options['manual-evidence'] ?? ''));
$manualPayload = [];
if ($manualEvidencePath !== '') {
    if (!str_starts_with($manualEvidencePath, '/')) {
        $manualEvidencePath = $root . '/' . ltrim($manualEvidencePath, '/');
    }
    if (is_file($manualEvidencePath)) {
        try {
            $decoded = json_decode((string) file_get_contents($manualEvidencePath), true, 512, JSON_THROW_ON_ERROR);
            $manualPayload = is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            $manualPayload = [];
        }
    }
}

$manualApproved = $manualDefinitions !== [];
foreach ($manualDefinitions as $id => $description) {
    $entry = is_array($manualPayload['signoffs'][$id] ?? null) ? $manualPayload['signoffs'][$id] : [];
    $status = strtolower(trim((string) ($entry['status'] ?? 'pending')));
    $approved = $status === 'approved'
        && trim((string) ($entry['approved_by'] ?? '')) !== ''
        && trim((string) ($entry['approved_at'] ?? '')) !== '';
    if (!$approved) {
        $manualApproved = false;
    }
    $manualResults[$id] = [
        'description' => (string) $description,
        'status' => $status,
        'approved' => $approved,
        'approved_by' => (string) ($entry['approved_by'] ?? ''),
        'approved_at' => (string) ($entry['approved_at'] ?? ''),
        'evidence' => (string) ($entry['evidence'] ?? ''),
    ];
}

if (!$automatedPassed) {
    $launchDecision = 'blocked_automated_certification_failed';
} elseif (!$manualApproved) {
    $launchDecision = 'automated_certified_manual_signoff_required';
} else {
    $launchDecision = 'approved_for_controlled_production_launch';
}

$payload = [
    'suite' => 'production_launch_certification_v1',
    'version' => (string) ($manifest['version'] ?? 'unknown'),
    'candidate_head_sha' => $expectedHead,
    'automated_status' => $automatedPassed ? 'passed' : 'failed',
    'automated_score_out_of_10' => $automatedScore,
    'automated_points' => ['earned' => $earned, 'possible' => $possible],
    'manual_status' => $manualApproved ? 'approved' : 'pending',
    'launch_decision' => $launchDecision,
    'scope_exclusions' => array_values((array) ($manifest['scope_exclusions'] ?? [])),
    'generated_at_utc' => gmdate('c'),
    'automated_gates' => $results,
    'manual_signoffs' => $manualResults,
];
file_put_contents($output, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);

$markdownPath = preg_replace('/\.json$/', '.md', $output) ?: $output . '.md';
$markdown = [
    '# Microgifter Production Launch Certification v1',
    '',
    '- Candidate head: `' . ($expectedHead !== '' ? $expectedHead : 'not supplied') . '`',
    '- Automated status: **' . strtoupper($payload['automated_status']) . '**',
    '- Automated score: **' . number_format($automatedScore, 2) . '/10**',
    '- Manual status: **' . strtoupper($payload['manual_status']) . '**',
    '- Launch decision: **' . $launchDecision . '**',
    '',
    '## Automated gates',
    '',
];
foreach ($results as $id => $result) {
    $markdown[] = sprintf(
        '- [%s] **%s** — %d points — %s%s',
        $result['passed'] ? 'x' : ' ',
        $result['label'],
        $result['weight'],
        $result['status'],
        $result['source'] !== '' ? ' — `' . $result['source'] . '`' : ''
    );
}
$markdown[] = '';
$markdown[] = '## Manual production sign-offs';
$markdown[] = '';
foreach ($manualResults as $id => $result) {
    $markdown[] = sprintf('- [%s] **%s** — %s', $result['approved'] ? 'x' : ' ', $id, $result['description']);
}
$markdown[] = '';
$markdown[] = 'Automated certification never substitutes for live payment, email, legal, production configuration, backup retention, restoration, support, feature-scope, deployment, or rollback approval.';
file_put_contents($markdownPath, implode(PHP_EOL, $markdown) . PHP_EOL);

echo json_encode([
    'automated_status' => $payload['automated_status'],
    'automated_score_out_of_10' => $automatedScore,
    'manual_status' => $payload['manual_status'],
    'launch_decision' => $launchDecision,
    'summary_json' => str_replace($root . '/', '', $output),
    'summary_markdown' => str_replace($root . '/', '', $markdownPath),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

if ($gateMode && !$automatedPassed) {
    exit(1);
}
