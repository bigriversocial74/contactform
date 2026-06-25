<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-market-explainer.php';

function mg_market_alert_metric(array $payload, string $key): float
{
    $value = $payload['metrics'][$key]['raw'] ?? 0;
    return is_numeric($value) ? (float)$value : 0.0;
}
function mg_market_alert_add(array &$alerts, string $key, string $level, string $title, string $body, string $why, string $action, string $href = '/account-market.php'): void
{
    $rank = ['critical'=>0,'warning'=>1,'opportunity'=>2,'info'=>3][$level] ?? 9;
    $alerts[$key] = ['key'=>$key,'level'=>$level,'rank'=>$rank,'title'=>$title,'body'=>$body,'why'=>$why,'action'=>$action,'href'=>$href];
}
function mg_market_alerts_build(array $payload, array $movement, array $snapshots): array
{
    $alerts = [];
    $today = date('Y-m-d');
    $latest = $snapshots ? $snapshots[count($snapshots) - 1] : [];
    $latestDate = (string)($latest['snapshot_date'] ?? '');
    $market = $payload['merchant_market'] ?? [];
    $cards = $movement['cards'] ?? [];
    $tickerDelta = (float)($cards[0]['raw'] ?? 0);
    $scoreDelta = (float)($cards[1]['raw'] ?? 0);
    $funnelDelta = (float)($cards[2]['raw'] ?? 0);
    $riskDelta = (float)($cards[3]['raw'] ?? 0);
    $score = (int)($market['merchant_score'] ?? 0);
    $ticker = (int)($market['ticker_value_cents'] ?? 0);
    $redemptionRate = mg_market_alert_metric($payload, 'campaign_redemption_rate');
    $distributionChannels = mg_market_alert_metric($payload, 'distribution_channels');
    $stampInventory = mg_market_alert_metric($payload, 'stamp_inventory');
    $campaignConversions = mg_market_alert_metric($payload, 'campaign_conversions');

    if ($latestDate !== $today) {
        mg_market_alert_add($alerts, 'no_snapshot_today', 'warning', 'No snapshot today', 'Today does not have a saved market snapshot yet.', 'Without today’s snapshot, the movement engine has less proof of what changed.', 'Open Market Dashboard and use Take Snapshot Now.', '/account-market.php');
    }
    if ($tickerDelta < 0) {
        mg_market_alert_add($alerts, 'ticker_dropped', abs($tickerDelta) > 10000 ? 'critical' : 'warning', 'Ticker value dropped', 'Ticker value moved down by ' . mg_market_explainer_delta((int)$tickerDelta, 'money') . '.', 'A falling ticker means demand, funnel, distribution, stamp, follower, or risk signals weakened against the comparison point.', 'Review the top negative driver and complete the highest priority Action Center card.', '/account-market.php');
    }
    if ($scoreDelta < 0) {
        mg_market_alert_add($alerts, 'score_dropped', $scoreDelta <= -8 ? 'critical' : 'warning', 'Merchant score dropped', 'Merchant score changed by ' . mg_market_explainer_delta($scoreDelta) . ' points.', 'A score drop means one or more market components weakened: campaigns, funnel quality, redemptions, distribution, stamps, followers, or risk.', 'Use the score component graph to find the weak spot.', '/account-market.php');
    }
    if ($riskDelta < 0) {
        mg_market_alert_add($alerts, 'risk_increased', 'warning', 'Risk increased', 'Risk adjustment worsened by ' . mg_market_explainer_delta((int)$riskDelta, 'money') . '.', 'Risk can come from opt-outs, bounces, complaints, expired/cancelled rewards, failed distribution, or follower loss.', 'Clean low-quality contacts and simplify any reward that is not being redeemed.', '/account-market.php');
    }
    if ($funnelDelta < 0) {
        mg_market_alert_add($alerts, 'funnel_fell', 'warning', 'Funnel quality fell', 'Campaign funnel quality changed by ' . mg_market_explainer_delta($funnelDelta) . '.', 'Funnel quality falls when contacts, issued rewards, claims, and redemptions stop moving together.', 'Simplify the offer or send a redemption reminder.', '/account-market.php');
    }
    if ($campaignConversions >= 25 && $tickerDelta >= 0) {
        mg_market_alert_add($alerts, 'conversions_jumped', 'opportunity', 'Campaign conversions are building', 'Campaign conversions are now at ' . number_format($campaignConversions) . '.', 'Conversions are one of the clearest signs of measurable demand.', 'Take a fresh snapshot after the campaign push and keep the offer active.', '/account-market.php');
    }
    if ($snapshots) {
        $maxTicker = max(array_map(static fn(array $row): int => (int)($row['ticker_value_cents'] ?? 0), $snapshots));
        if ($ticker > 0 && $ticker >= $maxTicker) {
            mg_market_alert_add($alerts, 'ticker_high', 'opportunity', 'Ticker value hit a high', 'Current ticker value is at or above the saved snapshot high.', 'This is a good time to capture proof, share the campaign, and keep the signal moving.', 'Take Snapshot Now and push the strongest offer again.', '/account-market.php');
        }
    }
    if ($redemptionRate > 0 && $redemptionRate < 20) {
        mg_market_alert_add($alerts, 'low_redemption', 'warning', 'Redemption rate is low', 'Campaign redemption rate is under 20%.', 'Claims without redemptions show interest, but not verified committed demand.', 'Make the reward easier to redeem and train staff on the verification path.', '/merchant-catalog-operations.php');
    }
    if ($distributionChannels < 1) {
        mg_market_alert_add($alerts, 'distribution_missing', 'info', 'Distribution is missing', 'No active distribution channels are visible to the market model.', 'Distribution reach helps the market model see demand beyond your own audience.', 'Connect one distribution channel for the next campaign.', '/commerce-operations.php');
    }
    if ($stampInventory < 10) {
        mg_market_alert_add($alerts, 'stamp_low', 'info', 'Stamp inventory is low', 'Available stamp inventory is below 10.', 'Stamps are campaign fuel. Low inventory can limit distribution and reward activity.', 'Add stamp inventory before the next campaign push.', '/wallet.php');
    }
    if (!$alerts) {
        mg_market_alert_add($alerts, 'market_stable', 'info', 'Market signals look stable', 'No urgent market alerts are active.', 'Stable signals mean the dashboard is ready for a new campaign push and a fresh comparison point.', 'Complete one Action Center card, then take a new snapshot.', '/account-market.php');
    }

    $list = array_values($alerts);
    usort($list, static fn(array $a, array $b): int => ($a['rank'] <=> $b['rank']) ?: strcmp($a['title'], $b['title']));
    return array_slice($list, 0, 8);
}
