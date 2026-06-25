<?php
declare(strict_types=1);

function mg_market_action_center_metric_raw(array $payload, string $key): float
{
    $value = $payload['metrics'][$key]['raw'] ?? 0;
    return is_numeric($value) ? (float)$value : 0.0;
}
function mg_market_action_center_cards(array $payload, array $movement): array
{
    $metrics = $payload['metrics'] ?? [];
    $market = $payload['merchant_market'] ?? [];
    $risk = $payload['risk'] ?? [];
    $actions = [];
    $add = static function (array $card) use (&$actions): void {
        $actions[$card['key']] = $card;
    };

    if (mg_market_action_center_metric_raw($payload, 'active_campaigns') < 1 || mg_market_action_center_metric_raw($payload, 'campaign_conversions') < 10) {
        $add([
            'key'=>'launch_qr_campaign','priority'=>'High','title'=>'Launch a QR reward campaign','why'=>'The market model rewards measurable entry points: QR scans, issued rewards, claims, and redemptions. A simple QR campaign creates clean demand data quickly.','score'=>'Medium to high score lift through campaign velocity and funnel quality.','ticker'=>'Adds campaign conversion value and can raise ticker confidence once claims or redemptions happen.','next'=>'Create a short, easy-to-redeem QR reward tied to one product or in-store offer.','href'=>'/qr-reward.php','button'=>'Create QR reward',
        ]);
    }
    if ((float)($metrics['campaign_redemption_rate']['raw'] ?? 0) < 20 && mg_market_action_center_metric_raw($payload, 'campaign_conversions') > 0) {
        $add([
            'key'=>'improve_redemption_path','priority'=>'High','title'=>'Increase redemptions with a simpler offer','why'=>'Claims are useful, but redeemed rewards create the strongest proof of committed demand. If redemptions lag, funnel quality and confidence stay limited.','score'=>'Improves redemption quality, funnel quality, and risk confidence.','ticker'=>'Can increase ticker value by converting issued demand into verified demand.','next'=>'Make the reward easier to understand, reduce staff friction, and keep the redeem process obvious.','href'=>'/merchant-catalog-operations.php','button'=>'Review offers',
        ]);
    }
    if ((int)($risk['opt_outs'] ?? 0) > 0 || (int)($risk['bad_signals'] ?? 0) > 0) {
        $add([
            'key'=>'clean_contacts','priority'=>'High','title'=>'Clean up low-quality campaign contacts','why'=>'Opt-outs, bounces, and complaints lower market quality. The model treats these as weak or negative demand signals.','score'=>'Reduces risk penalty and protects funnel quality.','ticker'=>'Stops avoidable value deductions from risk adjustment.','next'=>'Remove bounced or complained contacts before sending another campaign.','href'=>'/account-market.php','button'=>'Review risk signals',
        ]);
    }
    if (mg_market_action_center_metric_raw($payload, 'distribution_channels') < 1) {
        $add([
            'key'=>'connect_distribution','priority'=>'Medium','title'=>'Connect a distribution channel','why'=>'Distribution shows that demand can come from more than your own audience. Channels, events, and allocations expand market reach.','score'=>'Adds distribution reach and can improve campaign velocity.','ticker'=>'Adds distribution value and creates more paths to claims and redemptions.','next'=>'Connect one feed, partner source, or direct distribution path for your next campaign.','href'=>'/commerce-operations.php','button'=>'Open operations',
        ]);
    }
    if (mg_market_action_center_metric_raw($payload, 'stamp_inventory') < 10 && mg_market_action_center_metric_raw($payload, 'stamp_spend_30d') < 1) {
        $add([
            'key'=>'add_stamp_inventory','priority'=>'Medium','title'=>'Add stamp inventory','why'=>'Stamp inventory and spend show the platform has fuel to distribute rewards. Without stamps, campaigns have less measurable push behind them.','score'=>'Improves stamp power and campaign readiness.','ticker'=>'Adds stamp inventory/spend value and supports future campaign conversion value.','next'=>'Add enough stamp inventory to support the next campaign push.','href'=>'/wallet.php','button'=>'Open wallet',
        ]);
    }
    if (mg_market_action_center_metric_raw($payload, 'active_drops') < 2) {
        $add([
            'key'=>'publish_product_drop','priority'=>'Medium','title'=>'Publish one more product or drop','why'=>'The score improves when the market can see a deeper catalog of redeemable local value. Products give campaigns something concrete to point at.','score'=>'Improves product depth and commerce signal.','ticker'=>'Adds product value and strengthens future demand value.','next'=>'Publish one simple offer, product, or local experience that can be redeemed easily.','href'=>'/merchant-catalog-operations.php','button'=>'Open catalog',
        ]);
    }
    if (mg_market_action_center_metric_raw($payload, 'post_interactions') < 5) {
        $add([
            'key'=>'post_engagement_update','priority'=>'Low','title'=>'Post an engagement update','why'=>'Public engagement helps prove the merchant has active attention, not just inventory. Posts, reactions, comments, saves, and shares contribute to market signal.','score'=>'Improves engagement signal when people interact.','ticker'=>'Adds brand confidence and can increase follower value over time.','next'=>'Post one update tied to the active offer and ask customers to claim or share it.','href'=>'/feed.php','button'=>'Open feed',
        ]);
    }

    $movementRaw = (float)($movement['cards'][0]['raw'] ?? 0);
    if ($movementRaw <= 0) {
        $add([
            'key'=>'snapshot_after_action','priority'=>'Low','title'=>'Take another snapshot after action is complete','why'=>'Snapshots make the dashboard explain what changed. Without new snapshots, movement is harder to prove.','score'=>'No direct score lift, but improves measurement and trend confidence.','ticker'=>'Creates the comparison point needed to prove ticker movement.','next'=>'Complete one action above, then use Take Snapshot Now to capture the new state.','href'=>'/account-market.php','button'=>'Return to snapshot',
        ]);
    }

    if (!$actions) {
        $add([
            'key'=>'keep_compounding','priority'=>'Next','title'=>'Keep compounding the current signal','why'=>'Your market model has enough active signals to keep measuring movement. The next win is consistency.','score'=>'Protects score momentum by keeping campaign, distribution, and redemption data fresh.','ticker'=>'Keeps ticker value from going stale and gives the model new comparison points.','next'=>'Run one focused campaign push, then take a fresh snapshot tomorrow.','href'=>'/account-market.php','button'=>'Back to market',
        ]);
    }

    $priorityOrder = ['High'=>0,'Medium'=>1,'Low'=>2,'Next'=>3];
    usort($actions, static fn(array $a, array $b): int => ($priorityOrder[$a['priority']] ?? 9) <=> ($priorityOrder[$b['priority']] ?? 9));
    return array_slice(array_values($actions), 0, 8);
}

$marketActionCards = mg_market_action_center_cards($marketPayload ?? [], $movement ?? []);
?>
<section class="mg-market-action-center">
  <header>
    <div>
      <span class="mg-market-kicker">Action Center</span>
      <h3>Turn the explanation into next steps</h3>
      <p>These actions are generated from your current market score, funnel data, risk signals, distribution reach, stamp activity, and recent movement.</p>
    </div>
  </header>
  <div class="mg-market-action-card-grid">
    <?php foreach ($marketActionCards as $card): ?>
      <article class="mg-market-action-card is-<?= mg_e(strtolower((string)$card['priority'])) ?>">
        <div class="mg-market-action-card-head">
          <span><?= mg_e((string)$card['priority']) ?></span>
          <h4><?= mg_e((string)$card['title']) ?></h4>
        </div>
        <dl>
          <div><dt>Why this matters</dt><dd><?= mg_e((string)$card['why']) ?></dd></div>
          <div><dt>Expected score impact</dt><dd><?= mg_e((string)$card['score']) ?></dd></div>
          <div><dt>Expected ticker impact</dt><dd><?= mg_e((string)$card['ticker']) ?></dd></div>
          <div><dt>Suggested next step</dt><dd><?= mg_e((string)$card['next']) ?></dd></div>
        </dl>
        <a class="mg-btn mg-btn-soft" href="<?= mg_e((string)$card['href']) ?>"><?= mg_e((string)$card['button']) ?></a>
      </article>
    <?php endforeach; ?>
  </div>
</section>
