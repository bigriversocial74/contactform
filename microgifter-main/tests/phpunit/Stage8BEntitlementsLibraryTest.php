<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage8BEntitlementsLibraryTest extends TestCase
{
    public function testEntitlementSchemaDefinesAccessGrantModel(): void
    {
        $sql=file_get_contents(dirname(__DIR__,2).'/database/stage_8b_entitlements_library.sql');
        self::assertIsString($sql);
        foreach(['entitlements','entitlement_events','entitlement_access_events','asset_delivery_grants','entitlement_review_items'] as $table){
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        }
        self::assertStringContainsString('uq_entitlements_idempotency',$sql);
        self::assertStringContainsString('uq_entitlements_active_grant',$sql);
        self::assertStringContainsString('uq_asset_delivery_grants_token',$sql);
    }

    public function testPaidPppmIssuanceCreatesEntitlements(): void
    {
        $fulfillment=file_get_contents(dirname(__DIR__,2).'/api/payments/_fulfillment.php');
        self::assertIsString($fulfillment);
        self::assertStringContainsString('/entitlements/_entitlements.php',$fulfillment);
        self::assertStringContainsString('mg_entitlement_grant_for_pppm_item',$fulfillment);
        self::assertStringContainsString('entitlements',$fulfillment);
    }

    public function testEntitlementServiceLinksPppmItemsToProductAssets(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/entitlements/_entitlements.php');
        self::assertIsString($source);
        self::assertStringContainsString('commerce_order_items',$source);
        self::assertStringContainsString('catalog_product_version_assets',$source);
        self::assertStringContainsString('catalog_assets',$source);
        self::assertStringContainsString('pppm_item',$source);
        self::assertStringContainsString('entitlement.granted',$source);
    }

    public function testProtectedAccessRequiresActiveEntitlementAndKeepsStorageServerSide(): void
    {
        $access=file_get_contents(dirname(__DIR__,2).'/api/library/access.php');
        $download=file_get_contents(dirname(__DIR__,2).'/api/library/download.php');
        self::assertIsString($access);
        self::assertIsString($download);
        self::assertStringContainsString('mg_require_api_user()',$access);
        self::assertStringContainsString('mg_require_csrf_for_write',$access);
        self::assertStringContainsString('mg_entitlement_authorize_asset',$access);
        self::assertStringContainsString('mg_entitlement_delivery_response',$download);
        self::assertStringContainsString('entitlement_status',$download);
        self::assertStringContainsString('asset_status',$download);
        self::assertStringNotContainsString("'storage_key'=>",$access);
        self::assertStringNotContainsString("'storage_key'=>",$download);
    }

    public function testDeliveryGrantConsumptionIsAtomicAndSingleUse(): void
    {
        $download=file_get_contents(dirname(__DIR__,2).'/api/library/download.php');
        self::assertIsString($download);
        self::assertStringContainsString('$pdo->beginTransaction();',$download);
        self::assertStringContainsString('LIMIT 1 FOR UPDATE',$download);
        self::assertStringContainsString('$consume->rowCount()!==1',$download);
        self::assertStringContainsString('delivery_grant_already_consumed',$download);
        self::assertStringContainsString('$pdo->commit();',$download);
        self::assertStringContainsString('if($pdo->inTransaction())$pdo->rollBack();',$download);
    }

    public function testAccessAndDownloadEventsAreAppendOnly(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/entitlements/_entitlements.php');
        self::assertIsString($source);
        self::assertStringContainsString('entitlement_access_events',$source);
        self::assertStringContainsString('authorized',$source);
        self::assertStringContainsString('denied',$source);
        self::assertStringContainsString('download_started',$source);
    }

    public function testRefundPolicyRevokesFullRefundsAndReviewsPartialRefunds(): void
    {
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/merchant/refund.php');
        $refundService=file_get_contents(dirname(__DIR__,2).'/api/payments/_refund.php');
        $entitlementService=file_get_contents(dirname(__DIR__,2).'/api/entitlements/_entitlements.php');
        self::assertIsString($endpoint);
        self::assertIsString($refundService);
        self::assertIsString($entitlementService);
        self::assertStringContainsString('mg_finance_refund_order(',$endpoint);
        self::assertStringContainsString('mg_entitlements_apply_refund_policy',$refundService);
        self::assertStringContainsString('full_refund',$entitlementService);
        self::assertStringContainsString('partial_refund_review',$entitlementService);
        self::assertStringContainsString('entitlement_review_items',$entitlementService);
    }

    public function testLibraryExtendsExistingAccountItemsRatherThanReplacingPppm(): void
    {
        $commerce=file_get_contents(dirname(__DIR__,2).'/api/account/_commerce.php');
        self::assertIsString($commerce);
        self::assertStringContainsString('FROM pppm_items i',$commerce);
        self::assertStringContainsString('FROM entitlements e WHERE e.pppm_item_id=i.id',$commerce);
        self::assertStringContainsString('active_entitlement_count',$commerce);
    }

    public function testAdminEntitlementWritesRequirePermissionAndCsrf(): void
    {
        $admin=file_get_contents(dirname(__DIR__,2).'/api/admin/entitlements.php');
        self::assertIsString($admin);
        self::assertStringContainsString("mg_require_permission('entitlements.manage')",$admin);
        self::assertStringContainsString('mg_require_csrf_for_write',$admin);
        self::assertStringContainsString('entitlement.revoked',$admin);
        self::assertStringContainsString('entitlement.restored',$admin);
    }

    public function testStage8MigrationAndSmokeAreIncluded(): void
    {
        $migration=file_get_contents(dirname(__DIR__,2).'/scripts/stage8b.php');
        $smoke=file_get_contents(dirname(__DIR__,2).'/scripts/stage8b_smoke.php');
        self::assertIsString($migration);
        self::assertIsString($smoke);
        self::assertStringContainsString('stage_8b_entitlements_library.sql',$migration);
        self::assertStringContainsString('stage_8c_entitlement_lifecycle.sql',$migration);
        self::assertStringContainsString('Stage 8 lifecycle smoke checks passed',$smoke);
    }
}
