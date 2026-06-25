<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';

$options = getopt('', ['input:', 'date::', 'slug::', 'dry-run']);
$input = (string)($options['input'] ?? '');
$snapshotDate = (string)($options['date'] ?? date('Y-m-d'));
$slugOverride = trim((string)($options['slug'] ?? ''));
$dryRun = array_key_exists('dry-run', $options);

if ($input === '' || !is_file($input)) {
    fwrite(STDERR, "Usage: php scripts/snapshot_merchant_market.php --input=/path/to/profile-investment-response.json [--date=YYYY-MM-DD] [--slug=merchant-slug] [--dry-run]\n");
    exit(1);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $snapshotDate)) {
    fwrite(STDERR, "Invalid --date. Use YYYY-MM-DD.\n");
    exit(1);
}

$payload = json_decode((string)file_get_contents($input), true);
if (!is_array($payload)) {
    fwrite(STDERR, "Input is not valid JSON.\n");
    exit(1);
}
$data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
if (!isset($data['merchant_market']) || !is_array($data['merchant_market'])) {
    fwrite(STDERR, "Input must be a profile-investment API response containing merchant_market.\n");
    exit(1);
}

$pdo = mg_db();
try {
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $stmt->execute(['merchant_market_snapshots']);
    if (!$stmt->fetchColumn()) throw new RuntimeException('missing');
} catch (Throwable) {
    fwrite(STDERR, "merchant_market_snapshots table is missing. Apply database/stage_19_merchant_market_snapshots.sql first.\n");
    exit(1);
}

$slug = $slugOverride !== '' ? strtolower($slugOverride) : (string)($data['profile']['slug'] ?? '');
if ($slug === '') {
    fwrite(STDERR, "Slug is missing. Pass --slug=merchant-slug.\n");
    exit(1);
}
$stmt = $pdo->prepare("SELECT id,user_id,slug FROM public_profiles WHERE slug=? LIMIT 1");
$stmt->execute([$slug]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
if (!$profile) {
    fwrite(STDERR, "Profile not found for slug {$slug}.\n");
    exit(1);
}

$market = $data['merchant_market'];
$metricRaw = static function (array $data, string $key): int {
    $v = $data['metrics'][$key]['raw'] ?? 0;
    return is_numeric($v) ? (int)$v : 0;
};
$marketCents = static function (array $market, string $key): int {
    $v = $market[$key] ?? 0;
    return is_numeric($v) ? (int)$v : 0;
};
$snapshotJson = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$row = [
    (int)$profile['user_id'],
    (int)$profile['id'],
    (string)$profile['slug'],
    $snapshotDate,
    (string)($market['formula_version'] ?? 'unknown'),
    (string)($market['ticker_symbol'] ?? 'MGFT'),
    (int)($market['merchant_score'] ?? 0),
    $marketCents($market, 'ticker_value_cents'),
    $metricRaw($data, 'demand_value'),
    $marketCents($market, 'campaign_conversion_value_cents'),
    (float)($market['campaign_funnel_quality'] ?? 0),
    $marketCents($market, 'funnel_quality_value_cents'),
    $marketCents($market, 'distribution_value_cents'),
    $marketCents($market, 'stamp_inventory_value_cents'),
    $marketCents($market, 'stamp_spend_value_cents'),
    $marketCents($market, 'follower_brand_value_cents'),
    $marketCents($market, 'risk_adjustment_value_cents'),
    $snapshotJson,
];

if ($dryRun) {
    echo "DRY RUN {$slug}: score={$row[6]} ticker={$row[7]} formula={$row[4]} date={$snapshotDate}\n";
    exit(0);
}

$insert = $pdo->prepare(
    "INSERT INTO merchant_market_snapshots
     (public_id,merchant_user_id,public_profile_id,profile_slug,snapshot_date,formula_version,ticker_symbol,merchant_score,ticker_value_cents,demand_value_cents,campaign_conversion_value_cents,funnel_quality_score,funnel_quality_value_cents,distribution_value_cents,stamp_inventory_value_cents,stamp_spend_value_cents,follower_brand_value_cents,risk_adjustment_cents,snapshot_json,created_at,updated_at)
     VALUES (UUID(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
     ON DUPLICATE KEY UPDATE
       public_profile_id=VALUES(public_profile_id),profile_slug=VALUES(profile_slug),ticker_symbol=VALUES(ticker_symbol),merchant_score=VALUES(merchant_score),ticker_value_cents=VALUES(ticker_value_cents),demand_value_cents=VALUES(demand_value_cents),campaign_conversion_value_cents=VALUES(campaign_conversion_value_cents),funnel_quality_score=VALUES(funnel_quality_score),funnel_quality_value_cents=VALUES(funnel_quality_value_cents),distribution_value_cents=VALUES(distribution_value_cents),stamp_inventory_value_cents=VALUES(stamp_inventory_value_cents),stamp_spend_value_cents=VALUES(stamp_spend_value_cents),follower_brand_value_cents=VALUES(follower_brand_value_cents),risk_adjustment_cents=VALUES(risk_adjustment_cents),snapshot_json=VALUES(snapshot_json),updated_at=NOW()"
);
$insert->execute($row);

echo "Merchant market snapshot saved: slug={$slug} date={$snapshotDate} score={$row[6]} ticker={$row[7]} formula={$row[4]}\n";
