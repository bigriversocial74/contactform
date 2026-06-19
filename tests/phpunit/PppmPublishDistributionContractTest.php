<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PppmPublishDistributionContractTest extends TestCase
{
    private function source(string $path): string
    {
        $value = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($value, $path);
        return $value;
    }

    public function testSchemaAndCanonicalMigrationAreRegistered(): void
    {
        $sql = $this->source('database/stage_18f_pppm_publish_distribution.sql');
        self::assertStringContainsString('microgift_template_version_id', $sql);
        self::assertStringContainsString('catalog_product_version_locations', $sql);
        self::assertStringContainsString('merchant_location_id', $sql);
        self::assertStringContainsString("'stage_18f_pppm_publish_distribution.sql'", $this->source('config/migrations.php'));
    }

    public function testBuilderDistributesInsteadOfPurchasingOwnProduct(): void
    {
        $page = $this->source('build.php');
        $client = $this->source('assets/js/builder-stage4b.js');
        self::assertStringNotContainsString('Add published product to cart', $client);
        self::assertStringNotContainsString('addPublishedProductToCart', $client);
        self::assertStringNotContainsString('data-builder-cart-add', $client);
        foreach (['data-publish-product-link', 'data-publish-store-link', 'data-publish-feed-link'] as $marker) {
            self::assertStringContainsString($marker, $page);
            self::assertStringContainsString($marker, $client);
        }
    }

    public function testBuilderUsesPublishDistributionService(): void
    {
        $endpoint = $this->source('api/catalog/builder-draft.php');
        $service = $this->source('api/catalog/_publish_distribution.php');
        self::assertStringContainsString("require_once __DIR__ . '/_publish_distribution.php'", $endpoint);
        self::assertStringContainsString('mg_catalog_publish_distribution(', $endpoint);
        foreach (['mg_catalog_publish_to_storefront', 'mg_catalog_publish_feed_post', 'mg_catalog_publish_microgift_definition', 'mg_catalog_publish_locations'] as $function) {
            self::assertStringContainsString('function ' . $function, $service);
        }
    }

    public function testCheckoutUsesCanonicalPublishedVoucherDefinition(): void
    {
        $fulfillment = $this->source('api/payments/_fulfillment.php');
        self::assertStringContainsString('catalog_pppm_templates', $fulfillment);
        self::assertStringContainsString('microgift_template_version_id', $fulfillment);
        self::assertStringContainsString('canonical published PPPM voucher definition', $fulfillment);
    }

    public function testProductDiscoveryServiceAndCurrentProfileDiscoveryPageRemainAvailable(): void
    {
        $helper = $this->source('api/profiles/_product_discovery.php');
        $endpoint = $this->source('api/public/product-discovery.php');
        $page = $this->source('discover.php');
        $client = $this->source('assets/js/product-discovery.js');
        self::assertStringContainsString('function mg_product_discovery_search', $helper);
        self::assertStringContainsString('catalog_product_version_locations', $helper);
        self::assertStringContainsString('mg_product_discovery_search(', $endpoint);
        self::assertStringContainsString('data-product-results-grid', $client);
        self::assertStringContainsString('data-profile-discovery', $page);
        self::assertStringContainsString('name="location"', $page);
        self::assertStringContainsString('name="category"', $page);
        self::assertStringContainsString('Newest profiles', $page);
    }

    public function testFocusedBehaviorIsInRecoveryBaseline(): void
    {
        self::assertStringContainsString('test-pppm-publish-distribution', $this->source('composer.json'));
        self::assertStringContainsString('test-pppm-publish-distribution', $this->source('scripts/recovery_baseline.sh'));
    }
}
