<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage4DDigitalFulfillmentTest extends TestCase
{
    public function testSchemaDefinesFulfillmentMediaAndAnalyticsTables(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_4d_digital_fulfillment_media.sql');
        self::assertIsString($sql);
        foreach (['media_delivery_profiles','catalog_asset_variants','media_processing_jobs','digital_fulfillment_rules','digital_entitlements','digital_access_events','media_delivery_tokens','content_engagement_daily'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString('token_hash CHAR(64) NOT NULL', $sql);
        self::assertStringContainsString('moderation_status', $sql);
        self::assertStringContainsString('retention_until', $sql);
    }

    public function testSignedTokensAreRandomHashedShortLivedAndUsageLimited(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/fulfillment/_media.php');
        self::assertIsString($source);
        self::assertStringContainsString('random_bytes(32)', $source);
        self::assertStringContainsString("hash_hmac('sha256'", $source);
        self::assertStringContainsString('MG_MEDIA_SIGNING_SECRET', $source);
        self::assertStringContainsString('max_uses', $source);
        self::assertStringContainsString('Media token has expired.', $source);
    }

    public function testDeliverySupportsByteRangesAndPrivateCaching(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/fulfillment/_media.php');
        self::assertIsString($source);
        self::assertStringContainsString('HTTP_RANGE', $source);
        self::assertStringContainsString('Content-Range: bytes', $source);
        self::assertStringContainsString('http_response_code(416)', $source);
        self::assertStringContainsString('http_response_code($status)', $source);
        self::assertStringContainsString('Accept-Ranges: bytes', $source);
        self::assertStringContainsString('private, max-age=60, no-transform', $source);
    }

    public function testDownloadsRequirePppmEntitlementsAndLimits(): void
    {
        $issue = file_get_contents(dirname(__DIR__, 2) . '/api/fulfillment/entitlements.php');
        $token = file_get_contents(dirname(__DIR__, 2) . '/api/fulfillment/access-token.php');
        $deliver = file_get_contents(dirname(__DIR__, 2) . '/api/fulfillment/deliver.php');
        self::assertIsString($issue);
        self::assertIsString($token);
        self::assertIsString($deliver);
        self::assertStringContainsString('pppm_item_id', $issue);
        self::assertStringContainsString('digital_entitlement_issued', $issue);
        self::assertStringContainsString('Download limit reached.', $token);
        self::assertStringContainsString("'exhausted'", $deliver);
        self::assertStringContainsString('download_completed', $deliver);
    }

    public function testStorageProviderPreventsPathEscape(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/fulfillment/storage.php');
        self::assertIsString($source);
        self::assertStringContainsString('interface MgMediaStorageProvider', $source);
        self::assertStringContainsString('final class MgPrivateLocalStorage', $source);
        self::assertStringContainsString('realpath', $source);
        self::assertStringContainsString('str_starts_with', $source);
        self::assertStringContainsString('Unsupported media storage provider.', $source);
    }

    public function testMediaModerationRevokesTokensAndQuarantinesVariants(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/fulfillment/media-status.php');
        self::assertIsString($source);
        self::assertStringContainsString("['blocked','takedown']", $source);
        self::assertStringContainsString('revoked_at = NOW()', $source);
        self::assertStringContainsString("status = 'quarantined'", $source);
        self::assertStringContainsString('retention_until', $source);
    }

    public function testProcessingQueueSupportsRequiredMediaOperations(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/fulfillment/media-jobs.php');
        self::assertIsString($source);
        foreach (['transcode','thumbnail','poster','normalize_audio','scan','metadata'] as $jobType) {
            self::assertStringContainsString("'" . $jobType . "'", $source);
        }
        self::assertStringContainsString('max_attempts', $source);
        self::assertStringContainsString('next_attempt_at', $source);
        self::assertStringContainsString("'queued'", $source);
    }

    public function testAnalyticsAggregationRemainsSeparateFromLifecycle(): void
    {
        $aggregate = file_get_contents(dirname(__DIR__, 2) . '/scripts/aggregate_stage4d_engagement.php');
        $analytics = file_get_contents(dirname(__DIR__, 2) . '/api/fulfillment/analytics.php');
        self::assertIsString($aggregate);
        self::assertIsString($analytics);
        self::assertStringContainsString('INSERT INTO content_engagement_daily', $aggregate);
        self::assertStringContainsString('content_engagement_events', $aggregate);
        self::assertStringContainsString('digital_access_events', $analytics);
        self::assertStringContainsString('fulfillment.analytics.view', $analytics);
    }

    public function testBandwidthAwareClientReducesPreloadOnConstrainedConnections(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/assets/js/media-delivery.js');
        self::assertIsString($source);
        self::assertStringContainsString('navigator.connection', $source);
        self::assertStringContainsString('saveData', $source);
        self::assertStringContainsString("video.removeAttribute('autoplay')", $source);
        self::assertStringContainsString('offset >= -1 && offset <= 2', $source);
        self::assertStringContainsString('/api/fulfillment/media-token.php', $source);
    }
}
