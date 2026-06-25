<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-market-explainer.php';

function mg_marketplace_index_table(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}
function mg_marketplace_index_money(int $cents): string
{
    $prefix = $cents < 0 ? '-$' : '$';
    return $prefix . number_format(abs($cents) / 100, abs($cents) >= 10000 ? 0 : 2);
}
function mg_marketplace_index_pct(float $value): string
{
    return number_format($value, 1) . '%';
}
function mg_marketplace_index_latest_rows(PDO $pdo): array
{
    if (!mg_marketplace_index_table($pdo, 'merchant_market_snapshots')) return [];
    $sql = "SELECT mms.* FROM merchant_market_snapshots mms INNER JOIN (SELECT merchant_user_id, MAX(snapshot_date) AS max_snapshot_date FROM merchant_market_snapshots GROUP BY merchant_user_id) latest ON latest.merchant_user_id=mms.merchant_user_id AND latest.max_snapshot_date=mms.snapshot_date ORDER BY mms.ticker_value_cents DESC, mms.merchant_score DESC";
    $stmt = $pdo->query($sql);
    return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
}
function mg_marketplace_index_series(PDO $pdo, int $days = 60): array
{
    if (!mg_marketplace_index_table($pdo, 'merchant_market_snapshots')) return [];
    $days = max(7, min(365, $days));
    $stmt = $pdo->prepare("SELECT snapshot_date, COUNT(DISTINCT merchant_user_id) AS merchant_count, SUM(ticker_value_cents) AS marketplace_value_cents, AVG(merchant_score) AS avg_score, SUM(campaign_conversion_value_cents) AS campaign_conversion_value_cents, SUM(distribution_value_cents) AS distribution_value_cents, SUM(stamp_inventory_value_cents + stamp_spend_value_cents) AS stamp_value_cents, SUM(follower_brand_value_cents) AS follower_brand_value_cents, SUM(risk_adjustment_cents) AS risk_adjustment_cents FROM merchant_market_snapshots WHERE snapshot_date>=DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY snapshot_date ORDER BY snapshot_date ASC");
    $stmt->execute([$days]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function mg_marketplace_index_movers(array $latestRows, PDO $pdo, int $limit = 10): array
{
    if (!$latestRows || !mg_marketplace_index_table($pdo, 'merchant_market_snapshots')) return [];
    $out = [];
    $stmt = $pdo->prepare("SELECT ticker_value_cents,merchant_score,snapshot_date FROM merchant_market_snapshots WHERE merchant_user_id=? AND snapshot_date < ? ORDER BY snapshot_date DESC LIMIT 1");
    foreach ($latestRows as $row) {
        $merchantId = (int)($row['merchant_user_id'] ?? 0);
        $date = (string)($row['snapshot_date'] ?? '');
        if ($merchantId < 1 || $date === '') continue;
        $stmt->execute([$merchantId, $date]);
        $prev = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $delta = (int)($row['ticker_value_cents'] ?? 0) - (int)($prev['ticker_value_cents'] ?? 0);
        $scoreDelta = (int)($row['merchant_score'] ?? 0) - (int)($prev['merchant_score'] ?? 0);
        if (!$prev && $delta === 0) continue;
        $out[] = [
            'profile_slug' => (string)($row['profile_slug'] ?? ''),
            'ticker_symbol' => (string)($row['ticker_symbol'] ?? 'MGFT'),
            'ticker_value_cents' => (int)($row['ticker_value_cents'] ?? 0),
            'ticker_value' => mg_marketplace_index_money((int)($row['ticker_value_cents'] ?? 0)),
            'delta_cents' => $delta,
            'delta_value' => ($delta >= 0 ? '+' : '') . mg_marketplace_index_money($delta),
            'merchant_score' => (int)($row['merchant_score'] ?? 0),
            'score_delta' => $scoreDelta,
            'snapshot_date' => $date,
        ];
    }
    usort($out, static fn(array $a, array $b): int => abs((int)$b['delta_cents']) <=> abs((int)$a['delta_cents']));
    return array_slice($out, 0, $limit);
}
function mg_marketplace_index_build(PDO $pdo, int $days = 60): array
{
    $latestRows = mg_marketplace_index_latest_rows($pdo);
    $seriesRows = mg_marketplace_index_series($pdo, $days);
    $latestDate = $seriesRows ? (string)$seriesRows[count($seriesRows) - 1]['snapshot_date'] : null;
    $previous = count($seriesRows) > 1 ? $seriesRows[count($seriesRows) - 2] : [];
    $currentSeries = $seriesRows ? $seriesRows[count($seriesRows) - 1] : [];
    $totalMerchants = count($latestRows);
    $freshRows = array_values(array_filter($latestRows, static fn(array $row): bool => (string)($row['snapshot_date'] ?? '') === date('Y-m-d')));
    $freshness = $totalMerchants > 0 ? (count($freshRows) / $totalMerchants) * 100 : 0.0;
    $sum = static function (array $rows, string $key): int {
        $total = 0;
        foreach ($rows as $row) $total += (int)($row[$key] ?? 0);
        return $total;
    };
    $avgScore = $totalMerchants > 0 ? array_sum(array_map(static fn(array $row): int => (int)($row['merchant_score'] ?? 0), $latestRows)) / $totalMerchants : 0.0;
    $marketplaceValue = $sum($latestRows, 'ticker_value_cents');
    $campaignValue = $sum($latestRows, 'campaign_conversion_value_cents');
    $distributionValue = $sum($latestRows, 'distribution_value_cents');
    $stampValue = 0;
    foreach ($latestRows as $row) $stampValue += (int)($row['stamp_inventory_value_cents'] ?? 0) + (int)($row['stamp_spend_value_cents'] ?? 0);
    $followerValue = $sum($latestRows, 'follower_brand_value_cents');
    $riskValue = $sum($latestRows, 'risk_adjustment_cents');
    $previousValue = (int)($previous['marketplace_value_cents'] ?? $marketplaceValue);
    $previousScore = (float)($previous['avg_score'] ?? $avgScore);
    $topMerchants = array_slice(array_map(static function (array $row): array {
        return [
            'profile_slug' => (string)($row['profile_slug'] ?? ''),
            'ticker_symbol' => (string)($row['ticker_symbol'] ?? 'MGFT'),
            'ticker_value_cents' => (int)($row['ticker_value_cents'] ?? 0),
            'ticker_value' => mg_marketplace_index_money((int)($row['ticker_value_cents'] ?? 0)),
            'merchant_score' => (int)($row['merchant_score'] ?? 0),
            'snapshot_date' => (string)($row['snapshot_date'] ?? ''),
        ];
    }, $latestRows), 0, 10);
    $series = array_map(static function (array $row): array {
        return [
            'date' => (string)$row['snapshot_date'],
            'merchant_count' => (int)$row['merchant_count'],
            'marketplace_value_cents' => (int)$row['marketplace_value_cents'],
            'marketplace_value' => mg_marketplace_index_money((int)$row['marketplace_value_cents']),
            'avg_score' => round((float)$row['avg_score'], 1),
            'campaign_conversion_value_cents' => (int)$row['campaign_conversion_value_cents'],
            'distribution_value_cents' => (int)$row['distribution_value_cents'],
            'stamp_value_cents' => (int)$row['stamp_value_cents'],
            'follower_brand_value_cents' => (int)$row['follower_brand_value_cents'],
            'risk_adjustment_cents' => (int)$row['risk_adjustment_cents'],
        ];
    }, $seriesRows);
    $composition = [
        ['label'=>'Campaign conversions','value_cents'=>$campaignValue,'value'=>mg_marketplace_index_money($campaignValue)],
        ['label'=>'Distribution','value_cents'=>$distributionValue,'value'=>mg_marketplace_index_money($distributionValue)],
        ['label'=>'Stamps','value_cents'=>$stampValue,'value'=>mg_marketplace_index_money($stampValue)],
        ['label'=>'Followers','value_cents'=>$followerValue,'value'=>mg_marketplace_index_money($followerValue)],
        ['label'=>'Risk adjustment','value_cents'=>$riskValue,'value'=>mg_marketplace_index_money($riskValue)],
    ];
    return [
        'summary' => [
            'marketplace_value_cents' => $marketplaceValue,
            'marketplace_value' => mg_marketplace_index_money($marketplaceValue),
            'marketplace_value_delta_cents' => $marketplaceValue - $previousValue,
            'marketplace_value_delta' => (($marketplaceValue - $previousValue) >= 0 ? '+' : '') . mg_marketplace_index_money($marketplaceValue - $previousValue),
            'avg_score' => round($avgScore, 1),
            'avg_score_delta' => round($avgScore - $previousScore, 1),
            'merchant_count' => $totalMerchants,
            'snapshot_coverage' => mg_marketplace_index_pct($freshness),
            'snapshot_coverage_raw' => round($freshness, 1),
            'latest_snapshot_date' => $latestDate,
            'campaign_conversion_value' => mg_marketplace_index_money($campaignValue),
            'distribution_value' => mg_marketplace_index_money($distributionValue),
            'stamp_value' => mg_marketplace_index_money($stampValue),
            'follower_brand_value' => mg_marketplace_index_money($followerValue),
            'risk_adjustment' => mg_marketplace_index_money($riskValue),
        ],
        'series' => $series,
        'top_merchants' => $topMerchants,
        'top_movers' => mg_marketplace_index_movers($latestRows, $pdo, 10),
        'composition' => $composition,
        'has_data' => $totalMerchants > 0,
    ];
}
