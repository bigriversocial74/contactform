<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage9DIntegrationCloseoutTest extends TestCase
{
    public function testOperationalSchemaDefinesReviewsAndMetrics(): void
    {
        $sql=file_get_contents(dirname(__DIR__,2).'/database/stage_9d_microgift_operations.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS microgift_review_items',$sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS microgift_daily_metrics',$sql);
        self::assertStringContainsString('uq_microgift_review_source',$sql);
        self::assertStringContainsString('uq_microgift_daily_metric',$sql);
    }

    public function testCustomerLibraryUsesCanonicalMicrogiftPppmAndEntitlementRecords(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/api/microgifts/_operations.php');
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/account/microgifts.php');
        self::assertStringContainsString('FROM microgift_instances i',$service);
        self::assertStringContainsString('LEFT JOIN pppm_items p',$service);
        self::assertStringContainsString('FROM entitlements e',$service);
        self::assertStringContainsString('mg_require_api_user()',$endpoint);
    }

    public function testMerchantOperationsArePermissionAndOwnerScoped(): void
    {
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/merchant/microgifts.php');
        self::assertStringContainsString("mg_require_permission('microgift.operations.view')",$endpoint);
        self::assertStringContainsString('WHERE t.owner_user_id=?',$endpoint);
        self::assertStringContainsString('mg_microgift_merchant_summary',$endpoint);
    }

    public function testAdminInspectionAndReviewsPreserveCompleteTimeline(): void
    {
        $inspect=file_get_contents(dirname(__DIR__,2).'/api/admin/microgift-inspect.php');
        $reviews=file_get_contents(dirname(__DIR__,2).'/api/admin/microgift-reviews.php');
        self::assertStringContainsString('microgift_events',$inspect);
        self::assertStringContainsString('microgift_claims',$inspect);
        self::assertStringContainsString('microgift_redemptions',$inspect);
        self::assertStringContainsString('microgift_lifecycle_actions',$inspect);
        self::assertStringContainsString("mg_require_permission('microgift.reviews.manage')",$reviews);
        self::assertStringContainsString('mg_require_csrf_for_write',$reviews);
    }

    public function testLegacyReconciliationCreatesReviewItemsWithoutDeletingLegacyRecords(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/scripts/reconcile_stage9d_legacy_gifts.php');
        self::assertStringContainsString('mg_microgift_create_review',$script);
        self::assertStringContainsString("'legacy_unmapped'",$script);
        self::assertStringContainsString("'ownership_mismatch'",$script);
        self::assertStringNotContainsString('DELETE FROM gifts',$script);
        self::assertStringNotContainsString('DELETE FROM gift_claims',$script);
    }

    public function testFutureDemandSourceMetricsRemainAggregatesNotScores(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/scripts/aggregate_stage9d_microgifts.php');
        self::assertStringContainsString('issued_count',$script);
        self::assertStringContainsString('claimed_count',$script);
        self::assertStringContainsString('redeemed_count',$script);
        self::assertStringContainsString('unique_locations',$script);
        self::assertStringNotContainsString('future_demand_score',$script);
    }

    public function testConsolidatedCiRunsStage9DCloseoutChecks(): void
    {
        $workflow=file_get_contents(dirname(__DIR__,2).'/.github/workflows/pr-validation.yml');
        self::assertStringContainsString('php scripts/stage9d.php',$workflow);
        self::assertStringContainsString('php scripts/stage9d_smoke.php',$workflow);
        self::assertStringContainsString('php scripts/reconcile_stage9d_legacy_gifts.php',$workflow);
        self::assertStringContainsString('php scripts/aggregate_stage9d_microgifts.php',$workflow);
    }

    public function testCloseoutAndStage10HandoffDocumentsExist(): void
    {
        self::assertFileExists(dirname(__DIR__,2).'/docs/stages/stage_9d_integration_closeout.md');
        self::assertFileExists(dirname(__DIR__,2).'/docs/stages/stage_10_handoff_from_stage_9.md');
    }
}
