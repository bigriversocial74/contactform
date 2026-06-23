<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$days = min(180, max(7, (int) ($_GET['days'] ?? 30)));
$multiplier = min(5.0, max(0.5, (float) ($_GET['multiplier'] ?? 1.5)));

try {
    $summary = $pdo->prepare('SELECT COUNT(DISTINCT c.id) campaigns, COUNT(DISTINCT CASE WHEN c.status = \'active\' THEN c.id END) active_campaigns, COUNT(DISTINCT cc.id) contacts, COUNT(DISTINCT wi.id) wallet_items, COUNT(DISTINCT CASE WHEN wi.status = \'claimed\' THEN wi.id END) claimed, COUNT(DISTINCT CASE WHEN wi.status = \'redeemed\' THEN wi.id END) completed, COALESCE(SUM(CASE WHEN wi.status = \'redeemed\' THEN wi.value_cents_snapshot ELSE 0 END),0) completed_value_cents FROM campaigns c LEFT JOIN campaign_contacts cc ON cc.campaign_id = c.id LEFT JOIN wallet_items wi ON wi.campaign_id = c.id WHERE c.merchant_user_id = ?');
    $summary->execute([$merchantId]);
    $s = $summary->fetch() ?: [];

    $events = $pdo->prepare('SELECT COUNT(DISTINCT CASE WHEN event_type = \'wallet_item.claimed\' THEN wallet_item_id END) window_claimed, COUNT(DISTINCT CASE WHEN event_type = \'wallet_item.redeemed\' THEN wallet_item_id END) window_completed, COUNT(DISTINCT CASE WHEN event_type = \'agent_offer.added_to_wallet\' THEN wallet_item_id END) agent_adds FROM campaign_events WHERE merchant_user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)');
    $events->execute([$merchantId]);
    $e = $events->fetch() ?: [];

    $top = $pdo->prepare('SELECT c.public_id,c.title,c.campaign_type,c.status,COUNT(DISTINCT cc.id) contacts,COUNT(DISTINCT wi.id) wallet_items,COUNT(DISTINCT CASE WHEN wi.status = \'claimed\' THEN wi.id END) claimed,COUNT(DISTINCT CASE WHEN wi.status = \'redeemed\' THEN wi.id END) completed,COALESCE(SUM(CASE WHEN wi.status = \'redeemed\' THEN wi.value_cents_snapshot ELSE 0 END),0) completed_value_cents FROM campaigns c LEFT JOIN campaign_contacts cc ON cc.campaign_id = c.id LEFT JOIN wallet_items wi ON wi.campaign_id = c.id WHERE c.merchant_user_id = ? GROUP BY c.id ORDER BY completed DESC, claimed DESC, contacts DESC, c.updated_at DESC LIMIT 10');
    $top->execute([$merchantId]);
    $campaigns = [];
    foreach ($top->fetchAll() as $row) {
        $contacts = (int) $row['contacts'];
        $claimed = (int) $row['claimed'];
        $completed = (int) $row['completed'];
        $avg = $completed > 0 ? ((int) $row['completed_value_cents'] / $completed) : 0;
        $campaigns[] = [
            'id' => (string) $row['public_id'],
            'title' => (string) $row['title'],
            'campaign_type' => (string) $row['campaign_type'],
            'status' => (string) $row['status'],
            'contacts' => $contacts,
            'wallet_items' => (int) $row['wallet_items'],
            'claimed' => $claimed,
            'completed' => $completed,
            'claim_rate' => $contacts > 0 ? round($claimed / $contacts, 3) : 0,
            'completion_rate' => $claimed > 0 ? round($completed / $claimed, 3) : 0,
            'projected_value_cents' => (int) round($avg * $completed * $multiplier),
        ];
    }

    $wallets = (int) ($s['wallet_items'] ?? 0);
    $claimedTotal = (int) ($s['claimed'] ?? 0);
    $completedTotal = (int) ($s['completed'] ?? 0);
    $windowCompleted = (int) ($e['window_completed'] ?? 0);
    $avgTotal = $completedTotal > 0 ? ((int) ($s['completed_value_cents'] ?? 0) / $completedTotal) : 0;
    $projected30 = (int) round(($windowCompleted / $days) * 30);

    mg_ok(['insights' => [
        'days' => $days,
        'multiplier' => $multiplier,
        'campaigns' => (int) ($s['campaigns'] ?? 0),
        'active_campaigns' => (int) ($s['active_campaigns'] ?? 0),
        'contacts' => (int) ($s['contacts'] ?? 0),
        'wallet_items' => $wallets,
        'claimed' => $claimedTotal,
        'completed' => $completedTotal,
        'claim_rate' => $wallets > 0 ? round($claimedTotal / $wallets, 3) : 0,
        'completion_rate' => $claimedTotal > 0 ? round($completedTotal / $claimedTotal, 3) : 0,
        'projected_30d_completions' => $projected30,
        'projected_30d_value_cents' => (int) round($projected30 * $avgTotal * $multiplier),
        'agent_wallet_adds' => (int) ($e['agent_adds'] ?? 0),
        'top_campaigns' => $campaigns,
    ], 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_insights.unavailable', 'Campaign insights unavailable.', ['exception_class' => $error::class], $merchantId);
    mg_ok(['insights' => null, 'schema_ready' => false], 'Campaign insights unavailable until the Stage 12 schema is installed.');
}
