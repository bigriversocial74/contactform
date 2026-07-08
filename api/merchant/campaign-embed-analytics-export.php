<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_embed_export_days(mixed $value): int
{
    $days = (int)$value;
    return in_array($days, [7, 30, 90], true) ? $days : 30;
}

function mg_embed_export_dataset(mixed $value): string
{
    $dataset = strtolower(trim((string)$value));
    return in_array($dataset, ['campaigns', 'domains', 'events'], true) ? $dataset : 'campaigns';
}

function mg_embed_export_table_ready(PDO $pdo, string $table, array $columns): bool
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
        mg_security_log('warning', 'merchant.campaign_embed_export.table_check_failed', 'Unable to inspect campaign embed export table.', ['table' => $table, 'exception_class' => $error::class]);
        return false;
    }
}

function mg_embed_export_campaign(PDO $pdo, int $merchantId, string $campaignRef): ?array
{
    if ($campaignRef === '') return null;
    if (strlen($campaignRef) > 180) mg_fail('Campaign reference is invalid.', 422);
    $numericId = ctype_digit($campaignRef) ? (int)$campaignRef : 0;
    $stmt = $pdo->prepare('SELECT id, public_id, public_slug, title FROM campaigns WHERE merchant_user_id = ? AND ((? > 0 AND id = ?) OR public_id = ? OR public_slug = ?) LIMIT 1');
    $stmt->execute([$merchantId, $numericId, $numericId, $campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) mg_fail('Campaign not found.', 404);
    return $campaign;
}

function mg_embed_export_rate(int $part, int $whole): float
{
    return $whole > 0 ? round(($part / $whole) * 100, 2) : 0.0;
}

function mg_embed_export_domains(mixed $json): array
{
    $decoded = is_string($json) && $json !== '' ? json_decode($json, true) : null;
    return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
}

function mg_embed_export_domain_allowed(?string $origin, array $allowedDomains): bool
{
    if (!$origin || !$allowedDomains) return true;
    $host = strtolower(preg_replace('/^www\./', '', $origin) ?: $origin);
    foreach ($allowedDomains as $domain) {
        $domain = strtolower(preg_replace('/^www\./', '', (string)$domain) ?: (string)$domain);
        if ($host === $domain || str_ends_with($host, '.' . $domain)) return true;
    }
    return false;
}

function mg_embed_export_filename(string $dataset, int $days, ?array $campaign): string
{
    $campaignRef = $campaign ? ((string)($campaign['public_slug'] ?: $campaign['public_id'])) : 'all';
    $name = 'campaign-embed-' . $dataset . '-' . $campaignRef . '-' . $days . 'd-' . date('Ymd-His') . '.csv';
    return preg_replace('/[^a-zA-Z0-9_.-]/', '-', $name) ?: 'campaign-embed-export.csv';
}

function mg_embed_export_cell(mixed $value): string
{
    $cell = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', (string)$value) ?? '');
    if ($cell !== '' && preg_match('/^[=+\-@]/', $cell) === 1) $cell = "'" . $cell;
    return $cell;
}

function mg_embed_export_rows(array $rows): array
{
    return array_map(static function (array $row): array {
        return array_map('mg_embed_export_cell', $row);
    }, $rows);
}

function mg_embed_export_stream(string $filename, array $headers, array $rows): void
{
    $safeRows = mg_embed_export_rows($rows);
    while (ob_get_level() > 0) @ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_.-]/', '-', $filename) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('X-Microgifter-Export-Rows: ' . count($safeRows));
    $out = fopen('php://output', 'w');
    if ($out === false) exit;
    fputcsv($out, array_map('mg_embed_export_cell', $headers));
    foreach ($safeRows as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)($user['id'] ?? 0);
$pdo = mg_db();
$days = mg_embed_export_days($_GET['days'] ?? 30);
$dataset = mg_embed_export_dataset($_GET['dataset'] ?? 'campaigns');
$campaign = mg_embed_export_campaign($pdo, $merchantId, trim((string)($_GET['campaign'] ?? $_GET['campaign_id'] ?? '')));
$cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));

$eventsReady = mg_embed_export_table_ready($pdo, 'campaign_embed_events', ['id', 'campaign_id', 'merchant_user_id', 'event_type', 'origin_host', 'page_url', 'embed_mode', 'created_at']);
$settingsReady = mg_embed_export_table_ready($pdo, 'campaign_embed_settings', ['campaign_id', 'allowed_domains_json']);
if (!$eventsReady) mg_fail('Campaign embed analytics SQL is required before exporting.', 500);

$where = 'e.merchant_user_id = ? AND e.created_at >= ?';
$params = [$merchantId, $cutoff];
if ($campaign) {
    $where .= ' AND e.campaign_id = ?';
    $params[] = (int)$campaign['id'];
}

try {
    if ($dataset === 'campaigns') {
        $sql = 'SELECT c.public_id, c.public_slug, c.title, c.campaign_type, c.status,
            SUM(CASE WHEN e.event_type = \'loaded\' THEN 1 ELSE 0 END) loaded,
            SUM(CASE WHEN e.event_type = \'opened\' THEN 1 ELSE 0 END) opened,
            SUM(CASE WHEN e.event_type = \'submitted\' THEN 1 ELSE 0 END) submitted,
            SUM(CASE WHEN e.event_type = \'invalid\' THEN 1 ELSE 0 END) invalid_count,
            SUM(CASE WHEN e.event_type = \'error\' THEN 1 ELSE 0 END) error_count,
            MAX(e.created_at) last_event_at
            FROM campaigns c
            LEFT JOIN campaign_embed_events e ON e.campaign_id = c.id AND e.merchant_user_id = c.merchant_user_id AND e.created_at >= ?
            WHERE c.merchant_user_id = ?';
        $campaignParams = [$cutoff, $merchantId];
        if ($campaign) {
            $sql .= ' AND c.id = ?';
            $campaignParams[] = (int)$campaign['id'];
        }
        $sql .= ' GROUP BY c.id, c.public_id, c.public_slug, c.title, c.campaign_type, c.status ORDER BY loaded DESC, submitted DESC, c.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($campaignParams);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $loaded = (int)($row['loaded'] ?? 0);
            $opened = (int)($row['opened'] ?? 0);
            $submitted = (int)($row['submitted'] ?? 0);
            $rows[] = [(string)$row['public_id'], (string)($row['public_slug'] ?? ''), (string)$row['title'], (string)$row['campaign_type'], (string)$row['status'], $loaded, $opened, $submitted, (int)($row['invalid_count'] ?? 0), (int)($row['error_count'] ?? 0), mg_embed_export_rate($opened, $loaded), mg_embed_export_rate($submitted, $loaded), (string)($row['last_event_at'] ?? '')];
        }
        mg_embed_export_stream(mg_embed_export_filename('campaigns', $days, $campaign), ['public_id','slug','title','campaign_type','status','loads','opens','submissions','invalid','errors','open_rate_percent','conversion_rate_percent','last_event_at'], $rows);
    }

    if ($dataset === 'domains') {
        $settingsJoin = $settingsReady ? 'LEFT JOIN campaign_embed_settings s ON s.campaign_id = c.id' : '';
        $sql = 'SELECT e.origin_host, c.title, c.public_id, c.public_slug, COUNT(*) total, SUM(CASE WHEN e.event_type = \'submitted\' THEN 1 ELSE 0 END) submitted, MAX(e.created_at) last_seen' . ($settingsReady ? ', s.allowed_domains_json' : ', NULL allowed_domains_json') . ' FROM campaign_embed_events e JOIN campaigns c ON c.id = e.campaign_id ' . $settingsJoin . ' WHERE ' . $where . ' AND e.origin_host IS NOT NULL AND e.origin_host <> \'\' GROUP BY e.origin_host, c.id, c.title, c.public_id, c.public_slug' . ($settingsReady ? ', s.allowed_domains_json' : '') . ' ORDER BY total DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $approved = mg_embed_export_domain_allowed((string)($row['origin_host'] ?? ''), mg_embed_export_domains($row['allowed_domains_json'] ?? null));
            $rows[] = [(string)$row['origin_host'], (string)$row['title'], (string)$row['public_id'], (string)($row['public_slug'] ?? ''), (int)$row['total'], (int)$row['submitted'], $approved ? 'allowed_or_unrestricted' : 'review_domain', (string)($row['last_seen'] ?? '')];
        }
        mg_embed_export_stream(mg_embed_export_filename('domains', $days, $campaign), ['origin_host','campaign_title','campaign_public_id','campaign_slug','events','submissions','domain_status','last_seen'], $rows);
    }

    $sql = 'SELECT e.created_at, e.event_type, e.origin_host, e.page_url, e.embed_mode, c.title, c.public_id, c.public_slug FROM campaign_embed_events e JOIN campaigns c ON c.id = e.campaign_id WHERE ' . $where . ' ORDER BY e.id DESC LIMIT 1000';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $rows[] = [(string)($row['created_at'] ?? ''), (string)$row['event_type'], (string)($row['origin_host'] ?? ''), (string)($row['page_url'] ?? ''), (string)($row['embed_mode'] ?? ''), (string)$row['title'], (string)$row['public_id'], (string)($row['public_slug'] ?? '')];
    }
    mg_embed_export_stream(mg_embed_export_filename('events', $days, $campaign), ['created_at','event_type','origin_host','page_url','embed_mode','campaign_title','campaign_public_id','campaign_slug'], $rows);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_embed_export.failed', 'Unable to export campaign embed analytics.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to export campaign embed analytics.', 500);
}
