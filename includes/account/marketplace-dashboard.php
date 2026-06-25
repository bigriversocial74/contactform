<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/market/marketplace-index-engine.php';

$marketplacePdo = mg_db();
$marketplaceError = null;
$marketplace = ['has_data' => false, 'summary' => [], 'series' => [], 'top_merchants' => [], 'top_movers' => [], 'composition' => []];
try {
    $marketplace = mg_marketplace_index_build($marketplacePdo, 90);
} catch (Throwable $e) {
    $marketplaceError = $e->getMessage();
}
$summary = $marketplace['summary'] ?? [];
$series = $marketplace['series'] ?? [];
$topMerchants = $marketplace['top_merchants'] ?? [];
$topMovers = $marketplace['top_movers'] ?? [];
$composition = $marketplace['composition'] ?? [];
function mg_marketplace_chart_points(array $rows, string $key, int $width = 680, int $height = 210): string
{
    if (count($rows) < 2) return '';
    $values = array_map(static fn(array $row): float => (float)($row[$key] ?? 0), $rows);
    $max = max($values); $min = min($values);
    if ($max === $min) { $max += 1; $min -= 1; }
    $padX = 20; $padY = 24; $plotW = $width - ($padX * 2); $plotH = $height - ($padY * 2);
    $points = [];
    $last = max(1, count($rows) - 1);
    foreach ($rows as $i => $row) {
        $v = (float)($row[$key] ?? 0);
        $x = $padX + ($i / $last) * $plotW;
        $y = $padY + (1 - (($v - $min) / ($max - $min))) * $plotH;
        $points[] = round($x, 1) . ',' . round($y, 1);
    }
    return implode(' ', $points);
}
function mg_marketplace_line_chart(array $rows, string $key, string $empty): string
{
    $points = mg_marketplace_chart_points($rows, $key);
    if ($points === '') return '<div class="mg-marketplace-empty-chart">' . mg_e($empty) . '</div>';
    $first = (string)($rows[0]['date'] ?? '');
    $last = (string)($rows[count($rows) - 1]['date'] ?? '');
    return '<svg class="mg-marketplace-line-chart" viewBox="0 0 680 210" role="img" aria-label="Marketplace trend"><polyline points="' . mg_e($points) . '" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><g class="mg-marketplace-axis"><text x="20" y="202">' . mg_e($first) . '</text><text x="660" y="202" text-anchor="end">' . mg_e($last) . '</text></g></svg>';
}
function mg_marketplace_bar_list(array $rows, string $labelKey, string $valueKey, string $displayKey): string
{
    if (!$rows) return '<div class="mg-marketplace-empty-chart">No ranked data yet.</div>';
    $max = max(array_map(static fn(array $row): int => abs((int)($row[$valueKey] ?? 0)), $rows)) ?: 1;
    $out = '<div class="mg-marketplace-bars">';
    foreach ($rows as $row) {
        $value = abs((int)($row[$valueKey] ?? 0));
        $pct = max(2, min(100, ($value / $max) * 100));
        $out .= '<div class="mg-marketplace-bar-row"><span>' . mg_e((string)($row[$labelKey] ?? 'Merchant')) . '</span><div><b style="width:' . mg_e((string)round($pct, 1)) . '%"></b></div><em>' . mg_e((string)($row[$displayKey] ?? '')) . '</em></div>';
    }
    return $out . '</div>';
}
?>
<section class="mg-app-panel mg-account-pane is-active mg-marketplace-dashboard" data-account-pane="marketplace_index">
  <div class="mg-marketplace-hero">
    <div>
      <span class="mg-marketplace-kicker">Admin Marketplace Index</span>
      <h2>Marketplace value and movement</h2>
      <p>Aggregate merchant market snapshots into one admin-level index for total value, score, coverage, movers, and risk.</p>
    </div>
    <a class="mg-btn mg-btn-primary" href="/account-investment-tests.php">Open Investment Tests</a>
  </div>

  <?php if ($marketplaceError): ?>
    <div class="mg-marketplace-empty"><h3>Marketplace index unavailable.</h3><p><?= mg_e($marketplaceError) ?></p></div>
  <?php elseif (empty($marketplace['has_data'])): ?>
    <div class="mg-marketplace-empty"><h3>No marketplace snapshots yet.</h3><p>Run market snapshots from Investment Tests or the merchant Market Dashboard to populate this admin index.</p><a class="mg-btn mg-btn-primary" href="/account-investment-tests.php">Create snapshots</a></div>
  <?php else: ?>
    <div class="mg-marketplace-kpi-grid">
      <article class="is-main"><span>Marketplace Index Value</span><strong><?= mg_e((string)($summary['marketplace_value'] ?? '$0')) ?></strong><small><?= mg_e((string)($summary['marketplace_value_delta'] ?? '$0')) ?> vs previous snapshot day</small></article>
      <article><span>Average Merchant Score</span><strong><?= mg_e((string)($summary['avg_score'] ?? '0')) ?></strong><small><?= mg_e((string)($summary['avg_score_delta'] ?? '0')) ?> point movement</small></article>
      <article><span>Active Merchants</span><strong><?= mg_e((string)($summary['merchant_count'] ?? '0')) ?></strong><small>Merchants with saved market snapshots</small></article>
      <article><span>Snapshot Coverage</span><strong><?= mg_e((string)($summary['snapshot_coverage'] ?? '0%')) ?></strong><small>Fresh snapshots captured today</small></article>
    </div>

    <div class="mg-marketplace-chart-grid">
      <section class="mg-marketplace-card is-wide"><header><h3>Total Marketplace Value</h3><p>Sum of latest merchant ticker values over time.</p></header><?= mg_marketplace_line_chart($series, 'marketplace_value_cents', 'No value trend yet.') ?></section>
      <section class="mg-marketplace-card"><header><h3>Average Merchant Score</h3><p>Average score across snapshot merchants.</p></header><?= mg_marketplace_line_chart($series, 'avg_score', 'No score trend yet.') ?></section>
      <section class="mg-marketplace-card"><header><h3>Campaign Conversion Value</h3><p>Total conversion value across marketplace snapshots.</p></header><?= mg_marketplace_line_chart($series, 'campaign_conversion_value_cents', 'No conversion trend yet.') ?></section>
      <section class="mg-marketplace-card"><header><h3>Risk Adjustment</h3><p>Aggregate risk impact from snapshots.</p></header><?= mg_marketplace_line_chart($series, 'risk_adjustment_cents', 'No risk trend yet.') ?></section>
      <section class="mg-marketplace-card is-wide"><header><h3>Top Merchants by Ticker Value</h3><p>Highest current marketplace value contributors.</p></header><?= mg_marketplace_bar_list($topMerchants, 'ticker_symbol', 'ticker_value_cents', 'ticker_value') ?></section>
      <section class="mg-marketplace-card is-wide"><header><h3>Top Movers</h3><p>Largest absolute movement from the previous merchant snapshot.</p></header><?= mg_marketplace_bar_list($topMovers, 'ticker_symbol', 'delta_cents', 'delta_value') ?></section>
    </div>

    <div class="mg-marketplace-component-grid">
      <article><span>Campaign Conversion Value</span><strong><?= mg_e((string)($summary['campaign_conversion_value'] ?? '$0')) ?></strong></article>
      <article><span>Distribution Value</span><strong><?= mg_e((string)($summary['distribution_value'] ?? '$0')) ?></strong></article>
      <article><span>Stamp Value</span><strong><?= mg_e((string)($summary['stamp_value'] ?? '$0')) ?></strong></article>
      <article><span>Follower Brand Value</span><strong><?= mg_e((string)($summary['follower_brand_value'] ?? '$0')) ?></strong></article>
      <article><span>Risk Adjustment</span><strong><?= mg_e((string)($summary['risk_adjustment'] ?? '$0')) ?></strong></article>
    </div>

    <section class="mg-marketplace-table-card"><header><h3>Top merchant index table</h3><p>Current snapshot leaders for admin review.</p></header><div class="mg-marketplace-table-wrap"><table><thead><tr><th>Merchant</th><th>Ticker</th><th>Value</th><th>Score</th><th>Snapshot</th></tr></thead><tbody><?php foreach ($topMerchants as $row): ?><tr><td><?= mg_e((string)$row['profile_slug']) ?></td><td><?= mg_e((string)$row['ticker_symbol']) ?></td><td><?= mg_e((string)$row['ticker_value']) ?></td><td><?= mg_e((string)$row['merchant_score']) ?></td><td><?= mg_e((string)$row['snapshot_date']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
  <?php endif; ?>
</section>
