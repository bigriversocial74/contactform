<?php
declare(strict_types=1);

function mg_admin_system_health_readonly_storage_path(string $root, string $storageKey): string
{
    $key = mg_storage_normalize_key($storageKey);
    $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
    $parent = realpath(dirname($path));
    if ($parent === false || !mg_storage_path_is_within($parent, $root)) {
        throw new RuntimeException('Persistent media parent directory is unavailable.');
    }
    return $path;
}

function mg_admin_system_health_media_metrics(PDO $pdo): array
{
    $empty = [
        'available' => false,
        'media_files' => 0,
        'storage_used_bytes' => 0,
        'storage_free_bytes' => null,
        'storage_total_bytes' => null,
        'unattached_uploads' => 0,
        'missing_files' => 0,
        'checked_files' => 0,
        'scan_limited' => false,
    ];
    if (!mg_admin_system_health_table_exists($pdo, 'catalog_assets')) return $empty;

    try {
        $storageRoot = mg_storage_root(false);
        $aggregate = $pdo->query(
            "SELECT COUNT(*) media_files,COALESCE(SUM(byte_size),0) storage_used_bytes
             FROM catalog_assets
             WHERE storage_provider='persistent_local' AND status='ready'"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $unattached = 0;
        if (mg_admin_system_health_table_exists($pdo, 'feed_post_assets')) {
            $unattached = (int)$pdo->query(
                "SELECT COUNT(*)
                 FROM catalog_assets a
                 LEFT JOIN feed_post_assets fpa ON fpa.asset_id=a.id
                 WHERE a.storage_provider='persistent_local' AND a.status='ready' AND fpa.id IS NULL
                   AND JSON_UNQUOTE(JSON_EXTRACT(a.metadata_json,'$.source'))='social_feed'"
            )->fetchColumn();
        }

        $scanLimit = 500;
        $rows = $pdo->query(
            "SELECT storage_key
             FROM catalog_assets
             WHERE storage_provider='persistent_local' AND status='ready'
             ORDER BY updated_at DESC,id DESC
             LIMIT " . ($scanLimit + 1)
        )->fetchAll(PDO::FETCH_COLUMN);
        $scanLimited = count($rows) > $scanLimit;
        if ($scanLimited) array_pop($rows);
        $missing = 0;
        foreach ($rows as $storageKey) {
            try {
                $path = mg_admin_system_health_readonly_storage_path($storageRoot, (string)$storageKey);
                if (!is_file($path) || !is_readable($path)) $missing++;
            } catch (Throwable) {
                $missing++;
            }
        }

        $free = @disk_free_space($storageRoot);
        $total = @disk_total_space($storageRoot);
        return [
            'available' => true,
            'media_files' => (int)($aggregate['media_files'] ?? 0),
            'storage_used_bytes' => (int)($aggregate['storage_used_bytes'] ?? 0),
            'storage_free_bytes' => is_float($free) || is_int($free) ? (int)$free : null,
            'storage_total_bytes' => is_float($total) || is_int($total) ? (int)$total : null,
            'unattached_uploads' => $unattached,
            'missing_files' => $missing,
            'checked_files' => count($rows),
            'scan_limited' => $scanLimited,
        ];
    } catch (Throwable $error) {
        error_log('[microgifter-admin-health] media metrics: ' . $error::class . ': ' . $error->getMessage());
        return $empty;
    }
}

function mg_admin_system_health_notification_metrics(PDO $pdo): array
{
    $empty = [
        'available' => false,
        'queued' => 0,
        'processing' => 0,
        'sent' => 0,
        'delivered' => 0,
        'failed' => 0,
        'retrying' => 0,
        'suppressed' => 0,
        'overdue' => 0,
    ];
    if (!mg_admin_system_health_table_exists($pdo, 'notification_delivery_jobs')) return $empty;

    try {
        $rows = $pdo->query(
            "SELECT status,COUNT(*) total
             FROM notification_delivery_jobs
             GROUP BY status"
        )->fetchAll(PDO::FETCH_ASSOC);
        $metrics = $empty;
        $metrics['available'] = true;
        foreach ($rows as $row) {
            $status = (string)$row['status'];
            if (array_key_exists($status, $metrics)) $metrics[$status] = (int)$row['total'];
        }
        $metrics['retrying'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM notification_delivery_jobs
             WHERE status='failed' AND next_attempt_at IS NOT NULL"
        )->fetchColumn();
        $metrics['overdue'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM notification_delivery_jobs
             WHERE status='queued' AND next_attempt_at IS NOT NULL AND next_attempt_at<NOW()"
        )->fetchColumn();
        return $metrics;
    } catch (Throwable $error) {
        error_log('[microgifter-admin-health] notification metrics: ' . $error::class . ': ' . $error->getMessage());
        return $empty;
    }
}

function mg_admin_system_health_recent_warnings(PDO $pdo, int $limit = 12): array
{
    $limit = max(1, min(30, $limit));
    $items = [];
    try {
        if (mg_admin_system_health_table_exists($pdo, 'security_logs')) {
            $rows = $pdo->query(
                "SELECT severity,event_type,message,created_at
                 FROM security_logs
                 WHERE severity IN ('warning','error','critical')
                 ORDER BY created_at DESC,id DESC
                 LIMIT {$limit}"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $items[] = [
                    'source' => 'security',
                    'severity' => (string)$row['severity'],
                    'title' => (string)$row['event_type'],
                    'message' => mb_substr((string)$row['message'], 0, 255),
                    'created_at' => $row['created_at'] ?? null,
                ];
            }
        }
        if (mg_admin_system_health_table_exists($pdo, 'operational_alerts')) {
            $rows = $pdo->query(
                "SELECT severity,alert_type,title,body,created_at
                 FROM operational_alerts
                 WHERE status IN ('open','acknowledged') AND severity IN ('warning','high','critical')
                 ORDER BY created_at DESC,id DESC
                 LIMIT {$limit}"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $items[] = [
                    'source' => 'operations',
                    'severity' => (string)$row['severity'],
                    'title' => (string)($row['title'] ?: $row['alert_type']),
                    'message' => mb_substr((string)($row['body'] ?? ''), 0, 255),
                    'created_at' => $row['created_at'] ?? null,
                ];
            }
        }
    } catch (Throwable $error) {
        error_log('[microgifter-admin-health] warning metrics: ' . $error::class . ': ' . $error->getMessage());
    }

    usort($items, static fn(array $left, array $right): int => strcmp((string)$right['created_at'], (string)$left['created_at']));
    return array_slice($items, 0, $limit);
}
