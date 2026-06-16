<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage8CEntitlementLifecycleCloseoutTest extends TestCase
{
    public function testLifecycleSchemaAddsTransferAndPolicyIdempotency(): void
    {
        $sql=file_get_contents(dirname(__DIR__,2).'/database/stage_8c_entitlement_lifecycle.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS entitlement_transfers',$sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS entitlement_policy_actions',$sql);
        self::assertStringContainsString('uq_entitlement_transfers_idempotency',$sql);
        self::assertStringContainsString('uq_entitlement_policy_actions_idempotency',$sql);
    }

    public function testOwnershipSynchronizationTransfersAccessWithoutReplacingPppm(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/entitlements/_lifecycle.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg_entitlements_sync_pppm_owner',$source);
        self::assertStringContainsString('FROM pppm_items',$source);
        self::assertStringContainsString('entitlement.transferred_out',$source);
        self::assertStringContainsString('entitlement.transferred_in',$source);
        self::assertStringContainsString('entitlement_transfers',$source);
    }

    public function testDisputePolicySuspendsRestoresOrRevokesIdempotently(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/entitlements/_lifecycle.php');
        $webhook=file_get_contents(dirname(__DIR__,2).'/api/payments/entitlement-dispute-webhook.php');
        self::assertIsString($source);
        self::assertIsString($webhook);
        self::assertStringContainsString('mg_entitlements_apply_dispute',$source);
        self::assertStringContainsString('merchant_won',$source);
        self::assertStringContainsString('customer_won',$source);
        self::assertStringContainsString('mg_payment_verify_signature',$webhook);
        self::assertStringContainsString('payment_webhook_events',$webhook);
    }

    public function testAssetRemovalSuspendsAndCreatesReviewItems(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/entitlements/_lifecycle.php');
        self::assertStringContainsString('mg_entitlements_apply_asset_policy',$source);
        self::assertStringContainsString("'asset_removed'",$source);
        self::assertStringContainsString('mg_entitlement_create_review',$source);
        self::assertStringContainsString("'asset_restored'",$source);
    }

    public function testSignedDeliveryUsesAdapterAndRechecksEntitlement(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/api/entitlements/_lifecycle.php');
        $download=file_get_contents(dirname(__DIR__,2).'/api/library/download.php');
        self::assertStringContainsString('MG_ASSET_DELIVERY_SECRET',$service);
        self::assertStringContainsString('MG_ASSET_SIGNED_URL_TEMPLATE',$service);
        self::assertStringContainsString('hash_hmac',$service);
        self::assertStringContainsString('entitlement_status',$download);
        self::assertStringContainsString('asset_status',$download);
        self::assertStringContainsString('mg_entitlement_delivery_response',$download);
        self::assertStringNotContainsString("'storage_key'=>",$download);
    }

    public function testMerchantVisibilityIsScopedToCurrentMerchant(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/merchant/entitlements.php');
        self::assertIsString($source);
        self::assertStringContainsString("mg_require_permission('merchant.entitlements.view')",$source);
        self::assertStringContainsString('WHERE e.merchant_user_id=?',$source);
    }

    public function testLifecycleEndpointRequiresPermissionCsrfAndSourceReference(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/entitlements/lifecycle.php');
        self::assertIsString($source);
        self::assertStringContainsString("mg_require_permission('entitlements.lifecycle')",$source);
        self::assertStringContainsString('mg_require_csrf_for_write',$source);
        self::assertStringContainsString('Source reference is required.',$source);
    }

    public function testStage8CCloseoutDocumentationExists(): void
    {
        $closeout=file_get_contents(dirname(__DIR__,2).'/docs/stages/stage_8c_entitlement_lifecycle_closeout.md');
        $handoff=file_get_contents(dirname(__DIR__,2).'/docs/stages/stage_9_handoff_from_stage_8.md');
        self::assertIsString($closeout);
        self::assertIsString($handoff);
        self::assertStringContainsString('Stage 8A',$closeout);
        self::assertStringContainsString('Stage 8B',$closeout);
        self::assertStringContainsString('Stage 9',$handoff);
    }
}
