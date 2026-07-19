<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductBundleCheckoutFulfillmentV3ContractTest extends TestCase
{
    public function testMigrationContainsCheckoutAndDispatchAuthority(): void
    {
        $sql=file_get_contents(__DIR__.'/../../database/20260719_product_bundle_checkout_fulfillment_v3.sql');
        self::assertIsString($sql);
        foreach (['gift_bundle_checkout_attempts','gift_bundle_fulfillment_dispatches','payment_intent_id','checkout_started_at','fulfilled_at'] as $marker) {
            self::assertStringContainsString($marker,$sql);
        }
        self::assertStringContainsString("ENUM('pending','processing','completed','failed','cancelled')",$sql);
    }

    public function testCheckoutServiceUsesCanonicalPaymentIntentAuthority(): void
    {
        $php=file_get_contents(__DIR__.'/../../api/bundles/_checkout.php');
        self::assertIsString($php);
        foreach (['mg_payment_create_source_intent','gift_bundle_order','mg_bundle_checkout_start','mg_bundle_checkout_mark_paid','mg_bundle_fulfillment_dispatch'] as $marker) {
            self::assertStringContainsString($marker,$php);
        }
        self::assertStringContainsString("payment_status='paid'",$php);
        self::assertStringContainsString("reservation_status='committed'",$php);
        self::assertStringContainsString("dispatch_status='completed'",$php);
    }

    public function testFulfillmentRemainsComponentScopedAndIdempotent(): void
    {
        $php=file_get_contents(__DIR__.'/../../api/bundles/_checkout.php');
        self::assertStringContainsString("component:",$php);
        self::assertStringContainsString('idempotency_key',$php);
        self::assertStringContainsString('microgift_instance_id',$php);
        self::assertStringNotContainsString('stripe_transfers',$php);
        self::assertStringNotContainsString('transfer_data',$php);
    }

    public function testMigrationIsRegistered(): void
    {
        $manifest=require __DIR__.'/../../config/migrations.php';
        self::assertContains('20260719_product_bundle_checkout_fulfillment_v3.sql',$manifest['ordered_files']);
    }
}
