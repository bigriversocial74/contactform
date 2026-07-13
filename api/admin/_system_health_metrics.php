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

function mg_admin_system_health_warning_time(string $value): int
{
    $time = strtotime($value);
    return $time === false ? 0 : $time;
}

function mg_admin_system_health_warning_resolution(array $item, array $warningState): array
{
    $type = (string)($item['title'] ?? '');
    $created = mg_admin_system_health_warning_time((string)($item['created_at'] ?? ''));
    $age = $created > 0 ? max(0, time() - $created) : 0;

    if (isset($warningState[$type]) && is_array($warningState[$type])) {
        $state = $warningState[$type];
        if (empty($state['active'])) {
            return [
                'resolved' => true,
                'reason' => (string)($state['summary'] ?? 'The current runtime check is healthy.'),
                'check_key' => $state['check_key'] ?? null,
            ];
        }
        return [
            'resolved' => false,
            'reason' => (string)($state['summary'] ?? 'The current runtime check still requires attention.'),
            'check_key' => $state['check_key'] ?? null,
        ];
    }

    if ($type === 'admin.system_health.sensitive_token_invalid') {
        return [
            'resolved' => $age > 900,
            'reason' => $age > 900
                ? 'Expired System Health confirmation attempts remain available in Security Logs but are outside the active warning window.'
                : 'A protected System Health request recently used an invalid or expired confirmation token.',
            'check_key' => 'system_health_sensitive_token',
        ];
    }

    $shortWindowTypes = [
        'admin.queue_reporting.failed',
        'admin.queue_automation.failed',
        'admin.risk_forecast.failed',
        'admin.operations_command.failed',
        'admin.ops_activity.failed',
        'admin.system_sql_diagnostics.failed',
    ];
    if (in_array($type, $shortWindowTypes, true) && $age > 3600) {
        return [
            'resolved' => true,
            'reason' => 'The request failure is older than the active one-hour operations window.',
            'check_key' => 'request_failure_window',
        ];
    }

    return ['resolved' => false, 'reason' => null, 'check_key' => null];
}

function mg_admin_system_health_warning_group(array &$groups, array $item): void
{
    $key = implode('|', [
        (string)($item['source'] ?? ''),
        (string)($item['title'] ?? ''),
        (string)($item['message'] ?? ''),
        !empty($item['resolved']) ? 'resolved' : 'active',
    ]);
    if (!isset($groups[$key])) {
        $item['occurrence_count'] = 1;
        $item['first_seen_at'] = $item['created_at'] ?? null;
        $item['last_seen_at'] = $item['created_at'] ?? null;
        $groups[$key] = $item;
        return;
    }

    $groups[$key]['occurrence_count'] = (int)($groups[$key]['occurrence_count'] ?? 1) + 1;
    $current = (string)($groups[$key]['last_seen_at'] ?? '');
    $candidate = (string)($item['created_at'] ?? '');
    if ($candidate > $current) {
        $groups[$key]['last_seen_at'] = $candidate;
        $groups[$key]['created_at'] = $candidate;
    }
    $first = (string)($groups[$key]['first_seen_at'] ?? '');
    if ($first === '' || ($candidate !== '' && $candidate < $first)) {
        $groups[$key]['first_seen_at'] = $candidate;
    }
}

function mg_admin_system_health_warning_feed(PDO $pdo, array $warningState = [], int $limit = 12): array
{
    $limit = max(1, min(30, $limit));
    $rowsToRead = max(100, $limit * 10);
    $raw = [];

    try {
        if (mg_admin_system_health_table_exists($pdo, 'security_logs')) {
            $rows = $pdo->query(
                "SELECT severity,event_type,message,created_at
                 FROM security_logs
                 WHERE severity IN ('warning','error','critical')
                 ORDER BY created_at DESC,id DESC
                 LIMIT {$rowsToRead}"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $raw[] = [
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
                 LIMIT {$rowsToRead}"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $raw[] = [
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

    usort($raw, static fn(array $left, array $right): int => strcmp((string)$right['created_at'], (string)$left['created_at']));
    $activeGroups = [];
    $resolvedGroups = [];
    foreach ($raw as $item) {
        $resolution = mg_admin_system_health_warning_resolution($item, $warningState);
        $item['resolved'] = (bool)$resolution['resolved'];
        $item['resolution_reason'] = $resolution['reason'];
        $item['check_key'] = $resolution['check_key'];
        if ($item['resolved']) mg_admin_system_health_warning_group($resolvedGroups, $item);
        else mg_admin_system_health_warning_group($activeGroups, $item);
    }

    $active = array_values($activeGroups);
    $resolved = array_values($resolvedGroups);
    usort($active, static fn(array $left, array $right): int => strcmp((string)$right['created_at'], (string)$left['created_at']));
    usort($resolved, static fn(array $left, array $right): int => strcmp((string)$right['created_at'], (string)$left['created_at']));

    $activeOccurrences = array_sum(array_map(static fn(array $item): int => (int)($item['occurrence_count'] ?? 1), $active));
    $resolvedOccurrences = array_sum(array_map(static fn(array $item): int => (int)($item['occurrence_count'] ?? 1), $resolved));

    return [
        'active' => array_slice($active, 0, $limit),
        'resolved' => array_slice($resolved, 0, $limit),
        'summary' => [
            'active_groups' => count($active),
            'active_occurrences' => $activeOccurrences,
            'resolved_groups' => count($resolved),
            'resolved_occurrences' => $resolvedOccurrences,
            'raw_events_checked' => count($raw),
        ],
    ];
}

function mg_admin_system_health_recent_warnings(PDO $pdo, int $limit = 12): array
{
    return mg_admin_system_health_warning_feed($pdo, [], $limit)['active'];
}
