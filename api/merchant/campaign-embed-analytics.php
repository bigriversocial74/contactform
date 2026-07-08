<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_embed_analytics_days(mixed $value): int
{
    $days = (int)$value;
    return in_array($days, [7, 30, 90], true) ? $days : 30;
}

function mg_embed_analytics_table_ready(PDO $pdo, string $table, array $columns): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        $found = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        foreach ($columns as $column) {
            if (!in_array($column, $found, true)) return false;
        }
        return true;
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.campaign_embed_analytics.table_check_failed', 'Unable to inspect campaign embed analytics table.', ['table' => $table, 'exception_class' => $error::class]);
        return false;
    }
}

function mg_embed_analytics_campaign(PDO $pdo, int $merchantId, string $campaignRef): ?array
{
    if ($campaignRef === '') return null;
    if (strlen($campaignRef) > 180) mg_fail('Campaign reference is invalid.', 422);
    $numericId = ctype_digit($campaignRef) ? (int)$campaignRef : 0;
    $stmt = $pdo->prepare('SELECT id, public_id, public_slug, title, campaign_type, status FROM campaigns WHERE merchant_user_id = ? AND ((? > 0 AND id = ?) OR public_id = ? OR public_slug = ?) LIMIT 1');
    $stmt->execute([$merchantId, $numericId, $numericId, $campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) mg_fail('Campaign not found.', 404);
    return $campaign;
}

function mg_embed_analytics_campaigns(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare('SELECT id, public_id, public_slug, title, campaign_type, status FROM campaigns WHERE merchant_user_id = ? ORDER BY id DESC LIMIT 200');
    $stmt->execute([$merchantId]);
    return array_map(static function (array $row): array {
        return [
            'id' => (string)$row['public_id'],
            'slug' => $row['public_slug'] ?? null,
            'title' => (string)$row['title'],
            'campaign_type' => (string)$row['campaign_type'],
            'status' => (string)$row['status'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_embed_analytics_rate(int $part, int $whole): float
{
    return $whole > 0 ? round(($part / $whole) * 100, 2) : 0.0;
}

function mg_embed_analytics_domains(mixed $json): array
{
    $decoded = is_string($json) && $json !== '' ? json_decode($json, true) : null;
    return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
}

function mg_embed_analytics_domain_allowed(?string $origin, array $allowedDomains): bool
{
    if (!$origin || !$allowedDomains) return true;
    $host = strtolower(preg_replace('/^www\./', '', $origin) ?: $origin);
    foreach ($allowedDomains as $domain) {
        $domain = strtolower(preg_replace('/^www\./', '', (string)$domain) ?: (string)$domain);
        if ($host === $domain || str_ends_with($host, '.' . $domain)) return true;
    }
    return false;
}

function mg_embed_analytics_empty_totals(): array
{
    return ['loaded' => 0, 'opened' => 0, 'submitted' => 0, 'invalid' => 0, 'error' => 0, 'open_rate' => 0.0, 'conversion_rate' => 0.0, 'error_rate' => 0.0];
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)($user['id'] ?? 0);
$pdo = mg_db();
$days = mg_embed_analytics_days($_GET['days'] ?? 30);
$campaignRef = trim((string)($_GET['campaign'] ?? $_GET['campaign_id'] ?? ''));
$campaign = mg_embed_analytics_campaign($pdo, $merchantId, $campaignRef);
$cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));

$eventsReady = mg_embed_analytics_table_ready($pdo, 'campaign_embed_events', ['id', 'campaign_id', 'merchant_user_id', 'event_type', 'origin_host', 'page_url', 'embed_mode', 'created_at']);
$settingsReady = mg_embed_analytics_table_ready($pdo, 'campaign_embed_settings', ['campaign_id', 'merchant_user_id', 'allowed_domains_json', 'embed_enabled', 'default_layout']);
$totals = mg_embed_analytics_empty_totals();
$timeline = [];
$campaignRows = [];
$originRows = [];
$recentEvents = [];

for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime('-' . $i . ' days'));
    $timeline[$date] = ['date' => $date, 'loaded' => 0, 'opened' => 0, 'submitted' => 0, 'invalid' => 0, 'error' => 0];
}

if ($eventsReady) {
    $where = 'e.merchant_user_id = ? AND e.created_at >= ?';
    $params = [$merchantId, $cutoff];
    if ($campaign) {
        $where .= ' AND e.campaign_id = ?';
        $params[] = (int)$campaign['id'];
    }

    try {
        $stmt = $pdo->prepare('SELECT e.event_type, COUNT(*) total FROM campaign_embed_events e WHERE ' . $where . ' GROUP BY e.event_type');
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $type = (string)$row['event_type'];
            if (array_key_exists($type, $totals)) $totals[$type] = (int)$row['total'];
        }
        $totals['open_rate'] = mg_embed_analytics_rate((int)$totals['opened'], (int)$totals['loaded']);
        $totals['conversion_rate'] = mg_embed_analytics_rate((int)$totals['submitted'], (int)$totals['loaded']);
        $totals['error_rate'] = mg_embed_analytics_rate((int)$totals['error'], max(1, (int)$totals['loaded'] + (int)$totals['submitted']));

        $daily = $pdo->prepare('SELECT DATE(e.created_at) event_date, e.event_type, COUNT(*) total FROM campaign_embed_events e WHERE ' . $where . ' GROUP BY DATE(e.created_at), e.event_type ORDER BY event_date ASC');
        $daily->execute($params);
        foreach ($daily->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $date = (string)$row['event_date'];
            $type = (string)$row['event_type'];
            if (isset($timeline[$date]) && array_key_exists($type, $timeline[$date])) $timeline[$date][$type] = (int)$row['total'];
        }

        $campaignSql = 'SELECT c.id, c.public_id, c.public_slug, c.title, c.campaign_type, c.status,
            SUM(CASE WHEN e.event_type = \'loaded\' THEN 1 ELSE 0 END) loaded,
            SUM(CASE WHEN e.event_type = \'opened\' THEN 1 ELSE 0 END) opened,
            SUM(CASE WHEN e.event_type = \'submitted\' THEN 1 ELSE 0 END) submitted,
            SUM(CASE WHEN e.event_type = \'invalid\' THEN 1 ELSE 0 END) invalid,
            SUM(CASE WHEN e.event_type = \'error\' THEN 1 ELSE 0 END) error,
            MAX(e.created_at) last_event_at
            FROM campaigns c
            LEFT JOIN campaign_embed_events e ON e.campaign_id = c.id AND e.merchant_user_id = c.merchant_user_id AND e.created_at >= ?
            WHERE c.merchant_user_id = ?';
        $campaignParams = [$cutoff, $merchantId];
        if ($campaign) {
            $campaignSql .= ' AND c.id = ?';
            $campaignParams[] = (int)$campaign['id'];
        }
        $campaignSql .= ' GROUP BY c.id, c.public_id, c.public_slug, c.title, c.campaign_type, c.status ORDER BY loaded DESC, submitted DESC, c.id DESC LIMIT 100';
        $campaignStmt = $pdo->prepare($campaignSql);
        $campaignStmt->execute($campaignParams);
        foreach ($campaignStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $loaded = (int)($row['loaded'] ?? 0);
            $submitted = (int)($row['submitted'] ?? 0);
            $opened = (int)($row['opened'] ?? 0);
            $ref = trim((string)($row['public_slug'] ?? '')) !== '' ? (string)$row['public_slug'] : (string)$row['public_id'];
            $campaignRows[] = [
                'id' => (string)$row['public_id'],
                'slug' => $row['public_slug'] ?? null,
                'title' => (string)$row['title'],
                'campaign_type' => (string)$row['campaign_type'],
                'status' => (string)$row['status'],
                'loaded' => $loaded,
                'opened' => $opened,
                'submitted' => $submitted,
                'invalid' => (int)($row['invalid'] ?? 0),
                'error' => (int)($row['error'] ?? 0),
                'open_rate' => mg_embed_analytics_rate($opened, $loaded),
                'conversion_rate' => mg_embed_analytics_rate($submitted, $loaded),
                'last_event_at' => $row['last_event_at'] ?? null,
                'qa_url' => '/merchant-campaign-embed-qa.php?campaign=' . rawurlencode($ref),
                'campaign_url' => '/merchant-campaigns.php',
            ];
        }

        $settingsJoin = $settingsReady ? 'LEFT JOIN campaign_embed_settings s ON s.campaign_id = c.id' : '';
        $originSql = 'SELECT e.origin_host, e.campaign_id, c.public_id, c.public_slug, c.title, COUNT(*) total, SUM(CASE WHEN e.event_type = \'submitted\' THEN 1 ELSE 0 END) submitted, MAX(e.created_at) last_seen' . ($settingsReady ? ', s.allowed_domains_json' : ', NULL allowed_domains_json') . ' FROM campaign_embed_events e JOIN campaigns c ON c.id = e.campaign_id ' . $settingsJoin . ' WHERE ' . $where . ' AND e.origin_host IS NOT NULL AND e.origin_host <> \'\' GROUP BY e.origin_host, e.campaign_id, c.public_id, c.public_slug, c.title' . ($settingsReady ? ', s.allowed_domains_json' : '') . ' ORDER BY total DESC LIMIT 200';
        $originStmt = $pdo->prepare($originSql);
        $originStmt->execute($params);
        $originMap = [];
        foreach ($originStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $host = (string)$row['origin_host'];
            $allowed = mg_embed_analytics_domain_allowed($host, mg_embed_analytics_domains($row['allowed_domains_json'] ?? null));
            if (!isset($originMap[$host])) $originMap[$host] = ['origin_host' => $host, 'total' => 0, 'submitted' => 0, 'last_seen' => null, 'approved' => true, 'campaigns' => []];
            $originMap[$host]['total'] += (int)$row['total'];
            $originMap[$host]['submitted'] += (int)$row['submitted'];
            $originMap[$host]['approved'] = $originMap[$host]['approved'] && $allowed;
            $originMap[$host]['last_seen'] = max((string)($originMap[$host]['last_seen'] ?? ''), (string)($row['last_seen'] ?? '')) ?: null;
            $originMap[$host]['campaigns'][] = (string)$row['title'];
        }
        $originRows = array_values($originMap);
        usort($originRows, static fn(array $a, array $b): int => ($b['total'] <=> $a['total']));
        $originRows = array_slice($originRows, 0, 25);

        $recent = $pdo->prepare('SELECT e.event_type, e.origin_host, e.page_url, e.embed_mode, e.created_at, c.public_id, c.public_slug, c.title FROM campaign_embed_events e JOIN campaigns c ON c.id = e.campaign_id WHERE ' . $where . ' ORDER BY e.id DESC LIMIT 25');
        $recent->execute($params);
        foreach ($recent->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $recentEvents[] = [
                'event_type' => (string)$row['event_type'],
                'origin_host' => $row['origin_host'] ?? null,
                'page_url' => $row['page_url'] ?? null,
                'embed_mode' => $row['embed_mode'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'campaign_title' => (string)$row['title'],
                'campaign_id' => (string)$row['public_id'],
                'campaign_slug' => $row['public_slug'] ?? null,
            ];
        }
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.campaign_embed_analytics.query_failed', 'Unable to load campaign embed analytics.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
        mg_fail('Unable to load campaign embed analytics.', 500);
    }
}

mg_ok([
    'migration_ready' => $eventsReady,
    'settings_ready' => $settingsReady,
    'sql_required' => $eventsReady ? null : 'database/campaign_embed_settings_v2.sql',
    'filters' => ['days' => $days, 'campaign' => $campaign ? ['id' => (string)$campaign['public_id'], 'slug' => $campaign['public_slug'] ?? null, 'title' => (string)$campaign['title']] : null],
    'campaigns' => mg_embed_analytics_campaigns($pdo, $merchantId),
    'totals' => $totals,
    'timeline' => array_values($timeline),
    'campaign_rows' => $campaignRows,
    'origin_rows' => $originRows,
    'recent_events' => $recentEvents,
], $eventsReady ? 'Campaign embed analytics loaded.' : 'Campaign embed analytics SQL is required.');
