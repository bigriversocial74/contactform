<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$options = getopt('', [
    'artifact-manifest:',
    'backup-report:',
    'rollback-report:',
    'reproducible-report:',
    'output:',
]);

$required = [
    'artifact-manifest',
    'backup-report',
    'rollback-report',
    'reproducible-report',
    'output',
];
foreach ($required as $key) {
    if (!isset($options[$key]) || trim((string) $options[$key]) === '') {
        fwrite(STDERR, "Missing required option --{$key}.\n");
        exit(2);
    }
}

$readJson = static function (string $path, string $label): array {
    if (!is_file($path)) {
        throw new RuntimeException("{$label} file does not exist: {$path}");
    }
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException("{$label} did not contain a JSON object.");
    }
    return $decoded;
};

try {
    $artifact = $readJson((string) $options['artifact-manifest'], 'Artifact manifest');
    $backup = $readJson((string) $options['backup-report'], 'Backup report');
    $rollback = $readJson((string) $options['rollback-report'], 'Rollback report');
    $reproducible = $readJson((string) $options['reproducible-report'], 'Reproducibility report');

    foreach ([
        'backup_restore' => $backup['status'] ?? null,
        'rollback_package' => $rollback['status'] ?? null,
        'reproducible_artifact' => $reproducible['status'] ?? null,
    ] as $gate => $status) {
        if ($status !== 'passed') {
            throw new RuntimeException("Cannot assemble release evidence because {$gate} did not pass.");
        }
    }

    $commitSha = strtolower(trim((string) ($artifact['git_commit_sha'] ?? '')));
    if (preg_match('/^[a-f0-9]{40}$/', $commitSha) !== 1) {
        throw new RuntimeException('Artifact manifest does not contain a valid commit SHA.');
    }

    $payload = [
        'schema_version' => 1,
        'release_version' => (string) ($artifact['release_version'] ?? ''),
        'git_commit_sha' => $commitSha,
        'generated_at' => gmdate('c'),
        'source_of_truth' => 'bigriversocial74/contactform repository root',
        'candidate_artifact' => [
            'file' => (string) ($artifact['artifact_file'] ?? ''),
            'sha256' => (string) ($artifact['artifact_sha256'] ?? ''),
            'size_bytes' => (int) ($artifact['artifact_size_bytes'] ?? 0),
            'migration_manifest_sha256' => (string) ($artifact['migration_manifest_sha256'] ?? ''),
        ],
        'automated_evidence' => [
            'reproducible_release_artifact' => [
                'status' => 'passed',
                'builds_compared' => (int) ($reproducible['builds_compared'] ?? 0),
                'artifact_sha256' => (string) ($reproducible['artifact_sha256'] ?? ''),
            ],
            'database_backup_restore_dry_run' => [
                'status' => 'passed',
                'canary_verified' => (bool) ($backup['canary_verified'] ?? false),
                'canonical_migration_manifest_verified' => (bool) ($backup['canonical_migration_manifest_verified'] ?? false),
                'source_table_count' => (int) ($backup['source_table_count'] ?? 0),
                'restore_table_count' => (int) ($backup['restore_table_count'] ?? 0),
            ],
            'rollback_artifact_pair' => [
                'status' => 'passed',
                'rollback_git_commit_sha' => (string) ($rollback['rollback']['git_commit_sha'] ?? ''),
                'rollback_artifact_sha256' => (string) ($rollback['rollback']['artifact_sha256'] ?? ''),
                'php_syntax_verified' => (bool) ($rollback['rollback']['php_syntax_verified'] ?? false),
            ],
        ],
        'live_environment_gates' => [
            'candidate_uploaded_to_staging' => ['status' => 'deferred', 'reason' => 'Candidate files have not been uploaded to the staging host.'],
            'target_database_backup_retained' => ['status' => 'deferred', 'reason' => 'Must be executed against the target staging database immediately before deployment.'],
            'target_media_backup_retained' => ['status' => 'deferred', 'reason' => 'Must be executed against the configured persistent media directory.'],
            'target_rollback_drill' => ['status' => 'deferred', 'reason' => 'Requires the target host and its predeployment backup.'],
            'stripe_test_provider_boundary' => ['status' => 'deferred', 'reason' => 'Requires protected Stripe test credentials.'],
            'hosted_checkout_and_signed_webhook' => ['status' => 'deferred', 'reason' => 'Requires the deployed staging application and public webhook URL.'],
            'end_to_end_fulfillment_transfer_and_redemption' => ['status' => 'deferred', 'reason' => 'Requires a deployed staging purchase and merchant claim.'],
            'deployed_launch_readiness' => ['status' => 'deferred', 'reason' => 'Requires the target server configuration and database.'],
        ],
        'release_approval' => [
            'status' => 'blocked',
            'reason' => 'Automated package evidence is complete, but live staging gates remain deferred.',
        ],
    ];

    $output = (string) $options['output'];
    $directory = dirname($output);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create evidence directory: {$directory}");
    }
    file_put_contents(
        $output,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
    );
    fwrite(STDOUT, "Release evidence created: {$output}\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
