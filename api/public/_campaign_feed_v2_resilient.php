<?php
declare(strict_types=1);

require_once __DIR__ . '/_campaign_feed_v2_progress.php';

function mg_campaign_feed_v2_resilient_items(PDO $pdo, string $mode, ?int $viewerId, int $limit = MG_CAMPAIGN_FEED_V2_MAX): array
{
    try {
        return mg_campaign_feed_v2_items_with_progress($pdo, $mode, $viewerId, $limit);
    } catch (Throwable $error) {
        mg_security_log('warning', 'campaign.feed_v2_enrichment_failed', 'The Action Center enriched campaign feed fell back to the base campaign projection.', [
            'mode' => $mode,
            'exception_class' => $error::class,
        ], $viewerId);
    }

    try {
        if ($mode === 'discover') {
            return mg_campaign_feed_v1_items($pdo, 'discover', null, min($limit, MG_CAMPAIGN_FEED_V1_MAX));
        }
        return mg_campaign_feed_v1_items($pdo, $mode, $viewerId, min($limit, MG_CAMPAIGN_FEED_V1_MAX));
    } catch (Throwable $error) {
        mg_security_log('warning', 'campaign.feed_v2_base_failed', 'The base campaign feed projection was also unavailable.', [
            'mode' => $mode,
            'exception_class' => $error::class,
        ], $viewerId);
        return [];
    }
}
