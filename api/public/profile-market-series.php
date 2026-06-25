<?php
declare(strict_types=1);

require_once __DIR__ . '/../profiles/_public_profile.php';
mg_require_method('GET');

function mg_market_series_table(PDO $pdo, string $table): bool
{
    if ($table !== 'merchant_market_snapshots') return false;
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        try { $stmt = $pdo->prepare('SHOW TABLES LIKE ?'); $stmt->execute([$table]); return (bool)$stmt->fetchColumn(); }
        catch (Throwable) { return false; }
    }
}
function mg_market_series_money(int $cents): string
{
    return '$' . number_format(max(0, $cents) / 100, $cents > 0 && $cents < 10000 ? 2 : 0);
}

$pdo = mg_db();
$slug = mg_public_profile_slug((string)($_GET['slug'] ?? ''));
$days = max(7, min(365, (int)($_GET['days'] ?? 30)));

$profile = [];
try {
    $stmt = $pdo->prepare("SELECT id,user_id,slug FROM public_profiles WHERE slug=? AND status='active' AND visibility IN ('public','unlisted') LIMIT 1");
    $stmt->execute([$slug]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable) {
    $profile = [];
}
if (!$profile) mg_fail('Profile not found.', 404);

$rows = [];
if (mg_market_series_table($pdo, 'merchant_market_snapshots')) {
    try {
        $stmt = $pdo->prepare(
            "SELECT snapshot_date,formula_version,ticker_symbol,merchant_score,ticker_value_cents,demand_value_cents,campaign_conversion_value_cents,funnel_quality_score,funnel_quality_value_cents,distribution_value_cents,stamp_inventory_value_cents,stamp_spend_value_cents,follower_brand_value_cents,risk_adjustment_cents
             FROM merchant_market_snapshots
             WHERE merchant_user_id=? AND snapshot_date>=DATE_SUB(CURDATE(),INTERVAL ? DAY)
             ORDER BY snapshot_date ASC"
        );
        $stmt->execute([(int)$profile['user_id'], $days - 1]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $ticker = (int)($row['ticker_value_cents'] ?? 0);
            $risk = (int)($row['risk_adjustment_cents'] ?? 0);
            $rows[] = [
                'date' => (string)$row['snapshot_date'],
                'formula_version' => (string)$row['formula_version'],
                'ticker_symbol' => (string)$row['ticker_symbol'],
                'merchant_score' => (int)$row['merchant_score'],
                'ticker_value' => mg_market_series_money($ticker),
                'ticker_value_cents' => $ticker,
                'demand_value_cents' => (int)$row['demand_value_cents'],
                'campaign_conversion_value_cents' => (int)$row['campaign_conversion_value_cents'],
                'funnel_quality_score' => (float)$row['funnel_quality_score'],
                'funnel_quality_value_cents' => (int)$row['funnel_quality_value_cents'],
                'distribution_value_cents' => (int)$row['distribution_value_cents'],
                'stamp_value_cents' => (int)$row['stamp_inventory_value_cents'] + (int)$row['stamp_spend_value_cents'],
                'follower_brand_value_cents' => (int)$row['follower_brand_value_cents'],
                'risk_adjustment_cents' => $risk,
            ];
        }
    } catch (Throwable) {
        $rows = [];
    }
}

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok([
    'profile' => ['slug' => (string)$profile['slug']],
    'series' => [
        'market_snapshots' => $rows,
        'has_data' => count($rows) >= 2,
        'days' => $days,
    ],
]);
