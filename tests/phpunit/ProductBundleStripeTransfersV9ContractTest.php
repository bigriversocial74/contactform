<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductBundleStripeTransfersV9ContractTest extends TestCase
{
    public function testTransferOrchestrationContracts(): void
    {
        $root=dirname(__DIR__,2);
        $api=file_get_contents($root.'/api/admin/bundle-settlement-transfers.php');
        $sql=file_get_contents($root.'/database/20260719_product_bundle_stripe_transfers_v9.sql');
        $page=file_get_contents($root.'/admin/bundle-settlement-transfers.php');
        self::assertStringContainsString('MG_BUNDLE_TRANSFER_EXECUTION_ENABLED',$api);
        self::assertStringContainsString("event_type='admin_review_mark_release_ready'",$api);
        self::assertStringContainsString("confirmation",$api);
        self::assertStringContainsString("'RELEASE'",$api);
        self::assertStringContainsString('gift_bundle_settlement_transfers',$sql);
        self::assertStringContainsString('UNIQUE KEY uq_bundle_transfer_settlement',$sql);
        self::assertStringContainsString('idempotency_key',$sql);
        self::assertStringContainsString('provider_dispatch_required',$api);
        self::assertStringContainsString('data-transfer-page',$page);
        self::assertStringNotContainsString("/v1/transfers",$api,'Provider dispatch must remain adapter-controlled in this phase.');
    }
}
