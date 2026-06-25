<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/market/merchant-market-explainer.php';

$marketPdo = mg_db();
$marketUserId = (int)($user['id'] ?? 0);
$marketProfile = [];
$marketPayload = null;
$marketSnapshots = [];
$marketError = null;
$marketMessage = null;

function mg_account_market_chart_points(array $rows, string $key, int $width = 640, int $height = 190): string
{
    if (count($rows) < 2) return '';
    $values = array_map(static fn(array $row): float => (float)($row[$key] ?? 0), $rows);
    $max = max($values); $min = min($values);
    if ($max === $min) { $max += 1; $min -= 1; }
    $padX = 18; $padY = 20; $plotW = $width - ($padX * 2); $plotH = $height - ($padY * 2);
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
function mg_account_market_chart(array $rows, string $key, string $empty = 'No chart data yet.'): string
{
    $points = mg_account_market_chart_points($rows, $key);
    if ($points === '') return '<div class="mg-market-empty-chart">' . mg_e($empty) . '</div>';
    $first = (string)($rows[0]['snapshot_date'] ?? '');
    $last = (string)($rows[count($rows)-1]['snapshot_date'] ?? '');
    return '<svg class="mg-market-line-chart" viewBox="0 0 640 190" role="img" aria-label="Market trend chart"><defs><linearGradient id="mgMarketFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-opacity=".20"/><stop offset="100%" stop-opacity="0"/></linearGradient></defs><polyline points="' . mg_e($points) . '" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><polyline points="18,170 ' . mg_e($points) . ' 622,170" fill="url(#mgMarketFill)" stroke="none"/><g class="mg-market-axis"><text x="18" y="184">' . mg_e($first) . '</text><text x="622" y="184" text-anchor="end">' . mg_e($last) . '</text></g></svg>';
}
function mg_account_market_bar_chart(array $components): string
{
    if (!$components) return '<div class="mg-market-empty-chart">No component data yet.</div>';
    $out = '<div class="mg-market-bar-chart">';
    foreach ($components as $row) {
        $score = (float)($row['score'] ?? 0);
        $pct = max(0, min(100, ($score / 12) * 100));
        $out .= '<div class="mg-market-bar-row"><span>' . mg_e((string)($row['component'] ?? 'Component')) . '</span><div><b style="width:' . mg_e((string)round($pct, 1)) . '%"></b></div><em>' . mg_e((string)$score) . '</em></div>';
    }
    return $out . '</div>';
}
function mg_account_market_next_actions(array $payload, array $movement): array
{
    $m = $payload['metrics'] ?? [];
    $actions = [];
    if (!empty($movement['recommended_action'])) $actions[] = (string)$movement['recommended_action'];
    if ((int)($m['active_campaigns']['raw'] ?? 0) < 1) $actions[] = 'Launch one simple active campaign tied to a QR, newsletter, contest, or referral entry point.';
    if ((int)($m['campaign_conversions']['raw'] ?? 0) < 10) $actions[] = 'Drive more campaign conversions. The model rewards contacts, issued rewards, claims, and redemptions.';
    if ((float)($m['campaign_redemption_rate']['raw'] ?? 0) < 20) $actions[] = 'Improve the redemption path. Make the offer easier to claim and easier for staff to verify.';
    if ((int)($m['distribution_channels']['raw'] ?? 0) < 1) $actions[] = 'Connect at least one distribution channel so demand is not limited to your own audience.';
    if (!$actions) $actions[] = 'Keep the campaign running and take another snapshot tomorrow to track movement.';
    return array_values(array_unique($actions));
}

try {
    $stmt = $marketPdo->prepare("SELECT id,user_id,slug,display_name,status,visibility FROM public_profiles WHERE user_id=? AND status='active' AND visibility IN ('public','unlisted') ORDER BY updated_at DESC,id DESC LIMIT 1");
    $stmt->execute([$marketUserId]);
    $marketProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($marketProfile) {
        $marketPayload = mg_merchant_market_build($marketPdo, (string)$marketProfile['slug'], ['viewer_id'=>$marketUserId]);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['market_action'] ?? '') === 'take_snapshot') {
            if (!mg_verify_csrf((string)($_POST['csrf_token'] ?? ''))) throw new RuntimeException('Security token expired. Refresh and try again.');
            $saved = mg_market_snapshot_save($marketPdo, $marketProfile, $marketPayload, date('Y-m-d'));
            $marketMessage = 'Snapshot saved for ' . $saved['date'] . ' · ' . $saved['ticker_value'] . ' · score ' . $saved['merchant_score'] . '.';
        }
        $marketSnapshots = mg_market_snapshot_load($marketPdo, $marketUserId, 90);
    }
} catch (Throwable $e) {
    $marketError = $e->getMessage();
}

$mm = $marketPayload['merchant_market'] ?? [];
$metrics = $marketPayload['metrics'] ?? [];
$conv = $marketPayload['campaign_conversions'] ?? [];
$components = $marketPayload['series']['score_components'] ?? [];
$movement = $marketPayload ? mg_market_explain_movement($marketPayload, $marketSnapshots) : [];
$chartRows = $marketSnapshots;
if (!$chartRows && !empty($marketPayload['series']['volume_30d'])) {
    foreach ($marketPayload['series']['volume_30d'] as $row) $chartRows[] = ['snapshot_date'=>$row['date'] ?? '', 'ticker_value_cents'=>$row['value_cents'] ?? 0, 'merchant_score'=>$mm['merchant_score'] ?? 0, 'funnel_quality_score'=>$mm['campaign_funnel_quality'] ?? 0, 'risk_adjustment_cents'=>$mm['risk_adjustment_value_cents'] ?? 0];
}
?>
<section class="mg-app-panel mg-account-pane is-active mg-market-dashboard" data-account-pane="market">
  <div class="mg-market-hero">
    <div>
      <span class="mg-market-kicker">Merchant Market Dashboard</span>
      <h2>Your local demand ticker</h2>
      <p>Track the value signals Microgifter can see: products, campaigns, funnel quality, redemptions, distribution, stamps, followers, and risk.</p>
    </div>
    <?php if ($marketProfile): ?>
      <div class="mg-market-hero-actions">
        <form method="post"><?= mg_csrf_field() ?><input type="hidden" name="market_action" value="take_snapshot"><button class="mg-btn mg-btn-primary" type="submit">Take Snapshot Now</button></form>
        <a class="mg-btn mg-btn-ghost" href="/profile.php?slug=<?= mg_e((string)$marketProfile['slug']) ?>">View public profile</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($marketMessage): ?><div class="mg-market-notice is-success"><strong>Snapshot captured</strong><span><?= mg_e($marketMessage) ?></span></div><?php endif; ?>

  <?php if (!$marketProfile): ?>
    <div class="mg-market-empty"><h3>No public merchant profile yet.</h3><p>Create or publish your profile first. The market dashboard needs an active public or unlisted merchant profile to calculate a ticker.</p><a class="mg-btn mg-btn-primary" href="/account.php">Open profile editor</a></div>
  <?php elseif ($marketError): ?>
    <div class="mg-market-empty"><h3>Market dashboard unavailable.</h3><p><?= mg_e($marketError) ?></p></div>
  <?php else: ?>
    <section class="mg-market-movement-card">
      <header><div><span class="mg-market-kicker">Market Movement</span><h3><?= mg_e((string)($movement['summary'] ?? 'Market movement is ready.')) ?></h3><p>Comparison point: <?= mg_e((string)($movement['baseline_date'] ?? 'No baseline')) ?> · Latest snapshot: <?= mg_e((string)($movement['latest_snapshot_date'] ?? 'No snapshot yet')) ?></p></div></header>
      <div class="mg-market-movement-grid"><?php foreach (($movement['cards'] ?? []) as $card): ?><article class="<?= ((float)($card['raw'] ?? 0) < 0) ? 'is-negative' : (((float)($card['raw'] ?? 0) > 0) ? 'is-positive' : '') ?>"><span><?= mg_e((string)$card['label']) ?></span><strong><?= mg_e((string)$card['value']) ?></strong></article><?php endforeach; ?></div>
      <div class="mg-market-driver-grid"><article><span>Top Positive Driver</span><p><?= mg_e((string)($movement['top_positive'] ?? 'No major positive movement detected yet.')) ?></p></article><article><span>Top Negative Driver</span><p><?= mg_e((string)($movement['top_negative'] ?? 'No major negative movement detected yet.')) ?></p></article><article class="is-wide"><span>Recommended Action</span><p><?= mg_e((string)($movement['recommended_action'] ?? 'Take a snapshot after the next campaign push.')) ?></p></article></div>
    </section>

    <div class="mg-market-top-grid">
      <article class="mg-market-main-card"><span>Ticker Value</span><strong><?= mg_e((string)($mm['ticker_value'] ?? '$0')) ?></strong><small><?= mg_e((string)($mm['ticker_symbol'] ?? 'MGFT')) ?> · <?= mg_e((string)($mm['rating'] ?? 'No Market Signal')) ?> · confidence <?= mg_e((string)($mm['confidence'] ?? 'no data')) ?></small></article>
      <article><span>Merchant Score</span><strong><?= mg_e((string)($mm['merchant_score'] ?? '0')) ?></strong><small>Composite score out of 100</small></article>
      <article><span>Funnel Quality</span><strong><?= mg_e((string)($mm['campaign_funnel_quality'] ?? '0')) ?></strong><small>Campaign claim and redemption signal</small></article>
      <article><span>Risk Adjustment</span><strong><?= mg_e((string)($mm['risk_adjustment_value'] ?? '$0')) ?></strong><small>Opt-outs, failed distribution, expired rewards, lost followers</small></article>
    </div>

    <div class="mg-market-chart-grid">
      <section class="mg-market-chart-card is-wide"><header><h3>Ticker Value History</h3><p>Stored market snapshots over time. Falls back to recent wallet volume until snapshots exist.</p></header><?= mg_account_market_chart($chartRows, 'ticker_value_cents', 'No ticker snapshots yet.') ?></section>
      <section class="mg-market-chart-card"><header><h3>Merchant Score</h3><p>Score trend from saved market snapshots.</p></header><?= mg_account_market_chart($chartRows, 'merchant_score', 'No score snapshots yet.') ?></section>
      <section class="mg-market-chart-card"><header><h3>Funnel Quality</h3><p>Campaign funnel quality trend.</p></header><?= mg_account_market_chart($chartRows, 'funnel_quality_score', 'No funnel snapshots yet.') ?></section>
      <section class="mg-market-chart-card"><header><h3>Risk Adjustment</h3><p>Negative value impact from risk signals.</p></header><?= mg_account_market_chart($chartRows, 'risk_adjustment_cents', 'No risk snapshots yet.') ?></section>
      <section class="mg-market-chart-card is-wide"><header><h3>Score Component Graph</h3><p>What is currently contributing to the market score.</p></header><?= mg_account_market_bar_chart($components) ?></section>
    </div>

    <div class="mg-market-metric-grid">
      <article><span>Campaign Conversions</span><strong><?= mg_e((string)($metrics['campaign_conversions']['display'] ?? '0')) ?></strong><small>Contacts + events + issued + claims + redemptions</small></article>
      <article><span>Claim Rate</span><strong><?= mg_e((string)($metrics['campaign_claim_rate']['display'] ?? 'No data')) ?></strong><small>Claimed or redeemed ÷ issued</small></article>
      <article><span>Redemption Rate</span><strong><?= mg_e((string)($metrics['campaign_redemption_rate']['display'] ?? 'No data')) ?></strong><small>Redeemed ÷ issued</small></article>
      <article><span>Distribution Value</span><strong><?= mg_e((string)($mm['distribution_value'] ?? '$0')) ?></strong><small>Programs, channels, events, allocations</small></article>
      <article><span>Stamp Inventory</span><strong><?= mg_e((string)($metrics['stamp_inventory']['display'] ?? '0')) ?></strong><small>Available fuel for campaigns</small></article>
      <article><span>Stamp Spend 30D</span><strong><?= mg_e((string)($metrics['stamp_spend_30d']['display'] ?? '0')) ?></strong><small>Recent campaign investment</small></article>
      <article><span>Follower Momentum</span><strong><?= mg_e((string)($metrics['follower_momentum']['display'] ?? '0')) ?></strong><small>New followers minus detected losses</small></article>
      <article><span>Value per Follower</span><strong><?= mg_e((string)($metrics['value_per_follower']['display'] ?? '$0')) ?></strong><small>Estimated follower brand value</small></article>
    </div>

    <div class="mg-market-explain-grid">
      <section><h3>Why this value moved up</h3><p><?= mg_e((string)($movement['top_positive'] ?? 'No major positive movement detected yet.')) ?></p></section>
      <section><h3>What is pulling it down</h3><p><?= mg_e((string)($movement['top_negative'] ?? 'No major negative movement detected yet.')) ?></p></section>
      <section class="is-wide"><h3>Recommended next moves</h3><div class="mg-market-action-list"><?php foreach (mg_account_market_next_actions($marketPayload, $movement) as $i => $text): ?><p><b><?= $i + 1 ?></b><span><?= mg_e($text) ?></span></p><?php endforeach; ?></div></section>
    </div>

    <?php require __DIR__ . '/market-action-center.php'; ?>

    <section class="mg-market-table-card"><header><h3>Campaign funnel detail</h3><p>Source-level conversion quality feeding the score.</p></header><div class="mg-market-funnel-table"><table><thead><tr><th>Source</th><th>Contacts</th><th>Issued</th><th>Claimed</th><th>Redeemed</th><th>Drop-off</th><th>Value</th></tr></thead><tbody><?php foreach (($conv['sources'] ?? []) as $row): ?><tr><td><?= mg_e((string)$row['label']) ?></td><td><?= mg_e((string)$row['contacts']) ?></td><td><?= mg_e((string)$row['issued']) ?></td><td><?= mg_e((string)$row['claimed']) ?></td><td><?= mg_e((string)$row['redeemed']) ?></td><td><?= mg_e((string)$row['drop_off_display']) ?></td><td><?= mg_e((string)$row['value']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
  <?php endif; ?>
</section>
