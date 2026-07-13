<?php
declare(strict_types=1);

require_once __DIR__ . '/_migration_reconciliation_analyze.php';

function mg_admin_migration_reconciliation_fingerprint(array $items): string
{
    $payload = [];
    foreach ($items as $item) {
        $payload[] = [
            'file' => $item['file'] ?? '',
            'status' => $item['status'] ?? '',
            'checksum' => $item['checksum'] ?? '',
            'keys' => $item['keys'] ?? [],
            'checks' => array_map(static fn(array $check): array => [
                'id' => $check['id'] ?? '',
                'ready' => !empty($check['ready']),
            ], $item['checks'] ?? []),
        ];
    }
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function mg_admin_migration_reconciliation_issue_token(int $userId, string $fingerprint): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $token = bin2hex(random_bytes(32));
    $_SESSION['mg_migration_reconciliation_token_hash'] = hash('sha256', $token);
    $_SESSION['mg_migration_reconciliation_fingerprint'] = $fingerprint;
    $_SESSION['mg_migration_reconciliation_user_id'] = $userId;
    $_SESSION['mg_migration_reconciliation_issued_at'] = time();
    return $token;
}

function mg_admin_migration_reconciliation_verify_token(array $input, int $userId, string $fingerprint): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $token = $input['reconciliation_token'] ?? null;
    $storedHash = $_SESSION['mg_migration_reconciliation_token_hash'] ?? null;
    $storedFingerprint = $_SESSION['mg_migration_reconciliation_fingerprint'] ?? null;
    $storedUser = (int)($_SESSION['mg_migration_reconciliation_user_id'] ?? 0);
    $issuedAt = (int)($_SESSION['mg_migration_reconciliation_issued_at'] ?? 0);
    $valid = is_string($token) && $token !== ''
        && is_string($storedHash) && hash_equals($storedHash, hash('sha256', $token))
        && is_string($storedFingerprint) && hash_equals($storedFingerprint, $fingerprint)
        && $storedUser === $userId
        && $issuedAt >= (time() - 900);
    if (!$valid) mg_fail('Migration reconciliation confirmation expired. Run the analysis again.', 419);
}

function mg_admin_migration_reconciliation_plan(PDO $pdo, int $userId, bool $issueToken = true): array
{
    $migrationPlan = mg_admin_system_health_migration_plan_v2($pdo);
    $files = array_values($migrationPlan['unapplied_files'] ?? []);
    $items = [];
    foreach ($files as $file) $items[] = mg_admin_migration_reconciliation_analyze_file($pdo, (string)$file);

    $summary = ['installed' => 0, 'partial' => 0, 'missing' => 0, 'unsupported' => 0, 'missing_file' => 0, 'empty_file' => 0];
    foreach ($items as $item) {
        $status = (string)($item['status'] ?? 'unsupported');
        if (array_key_exists($status, $summary)) $summary[$status]++;
        else $summary['unsupported']++;
    }
    $recordable = array_values(array_filter($items, static fn(array $item): bool => !empty($item['recordable'])));
    $repairSections = array_values(array_filter(array_map(
        static fn(array $item): ?string => is_string($item['repair_sql'] ?? null) ? (string)$item['repair_sql'] : null,
        $items
    )));
    $fingerprint = mg_admin_migration_reconciliation_fingerprint($items);
    $token = $issueToken && $recordable !== [] ? mg_admin_migration_reconciliation_issue_token($userId, $fingerprint) : null;

    return [
        'ready' => $files === [],
        'generated_at' => gmdate('c'),
        'summary' => $summary,
        'unapplied_count' => count($files),
        'recordable_count' => count($recordable),
        'recordable_files' => array_values(array_map(static fn(array $item): string => (string)$item['file'], $recordable)),
        'partial_files' => array_values(array_map(static fn(array $item): string => (string)$item['file'], array_filter($items, static fn(array $item): bool => ($item['status'] ?? '') === 'partial'))),
        'missing_files' => array_values(array_map(static fn(array $item): string => (string)$item['file'], array_filter($items, static fn(array $item): bool => ($item['status'] ?? '') === 'missing'))),
        'unsupported_files' => array_values(array_map(static fn(array $item): string => (string)$item['file'], array_filter($items, static fn(array $item): bool => ($item['status'] ?? '') === 'unsupported'))),
        'items' => $items,
        'fingerprint' => $fingerprint,
        'reconciliation_token' => $token,
        'repair_plan' => [
            'available' => $repairSections !== [],
            'filename' => 'microgifter_migration_repair_' . gmdate('Ymd_His') . '.sql',
            'sql' => $repairSections === [] ? null : "-- Microgifter migration reconciliation repair plan\n-- Generated " . gmdate('c') . "\n-- Review and import through the approved database process.\n\n" . implode("\n\n", $repairSections) . "\n",
        ],
        'note' => 'Analysis is read-only. Reconciliation records only migrations whose required tables, columns, indexes, constraints, ENUM values, and supported seed rows are already present. It never executes migration DDL.',
    ];
}

function mg_admin_migration_reconciliation_apply(PDO $pdo, array $input, int $userId): array
{
    $plan = mg_admin_migration_reconciliation_plan($pdo, $userId, false);
    mg_admin_migration_reconciliation_verify_token($input, $userId, (string)$plan['fingerprint']);
    $recordable = array_values(array_filter($plan['items'], static fn(array $item): bool => !empty($item['recordable'])));
    if ($recordable === []) {
        return ['recorded_count' => 0, 'recorded_files' => [], 'plan' => mg_admin_migration_reconciliation_plan($pdo, $userId, true)];
    }

    $lockName = 'microgifter_migration_reconciliation';
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 15)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) mg_fail('Migration reconciliation is already running.', 409);

    $recordedFiles = [];
    try {
        $pdo->beginTransaction();
        $existing = mg_migration_applied_rows($pdo);
        $insert = $pdo->prepare(
            'INSERT INTO schema_migrations (migration_key,description,checksum,applied_at)
             VALUES (?,?,?,NOW())'
        );
        $update = $pdo->prepare(
            'UPDATE schema_migrations SET checksum=?,description=COALESCE(description,?) WHERE migration_key=?'
        );
        foreach ($recordable as $item) {
            foreach ($item['keys'] as $key) {
                $key = (string)$key;
                $checksum = (string)$item['checksum'];
                if (array_key_exists($key, $existing)) {
                    $stored = $existing[$key];
                    if (is_string($stored) && $stored !== '' && !hash_equals($stored, $checksum)) {
                        throw new RuntimeException('Checksum conflict appeared during reconciliation for ' . $key . '.');
                    }
                    if ($stored === null || $stored === '') $update->execute([$checksum, 'Schema verified by migration reconciliation: ' . $item['file'], $key]);
                    continue;
                }
                $insert->execute([$key, 'Schema verified by migration reconciliation: ' . $item['file'], $checksum]);
                $existing[$key] = $checksum;
            }
            $recordedFiles[] = (string)$item['file'];
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    } finally {
        try {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        } catch (Throwable) {
        }
    }

    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    unset(
        $_SESSION['mg_migration_reconciliation_token_hash'],
        $_SESSION['mg_migration_reconciliation_fingerprint'],
        $_SESSION['mg_migration_reconciliation_user_id'],
        $_SESSION['mg_migration_reconciliation_issued_at']
    );

    return [
        'recorded_count' => count($recordedFiles),
        'recorded_files' => $recordedFiles,
        'plan' => mg_admin_migration_reconciliation_plan($pdo, $userId, true),
    ];
}
