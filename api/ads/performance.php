<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_ads.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$admin = isset($_GET['scope']) && $_GET['scope'] === 'admin';
if ($admin) mg_ads_require_admin_user($user);
else mg_ads_require_merchant_user($user, $pdo);

function mg_ads_rate(int $part, int $whole): float
{
    return $whole > 0 ? round(($part / $whole) * 100, 2) : 0.0;
}

function mg_ads_performance_row_defaults(): array
{
    return [
        'impression' => 0,
        'click' => 0,
        'claim' => 0,
        'wallet_save' => 0,
        'gift_send' => 0,
        'share' => 0,
        'redeem' => 0,
        'followup_created' => 0,
        'crm_contact_created' => 0,
    ];
}

function mg_ads_money(int|float $amount): float
{
    return round(((float)$amount) / 100, 2);
}

function mg_ads_attr_empty(): array
{
    return [
        'wallet_items' => 0,
        'claimed_items' => 0,
        'redeemed_items' => 0,
        'claimed_value_cents' => 0,
        'redeemed_value_cents' => 0,
        'unredeemed_future_demand_cents' => 0,
        'pre_sale_revenue_impact_cents' => 0,
        'direct_wallet_items' => 0,
        'campaign_assisted_wallet_items' => 0,
    ];
}

function mg_ads_attr_add(array $base, array $row): array
{
    foreach (['wallet_items','claimed_items','redeemed_items','claimed_value_cents','redeemed_value_cents','unredeemed_future_demand_cents','pre_sale_revenue_impact_cents','direct_wallet_items','campaign_assisted_wallet_items'] as $key) {
        $base[$key] = (int)($base[$key] ?? 0) + (int)($row[$key] ?? 0);
    }
    return $base;
}

function mg_ads_attr_public(array $attr, float $budget = 0.0): array
{
    $claimedValue = mg_ads_money((int)($attr['claimed_value_cents'] ?? 0));
    $redeemedValue = mg_ads_money((int)($attr['redeemed_value_cents'] ?? 0));
    $futureDemand = mg_ads_money((int)($attr['unredeemed_future_demand_cents'] ?? 0));
    $psrImpact = mg_ads_money((int)($attr['pre_sale_revenue_impact_cents'] ?? 0));
    $claimedItems = (int)($attr['claimed_items'] ?? 0);
    $redeemedItems = (int)($attr['redeemed_items'] ?? 0);
    return $attr + [
        'claimed_value' => $claimedValue,
        'redeemed_value' => $redeemedValue,
        'unredeemed_future_demand' => $futureDemand,
        'pre_sale_revenue_impact' => $psrImpact,
        'cost_per_claim' => $claimedItems > 0 && $budget > 0 ? round($budget / max(1, $claimedItems), 2) : null,
        'cost_per_redemption' => $redeemedItems > 0 && $budget > 0 ? round($budget / max(1, $redeemedItems), 2) : null,
    ];
}

function mg_ads_perf_scope_where(bool $admin, int $merchantId, string $publicId, array &$params): string
{
    $where = '1=1';
    if (!$admin) {
        $where .= ' AND c.merchant_id=?';
        $params[] = $merchantId;
    }
    if ($publicId !== '') {
        $where .= ' AND c.public_id=?';
        $params[] = $publicId;
    }
    return $where;
}

function mg_ads_perf_attribution(PDO $pdo, bool $admin, int $merchantId, string $publicId = ''): array
{
    $empty = [
        'summary' => mg_ads_attr_empty(),
        'campaigns' => [],
        'placements' => [],
        'source_breakdown' => [
            'direct' => mg_ads_attr_empty(),
            'campaign_assisted' => mg_ads_attr_empty(),
        ],
        'ready' => false,
        'method' => 'unavailable',
        'notes' => 'Wallet attribution is unavailable because wallet_items is not present in this database.',
    ];
    if (!mg_ads_table_exists($pdo, 'wallet_items')) return $empty;

    $params = [];
    $where = mg_ads_perf_scope_where($admin, $merchantId, $publicId, $params);
    $walletRows = [];
    $sourceSeen = [];

    $campaignSql = "SELECT c.public_id ad_public_id,c.title ad_title,c.merchant_id,c.budget_amount,c.campaign_id,wi.id wallet_item_id,wi.status wallet_status,wi.value_cents_snapshot,wi.claimed_at,wi.redeemed_at,wi.expires_at FROM ad_campaigns c INNER JOIN wallet_items wi ON wi.campaign_id=c.campaign_id WHERE {$where} AND c.status<>'archived' AND c.campaign_id IS NOT NULL";
    $campaignStmt = $pdo->prepare($campaignSql);
    $campaignStmt->execute($params);
    foreach ($campaignStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = 'campaign:' . (string)$row['ad_public_id'] . ':' . (int)$row['wallet_item_id'];
        if (isset($sourceSeen[$key])) continue;
        $sourceSeen[$key] = true;
        $row['attribution_source'] = 'campaign_assisted';
        $row['placement_key'] = 'campaign_assisted';
        $walletRows[] = $row;
    }

    $directSql = "SELECT c.public_id ad_public_id,c.title ad_title,c.merchant_id,c.budget_amount,c.campaign_id,COALESCE(e.placement_key,'unassigned') placement_key,wi.id wallet_item_id,wi.status wallet_status,wi.value_cents_snapshot,wi.claimed_at,wi.redeemed_at,wi.expires_at FROM ad_events e INNER JOIN ad_campaigns c ON c.id=e.ad_campaign_id INNER JOIN wallet_items wi ON wi.id=e.wallet_item_id WHERE {$where} AND e.wallet_item_id IS NOT NULL AND c.status<>'archived'";
    $directStmt = $pdo->prepare($directSql);
    $directStmt->execute($params);
    foreach ($directStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = 'direct:' . (string)$row['ad_public_id'] . ':' . (int)$row['wallet_item_id'];
        if (isset($sourceSeen[$key])) continue;
        $sourceSeen[$key] = true;
        $row['attribution_source'] = 'direct';
        $walletRows[] = $row;
    }

    $summary = mg_ads_attr_empty();
    $byCampaign = [];
    $byPlacement = [];
    $bySource = ['direct' => mg_ads_attr_empty(), 'campaign_assisted' => mg_ads_attr_empty()];

    foreach ($walletRows as $row) {
        $value = max(0, (int)($row['value_cents_snapshot'] ?? 0));
        $claimed = !empty($row['claimed_at']) || in_array((string)($row['wallet_status'] ?? ''), ['claimed','redeemed'], true);
        $redeemed = !empty($row['redeemed_at']) || (string)($row['wallet_status'] ?? '') === 'redeemed';
        $expired = (string)($row['wallet_status'] ?? '') === 'expired' || (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time() && !$redeemed);
        $rowAttr = mg_ads_attr_empty();
        $rowAttr['wallet_items'] = 1;
        if ($claimed) {
            $rowAttr['claimed_items'] = 1;
            $rowAttr['claimed_value_cents'] = $value;
            $rowAttr['pre_sale_revenue_impact_cents'] = $value;
        }
        if ($redeemed) {
            $rowAttr['redeemed_items'] = 1;
            $rowAttr['redeemed_value_cents'] = $value;
        }
        if ($claimed && !$redeemed && !$expired) {
            $rowAttr['unredeemed_future_demand_cents'] = $value;
        }
        $source = (string)($row['attribution_source'] ?? 'campaign_assisted');
        if ($source === 'direct') $rowAttr['direct_wallet_items'] = 1;
        else $rowAttr['campaign_assisted_wallet_items'] = 1;
        $summary = mg_ads_attr_add($summary, $rowAttr);
        $bySource[$source] = mg_ads_attr_add($bySource[$source] ?? mg_ads_attr_empty(), $rowAttr);
        $campaignKey = (string)($row['ad_public_id'] ?? 'unknown');
        if (!isset($byCampaign[$campaignKey])) {
            $byCampaign[$campaignKey] = mg_ads_attr_empty() + [
                'id' => $campaignKey,
                'title' => (string)($row['ad_title'] ?? 'Sponsored Campaign'),
                'merchant_id' => (int)($row['merchant_id'] ?? 0),
                'budget_amount' => (float)($row['budget_amount'] ?? 0),
            ];
        }
        $byCampaign[$campaignKey] = mg_ads_attr_add($byCampaign[$campaignKey], $rowAttr);
        $placementKey = (string)($row['placement_key'] ?? 'campaign_assisted');
        if (!isset($byPlacement[$placementKey])) {
            $byPlacement[$placementKey] = mg_ads_attr_empty() + ['placement_key' => $placementKey, 'surface' => mg_ads_surface_for_placement($placementKey)];
        }
        $byPlacement[$placementKey] = mg_ads_attr_add($byPlacement[$placementKey], $rowAttr);
    }

    $campaigns = [];
    foreach ($byCampaign as $row) {
        $campaigns[] = mg_ads_attr_public($row, (float)($row['budget_amount'] ?? 0));
    }
    usort($campaigns, static fn($a, $b) => ((int)($b['pre_sale_revenue_impact_cents'] ?? 0)) <=> ((int)($a['pre_sale_revenue_impact_cents'] ?? 0)));

    $placements = [];
    foreach ($byPlacement as $row) {
        $placements[] = mg_ads_attr_public($row, 0.0);
    }
    usort($placements, static fn($a, $b) => ((int)($b['pre_sale_revenue_impact_cents'] ?? 0)) <=> ((int)($a['pre_sale_revenue_impact_cents'] ?? 0)));

    return [
        'summary' => mg_ads_attr_public($summary, 0.0),
        'campaigns' => $campaigns,
        'placements' => $placements,
        'source_breakdown' => [
            'direct' => mg_ads_attr_public($bySource['direct'], 0.0),
            'campaign_assisted' => mg_ads_attr_public($bySource['campaign_assisted'], 0.0),
        ],
        'ready' => true,
        'method' => 'wallet_items direct references + campaign-assisted links',
        'notes' => 'Read-only attribution uses wallet_items.value_cents_snapshot, claimed_at, redeemed_at, expires_at, ad_events.wallet_item_id when present, and ad_campaigns.campaign_id for campaign-assisted value. No billing, wallet, claim, or redemption writes are performed.',
    ];
}

function mg_ads_perf_payload(PDO $pdo, bool $admin, int $merchantId, string $publicId = ''): array
{
    mg_ads_require_schema($pdo);
    mg_ads_seed_placements($pdo);

    $params = [];
    $where = mg_ads_perf_scope_where($admin, $merchantId, $publicId, $params);
    $eventDefaults = mg_ads_performance_row_defaults();
    $events = $eventDefaults;

    $stmt = $pdo->prepare("SELECT e.event_type, COUNT(*) total FROM ad_events e INNER JOIN ad_campaigns c ON c.id=e.ad_campaign_id WHERE {$where} GROUP BY e.event_type");
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $type = (string)$row['event_type'];
        if (array_key_exists($type, $events)) $events[$type] = (int)$row['total'];
    }

    $campaignStmt = $pdo->prepare("SELECT COUNT(*) total, SUM(c.status IN ('approved','active')) active_total, SUM(c.status='pending_review') pending_total, COALESCE(SUM(c.budget_amount),0) budget_total FROM ad_campaigns c WHERE {$where} AND c.status<>'archived'");
    $campaignStmt->execute($params);
    $campaignSummary = $campaignStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $budget = (float)($campaignSummary['budget_total'] ?? 0);
    $attribution = mg_ads_perf_attribution($pdo, $admin, $merchantId, $publicId);
    $attrSummary = is_array($attribution['summary'] ?? null) ? $attribution['summary'] : mg_ads_attr_public(mg_ads_attr_empty(), 0.0);
    $claimedItems = (int)($attrSummary['claimed_items'] ?? 0);
    $redeemedItems = (int)($attrSummary['redeemed_items'] ?? 0);

    $summary = [
        'campaigns_total' => (int)($campaignSummary['total'] ?? 0),
        'active_campaigns' => (int)($campaignSummary['active_total'] ?? 0),
        'pending_campaigns' => (int)($campaignSummary['pending_total'] ?? 0),
        'impressions' => $events['impression'],
        'clicks' => $events['click'],
        'ctr' => mg_ads_rate($events['click'], $events['impression']),
        'claims' => $events['claim'],
        'wallet_saves' => $events['wallet_save'],
        'gift_sends' => $events['gift_send'],
        'shares' => $events['share'],
        'redemptions' => $events['redeem'],
        'conversion_rate' => mg_ads_rate($events['redeem'], max(1, $events['click'])),
        'claim_rate' => mg_ads_rate($events['claim'], max(1, $events['click'])),
        'save_rate' => mg_ads_rate($events['wallet_save'], max(1, $events['click'])),
        'crm_contacts_created' => $events['crm_contact_created'],
        'followups_created' => $events['followup_created'],
        'cost_per_claim' => $claimedItems > 0 && $budget > 0 ? round($budget / max(1, $claimedItems), 2) : ($events['claim'] > 0 && $budget > 0 ? round($budget / max(1, $events['claim']), 2) : null),
        'cost_per_redemption' => $redeemedItems > 0 && $budget > 0 ? round($budget / max(1, $redeemedItems), 2) : ($events['redeem'] > 0 && $budget > 0 ? round($budget / max(1, $events['redeem']), 2) : null),
        'claimed_value' => (float)($attrSummary['claimed_value'] ?? 0),
        'redeemed_value' => (float)($attrSummary['redeemed_value'] ?? 0),
        'unredeemed_future_demand' => (float)($attrSummary['unredeemed_future_demand'] ?? 0),
        'pre_sale_revenue_impact' => (float)($attrSummary['pre_sale_revenue_impact'] ?? 0),
        'attributed_wallet_items' => (int)($attrSummary['wallet_items'] ?? 0),
        'attributed_claimed_items' => $claimedItems,
        'attributed_redeemed_items' => $redeemedItems,
        'direct_wallet_items' => (int)($attrSummary['direct_wallet_items'] ?? 0),
        'campaign_assisted_wallet_items' => (int)($attrSummary['campaign_assisted_wallet_items'] ?? 0),
        'event_counts' => $events,
    ];

    $placementStmt = $pdo->prepare("SELECT COALESCE(e.placement_key,'unassigned') placement_key, e.event_type, COUNT(*) total FROM ad_events e INNER JOIN ad_campaigns c ON c.id=e.ad_campaign_id WHERE {$where} GROUP BY COALESCE(e.placement_key,'unassigned'), e.event_type ORDER BY placement_key ASC");
    $placementStmt->execute($params);
    $placementRows = [];
    foreach ($placementStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (string)$row['placement_key'];
        if (!isset($placementRows[$key])) $placementRows[$key] = $eventDefaults;
        $type = (string)$row['event_type'];
        if (array_key_exists($type, $placementRows[$key])) $placementRows[$key][$type] = (int)$row['total'];
    }
    $placementAttr = [];
    foreach (($attribution['placements'] ?? []) as $attrPlacement) {
        $placementAttr[(string)($attrPlacement['placement_key'] ?? '')] = $attrPlacement;
    }
    $placements = [];
    foreach ($placementRows as $key => $counts) {
        $attr = $placementAttr[$key] ?? [];
        $placements[] = [
            'placement_key' => $key,
            'surface' => mg_ads_surface_for_placement($key),
            'impressions' => $counts['impression'],
            'clicks' => $counts['click'],
            'ctr' => mg_ads_rate($counts['click'], $counts['impression']),
            'claims' => $counts['claim'],
            'wallet_saves' => $counts['wallet_save'],
            'redemptions' => $counts['redeem'],
            'conversion_rate' => mg_ads_rate($counts['redeem'], max(1, $counts['click'])),
            'claimed_value' => (float)($attr['claimed_value'] ?? 0),
            'redeemed_value' => (float)($attr['redeemed_value'] ?? 0),
            'unredeemed_future_demand' => (float)($attr['unredeemed_future_demand'] ?? 0),
            'pre_sale_revenue_impact' => (float)($attr['pre_sale_revenue_impact'] ?? 0),
            'event_counts' => $counts,
        ];
    }
    foreach ($placementAttr as $key => $attr) {
        if (isset($placementRows[$key])) continue;
        $placements[] = $attr + [
            'impressions' => 0,
            'clicks' => 0,
            'ctr' => 0,
            'claims' => 0,
            'wallet_saves' => 0,
            'redemptions' => 0,
            'conversion_rate' => 0,
        ];
    }

    $campaignSql = "SELECT c.id,c.public_id,c.title,c.status,c.objective,c.merchant_id,c.budget_amount,cr.headline,COALESCE(SUM(e.event_type='impression'),0) impressions,COALESCE(SUM(e.event_type='click'),0) clicks,COALESCE(SUM(e.event_type='claim'),0) claims,COALESCE(SUM(e.event_type='wallet_save'),0) wallet_saves,COALESCE(SUM(e.event_type='redeem'),0) redemptions,COALESCE(SUM(e.event_type='crm_contact_created'),0) crm_contacts_created FROM ad_campaigns c LEFT JOIN ad_creatives cr ON cr.ad_campaign_id=c.id LEFT JOIN ad_events e ON e.ad_campaign_id=c.id WHERE {$where} AND c.status<>'archived' GROUP BY c.id,c.public_id,c.title,c.status,c.objective,c.merchant_id,c.budget_amount,cr.headline ORDER BY impressions DESC, clicks DESC, c.updated_at DESC LIMIT 100";
    $campaignPerfStmt = $pdo->prepare($campaignSql);
    $campaignPerfStmt->execute($params);
    $campaignAttr = [];
    foreach (($attribution['campaigns'] ?? []) as $attrCampaign) {
        $campaignAttr[(string)($attrCampaign['id'] ?? '')] = $attrCampaign;
    }
    $campaigns = [];
    foreach ($campaignPerfStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $impressions = (int)$row['impressions'];
        $clicks = (int)$row['clicks'];
        $redemptions = (int)$row['redemptions'];
        $profile = mg_ads_merchant_profile($pdo, (int)$row['merchant_id']);
        $attr = $campaignAttr[(string)$row['public_id']] ?? [];
        $campaigns[] = [
            'id' => (string)$row['public_id'],
            'title' => (string)$row['title'],
            'headline' => (string)($row['headline'] ?? ''),
            'status' => (string)$row['status'],
            'objective' => (string)$row['objective'],
            'merchant_id' => (int)$row['merchant_id'],
            'merchant_name' => (string)($profile['merchant_name'] ?? 'Microgifter Merchant'),
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => mg_ads_rate($clicks, $impressions),
            'claims' => (int)$row['claims'],
            'wallet_saves' => (int)$row['wallet_saves'],
            'redemptions' => $redemptions,
            'conversion_rate' => mg_ads_rate($redemptions, max(1, $clicks)),
            'crm_contacts_created' => (int)$row['crm_contacts_created'],
            'claimed_value' => (float)($attr['claimed_value'] ?? 0),
            'redeemed_value' => (float)($attr['redeemed_value'] ?? 0),
            'unredeemed_future_demand' => (float)($attr['unredeemed_future_demand'] ?? 0),
            'pre_sale_revenue_impact' => (float)($attr['pre_sale_revenue_impact'] ?? 0),
            'attributed_wallet_items' => (int)($attr['wallet_items'] ?? 0),
            'attributed_claimed_items' => (int)($attr['claimed_items'] ?? 0),
            'attributed_redeemed_items' => (int)($attr['redeemed_items'] ?? 0),
            'cost_per_claim' => $attr['cost_per_claim'] ?? null,
            'cost_per_redemption' => $attr['cost_per_redemption'] ?? null,
        ];
    }

    $funnel = [
        ['label' => 'Impressions', 'value' => $events['impression'], 'rate' => 100],
        ['label' => 'Clicks', 'value' => $events['click'], 'rate' => mg_ads_rate($events['click'], $events['impression'])],
        ['label' => 'Wallet Saves', 'value' => $events['wallet_save'], 'rate' => mg_ads_rate($events['wallet_save'], max(1, $events['click']))],
        ['label' => 'Claims', 'value' => max($events['claim'], $claimedItems), 'rate' => mg_ads_rate(max($events['claim'], $claimedItems), max(1, $events['click']))],
        ['label' => 'Redemptions', 'value' => max($events['redeem'], $redeemedItems), 'rate' => mg_ads_rate(max($events['redeem'], $redeemedItems), max(1, max($events['claim'], $claimedItems)))],
        ['label' => 'CRM Contacts', 'value' => $events['crm_contact_created'], 'rate' => mg_ads_rate($events['crm_contact_created'], max(1, $events['click']))],
    ];

    return [
        'summary' => $summary,
        'funnel' => $funnel,
        'placements' => $placements,
        'campaigns' => $campaigns,
        'attribution' => $attribution,
        'notes' => $attribution['notes'] ?? 'Read-only Campaign Ads value attribution is active when wallet_items and linked campaign/wallet references are available.',
    ];
}

try {
    $schema = mg_ads_schema_status($pdo);
    if (!$schema['ready']) {
        mg_ok(['schema_ready' => false, 'tables' => $schema['tables'], 'performance' => null], 'Campaign Ads Manager migration is required.');
    }
    $publicId = mg_ads_text($_GET['ad_campaign_id'] ?? $_GET['public_id'] ?? '', 80, '');
    $performance = mg_ads_perf_payload($pdo, $admin, (int)$user['id'], $publicId);
    mg_ok(['schema_ready' => true, 'performance' => $performance], 'Ad performance loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'ads.performance_failed', 'Campaign Ads Manager performance failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail($error->getMessage(), 422);
}
