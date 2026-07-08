<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_campaign_embed_runtime_table_status(PDO $pdo, string $tableName, array $requiredColumns): array
{
    $exists = false;
    $columns = [];
    try {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
        $stmt->execute([$tableName]);
        $columns = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $exists = count($columns) > 0;
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.campaign_embed_runtime.table_check_failed', 'Unable to inspect campaign embed table.', ['table' => $tableName, 'exception_class' => $error::class]);
    }

    $missing = [];
    foreach ($requiredColumns as $column) {
        if (!in_array($column, $columns, true)) $missing[] = $column;
    }

    return [
        'name' => $tableName,
        'exists' => $exists,
        'ready' => $exists && !$missing,
        'missing_columns' => $missing,
    ];
}

function mg_campaign_embed_runtime_find_campaign(PDO $pdo, int $merchantId, mixed $campaignRef): ?array
{
    $ref = trim((string)$campaignRef);
    if ($ref === '') return null;
    if (strlen($ref) > 180) mg_fail('Campaign reference is invalid.', 422);
    $numericId = ctype_digit($ref) ? (int)$ref : 0;
    $stmt = $pdo->prepare('SELECT id, public_id, public_slug, title, campaign_type, status FROM campaigns WHERE merchant_user_id = ? AND ((? > 0 AND id = ?) OR public_id = ? OR public_slug = ?) LIMIT 1');
    $stmt->execute([$merchantId, $numericId, $numericId, $ref, $ref]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    return $campaign ?: null;
}

function mg_campaign_embed_runtime_empty_stats(): array
{
    return [
        'loaded' => 0,
        'opened' => 0,
        'submitted' => 0,
        'invalid' => 0,
        'error' => 0,
        'last_event_at' => null,
        'last_origin_host' => null,
        'last_page_url' => null,
    ];
}

function mg_campaign_embed_runtime_stats(PDO $pdo, int $merchantId, ?array $campaign, bool $eventsReady): array
{
    $stats = mg_campaign_embed_runtime_empty_stats();
    if (!$eventsReady) return $stats;

    try {
        $params = [$merchantId];
        $where = 'merchant_user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
        if ($campaign) {
            $where .= ' AND campaign_id = ?';
            $params[] = (int)$campaign['id'];
        }

        $stmt = $pdo->prepare('SELECT event_type, COUNT(*) total FROM campaign_embed_events WHERE ' . $where . ' GROUP BY event_type');
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = (string)$row['event_type'];
            if (array_key_exists($type, $stats)) $stats[$type] = (int)$row['total'];
        }

        $last = $pdo->prepare('SELECT created_at, origin_host, page_url FROM campaign_embed_events WHERE ' . ($campaign ? 'merchant_user_id = ? AND campaign_id = ?' : 'merchant_user_id = ?') . ' ORDER BY id DESC LIMIT 1');
        $last->execute($campaign ? [$merchantId, (int)$campaign['id']] : [$merchantId]);
        $row = $last->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $stats['last_event_at'] = $row['created_at'] ?? null;
            $stats['last_origin_host'] = $row['origin_host'] ?? null;
            $stats['last_page_url'] = $row['page_url'] ?? null;
        }
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.campaign_embed_runtime.stats_failed', 'Unable to load campaign embed runtime stats.', ['exception_class' => $error::class], $merchantId);
    }

    return $stats;
}

function mg_campaign_embed_runtime_recent_events(PDO $pdo, int $merchantId, ?array $campaign, bool $eventsReady): array
{
    if (!$eventsReady) return [];
    try {
        $sql = 'SELECT event_type, origin_host, page_url, embed_mode, created_at FROM campaign_embed_events WHERE merchant_user_id = ?';
        $params = [$merchantId];
        if ($campaign) {
            $sql .= ' AND campaign_id = ?';
            $params[] = (int)$campaign['id'];
        }
        $sql .= ' ORDER BY id DESC LIMIT 8';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(static function (array $row): array {
            return [
                'event_type' => (string)($row['event_type'] ?? ''),
                'origin_host' => $row['origin_host'] ?? null,
                'page_url' => $row['page_url'] ?? null,
                'embed_mode' => $row['embed_mode'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.campaign_embed_runtime.recent_failed', 'Unable to load recent campaign embed events.', ['exception_class' => $error::class], $merchantId);
        return [];
    }
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)($user['id'] ?? 0);
$pdo = mg_db();
$campaign = mg_campaign_embed_runtime_find_campaign($pdo, $merchantId, $_GET['campaign_id'] ?? $_GET['campaign'] ?? '');

$settingsTable = mg_campaign_embed_runtime_table_status($pdo, 'campaign_embed_settings', ['id', 'campaign_id', 'merchant_user_id', 'embed_enabled', 'default_layout', 'allowed_domains_json', 'created_at', 'updated_at']);
$eventsTable = mg_campaign_embed_runtime_table_status($pdo, 'campaign_embed_events', ['id', 'public_id', 'campaign_id', 'merchant_user_id', 'event_type', 'origin_host', 'page_url', 'embed_mode', 'created_at']);
$ready = $settingsTable['ready'] && $eventsTable['ready'];
$stats = mg_campaign_embed_runtime_stats($pdo, $merchantId, $campaign, $eventsTable['ready']);

mg_ok([
    'migration_ready' => $ready,
    'sql_required' => $ready ? null : 'database/campaign_embed_settings_v2.sql',
    'tables' => [
        'campaign_embed_settings' => $settingsTable,
        'campaign_embed_events' => $eventsTable,
    ],
    'campaign' => $campaign ? [
        'id' => (string)$campaign['public_id'],
        'slug' => $campaign['public_slug'] ?? null,
        'title' => (string)$campaign['title'],
        'campaign_type' => (string)$campaign['campaign_type'],
        'status' => (string)$campaign['status'],
        'qa_url' => '/merchant-campaign-embed-qa.php?campaign=' . rawurlencode((string)($campaign['public_slug'] ?: $campaign['public_id'])),
    ] : null,
    'stats' => $stats,
    'recent_events' => mg_campaign_embed_runtime_recent_events($pdo, $merchantId, $campaign, $eventsTable['ready']),
    'smoke_checks' => [
        ['event_type' => 'loaded', 'count' => $stats['loaded'], 'ready' => $eventsTable['ready']],
        ['event_type' => 'opened', 'count' => $stats['opened'], 'ready' => $eventsTable['ready']],
        ['event_type' => 'submitted', 'count' => $stats['submitted'], 'ready' => $eventsTable['ready']],
        ['event_type' => 'invalid', 'count' => $stats['invalid'], 'ready' => $eventsTable['ready']],
        ['event_type' => 'error', 'count' => $stats['error'], 'ready' => $eventsTable['ready']],
    ],
], $ready ? 'Campaign embed runtime is ready.' : 'Campaign embed SQL migration is required.');
