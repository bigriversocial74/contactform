<?php
declare(strict_types=1);

function mg_profile_discovery_market_table(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
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

function mg_profile_discovery_enrich_market_metrics(PDO $pdo, array $data): array
{
    if (!mg_profile_discovery_market_table($pdo, 'merchant_market_snapshots')) return $data;

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
      LEFT JOIN (
        SELECT s.* FROM merchant_market_snapshots s
        INNER JOIN (
          SELECT merchant_user_id, MAX(snapshot_date) AS latest_snapshot_date
          FROM merchant_market_snapshots
          GROUP BY merchant_user_id
        ) latest ON latest.merchant_user_id=s.merchant_user_id AND latest.latest_snapshot_date=s.snapshot_date
      ) mms ON mms.merchant_user_id=pp.user_id
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
    foreach (($data['sections'] ?? []) as $key => $sectionItems) {
        if (is_array($sectionItems)) $data['sections'][$key] = mg_profile_discovery_market_apply($sectionItems, $metrics);
    }
    return $data;
}
