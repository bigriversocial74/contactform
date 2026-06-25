<?php
declare(strict_types=1);

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

function mg_profile_discovery_market_apply(array $items, array $metrics): array
{
    foreach ($items as &$item) {
        $slug = (string)($item['slug'] ?? '');
        if ($slug !== '' && isset($metrics[$slug])) {
            $item['market'] = $metrics[$slug];
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
            $activityA = (int)($a['audience']['followers'] ?? 0) + (int)($a['audience']['supporters'] ?? 0) + ((int)($a['published_products'] ?? 0) * 3);
            $activityB = (int)($b['audience']['followers'] ?? 0) + (int)($b['audience']['supporters'] ?? 0) + ((int)($b['published_products'] ?? 0) * 3);
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
    if (!mg_profile_discovery_market_table($pdo, 'merchant_market_snapshots')) {
        if (isset($data['results']['items']) && is_array($data['results']['items'])) {
            $data['results']['items'] = mg_profile_discovery_market_sort($data['results']['items'], $sort === 'score' ? 'active' : $sort);
        }
        return $data;
    }

    $slugMap = [];
    mg_profile_discovery_market_collect_slugs($data['results']['items'] ?? [], $slugMap);
    foreach (($data['sections'] ?? []) as $sectionItems) {
        if (is_array($sectionItems)) mg_profile_discovery_market_collect_slugs($sectionItems, $slugMap);
    }
    $slugs = array_keys($slugMap);
    if (!$slugs) return $data;

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
    $stmt = $pdo->prepare($sql);
    $stmt->execute($slugs);

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
            'campaign_conversion_value_cents' => $campaignValue,
            'campaign_conversion_value' => mg_profile_discovery_market_money($campaignValue),
            'funnel_quality_score' => round((float)($row['funnel_quality_score'] ?? 0), 1),
            'distribution_value_cents' => $distributionValue,
            'distribution_value' => mg_profile_discovery_market_money($distributionValue),
            'risk_adjustment_cents' => $riskValue,
            'risk_adjustment' => mg_profile_discovery_market_money($riskValue),
            'snapshot_date' => $snapshotDate,
            'snapshot_freshness' => $fresh ? 'Fresh today' : ($snapshotDate !== '' ? 'Snapshot ' . $snapshotDate : 'No snapshot'),
        ];
    }

    $data['results']['items'] = mg_profile_discovery_market_apply($data['results']['items'] ?? [], $metrics);
    $data['results']['items'] = mg_profile_discovery_market_sort($data['results']['items'], $sort);
    foreach (($data['sections'] ?? []) as $key => $sectionItems) {
        if (is_array($sectionItems)) $data['sections'][$key] = mg_profile_discovery_market_apply($sectionItems, $metrics);
    }
    return $data;
}
