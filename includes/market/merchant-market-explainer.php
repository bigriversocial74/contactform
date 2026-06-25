<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-market-engine.php';

function mg_market_explainer_money(int $cents): string
{
    $prefix = $cents < 0 ? '-$' : '$';
    return $prefix . number_format(abs($cents) / 100, abs($cents) > 0 && abs($cents) < 10000 ? 2 : 0);
}
function mg_market_explainer_delta(int|float $value, string $type = 'number'): string
{
    $prefix = $value > 0 ? '+' : '';
    if ($type === 'money') return ($value > 0 ? '+' : '') . mg_market_explainer_money((int)$value);
    if ($type === 'percent') return $prefix . number_format((float)$value, 1) . '%';
    return $prefix . number_format((float)$value, is_float($value) && fmod((float)$value, 1.0) !== 0.0 ? 1 : 0);
}
function mg_market_snapshot_load(PDO $pdo, int $merchantUserId, int $limit = 90): array
{
    if (!mg_market_table($pdo, 'merchant_market_snapshots')) return [];
    $limit = max(2, min(365, $limit));
    $stmt = $pdo->prepare("SELECT snapshot_date,formula_version,ticker_symbol,merchant_score,ticker_value_cents,demand_value_cents,campaign_conversion_value_cents,funnel_quality_score,funnel_quality_value_cents,distribution_value_cents,stamp_inventory_value_cents,stamp_spend_value_cents,follower_brand_value_cents,risk_adjustment_cents,snapshot_json,updated_at FROM merchant_market_snapshots WHERE merchant_user_id=? ORDER BY snapshot_date ASC LIMIT {$limit}");
    $stmt->execute([$merchantUserId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function mg_market_snapshot_payload_field(array $payload, string $path, mixed $default = 0): mixed
{
    $value = $payload;
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}
function mg_market_snapshot_decode(array $snapshot): array
{
    $json = (string)($snapshot['snapshot_json'] ?? '');
    if ($json === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}
function mg_market_snapshot_save(PDO $pdo, array $profile, array $payload, ?string $snapshotDate = null): array
{
    if (!mg_market_table($pdo, 'merchant_market_snapshots')) throw new RuntimeException('Snapshot table is missing. Create it from Investment Tests first.');
    $snapshotDate = $snapshotDate ?: date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $snapshotDate)) throw new InvalidArgumentException('Invalid snapshot date.');
    $market = $payload['merchant_market'] ?? [];
    if (!is_array($market)) throw new RuntimeException('Market payload is missing merchant_market.');
    $snapshotJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $metricRaw = static function (array $payload, string $key): int {
        $v = $payload['metrics'][$key]['raw'] ?? 0;
        return is_numeric($v) ? (int)$v : 0;
    };
    $marketCents = static function (array $market, string $key): int {
        $v = $market[$key] ?? 0;
        return is_numeric($v) ? (int)$v : 0;
    };
    $row = [(int)$profile['user_id'],(int)$profile['id'],(string)$profile['slug'],$snapshotDate,(string)($market['formula_version'] ?? 'unknown'),(string)($market['ticker_symbol'] ?? 'MGFT'),(int)($market['merchant_score'] ?? 0),$marketCents($market, 'ticker_value_cents'),$metricRaw($payload, 'demand_value'),$marketCents($market, 'campaign_conversion_value_cents'),(float)($market['campaign_funnel_quality'] ?? 0),$marketCents($market, 'funnel_quality_value_cents'),$marketCents($market, 'distribution_value_cents'),$marketCents($market, 'stamp_inventory_value_cents'),$marketCents($market, 'stamp_spend_value_cents'),$marketCents($market, 'follower_brand_value_cents'),$marketCents($market, 'risk_adjustment_value_cents'),$snapshotJson];
    $stmt = $pdo->prepare("INSERT INTO merchant_market_snapshots (public_id,merchant_user_id,public_profile_id,profile_slug,snapshot_date,formula_version,ticker_symbol,merchant_score,ticker_value_cents,demand_value_cents,campaign_conversion_value_cents,funnel_quality_score,funnel_quality_value_cents,distribution_value_cents,stamp_inventory_value_cents,stamp_spend_value_cents,follower_brand_value_cents,risk_adjustment_cents,snapshot_json,created_at,updated_at) VALUES (UUID(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE public_profile_id=VALUES(public_profile_id),profile_slug=VALUES(profile_slug),ticker_symbol=VALUES(ticker_symbol),merchant_score=VALUES(merchant_score),ticker_value_cents=VALUES(ticker_value_cents),demand_value_cents=VALUES(demand_value_cents),campaign_conversion_value_cents=VALUES(campaign_conversion_value_cents),funnel_quality_score=VALUES(funnel_quality_score),funnel_quality_value_cents=VALUES(funnel_quality_value_cents),distribution_value_cents=VALUES(distribution_value_cents),stamp_inventory_value_cents=VALUES(stamp_inventory_value_cents),stamp_spend_value_cents=VALUES(stamp_spend_value_cents),follower_brand_value_cents=VALUES(follower_brand_value_cents),risk_adjustment_cents=VALUES(risk_adjustment_cents),snapshot_json=VALUES(snapshot_json),updated_at=NOW()");
    $stmt->execute($row);
    return ['slug'=>(string)$profile['slug'],'date'=>$snapshotDate,'ticker_value_cents'=>$row[7],'ticker_value'=>mg_market_explainer_money((int)$row[7]),'merchant_score'=>(int)$row[6],'formula_version'=>(string)$row[4]];
}
function mg_market_explain_movement(array $currentPayload, array $snapshots): array
{
    $latest = $snapshots ? $snapshots[count($snapshots) - 1] : [];
    $previous = count($snapshots) > 1 ? $snapshots[count($snapshots) - 2] : [];
    $basis = $previous ?: $latest;
    $basisPayload = $previous ? mg_market_snapshot_decode($previous) : mg_market_snapshot_decode($latest);
    $market = $currentPayload['merchant_market'] ?? [];
    $current = [
        'ticker_value_cents' => (int)($market['ticker_value_cents'] ?? 0),
        'merchant_score' => (int)($market['merchant_score'] ?? 0),
        'funnel_quality_score' => (float)($market['campaign_funnel_quality'] ?? 0),
        'risk_adjustment_cents' => (int)($market['risk_adjustment_value_cents'] ?? 0),
    ];
    $base = $basis ? [
        'ticker_value_cents' => (int)($basis['ticker_value_cents'] ?? 0),
        'merchant_score' => (int)($basis['merchant_score'] ?? 0),
        'funnel_quality_score' => (float)($basis['funnel_quality_score'] ?? 0),
        'risk_adjustment_cents' => (int)($basis['risk_adjustment_cents'] ?? 0),
    ] : $current;
    $delta = [
        'ticker_value_cents' => $current['ticker_value_cents'] - $base['ticker_value_cents'],
        'merchant_score' => $current['merchant_score'] - $base['merchant_score'],
        'funnel_quality_score' => $current['funnel_quality_score'] - $base['funnel_quality_score'],
        'risk_adjustment_cents' => $current['risk_adjustment_cents'] - $base['risk_adjustment_cents'],
    ];
    $currentConversions = (int)mg_market_snapshot_payload_field($currentPayload, 'campaign_conversions.total', 0);
    $baseConversions = (int)mg_market_snapshot_payload_field($basisPayload, 'campaign_conversions.total', 0);
    $currentRedeemed = (int)mg_market_snapshot_payload_field($currentPayload, 'campaign_conversions.redeemed', 0);
    $baseRedeemed = (int)mg_market_snapshot_payload_field($basisPayload, 'campaign_conversions.redeemed', 0);
    $currentDistribution = (int)mg_market_snapshot_payload_field($currentPayload, 'merchant_market.distribution_value_cents', 0);
    $baseDistribution = (int)mg_market_snapshot_payload_field($basisPayload, 'merchant_market.distribution_value_cents', 0);
    $currentStamp = (int)mg_market_snapshot_payload_field($currentPayload, 'merchant_market.stamp_spend_value_cents', 0) + (int)mg_market_snapshot_payload_field($currentPayload, 'merchant_market.stamp_inventory_value_cents', 0);
    $baseStamp = (int)mg_market_snapshot_payload_field($basisPayload, 'merchant_market.stamp_spend_value_cents', 0) + (int)mg_market_snapshot_payload_field($basisPayload, 'merchant_market.stamp_inventory_value_cents', 0);
    $drivers = [];
    $driverPairs = [
        ['label'=>'Campaign conversions','delta'=>$currentConversions - $baseConversions,'positive'=>'Campaign conversions increased, adding more proof of demand.','negative'=>'Campaign conversions fell, reducing market confidence.'],
        ['label'=>'Redemptions','delta'=>$currentRedeemed - $baseRedeemed,'positive'=>'More rewards were redeemed, strengthening the funnel signal.','negative'=>'Redemptions did not keep pace, which can weaken funnel quality.'],
        ['label'=>'Distribution value','delta'=>$currentDistribution - $baseDistribution,'positive'=>'Distribution value improved through programs, channels, events, or allocations.','negative'=>'Distribution value moved down, reducing reach beyond your own audience.'],
        ['label'=>'Stamp value','delta'=>$currentStamp - $baseStamp,'positive'=>'Stamp inventory or spend increased, showing more campaign fuel.','negative'=>'Stamp value decreased, lowering campaign fuel visible to the model.'],
        ['label'=>'Risk adjustment','delta'=>$delta['risk_adjustment_cents'],'positive'=>'Risk improved, so less value is being deducted.','negative'=>'Risk worsened, likely from opt-outs, failed distribution, expired rewards, or follower loss.'],
    ];
    foreach ($driverPairs as $d) {
        if ((float)$d['delta'] === 0.0) continue;
        $drivers[] = ['label'=>$d['label'],'delta'=>$d['delta'],'direction'=>$d['delta'] > 0 ? 'positive' : 'negative','text'=>$d['delta'] > 0 ? $d['positive'] : $d['negative']];
    }
    usort($drivers, static fn(array $a, array $b): int => abs((float)$b['delta']) <=> abs((float)$a['delta']));
    $positive = array_values(array_filter($drivers, static fn(array $d): bool => $d['direction'] === 'positive'));
    $negative = array_values(array_filter($drivers, static fn(array $d): bool => $d['direction'] === 'negative'));
    $summary = $delta['ticker_value_cents'] > 0 ? 'Ticker value is up since the comparison point.' : ($delta['ticker_value_cents'] < 0 ? 'Ticker value is down since the comparison point.' : 'Ticker value is flat against the comparison point.');
    $recommended = $negative[0]['text'] ?? ($positive[0]['text'] ?? 'Take a snapshot after each meaningful campaign push so movement becomes easier to explain.');
    if ($negative) $recommended = 'Priority: ' . $recommended;
    else $recommended = 'Next move: keep feeding the signal with a simple campaign, clean distribution, and easy redemptions.';
    return [
        'has_baseline' => (bool)$basis,
        'baseline_date' => (string)($basis['snapshot_date'] ?? 'Live baseline'),
        'latest_snapshot_date' => (string)($latest['snapshot_date'] ?? 'No snapshot yet'),
        'summary' => $summary,
        'cards' => [
            ['label'=>'Ticker Value Change','value'=>mg_market_explainer_delta($delta['ticker_value_cents'], 'money'),'raw'=>$delta['ticker_value_cents']],
            ['label'=>'Score Change','value'=>mg_market_explainer_delta($delta['merchant_score']),'raw'=>$delta['merchant_score']],
            ['label'=>'Funnel Quality Change','value'=>mg_market_explainer_delta($delta['funnel_quality_score']),'raw'=>$delta['funnel_quality_score']],
            ['label'=>'Risk Change','value'=>mg_market_explainer_delta($delta['risk_adjustment_cents'], 'money'),'raw'=>$delta['risk_adjustment_cents']],
        ],
        'top_positive' => $positive[0]['text'] ?? 'No major positive movement detected yet.',
        'top_negative' => $negative[0]['text'] ?? 'No major negative movement detected yet.',
        'recommended_action' => $recommended,
        'drivers' => $drivers,
    ];
}
