<?php
declare(strict_types=1);

require_once __DIR__ . '/_ops_installer.php';
require_once dirname(__DIR__, 2) . '/includes/pwa-push.php';

function mg_admin_system_health_can_manage(array $user): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    return in_array('super_admin', $roles, true);
}

function mg_admin_system_health_require_manager(array $user): void
{
    if (mg_admin_system_health_can_manage($user)) return;
    mg_security_log(
        'warning',
        'admin.system_health.action_denied',
        'Administrative system health action denied.',
        [],
        (int)$user['id']
    );
    mg_fail('Permission denied.', 403);
}

function mg_admin_system_health_migration_plan(PDO $pdo): array
{
    if (!mg_admin_system_health_table_exists($pdo, 'schema_migrations')) {
        $manifest = mg_migration_manifest();
        return [
            'ready' => false,
            'missing_count' => count($manifest['ordered_files']),
            'missing_files' => array_slice(array_values($manifest['ordered_files']), 0, 20),
            'command' => 'php scripts/run_migrations.php',
            'note' => 'Run the canonical migration runner from the application root with migration database credentials.',
        ];
    }
    $status = mg_migration_status($pdo);
    return [
        'ready' => (bool)$status['ready'],
        'missing_count' => count($status['missing']),
        'missing_files' => array_slice(array_values($status['missing']), 0, 20),
        'checksum_mismatch_count' => count($status['checksum_mismatches']),
        'checksum_mismatches' => array_slice($status['checksum_mismatches'], 0, 20),
        'coverage_cutoff' => (int)$status['coverage_cutoff'],
        'command' => 'php scripts/run_migrations.php',
        'note' => 'This action is read-only. It prepares the exact recovery command; it does not execute DDL from the browser.',
    ];
}

function mg_admin_system_health_verify_storage(): array
{
    $status = mg_storage_assert_ready(false, true);
    return [
        'verified' => true,
        'provider' => $status['driver'] === 'persistent_local' ? 'Local persistent storage' : (string)$status['driver'],
        'outside_web_root' => !empty($status['persistent']),
        'initialized' => !empty($status['initialized']),
        'writable' => !empty($status['writable']),
        'free_bytes' => isset($status['free_bytes']) ? (int)$status['free_bytes'] : null,
    ];
}

function mg_admin_system_health_retry_notifications(PDO $pdo, int $limit = 100): array
{
    $limit = max(1, min(250, $limit));
    if (!mg_admin_system_health_table_exists($pdo, 'notification_delivery_jobs')) {
        throw new RuntimeException('Notification delivery jobs are unavailable.');
    }

    $pdo->beginTransaction();
    try {
        $rows = $pdo->query(
            "SELECT id
             FROM notification_delivery_jobs
             WHERE status='failed' AND attempt_count<5
               AND updated_at<DATE_SUB(NOW(),INTERVAL 1 MINUTE)
             ORDER BY COALESCE(next_attempt_at,updated_at),id
             LIMIT {$limit}
             FOR UPDATE"
        )->fetchAll(PDO::FETCH_COLUMN);
        $ids = array_values(array_map('intval', $rows));
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare(
                "UPDATE notification_delivery_jobs
                 SET status='queued',next_attempt_at=NOW(),failed_at=NULL,
                     failure_code=NULL,failure_message=NULL,updated_at=NOW()
                 WHERE id IN ({$placeholders}) AND status='failed'"
            );
            $stmt->execute($ids);
            $updated = $stmt->rowCount();
        } else {
            $updated = 0;
        }
        $pdo->commit();
        return ['retried' => $updated, 'limit' => $limit];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_admin_system_health_test_pwa_notification(PDO $pdo, array $user): array
{
    return mg_pwa_push_send_test_to_user($pdo, (int)$user['id']);
}

function mg_admin_system_health_post_media_references(PDO $pdo): array
{
    $assetIds = [];
    if (!mg_admin_system_health_table_exists($pdo, 'feed_posts')) return $assetIds;
    $stmt = $pdo->query("SELECT media_json FROM feed_posts WHERE media_json IS NOT NULL AND media_json<>'[]'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $media = json_decode((string)$row['media_json'], true);
        if (!is_array($media)) continue;
        foreach ($media as $item) {
            if (!is_array($item)) continue;
            $assetId = strtolower(trim((string)($item['asset_id'] ?? '')));
            if (preg_match('/^[a-f0-9-]{36}$/', $assetId) === 1) $assetIds[$assetId] = true;
            $url = trim((string)($item['url'] ?? ''));
            $query = parse_url($url, PHP_URL_QUERY);
            if (!is_string($query)) continue;
            parse_str($query, $parameters);
            $urlAsset = strtolower(trim((string)($parameters['asset'] ?? '')));
            if (preg_match('/^[a-f0-9-]{36}$/', $urlAsset) === 1) $assetIds[$urlAsset] = true;
        }
    }
    return $assetIds;
}

function mg_admin_system_health_cleanup_uploads(PDO $pdo, int $hours = 24, int $limit = 100): array
{
    $hours = max(24, min(720, $hours));
    $limit = max(1, min(250, $limit));
    if (!mg_admin_system_health_table_exists($pdo, 'catalog_assets')) {
        throw new RuntimeException('Catalog assets are unavailable.');
    }
    if (!mg_admin_system_health_table_exists($pdo, 'feed_post_assets')) {
        throw new RuntimeException('Feed media relationships are unavailable.');
    }

    $referenced = mg_admin_system_health_post_media_references($pdo);
    $candidates = $pdo->query(
        "SELECT a.id,a.public_id,a.storage_provider,a.storage_key,a.byte_size
         FROM catalog_assets a
         LEFT JOIN feed_post_assets fpa ON fpa.asset_id=a.id
         WHERE a.status='ready' AND a.storage_provider='persistent_local' AND fpa.id IS NULL
           AND JSON_UNQUOTE(JSON_EXTRACT(a.metadata_json,'$.source'))='social_feed'
           AND a.updated_at<DATE_SUB(NOW(),INTERVAL {$hours} HOUR)
         ORDER BY a.updated_at,a.id
         LIMIT {$limit}"
    )->fetchAll(PDO::FETCH_ASSOC);

    $archived = 0;
    $deleted = 0;
    $skipped = 0;
    $bytes = 0;
    $lock = $pdo->prepare(
        'SELECT a.status,
                EXISTS(SELECT 1 FROM feed_post_assets fpa WHERE fpa.asset_id=a.id) linked
         FROM catalog_assets a WHERE a.id=? FOR UPDATE'
    );
    $archive = $pdo->prepare(
        "UPDATE catalog_assets
         SET status='archived',
             metadata_json=JSON_SET(COALESCE(metadata_json,JSON_OBJECT()),
               '$.source','social_feed','$.feed_state','cleaned','$.cleaned_at',?),
             updated_at=NOW()
         WHERE id=? AND status='ready'"
    );

    foreach ($candidates as $asset) {
        $publicId = strtolower((string)$asset['public_id']);
        if (isset($referenced[$publicId])) {
            $skipped++;
            continue;
        }
        $pdo->beginTransaction();
        try {
            $lock->execute([(int)$asset['id']]);
            $current = $lock->fetch(PDO::FETCH_ASSOC) ?: [];
            if (($current['status'] ?? '') !== 'ready' || !empty($current['linked'])) {
                $pdo->rollBack();
                $skipped++;
                continue;
            }
            $archive->execute([gmdate('c'), (int)$asset['id']]);
            if ($archive->rowCount() !== 1) {
                $pdo->rollBack();
                $skipped++;
                continue;
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }

        $archived++;
        $bytes += (int)($asset['byte_size'] ?? 0);
        try {
            if (mg_storage_delete_asset_file((string)$asset['storage_provider'], (string)$asset['storage_key'])) $deleted++;
        } catch (Throwable $error) {
            error_log('[microgifter-admin-health] cleanup file delete: ' . $error::class . ': ' . $error->getMessage());
        }
    }

    return [
        'archived' => $archived,
        'files_deleted' => $deleted,
        'skipped' => $skipped,
        'bytes_released' => $bytes,
        'minimum_age_hours' => $hours,
        'limit' => $limit,
    ];
}
