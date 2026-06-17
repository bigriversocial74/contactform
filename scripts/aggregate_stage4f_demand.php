<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/db.php';

$date = $argv[1] ?? date('Y-m-d', strtotime('-1 day'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    fwrite(STDERR, "Usage: php scripts/aggregate_stage4f_demand.php YYYY-MM-DD\n");
    exit(1);
}

$pdo = mg_db();

try {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM demand_fact_daily WHERE metric_date = ?')->execute([$date]);

    $sql = <<<'SQL'
INSERT INTO demand_fact_daily (
    metric_date,
    merchant_user_id,
    product_id,
    program_id,
    source_type,
    impressions,
    opens,
    media_starts,
    media_completions,
    cta_clicks,
    claim_opens,
    claims,
    redemptions,
    items_issued,
    issued_value_cents,
    unique_recipients,
    unique_viewers,
    avg_time_to_claim_seconds,
    avg_time_to_redeem_seconds,
    created_at,
    updated_at
)
SELECT
    ?,
    fp.merchant_user_id,
    fp.catalog_product_id,
    dp.id,
    COALESCE(dse.source_type, 'unknown'),
    COALESCE(SUM(cee.event_type = 'impression'), 0),
    COALESCE(SUM(cee.event_type = 'open'), 0),
    COALESCE(SUM(cee.event_type = 'play'), 0),
    COALESCE(SUM(cee.event_type = 'complete'), 0),
    COALESCE(SUM(cee.event_type = 'cta_click'), 0),
    COALESCE(SUM(cee.event_type = 'claim_open'), 0),
    COUNT(DISTINCT CASE WHEN p.status IN ('claimed', 'redeemed') THEN p.id END),
    COUNT(DISTINCT CASE WHEN p.status = 'redeemed' THEN p.id END),
    COUNT(DISTINCT p.id),
    COALESCE(SUM(DISTINCT p.value_cents_snapshot), 0),
    COUNT(DISTINCT COALESCE(p.recipient_user_id, p.owner_user_id, p.recipient_external_id)),
    COUNT(DISTINCT COALESCE(CAST(cee.viewer_user_id AS CHAR), cee.anonymous_session_hash)),
    AVG(CASE
        WHEN p.claimed_at IS NOT NULL
        THEN TIMESTAMPDIFF(SECOND, p.created_at, p.claimed_at)
    END),
    AVG(CASE
        WHEN p.redeemed_at IS NOT NULL
        THEN TIMESTAMPDIFF(SECOND, p.created_at, p.redeemed_at)
    END),
    NOW(),
    NOW()
FROM feed_posts fp
LEFT JOIN feed_post_versions fpv
    ON fpv.feed_post_id = fp.id
LEFT JOIN content_engagement_events cee
    ON cee.feed_post_version_id = fpv.id
   AND DATE(cee.occurred_at) = ?
LEFT JOIN pppm_feed_bindings pfb
    ON pfb.feed_post_version_id = fpv.id
LEFT JOIN pppm_items p
    ON p.id = pfb.pppm_item_id
   AND DATE(p.created_at) = ?
LEFT JOIN distribution_issuance_jobs dij
    ON dij.pppm_item_id = p.id
LEFT JOIN distribution_allocations da
    ON da.id = dij.allocation_id
LEFT JOIN distribution_programs dp
    ON dp.id = da.program_id
LEFT JOIN distribution_source_events dse
    ON dse.id = da.source_event_id
GROUP BY
    fp.merchant_user_id,
    fp.catalog_product_id,
    dp.id,
    COALESCE(dse.source_type, 'unknown')
HAVING
    COALESCE(SUM(cee.event_type = 'impression'), 0) > 0
    OR COUNT(DISTINCT p.id) > 0
SQL;

    $pdo->prepare($sql)->execute([$date, $date, $date]);
    $pdo->commit();

    echo "Aggregated demand facts for {$date}.\n";
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
