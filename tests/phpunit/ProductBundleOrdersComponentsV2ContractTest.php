<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductBundleOrdersComponentsV2ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    public function testCanonicalFilesExist(): void
    {
        foreach([
            'database/20260719_product_bundle_orders_components_v2.sql',
            'api/bundles/_orders.php',
            'docs/commerce/product-bundle-orders-components-v2.md',
        ] as $file) $this->assertFileExists($this->root.'/'.$file,$file);
    }

    public function testSchemaDefinesParentAndComponentAuthorities(): void
    {
        $sql=file_get_contents($this->root.'/database/20260719_product_bundle_orders_components_v2.sql');
        foreach([
            'gift_bundle_orders','gift_bundle_order_components','gift_bundle_inventory_reservations','gift_bundle_order_events',
            'bundle_snapshot_json','commission_snapshot_json','pppm_issuance_request_id','pppm_item_id','microgift_instance_id',
            'merchant_net_amount_cents','idempotency_key','fixed_platform_fee_cents'
        ] as $marker) $this->assertStringContainsString($marker,$sql);
    }

    public function testServiceCreatesOneParentWithIndependentComponents(): void
    {
        $php=file_get_contents($this->root.'/api/bundles/_orders.php');
        foreach([
            'mg_bundle_order_reserve','mg_bundle_order_link_commerce','gift_bundle_order_components',
            'gift_bundle_inventory_reservations','MG_COMMISSION_RULE_VERSION','commission_rate_bps',
            'merchant_net_amount_cents','FOR UPDATE','idempotency_key'
        ] as $marker) $this->assertStringContainsString($marker,$php);
        $this->assertStringNotContainsString('0.15',$php);
        $this->assertStringNotContainsString('15 / 100',$php);
    }

    public function testPaymentAndStripeExecutionRemainDeferred(): void
    {
        $php=file_get_contents($this->root.'/api/bundles/_orders.php');
        $this->assertStringNotContainsString('PaymentIntent::create',$php);
        $this->assertStringNotContainsString('stripe_transfer',$php);
        $this->assertStringNotContainsString('mg_payment_capture',$php);
    }
}
