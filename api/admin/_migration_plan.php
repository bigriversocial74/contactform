<?php
declare(strict_types=1);

/**
 * Build a read-only, file-by-file migration status report.
 *
 * Status meanings:
 * - applied: every migration key in the SQL file exists in schema_migrations
 * - covered: a later coverage marker satisfies the file
 * - missing: SQL file exists, but its migration key is not recorded as applied
 * - missing_file: manifest entry is absent from /database
 * - empty_file: manifest entry exists but contains no SQL
 * - missing_ledger: schema_migrations does not exist
 */
function mg_admin_system_health_migration_plan_v2(PDO $pdo): array
{
    $manifest = mg_migration_manifest();
    $orderedFiles = array_values($manifest['ordered_files']);
    $databaseDir = mg_migration_database_dir();
    $generatedAt = gmdate('c');

    if (!mg_admin_system_health_table_exists($pdo, 'schema_migrations')) {
        $items = [];
        foreach ($orderedFiles as $file) {
            $path = rtrim($databaseDir, '/') . '/' . $file;
            $exists = is_file($path);
            $items[] = [
                'file' => $file,
                'status' => $exists ? 'missing_ledger' : 'missing_file',
                'physical_file' => $exists,
                'keys' => [],
                'checksum' => null,
                'checksum_mismatch' => false,
                'action' => $exists ? 'restore_migration_ledger' : 'upload_file_and_restore_ledger',
            ];
        }

        $physicalMissing = array_values(array_map(
            static fn(array $item): string => (string)$item['file'],
            array_filter($items, static fn(array $item): bool => !$item['physical_file'])
        ));

        return [
            'ready' => false,
            'ledger_ready' => false,
            'generated_at' => $generatedAt,
            'manifest_count' => count($orderedFiles),
            'applied_key_count' => 0,
            'coverage_cutoff' => -1,
            'summary' => [
                'applied' => 0,
                'covered' => 0,
                'unapplied' => count($orderedFiles) - count($physicalMissing),
                'physical_missing' => count($physicalMissing),
                'empty_files' => 0,
                'checksum_mismatches' => 0,
            ],
            'missing_count' => count($orderedFiles),
            'missing_files' => $orderedFiles,
            'physical_missing_files' => $physicalMissing,
            'unapplied_files' => array_values(array_diff($orderedFiles, $physicalMissing)),
            'checksum_mismatch_count' => 0,
            'checksum_mismatches' => [],
            'items' => $items,
            'command' => 'php scripts/run_migrations.php',
            'note' => 'The schema_migrations ledger is missing. Do not run migrations until the database and deployment files are reviewed.',
        ];
    }

    $status = mg_migration_status($pdo);
    $mismatchByFile = [];
    foreach ($status['checksum_mismatches'] as $mismatch) {
        $file = (string)($mismatch['file'] ?? '');
        if ($file !== '') $mismatchByFile[$file][] = $mismatch;
    }

    $summary = [
        'applied' => 0,
        'covered' => 0,
        'unapplied' => 0,
        'physical_missing' => 0,
        'empty_files' => 0,
        'checksum_mismatches' => count($status['checksum_mismatches']),
    ];
    $items = [];
    $physicalMissing = [];
    $unapplied = [];

    foreach ($status['items'] as $item) {
        $file = (string)($item['file'] ?? '');
        $itemStatus = (string)($item['status'] ?? 'missing');
        $physicalFile = !in_array($itemStatus, ['missing_file', 'empty_file'], true);
        $checksumMismatch = isset($mismatchByFile[$file]);

        if ($itemStatus === 'applied') $summary['applied']++;
        elseif ($itemStatus === 'covered') $summary['covered']++;
        elseif ($itemStatus === 'missing_file') {
            $summary['physical_missing']++;
            $physicalMissing[] = $file;
        } elseif ($itemStatus === 'empty_file') {
            $summary['empty_files']++;
            $physicalMissing[] = $file;
        } else {
            $summary['unapplied']++;
            $unapplied[] = $file;
        }

        $action = match ($itemStatus) {
            'missing_file' => 'upload_file',
            'empty_file' => 'replace_empty_file',
            'missing' => 'review_then_apply',
            'covered' => 'none_covered',
            'applied' => $checksumMismatch ? 'review_checksum' : 'none',
            default => 'review',
        };

        $items[] = [
            'file' => $file,
            'status' => $itemStatus,
            'physical_file' => $physicalFile,
            'keys' => array_values($item['keys'] ?? []),
            'checksum' => $item['checksum'] ?? null,
            'checksum_mismatch' => $checksumMismatch,
            'mismatches' => $mismatchByFile[$file] ?? [],
            'action' => $action,
        ];
    }

    $missingFiles = array_values($status['missing']);

    return [
        'ready' => (bool)$status['ready'],
        'ledger_ready' => true,
        'generated_at' => $generatedAt,
        'manifest_count' => (int)$status['ordered_count'],
        'applied_key_count' => (int)$status['applied_key_count'],
        'coverage_cutoff' => (int)$status['coverage_cutoff'],
        'summary' => $summary,
        'missing_count' => count($missingFiles),
        'missing_files' => $missingFiles,
        'physical_missing_files' => array_values(array_unique($physicalMissing)),
        'unapplied_files' => array_values(array_unique($unapplied)),
        'checksum_mismatch_count' => count($status['checksum_mismatches']),
        'checksum_mismatches' => array_values($status['checksum_mismatches']),
        'items' => $items,
        'command' => 'php scripts/run_migrations.php',
        'note' => 'Read-only report. Upload missing SQL files first, review unapplied migrations in manifest order, and do not execute DDL from the browser.',
    ];
}
