<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductBundleCustomerStorefrontV4ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testStorefrontFilesExist(): void
    {
        foreach (['bundles.php','bundle.php','bundle-order.php','api/bundles/storefront.php','assets/js/bundle-storefront-v4.js','assets/css/bundle-storefront-v4.css'] as $file) {
            self::assertFileExists($this->root . '/' . $file, $file);
        }
    }

    public function testStorefrontApiUsesCanonicalBundleServices(): void
    {
        $api = file_get_contents($this->root . '/api/bundles/storefront.php');
        self::assertIsString($api);
        self::assertStringContainsString("require_once __DIR__ . '/_checkout.php'", $api);
        self::assertStringContainsString('mg_bundle_order_reserve(', $api);
        self::assertStringContainsString('mg_bundle_checkout_start(', $api);
        self::assertStringContainsString('mg_require_csrf_for_write(', $api);
        self::assertStringContainsString("b.status='published'", $api);
        self::assertStringContainsString("b.visibility='public'", $api);
    }

    public function testCustomerPagesExposeExpectedRuntimeHooks(): void
    {
        $catalog = file_get_contents($this->root . '/bundles.php');
        $detail = file_get_contents($this->root . '/bundle.php');
        $order = file_get_contents($this->root . '/bundle-order.php');
        self::assertStringContainsString('data-bundle-catalog', (string)$catalog);
        self::assertStringContainsString('data-bundle-detail', (string)$detail);
        self::assertStringContainsString('data-csrf', (string)$detail);
        self::assertStringContainsString('data-bundle-order', (string)$order);
    }

    public function testClientDoesNotExecuteTransfersOrSettlement(): void
    {
        $js = file_get_contents($this->root . '/assets/js/bundle-storefront-v4.js');
        self::assertIsString($js);
        self::assertStringNotContainsString('stripe_transfers', $js);
        self::assertStringNotContainsString('transfer_data', $js);
        self::assertStringNotContainsString('settlement_release', $js);
    }
}
