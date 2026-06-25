<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';

$options = getopt('', [
    'base-url::',
    'date::',
    'slug::',
    'limit::',
    'dry-run',
]);

$baseUrl = rtrim((string)($options['base-url'] ?? getenv('MICROGIFTER_BASE_URL') ?: getenv('APP_URL') ?: 'http://127.0.0.1'), '/');
$snapshotDate = (string)($options['date'] ?? date('Y-m-d'));
$slugFilter = trim((string)($options['slug'] ?? ''));
$limit = max(1, min(2000, (int)($options['limit'] ?? 500)));
$dryRun = array_key_exists('dry-run', $options);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $snapshotDate)) {
    fwrite(STDERR, "Invalid --date. Use YYYY-MM-DD.\n");
    exit(1);
}
if (!preg_match('#^https?://#i', $baseUrl)) {
    fwrite(STDERR, "Invalid --base-url. Use http:// or https://.\n");
    exit(1);
}

$pdo = mg_db();

function mg_market_snapshot_table_exists(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute(['merchant_market_snapshots']);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute(['merchant_market_snapshots']);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}

function mg_market_snapshot_fetch(string $url): ?array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: MicrogifterMarketSnapshot/1.0\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false || trim((string)$body) === '') return null;
    $decoded = json_decode((string)$body, true);
    if (!is_array($decoded)) return null;
    $data = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;
    return isset($data['merchant_market']) && is_array($data['merchant_market']) ? $data : null;
}

function mg_market_snapshot_metric_raw(array $data, string $key): int
{
    $value = $data['metrics'][$key]['raw'] ?? 0;
    return is_numeric($value) ? (int)$value : 0;
}

function mg_market_snapshot_market_cents(array $market, string $key): int
{
    $value = $market[$key] ?? 0;
    return is_numeric($value) ? (int)$value : 0;
}

function mg_market_snapshot_save(PDO $pdo, array $profile, array $data, string $snapshotDate, bool $dryRun): bool
{
    $market = $data['merchant_market'];
    $snapshotJson = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $row = [
        (int)$profile['user_id'],
        (int)$profile['id'],
        (string)$profile['slug'],
        $snapshotDate,
        (string)($market['formula_version'] ?? 'unknown'),
        (string)($market['ticker_symbol'] ?? 'MGFT'),
        (int)($market['merchant_score'] ?? 0),
        mg_market_snapshot_market_cents($market, 'ticker_value_cents'),
        mg_market_snapshot_metric_raw($data, 'demand_value'),
        mg_market_snapshot_market_cents($market, 'campaign_conversion_value_cents'),
        (float)($market['campaign_funnel_quality'] ?? 0),
        mg_market_snapshot_market_cents($market, 'funnel_quality_value_cents'),
        mg_market_snapshot_market_cents($market, 'distribution_value_cents'),
        mg_market_snapshot_market_cents($market, 'stamp_inventory_value_cents'),
        mg_market_snapshot_market_cents($market, 'stamp_spend_value_cents'),
        mg_market_snapshot_market_cents($market, 'follower_brand_value_cents'),
        mg_market_snapshot_market_cents($market, 'risk_adjustment_value_cents'),
        $snapshotJson,
    ];

    if ($dryRun) {
        echo "DRY RUN {$row[2]}: date={$row[3]} formula={$row[4]} score={$row[6]} ticker={$row[7]}\n";
        return true;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO merchant_market_snapshots
         (public_id,merchant_user_id,public_profile_id,profile_slug,snapshot_date,formula_version,ticker_symbol,merchant_score,ticker_value_cents,demand_value_cents,campaign_conversion_value_cents,funnel_quality_score,funnel_quality_value_cents,distribution_value_cents,stamp_inventory_value_cents,stamp_spend_value_cents,follower_brand_value_cents,risk_adjustment_cents,snapshot_json,created_at,updated_at)
         VALUES (UUID(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE
           public_profile_id=VALUES(public_profile_id),
           profile_slug=VALUES(profile_slug),
           ticker_symbol=VALUES(ticker_symbol),
           merchant_score=VALUES(merchant_score),
           ticker_value_cents=VALUES(ticker_value_cents),
           demand_value_cents=VALUES(demand_value_cents),
           campaign_conversion_value_cents=VALUES(campaign_conversion_value_cents),
           funnel_quality_score=VALUES(funnel_quality_score),
           funnel_quality_value_cents=VALUES(funnel_quality_value_cents),
           distribution_value_cents=VALUES(distribution_value_cents),
           stamp_inventory_value_cents=VALUES(stamp_inventory_value_cents),
           stamp_spend_value_cents=VALUES(stamp_spend_value_cents),
           follower_brand_value_cents=VALUES(follower_brand_value_cents),
           risk_adjustment_cents=VALUES(risk_adjustment_cents),
           snapshot_json=VALUES(snapshot_json),
           updated_at=NOW()"
    );
    $stmt->execute($row);
    echo "Saved {$row[2]}: date={$row[3]} formula={$row[4]} score={$row[6]} ticker={$row[7]}\n";
    return true;
}

if (!mg_market_snapshot_table_exists($pdo)) {
    fwrite(STDERR, "merchant_market_snapshots table is missing. Apply database/stage_19_merchant_market_snapshots.sql first.\n");
    exit(1);
}

$sql = "SELECT id,user_id,slug FROM public_profiles WHERE status='active' AND visibility IN ('public','unlisted')";
$params = [];
if ($slugFilter !== '') {
    $sql .= ' AND slug=?';
    $params[] = strtolower($slugFilter);
}
$sql .= ' ORDER BY id ASC LIMIT ' . $limit;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$profiles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$total = count($profiles);
$written = 0;
$failed = 0;
$startedAt = microtime(true);

echo "Merchant market snapshot runner starting. total={$total} date={$snapshotDate} base_url={$baseUrl} dry_run=" . ($dryRun ? 'yes' : 'no') . "\n";

foreach ($profiles as $profile) {
    $slug = (string)$profile['slug'];
    $url = $baseUrl . '/api/public/profile-investment.php?slug=' . rawurlencode($slug) . '&snapshot=1';
    $data = mg_market_snapshot_fetch($url);
    if (!$data) {
        $failed++;
        fwrite(STDERR, "Failed to calculate market snapshot for {$slug}.\n");
        continue;
    }
    try {
        if (mg_market_snapshot_save($pdo, $profile, $data, $snapshotDate, $dryRun)) $written++;
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "Failed to save market snapshot for {$slug}: " . $e->getMessage() . "\n");
    }
}

$elapsed = round(microtime(true) - $startedAt, 2);
echo "Merchant market snapshot runner complete. total={$total} written={$written} failed={$failed} elapsed={$elapsed}s\n";
exit($failed > 0 ? 2 : 0);
