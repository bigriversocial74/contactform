<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/migrations.php';
require_once __DIR__ . '/_system_health_metrics.php';
require_once __DIR__ . '/_system_health_security.php';

function mg_admin_system_health_require_user(): array
{
    return mg_require_permission('admin.health.view');
}

function mg_admin_system_health_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]{1,64}$/', $table) !== 1) {
        throw new InvalidArgumentException('Invalid system health table name.');
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function mg_admin_system_health_service(string $status, string $summary, array $details = []): array
{
    if (!in_array($status, ['healthy', 'warning', 'critical'], true)) $status = 'warning';
    return [
        'status' => $status,
        'summary' => mb_substr(trim($summary), 0, 240),
        'details' => $details,
    ];
}

function mg_admin_system_health_storage(): array
{
    try {
        $storage = mg_storage_assert_ready(false, false);
        $healthy = !empty($storage['persistent']) && !empty($storage['initialized']) && !empty($storage['writable']);
        return mg_admin_system_health_service(
            $healthy ? 'healthy' : 'critical',
            $healthy ? 'Persistent media storage is available.' : 'Persistent media storage is not ready.',
            [
                'provider' => $storage['driver'] === 'persistent_local' ? 'Local persistent storage' : (string)$storage['driver'],
                'outside_web_root' => !empty($storage['persistent']),
                'initialized' => !empty($storage['initialized']),
                'writable' => !empty($storage['writable']),
            ]
        );
    } catch (Throwable $error) {
        error_log('[microgifter-admin-health] storage: ' . $error::class . ': ' . $error->getMessage());
        return mg_admin_system_health_service(
            'critical',
            'Persistent media storage is unavailable.',
            [
                'provider' => 'Local persistent storage',
                'outside_web_root' => false,
                'initialized' => false,
                'writable' => false,
            ]
        );
    }
}

function mg_admin_system_health_migrations(PDO $pdo): array
{
    try {
        if (!mg_admin_system_health_table_exists($pdo, 'schema_migrations')) {
            $manifest = mg_migration_manifest();
            $missing = array_values($manifest['ordered_files']);
            return mg_admin_system_health_service('critical', 'The migration ledger is missing.', [
                'ready' => false,
                'manifest_files' => count($missing),
                'missing_count' => count($missing),
                'missing_files' => implode(', ', array_slice($missing, 0, 8)),
                'missing_files_more' => max(0, count($missing) - 8),
                'checksum_mismatch_count' => 0,
                'latest_key' => null,
                'latest_applied_at' => null,
                'recovery_command' => 'php scripts/run_migrations.php',
            ]);
        }

        $status = mg_migration_status($pdo);
        $latest = $pdo->query(
            'SELECT migration_key,applied_at FROM schema_migrations ORDER BY applied_at DESC,id DESC LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        $missing = array_values($status['missing']);
        return mg_admin_system_health_service(
            $status['ready'] ? 'healthy' : 'critical',
            $status['ready'] ? 'The canonical migration manifest is satisfied.' : 'Database migrations require attention. Run the canonical migration runner.',
            [
                'ready' => (bool)$status['ready'],
                'manifest_files' => (int)$status['ordered_count'],
                'applied_keys' => (int)$status['applied_key_count'],
                'missing_count' => count($missing),
                'missing_files' => $missing === [] ? null : implode(', ', array_slice($missing, 0, 8)),
                'missing_files_more' => max(0, count($missing) - 8),
                'checksum_mismatch_count' => count($status['checksum_mismatches']),
                'latest_key' => isset($latest['migration_key']) ? (string)$latest['migration_key'] : null,
                'latest_applied_at' => $latest['applied_at'] ?? null,
                'recovery_command' => $status['ready'] ? null : 'php scripts/run_migrations.php',
            ]
        );
    } catch (Throwable $error) {
        error_log('[microgifter-admin-health] migrations: ' . $error::class . ': ' . $error->getMessage());
        return mg_admin_system_health_service('critical', 'Migration status could not be verified.', [
            'ready' => false,
            'manifest_files' => 0,
            'missing_count' => 0,
            'checksum_mismatch_count' => 0,
            'latest_key' => null,
            'latest_applied_at' => null,
            'recovery_command' => 'php scripts/run_migrations.php',
        ]);
    }
}

function mg_admin_system_health_notifications(PDO $pdo): array
{
    try {
        $required = ['notifications', 'notification_preferences', 'notification_delivery_jobs'];
        $available = [];
        foreach ($required as $table) $available[$table] = mg_admin_system_health_table_exists($pdo, $table);
        $missing = array_keys(array_filter($available, static fn(bool $exists): bool => !$exists));
        return mg_admin_system_health_service(
            $missing === [] ? 'healthy' : 'critical',
            $missing === [] ? 'Notification persistence and delivery tables are available.' : 'Notification delivery tables are incomplete.',
            [
                'tables_ready' => $missing === [],
                'missing_table_count' => count($missing),
                'recipient_pipeline_ready' => $available['notifications'] && $available['notification_delivery_jobs'],
            ]
        );
    } catch (Throwable $error) {
        error_log('[microgifter-admin-health] notifications: ' . $error::class . ': ' . $error->getMessage());
        return mg_admin_system_health_service('critical', 'Notification delivery status could not be verified.', [
            'tables_ready' => false,
            'missing_table_count' => 0,
            'recipient_pipeline_ready' => false,
        ]);
    }
}

function mg_admin_system_health_runtime(PDO $pdo): array
{
    try {
        $databaseOk = (bool)$pdo->query('SELECT 1')->fetchColumn();
        $environment = strtolower(trim((string)mg_config_value('app', 'env', 'production')));
        $profile = strtolower(trim((string)mg_config_value('runtime', 'profile', 'hostgator')));
        return mg_admin_system_health_service(
            $databaseOk ? 'healthy' : 'critical',
            $databaseOk ? 'Application runtime and database are responding.' : 'The database is not responding.',
            [
                'database' => $databaseOk,
                'environment' => $environment,
                'runtime_profile' => $profile,
                'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            ]
        );
    } catch (Throwable $error) {
        error_log('[microgifter-admin-health] runtime: ' . $error::class . ': ' . $error->getMessage());
        return mg_admin_system_health_service('critical', 'Application runtime health could not be verified.', [
            'database' => false,
            'environment' => strtolower(trim((string)mg_config_value('app', 'env', 'production'))),
            'runtime_profile' => strtolower(trim((string)mg_config_value('runtime', 'profile', 'hostgator'))),
            'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
        ]);
    }
}

function mg_admin_system_health_overall(array $services): string
{
    $statuses = array_column($services, 'status');
    if (in_array('critical', $statuses, true)) return 'critical';
    if (in_array('warning', $statuses, true)) return 'warning';
    return 'healthy';
}

function mg_admin_system_health_read(PDO $pdo): array
{
    $mediaMetrics = mg_admin_system_health_media_metrics($pdo);
    $notificationMetrics = mg_admin_system_health_notification_metrics($pdo);
    $services = [
        'storage' => mg_admin_system_health_storage(),
        'notifications' => mg_admin_system_health_notifications($pdo),
        'migrations' => mg_admin_system_health_migrations($pdo),
        'runtime' => mg_admin_system_health_runtime($pdo),
    ];

    if ($services['storage']['status'] === 'healthy' && $mediaMetrics['available'] && $mediaMetrics['missing_files'] > 0) {
        $services['storage']['status'] = 'warning';
        $services['storage']['summary'] = 'Persistent storage is available, but one or more checked media files are missing.';
    }
    if ($services['notifications']['status'] === 'healthy' && $notificationMetrics['available']) {
        if ($notificationMetrics['failed'] > 0 || $notificationMetrics['overdue'] > 0) {
            $services['notifications']['status'] = 'warning';
            $services['notifications']['summary'] = 'Notification delivery is available, but queued work requires attention.';
        }
    }

    $overall = mg_admin_system_health_overall($services);
    return [
        'status' => $overall,
        'summary' => match ($overall) {
            'healthy' => 'All monitored systems are operating normally.',
            'warning' => 'One or more systems should be reviewed.',
            default => 'One or more systems require attention.',
        },
        'services' => $services,
        'metrics' => [
            'media' => $mediaMetrics,
            'notifications' => $notificationMetrics,
        ],
        'warnings' => mg_admin_system_health_recent_warnings($pdo),
        'actions' => [
            'verify_storage' => false,
            'retry_notifications' => false,
            'clean_uploads' => false,
            'migration_plan' => false,
        ],
        'generated_at' => gmdate('c'),
        'request_id' => mg_request_id(),
    ];
}
