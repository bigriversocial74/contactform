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
        'cost_per_claim' => $events['claim'] > 0 ? round($budget / max(1, $events['claim']), 2) : null,
        'cost_per_redemption' => $events['redeem'] > 0 ? round($budget / max(1, $events['redeem']), 2) : null,
        'claimed_value' => 0,
        'redeemed_value' => 0,
        'unredeemed_future_demand' => 0,
        'pre_sale_revenue_impact' => 0,
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
    $placements = [];
    foreach ($placementRows as $key => $counts) {
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
            'event_counts' => $counts,
        ];
    }

    $campaignSql = "SELECT c.id,c.public_id,c.title,c.status,c.objective,c.merchant_id,cr.headline,COALESCE(SUM(e.event_type='impression'),0) impressions,COALESCE(SUM(e.event_type='click'),0) clicks,COALESCE(SUM(e.event_type='claim'),0) claims,COALESCE(SUM(e.event_type='wallet_save'),0) wallet_saves,COALESCE(SUM(e.event_type='redeem'),0) redemptions,COALESCE(SUM(e.event_type='crm_contact_created'),0) crm_contacts_created FROM ad_campaigns c LEFT JOIN ad_creatives cr ON cr.ad_campaign_id=c.id LEFT JOIN ad_events e ON e.ad_campaign_id=c.id WHERE {$where} AND c.status<>'archived' GROUP BY c.id,c.public_id,c.title,c.status,c.objective,c.merchant_id,cr.headline ORDER BY impressions DESC, clicks DESC, c.updated_at DESC LIMIT 100";
    $campaignPerfStmt = $pdo->prepare($campaignSql);
    $campaignPerfStmt->execute($params);
    $campaigns = [];
    foreach ($campaignPerfStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $impressions = (int)$row['impressions'];
        $clicks = (int)$row['clicks'];
        $redemptions = (int)$row['redemptions'];
        $profile = mg_ads_merchant_profile($pdo, (int)$row['merchant_id']);
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
        ];
    }

    $funnel = [
        ['label' => 'Impressions', 'value' => $events['impression'], 'rate' => 100],
        ['label' => 'Clicks', 'value' => $events['click'], 'rate' => mg_ads_rate($events['click'], $events['impression'])],
        ['label' => 'Wallet Saves', 'value' => $events['wallet_save'], 'rate' => mg_ads_rate($events['wallet_save'], max(1, $events['click']))],
        ['label' => 'Claims', 'value' => $events['claim'], 'rate' => mg_ads_rate($events['claim'], max(1, $events['click']))],
        ['label' => 'Redemptions', 'value' => $events['redeem'], 'rate' => mg_ads_rate($events['redeem'], max(1, $events['claim']))],
        ['label' => 'CRM Contacts', 'value' => $events['crm_contact_created'], 'rate' => mg_ads_rate($events['crm_contact_created'], max(1, $events['click']))],
    ];

    return [
        'summary' => $summary,
        'funnel' => $funnel,
        'placements' => $placements,
        'campaigns' => $campaigns,
        'notes' => 'Value attribution fields are reserved for wallet, claim, redemption, and paid product integration. They return zero until live value sources are wired.',
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
