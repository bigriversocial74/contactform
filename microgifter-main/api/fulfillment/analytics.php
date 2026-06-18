<?php
declare(strict_types=1);

require_once __DIR__ . '/_media.php';

mg_require_method('GET');
$user = mg_require_permission('fulfillment.analytics.view');
$pdo = mg_db();
$from = trim((string) ($_GET['from'] ?? date('Y-m-d', strtotime('-30 days'))));
$to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) mg_fail('Invalid analytics date range.', 422);

$engagementStmt = $pdo->prepare(
    'SELECT ced.metric_date, ced.event_type, SUM(ced.event_count) AS event_count,
            SUM(ced.unique_viewers) AS unique_viewers,
            SUM(ced.total_playback_ms) AS total_playback_ms
     FROM content_engagement_daily ced
     WHERE ced.merchant_user_id = ? AND ced.metric_date BETWEEN ? AND ?
     GROUP BY ced.metric_date, ced.event_type
     ORDER BY ced.metric_date ASC, ced.event_type ASC'
);
$engagementStmt->execute([(int) $user['id'], $from, $to]);

$downloadStmt = $pdo->prepare(
    'SELECT DATE(dae.occurred_at) AS metric_date, dae.event_type,
            COUNT(*) AS event_count, COALESCE(SUM(dae.bytes_served),0) AS bytes_served
     FROM digital_access_events dae
     INNER JOIN digital_entitlements de ON de.id = dae.entitlement_id
     INNER JOIN digital_fulfillment_rules dfr ON dfr.id = de.fulfillment_rule_id
     INNER JOIN catalog_product_versions cpv ON cpv.id = dfr.product_version_id
     INNER JOIN catalog_products cp ON cp.id = cpv.product_id
     WHERE cp.merchant_user_id = ? AND dae.occurred_at >= ? AND dae.occurred_at < DATE_ADD(?, INTERVAL 1 DAY)
     GROUP BY DATE(dae.occurred_at), dae.event_type
     ORDER BY metric_date ASC, dae.event_type ASC'
);
$downloadStmt->execute([(int) $user['id'], $from, $to]);

$summaryStmt = $pdo->prepare(
    'SELECT
        COUNT(DISTINCT de.id) AS entitlement_count,
        SUM(de.downloads_used) AS downloads_used,
        SUM(de.status = \'active\') AS active_entitlements,
        SUM(de.status = \'exhausted\') AS exhausted_entitlements,
        SUM(de.status = \'expired\') AS expired_entitlements
     FROM digital_entitlements de
     INNER JOIN digital_fulfillment_rules dfr ON dfr.id = de.fulfillment_rule_id
     INNER JOIN catalog_product_versions cpv ON cpv.id = dfr.product_version_id
     INNER JOIN catalog_products cp ON cp.id = cpv.product_id
     WHERE cp.merchant_user_id = ?'
);
$summaryStmt->execute([(int) $user['id']]);

$mediaStmt = $pdo->prepare(
    'SELECT
        COUNT(DISTINCT ca.id) AS asset_count,
        SUM(ca.asset_type = \'video\') AS video_assets,
        SUM(ca.asset_type = \'audio\') AS audio_assets,
        SUM(ca.moderation_status IN (\'quarantined\',\'blocked\',\'takedown\')) AS restricted_assets,
        SUM(cav.status = \'ready\') AS ready_variants,
        SUM(cav.status = \'failed\') AS failed_variants
     FROM catalog_assets ca
     LEFT JOIN catalog_asset_variants cav ON cav.source_asset_id = ca.id
     WHERE ca.owner_user_id = ?'
);
$mediaStmt->execute([(int) $user['id']]);

mg_ok([
    'range' => ['from' => $from, 'to' => $to],
    'engagement' => $engagementStmt->fetchAll(),
    'digital_access' => $downloadStmt->fetchAll(),
    'entitlements' => $summaryStmt->fetch() ?: [],
    'media' => $mediaStmt->fetch() ?: [],
]);
