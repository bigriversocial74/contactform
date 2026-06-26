<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/market/merchant-market-explainer.php';
require_once dirname(__DIR__) . '/market/merchant-market-alerts.php';

$marketPdo = mg_db();
$marketUserId = (int)($user['id'] ?? 0);
$marketProfile = [];
$marketPayload = null;
$marketSnapshots = [];
$marketError = null;
$marketMessage = null;

function mg_account_market_chart_points(array $rows, string $key, int $width = 640, int $height = 210): string
{
    if (count($rows) < 2) return '';
    $values = array_map(static fn(array $row): float => (float)($row[$key] ?? 0), $rows);
    $max = max($values);
    $min = min($values);
    if ($max === $min) { $max += 1; $min -= 1; }
    $padX = 22;
    $padY = 24;
    $plotW = $width - ($padX * 2);
    $plotH = $height - ($padY * 2);
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
    $last = (string)($rows[count($rows) - 1]['snapshot_date'] ?? '');
    $gradientId = 'mgMarketFill' . substr(md5($key . count($rows)), 0, 8);
    return '<svg class="mg-market-line-chart" viewBox="0 0 640 210" role="img" aria-label="Market trend chart"><defs><linearGradient id="' . mg_e($gradientId) . '" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="currentColor" stop-opacity=".24"/><stop offset="100%" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs><g class="mg-market-grid-lines"><line x1="22" y1="40" x2="618" y2="40"/><line x1="22" y1="92" x2="618" y2="92"/><line x1="22" y1="144" x2="618" y2="144"/><line x1="22" y1="186" x2="618" y2="186"/></g><polyline points="22,186 ' . mg_e($points) . ' 618,186" fill="url(#' . mg_e($gradientId) . ')" stroke="none"/><polyline points="' . mg_e($points) . '" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><g class="mg-market-axis"><text x="22" y="204">' . mg_e($first) . '</text><text x="618" y="204" text-anchor="end">' . mg_e($last) . '</text></g></svg>';
}

function mg_account_market_signal_chart(array $components): string
{
    if (!$components) return '<div class="mg-market-empty-chart">No component data yet.</div>';
    $out = '<div class="mg-market-signal-chart">';
    foreach ($components as $row) {
        $score = (float)($row['score'] ?? 0);
        $pct = max(0, min(100, (abs($score) / 12) * 100));
        $out .= '<div class="mg-market-signal-row' . ($score < 0 ? ' is-negative' : '') . '"><span>' . mg_e((string)($row['component'] ?? 'Component')) . '</span><div><b style="width:' . mg_e((string)round($pct, 1)) . '%"></b></div><em>' . mg_e((string)$score) . '</em></div>';
    }
    return $out . '</div>';
}

function mg_account_market_next_actions(array $payload, array $movement): array
{
    $m = $payload['metrics'] ?? [];
    $actions = [];
    if (!empty($movement['recommended_action'])) $actions[] = (string)$movement['recommended_action'];
    if ((int)($m['active_campaigns']['raw'] ?? 0) < 1) $actions[] = 'Launch one simple action campaign tied to a QR, newsletter, contest, or referral entry point.';
    if ((int)($m['campaign_conversions']['raw'] ?? 0) < 10) $actions[] = 'Drive more campaign conversions. The model rewards contacts, issued rewards, claims, and redemptions.';
    if ((float)($m['campaign_redemption_rate']['raw'] ?? 0) < 20) $actions[] = 'Improve the redemption path. Make the offer easier to claim and easier for staff to verify.';
    if ((int)($m['distribution_channels']['raw'] ?? 0) < 1) $actions[] = 'Connect at least one distribution channel so demand is not limited to your own audience.';
    if (!$actions) $actions[] = 'Keep the campaign running and take another snapshot tomorrow to track movement.';
    return array_slice(array_values(array_unique($actions)), 0, 5);
}

function mg_account_market_action_cards(array $payload, array $movement): array
{
    $metrics = $payload['metrics'] ?? [];
    $risk = $payload['risk'] ?? [];
    $metric = static function (string $key) use ($metrics): float {
        $value = $metrics[$key]['raw'] ?? 0;
        return is_numeric($value) ? (float)$value : 0.0;
    };
    $cards = [
        ['priority'=>'High','icon'=>'↗','title'=>'Launch a QR reward campaign','body'=>'Drive action with targeted rewards and measure impact.','href'=>'/qr-reward.php','button'=>'Create QR reward'],
    ];
    if ($metric('distribution_channels') < 1) $cards[] = ['priority'=>'Medium','icon'=>'◎','title'=>'Connect a distribution channel','body'=>'Expand reach through channels, partners, events, or allocations.','href'=>'/commerce-operations.php','button'=>'Open connections'];
    if ($metric('stamp_inventory') < 10 || $metric('stamp_spend_30d') < 1) $cards[] = ['priority'=>'Medium','icon'=>'▣','title'=>'Add stamp inventory','body'=>'Make sure the next campaign has fuel for distribution.','href'=>'/wallet.php','button'=>'Open wallet'];
    if ($metric('post_interactions') < 5) $cards[] = ['priority'=>'Low','icon'=>'◔','title'=>'Post an engagement update','body'=>'Strengthen public market attention and customer confidence.','href'=>'/feed.php','button'=>'Open feed'];
    if ((int)($risk['opt_outs'] ?? 0) > 0 || (int)($risk['bad_signals'] ?? 0) > 0) $cards[] = ['priority'=>'High','icon'=>'!','title'=>'Review risk signals','body'=>'Clean low-quality campaign contacts before the next push.','href'=>'/account-market.php#risk','button'=>'Review risk'];
    if ((float)($movement['cards'][0]['raw'] ?? 0) <= 0) $cards[] = ['priority'=>'Low','icon'=>'↻','title'=>'Take a fresh snapshot','body'=>'Capture a new comparison point after one action is complete.','href'=>'/account-market.php','button'=>'Return to snapshot'];
    return array_slice($cards, 0, 4);
}

try {
    $stmt = $marketPdo->prepare("SELECT id,user_id,slug,display_name,status,visibility FROM public_profiles WHERE user_id=? AND status='active' AND visibility IN ('public','unlisted') ORDER BY updated_at DESC,id DESC LIMIT 1");
    $stmt->execute([$marketUserId]);
    $marketProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($marketProfile) {
        $marketPayload = mg_merchant_market_build($marketPdo, (string)$marketProfile['slug'], ['viewer_id' => $marketUserId]);
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
$marketAlerts = $marketPayload ? mg_market_alerts_build($marketPayload, $movement, $marketSnapshots) : [];
$marketActions = $marketPayload ? mg_account_market_action_cards($marketPayload, $movement) : [];
$chartRows = $marketSnapshots;
if (!$chartRows && !empty($marketPayload['series']['volume_30d'])) {
    foreach ($marketPayload['series']['volume_30d'] as $row) {
        $chartRows[] = ['snapshot_date'=>$row['date'] ?? '', 'ticker_value_cents'=>$row['value_cents'] ?? 0, 'merchant_score'=>$mm['merchant_score'] ?? 0, 'funnel_quality_score'=>$mm['campaign_funnel_quality'] ?? 0, 'risk_adjustment_cents'=>$mm['risk_adjustment_value_cents'] ?? 0];
    }
}
$secondaryMetrics = [
    ['label'=>'Campaign Conversions','value'=>$metrics['campaign_conversions']['display'] ?? '0','hint'=>'Contacts + events + issued + claims'],
    ['label'=>'Claim Rate','value'=>$metrics['campaign_claim_rate']['display'] ?? '—','hint'=>'Claimed or redeemed ÷ issued'],
    ['label'=>'Redemption Rate','value'=>$metrics['campaign_redemption_rate']['display'] ?? '—','hint'=>'Redeemed ÷ issued'],
    ['label'=>'Distribution Value','value'=>$mm['distribution_value'] ?? '$0','hint'=>'Programs, channels, events, locations'],
    ['label'=>'Stamp Inventory','value'=>$metrics['stamp_inventory']['display'] ?? '0','hint'=>'Available for campaigns'],
    ['label'=>'Follower Momentum','value'=>$metrics['follower_momentum']['display'] ?? '0','hint'=>'Net follows minus detected losses'],
    ['label'=>'Value per Follower','value'=>$metrics['value_per_follower']['display'] ?? '$0','hint'=>'Estimated brand value'],
];
?>
<section class="mg-app-panel mg-account-pane is-active mg-market-dashboard mg-investment-market" data-account-pane="market">
  <input class="mg-market-tab-radio" type="radio" name="mg_market_tab" id="mg-market-tab-overview" checked>
  <input class="mg-market-tab-radio" type="radio" name="mg_market_tab" id="mg-market-tab-signals">
  <input class="mg-market-tab-radio" type="radio" name="mg_market_tab" id="mg-market-tab-funnel">
  <input class="mg-market-tab-radio" type="radio" name="mg_market_tab" id="mg-market-tab-risk">
  <input class="mg-market-tab-radio" type="radio" name="mg_market_tab" id="mg-market-tab-alerts">
  <input class="mg-market-tab-radio" type="radio" name="mg_market_tab" id="mg-market-tab-actions">
  <input class="mg-market-tab-radio" type="radio" name="mg_market_tab" id="mg-market-tab-details">

  <div class="mg-market-topbar">
    <div><span class="mg-market-kicker">Market Dashboard</span><strong><?= mg_e((string)($marketProfile['display_name'] ?? 'Merchant market')) ?></strong></div>
    <div class="mg-market-topbar-tools"><span>Local demand model</span><span><?= $marketProfile ? 'Profile linked' : 'Profile needed' ?></span></div>
  </div>

  <nav class="mg-market-tabs" aria-label="Market dashboard sections">
    <label for="mg-market-tab-overview">Overview</label>
    <label for="mg-market-tab-signals">Market Signals</label>
    <label for="mg-market-tab-funnel">Funnel</label>
    <label for="mg-market-tab-risk">Risk</label>
    <label for="mg-market-tab-alerts">Alerts</label>
    <label for="mg-market-tab-actions">Actions</label>
    <label for="mg-market-tab-details">Details</label>
  </nav>

  <div class="mg-market-hero">
    <div>
      <span class="mg-market-kicker">Merchant Market Dashboard</span>
      <h2>Your local demand ticker</h2>
      <p>Track the value signal Microgifter can see: products, campaigns, funnel quality, redemptions, distribution, stamps, follower momentum, and more.</p>
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
    <div class="mg-market-tab-panels">
      <section class="mg-market-tab-panel mg-market-panel-overview">
        <div class="mg-market-primary-grid">
          <article class="mg-market-kpi-card is-featured"><span>Ticker Value</span><strong><?= mg_e((string)($mm['ticker_value'] ?? '$0')) ?></strong><em>▲ vs comparison point</em><small><?= mg_e((string)($mm['ticker_symbol'] ?? 'MGFT')) ?> · <?= mg_e((string)($mm['rating'] ?? 'No Market Signal')) ?> · confidence <?= mg_e((string)($mm['confidence'] ?? 'developing')) ?></small></article>
          <article class="mg-market-kpi-card"><span>Merchant Score</span><strong><?= mg_e((string)($mm['merchant_score'] ?? '0')) ?><small>/100</small></strong><div class="mg-market-score-track"><b style="width:<?= mg_e((string)max(0, min(100, (float)($mm['merchant_score'] ?? 0)))) ?>%"></b></div><small>Composite score out of 100</small></article>
          <article class="mg-market-kpi-card"><span>Funnel Quality</span><strong><?= mg_e((string)($mm['campaign_funnel_quality'] ?? '0')) ?></strong><small>Campaign claim and redemption signal</small></article>
          <article class="mg-market-kpi-card"><span>Risk Adjustment</span><strong><?= mg_e((string)($mm['risk_adjustment_value'] ?? '$0')) ?></strong><small>Risk-adjusted demand impact</small></article>
        </div>

        <div class="mg-market-mini-grid">
          <?php foreach ($secondaryMetrics as $metric): ?><article><span><?= mg_e((string)$metric['label']) ?></span><strong><?= mg_e((string)$metric['value']) ?></strong><small><?= mg_e((string)$metric['hint']) ?></small></article><?php endforeach; ?>
        </div>

        <div class="mg-market-dashboard-grid">
          <section class="mg-market-chart-card"><header><div><h3>Ticker Value History</h3><p>Stored market snapshots over time.</p></div><span class="mg-market-pill">30 Days</span></header><?= mg_account_market_chart($chartRows, 'ticker_value_cents', 'No ticker snapshots yet.') ?></section>
          <section class="mg-market-chart-card"><header><div><h3>Score Component Signals</h3><p>Contribution to merchant score.</p></div></header><?= mg_account_market_signal_chart($components) ?></section>
        </div>

        <div class="mg-market-insight-grid">
          <section class="mg-market-insight-card"><span class="mg-market-icon">✓</span><h3>Why this value moved</h3><p><?= mg_e((string)($movement['top_positive'] ?? 'Distribution value improved through programs, channels, events, and locations.')) ?></p><ul><li>Distribution value increased</li><li>Share reach expanded</li><li>Commerce volume is being watched</li></ul><label for="mg-market-tab-signals" class="mg-market-link-button">View market signals</label></section>
          <section class="mg-market-insight-card"><span class="mg-market-icon">◎</span><h3>Recommended next moves</h3><div class="mg-market-action-list"><?php foreach (mg_account_market_next_actions($marketPayload, $movement) as $i => $text): ?><p><b><?= $i + 1 ?></b><span><?= mg_e($text) ?></span></p><?php endforeach; ?></div></section>
        </div>
      </section>

      <section class="mg-market-tab-panel mg-market-panel-signals">
        <div class="mg-market-section-head"><span class="mg-market-kicker">Market Signals</span><h3>Score drivers and value movement</h3><p>This tab keeps the detailed market signal data separate from the overview.</p></div>
        <section class="mg-market-movement-card"><header><div><h3><?= mg_e((string)($movement['summary'] ?? 'Market movement is ready.')) ?></h3><p>Comparison point: <?= mg_e((string)($movement['baseline_date'] ?? 'No baseline')) ?> · Latest snapshot: <?= mg_e((string)($movement['latest_snapshot_date'] ?? 'No snapshot yet')) ?></p></div></header><div class="mg-market-movement-grid"><?php foreach (($movement['cards'] ?? []) as $card): ?><article class="<?= ((float)($card['raw'] ?? 0) < 0) ? 'is-negative' : (((float)($card['raw'] ?? 0) > 0) ? 'is-positive' : '') ?>"><span><?= mg_e((string)$card['label']) ?></span><strong><?= mg_e((string)$card['value']) ?></strong></article><?php endforeach; ?></div><div class="mg-market-driver-grid"><article><span>Top Positive Driver</span><p><?= mg_e((string)($movement['top_positive'] ?? 'No major positive movement detected yet.')) ?></p></article><article><span>Top Negative Driver</span><p><?= mg_e((string)($movement['top_negative'] ?? 'No major negative movement detected yet.')) ?></p></article><article class="is-wide"><span>Recommended Action</span><p><?= mg_e((string)($movement['recommended_action'] ?? 'Take a snapshot after the next campaign push.')) ?></p></article></div></section>
        <section class="mg-market-chart-card"><header><div><h3>Score Component Graph</h3><p>What is currently contributing to the market score.</p></div></header><?= mg_account_market_signal_chart($components) ?></section>
      </section>

      <section class="mg-market-tab-panel mg-market-panel-funnel">
        <div class="mg-market-section-head"><span class="mg-market-kicker">Campaign Funnel</span><h3>Conversion quality and source-level demand</h3><p>Claim, issue, redemption, and source data live here instead of lengthening the overview page.</p></div>
        <div class="mg-market-mini-grid"><?php foreach (array_slice($secondaryMetrics, 0, 4) as $metric): ?><article><span><?= mg_e((string)$metric['label']) ?></span><strong><?= mg_e((string)$metric['value']) ?></strong><small><?= mg_e((string)$metric['hint']) ?></small></article><?php endforeach; ?></div>
        <section class="mg-market-table-card"><header><h3>Campaign funnel detail</h3><p>Source-level conversion quality feeding the score.</p></header><div class="mg-market-funnel-table"><table><thead><tr><th>Source</th><th>Contacts</th><th>Issued</th><th>Claimed</th><th>Redeemed</th><th>Drop-off</th><th>Value</th></tr></thead><tbody><?php foreach (($conv['sources'] ?? []) as $row): ?><tr><td><?= mg_e((string)$row['label']) ?></td><td><?= mg_e((string)$row['contacts']) ?></td><td><?= mg_e((string)$row['issued']) ?></td><td><?= mg_e((string)$row['claimed']) ?></td><td><?= mg_e((string)$row['redeemed']) ?></td><td><?= mg_e((string)$row['drop_off_display']) ?></td><td><?= mg_e((string)$row['value']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
      </section>

      <section class="mg-market-tab-panel mg-market-panel-risk" id="risk">
        <div class="mg-market-section-head"><span class="mg-market-kicker">Risk</span><h3>Risk adjustment and negative pressure</h3><p>Risk is isolated so the dashboard can feel like an investment portal instead of a long operations log.</p></div>
        <div class="mg-market-primary-grid"><article class="mg-market-kpi-card"><span>Risk Adjustment</span><strong><?= mg_e((string)($mm['risk_adjustment_value'] ?? '$0')) ?></strong><small>Negative value impact from risk signals.</small></article><article class="mg-market-kpi-card"><span>What is pulling it down</span><p><?= mg_e((string)($movement['top_negative'] ?? 'No major negative movement detected yet.')) ?></p></article><article class="mg-market-kpi-card"><span>Funnel Quality</span><strong><?= mg_e((string)($mm['campaign_funnel_quality'] ?? '0')) ?></strong><small>Low redemption data can limit confidence.</small></article><article class="mg-market-kpi-card"><span>Action</span><p><?= mg_e((string)($movement['recommended_action'] ?? 'Take a snapshot after the next campaign push.')) ?></p></article></div>
        <section class="mg-market-chart-card"><header><div><h3>Risk Adjustment Trend</h3><p>Negative value impact from saved market snapshots.</p></div></header><?= mg_account_market_chart($chartRows, 'risk_adjustment_cents', 'No risk snapshots yet.') ?></section>
      </section>

      <section class="mg-market-tab-panel mg-market-panel-alerts">
        <div class="mg-market-section-head"><span class="mg-market-kicker">Market Alerts</span><h3>Signals that need attention</h3><p>Alerts are generated from snapshots, movement, campaign funnel quality, risk, distribution, and stamp activity.</p></div>
        <div class="mg-market-alert-grid"><?php foreach ($marketAlerts as $alert): ?><article class="mg-market-alert-card is-<?= mg_e((string)$alert['level']) ?>"><span><?= mg_e(ucfirst((string)$alert['level'])) ?></span><h4><?= mg_e((string)$alert['title']) ?></h4><p><?= mg_e((string)$alert['body']) ?></p><dl><div><dt>Why it matters</dt><dd><?= mg_e((string)$alert['why']) ?></dd></div><div><dt>Recommended action</dt><dd><?= mg_e((string)$alert['action']) ?></dd></div></dl><a class="mg-btn mg-btn-soft" href="<?= mg_e((string)$alert['href']) ?>">Open action</a></article><?php endforeach; ?></div>
      </section>

      <section class="mg-market-tab-panel mg-market-panel-actions">
        <div class="mg-market-section-head"><span class="mg-market-kicker">Action Center</span><h3>Turn the explanation into next steps</h3><p>These actions are generated from your current market score, funnel data, risk signals, distribution reach, stamp activity, and recent movement.</p></div>
        <div class="mg-market-action-card-grid"><?php foreach ($marketActions as $card): ?><article class="mg-market-action-card is-<?= mg_e(strtolower((string)$card['priority'])) ?>"><div class="mg-market-action-icon"><?= mg_e((string)$card['icon']) ?></div><span><?= mg_e((string)$card['priority']) ?></span><h4><?= mg_e((string)$card['title']) ?></h4><p><?= mg_e((string)$card['body']) ?></p><a class="mg-btn mg-btn-soft" href="<?= mg_e((string)$card['href']) ?>"><?= mg_e((string)$card['button']) ?></a></article><?php endforeach; ?></div>
      </section>

      <section class="mg-market-tab-panel mg-market-panel-details">
        <div class="mg-market-section-head"><span class="mg-market-kicker">Details</span><h3>Snapshot and trend details</h3><p>Historical charts are grouped here so the default view stays focused.</p></div>
        <div class="mg-market-dashboard-grid"><section class="mg-market-chart-card"><header><div><h3>Merchant Score</h3><p>Score trend from saved market snapshots.</p></div></header><?= mg_account_market_chart($chartRows, 'merchant_score', 'No score snapshots yet.') ?></section><section class="mg-market-chart-card"><header><div><h3>Funnel Quality</h3><p>Campaign funnel quality trend.</p></div></header><?= mg_account_market_chart($chartRows, 'funnel_quality_score', 'No funnel snapshots yet.') ?></section></div>
        <section class="mg-market-chart-card"><header><div><h3>Ticker Value History</h3><p>Stored market snapshots over time. Falls back to recent wallet volume until snapshots exist.</p></div></header><?= mg_account_market_chart($chartRows, 'ticker_value_cents', 'No ticker snapshots yet.') ?></section>
      </section>
    </div>
  <?php endif; ?>
</section>
