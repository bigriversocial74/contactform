<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/market/merchant-market-engine.php';

function mg_profile_discovery_market_table(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function mg_profile_discovery_market_money(int $cents): string
{
    $prefix = $cents < 0 ? '-$' : '$';
    return $prefix . number_format(abs($cents) / 100, abs($cents) >= 10000 ? 0 : 2);
}

function mg_profile_discovery_market_collect_slugs(array $items, array &$slugs): void
{
    foreach ($items as $item) {
        $slug = trim((string)($item['slug'] ?? ''));
        if ($slug !== '') $slugs[$slug] = true;
    }
}

function mg_profile_discovery_market_metric(array $metrics, string $key): array
{
    return is_array($metrics[$key] ?? null) ? $metrics[$key] : [];
}

function mg_profile_discovery_market_metric_display(array $metrics, string $key, string $fallback = ''): string
{
    $metric = mg_profile_discovery_market_metric($metrics, $key);
    $display = trim((string)($metric['display'] ?? ''));
    if ($display === '' || in_array(strtolower($display), ['no trend','no data','no issue data'], true)) return $fallback;
    return $display;
}

function mg_profile_discovery_market_metric_raw(array $metrics, string $key, mixed $fallback = null): mixed
{
    $metric = mg_profile_discovery_market_metric($metrics, $key);
    return array_key_exists('raw', $metric) ? $metric['raw'] : $fallback;
}

function mg_profile_discovery_market_engine_metrics(PDO $pdo, array $slugs): array
{
    $metricsBySlug = [];
    foreach ($slugs as $slug) {
        $slug = trim((string)$slug);
        if ($slug === '') continue;
        try {
            $payload = mg_merchant_market_build($pdo, $slug);
        } catch (Throwable) {
            continue;
        }

        $market = is_array($payload['merchant_market'] ?? null) ? $payload['merchant_market'] : [];
        $metrics = is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [];
        if (empty($market['has_data']) && empty($market['ticker_value_cents']) && empty($market['merchant_score'])) continue;

        $growthRaw = mg_profile_discovery_market_metric_raw($metrics, 'market_growth_30d');
        $engagementRaw = mg_profile_discovery_market_metric_raw($metrics, 'engagement_rate');
        $metricsBySlug[$slug] = [
            'ticker_symbol' => (string)($market['ticker_symbol'] ?? mg_profile_discovery_market_metric_display($metrics, 'ticker_symbol', 'MGFT')),
            'ticker_value_cents' => (int)($market['ticker_value_cents'] ?? 0),
            'ticker_value' => (string)($market['ticker_value'] ?? mg_profile_discovery_market_metric_display($metrics, 'ticker_value', mg_profile_discovery_market_money((int)($market['ticker_value_cents'] ?? 0)))),
            'merchant_score' => (int)($market['merchant_score'] ?? 0),
            'rating' => (string)($market['rating'] ?? mg_profile_discovery_market_metric_display($metrics, 'rating', 'Building Demand')),
            'confidence' => (string)($market['confidence'] ?? mg_profile_discovery_market_metric_display($metrics, 'confidence', 'developing')),
            'campaign_conversion_value_cents' => (int)($market['campaign_conversion_value_cents'] ?? 0),
            'campaign_conversion_value' => (string)($market['campaign_conversion_value'] ?? mg_profile_discovery_market_metric_display($metrics, 'campaign_conversions', '$0')),
            'campaign_funnel_quality' => (float)($market['campaign_funnel_quality'] ?? (float)mg_profile_discovery_market_metric_raw($metrics, 'campaign_funnel_quality', 0)),
            'funnel_quality_score' => (float)($market['campaign_funnel_quality'] ?? (float)mg_profile_discovery_market_metric_raw($metrics, 'campaign_funnel_quality', 0)),
            'distribution_value_cents' => (int)($market['distribution_value_cents'] ?? 0),
            'distribution_value' => (string)($market['distribution_value'] ?? '$0'),
            'risk_adjustment_cents' => (int)($market['risk_adjustment_value_cents'] ?? 0),
            'risk_adjustment' => (string)($market['risk_adjustment_value'] ?? '$0'),
            'active_drops' => (int)mg_profile_discovery_market_metric_raw($metrics, 'active_drops', 0),
            'active_campaigns' => (int)mg_profile_discovery_market_metric_raw($metrics, 'active_campaigns', 0),
            'posts_total' => (int)mg_profile_discovery_market_metric_raw($metrics, 'posts_total', 0),
            'post_interactions' => (int)mg_profile_discovery_market_metric_raw($metrics, 'post_interactions', 0),
            'engagement_rate' => is_numeric($engagementRaw) ? (float)$engagementRaw : 0.0,
            'engagement_rate_display' => mg_profile_discovery_market_metric_display($metrics, 'engagement_rate', ''),
            'market_growth_30d' => is_numeric($growthRaw) ? (float)$growthRaw : null,
            'market_growth_30d_display' => mg_profile_discovery_market_metric_display($metrics, 'market_growth_30d', ''),
            'volume_30d' => (string)mg_profile_discovery_market_metric_display($metrics, 'volume_30d', '$0'),
            'demand_value' => (string)mg_profile_discovery_market_metric_display($metrics, 'demand_value', '$0'),
            'issued_30d' => (int)mg_profile_discovery_market_metric_raw($metrics, 'issued_30d', 0),
            'redeemed_30d' => (int)mg_profile_discovery_market_metric_raw($metrics, 'redeemed_30d', 0),
            'campaign_conversions' => (int)mg_profile_discovery_market_metric_raw($metrics, 'campaign_conversions', 0),
            'snapshot_date' => date('Y-m-d'),
            'snapshot_freshness' => 'Computed now',
            'market_source' => 'merchant_market_engine',
            'has_data' => !empty($market['has_data']),
        ];
    }
    return $metricsBySlug;
}

function mg_profile_discovery_market_snapshot_metrics(PDO $pdo, array $slugs): array
{
    if (!mg_profile_discovery_market_table($pdo, 'merchant_market_snapshots') || !$slugs) return [];

    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $sql = "SELECT pp.slug,
        mms.ticker_symbol,mms.merchant_score,mms.ticker_value_cents,
        mms.campaign_conversion_value_cents,mms.funnel_quality_score,
        mms.distribution_value_cents,mms.risk_adjustment_cents,mms.snapshot_date
      FROM public_profiles pp
      LEFT JOIN merchant_market_snapshots mms ON mms.id = (
        SELECT latest.id
        FROM merchant_market_snapshots latest
        WHERE latest.merchant_user_id = pp.user_id
        ORDER BY latest.snapshot_date DESC, latest.id DESC
        LIMIT 1
      )
      WHERE pp.slug IN ($placeholders)";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($slugs);
    } catch (Throwable) {
        return [];
    }

    $metrics = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (empty($row['ticker_symbol']) && empty($row['snapshot_date'])) continue;
        $tickerValue = (int)($row['ticker_value_cents'] ?? 0);
        $campaignValue = (int)($row['campaign_conversion_value_cents'] ?? 0);
        $distributionValue = (int)($row['distribution_value_cents'] ?? 0);
        $riskValue = (int)($row['risk_adjustment_cents'] ?? 0);
        $snapshotDate = (string)($row['snapshot_date'] ?? '');
        $fresh = $snapshotDate !== '' && $snapshotDate === date('Y-m-d');
        $metrics[(string)$row['slug']] = [
            'ticker_symbol' => (string)($row['ticker_symbol'] ?? 'MGFT'),
            'ticker_value_cents' => $tickerValue,
            'ticker_value' => mg_profile_discovery_market_money($tickerValue),
            'merchant_score' => (int)($row['merchant_score'] ?? 0),
            'rating' => 'Snapshot score',
            'confidence' => 'snapshot',
            'campaign_conversion_value_cents' => $campaignValue,
            'campaign_conversion_value' => mg_profile_discovery_market_money($campaignValue),
            'funnel_quality_score' => round((float)($row['funnel_quality_score'] ?? 0), 1),
            'distribution_value_cents' => $distributionValue,
            'distribution_value' => mg_profile_discovery_market_money($distributionValue),
            'risk_adjustment_cents' => $riskValue,
            'risk_adjustment' => mg_profile_discovery_market_money($riskValue),
            'snapshot_date' => $snapshotDate,
            'snapshot_freshness' => $fresh ? 'Fresh today' : ($snapshotDate !== '' ? 'Snapshot ' . $snapshotDate : 'No snapshot'),
            'market_source' => 'merchant_market_snapshots',
            'has_data' => $tickerValue > 0 || (int)($row['merchant_score'] ?? 0) > 0,
        ];
    }
    return $metrics;
}

function mg_profile_discovery_market_apply(array $items, array $metrics): array
{
    foreach ($items as &$item) {
        $slug = (string)($item['slug'] ?? '');
        if ($slug !== '' && isset($metrics[$slug])) {
            $market = $metrics[$slug];
            if (!isset($market['active_drops'])) {
                $market['active_drops'] = (int)($item['published_products'] ?? 0) + (int)($market['active_campaigns'] ?? 0);
            }
            $item['market'] = $market;
        }
    }
    unset($item);
    return $items;
}

function mg_profile_discovery_market_sort(array $items, string $sort): array
{
    $sort = in_array($sort, ['trending', 'score', 'newest', 'active'], true) ? $sort : 'trending';
    usort($items, static function (array $a, array $b) use ($sort): int {
        $marketA = is_array($a['market'] ?? null) ? $a['market'] : [];
        $marketB = is_array($b['market'] ?? null) ? $b['market'] : [];
        if ($sort === 'score') {
            return ((int)($marketB['merchant_score'] ?? 0) <=> (int)($marketA['merchant_score'] ?? 0))
                ?: ((int)($marketB['ticker_value_cents'] ?? 0) <=> (int)($marketA['ticker_value_cents'] ?? 0))
                ?: strcmp((string)($a['display_name'] ?? ''), (string)($b['display_name'] ?? ''));
        }
        if ($sort === 'newest') {
            return strtotime((string)($b['recent_activity'] ?? $b['updated_at'] ?? '1970-01-01')) <=> strtotime((string)($a['recent_activity'] ?? $a['updated_at'] ?? '1970-01-01'));
        }
        if ($sort === 'active') {
            $activityA = (int)($marketA['active_drops'] ?? 0) + (int)($a['audience']['followers'] ?? 0) + (int)($a['audience']['supporters'] ?? 0) + ((int)($a['published_products'] ?? 0) * 3) + (int)($marketA['post_interactions'] ?? 0);
            $activityB = (int)($marketB['active_drops'] ?? 0) + (int)($b['audience']['followers'] ?? 0) + (int)($b['audience']['supporters'] ?? 0) + ((int)($b['published_products'] ?? 0) * 3) + (int)($marketB['post_interactions'] ?? 0);
            return ($activityB <=> $activityA)
                ?: ((int)($marketB['campaign_conversion_value_cents'] ?? 0) <=> (int)($marketA['campaign_conversion_value_cents'] ?? 0));
        }
        return ((int)($marketB['ticker_value_cents'] ?? 0) <=> (int)($marketA['ticker_value_cents'] ?? 0))
            ?: ((int)($marketB['merchant_score'] ?? 0) <=> (int)($marketA['merchant_score'] ?? 0))
            ?: ((int)($b['published_products'] ?? 0) <=> (int)($a['published_products'] ?? 0));
    });
    return array_values($items);
}

function mg_profile_discovery_enrich_market_metrics(PDO $pdo, array $data, string $sort = 'trending'): array
{
    $slugMap = [];
    mg_profile_discovery_market_collect_slugs($data['results']['items'] ?? [], $slugMap);
    foreach (($data['sections'] ?? []) as $sectionItems) {
        if (is_array($sectionItems)) mg_profile_discovery_market_collect_slugs($sectionItems, $slugMap);
    }
    $slugs = array_keys($slugMap);
    if (!$slugs) return $data;

    $snapshotMetrics = mg_profile_discovery_market_snapshot_metrics($pdo, $slugs);
    $engineMetrics = mg_profile_discovery_market_engine_metrics($pdo, $slugs);
    $metrics = array_replace($snapshotMetrics, $engineMetrics);

    if ($metrics) {
        $data['results']['items'] = mg_profile_discovery_market_apply($data['results']['items'] ?? [], $metrics);
        foreach (($data['sections'] ?? []) as $key => $sectionItems) {
            if (is_array($sectionItems)) $data['sections'][$key] = mg_profile_discovery_market_apply($sectionItems, $metrics);
        }
    }

    $data['results']['items'] = mg_profile_discovery_market_sort($data['results']['items'] ?? [], $sort);
    return $data;
}
