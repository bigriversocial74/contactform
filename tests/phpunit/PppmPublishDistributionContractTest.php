<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PppmPublishDistributionContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testPublishSchemaLinksCanonicalVoucherDefinitionAndMerchantLocations(): void
    {
        $sql = $this->read('database/stage_18f_pppm_publish_distribution.sql');
        self::assertStringContainsString('microgift_template_version_id', $sql);
        self::assertStringContainsString('catalog_product_version_locations', $sql);
        self::assertStringContainsString('merchant_location_id', $sql);
        self::assertStringContainsString('stage_18f_pppm_publish_distribution', $sql);

        $manifest = $this->read('config/migrations.php');
        self::assertStringContainsString("'stage_18f_pppm_publish_distribution.sql'", $manifest);
    }

    public function testBuilderNeverPromptsMerchantToPurchaseOwnProduct(): void
    {
        $page = $this->read('build.php');
        $client = $this->read('assets/js/builder-stage4b.js');

        self::assertStringNotContainsString('Add published product to cart', $client);
        self::assertStringNotContainsString('addPublishedProductToCart', $client);
        self::assertStringNotContainsString('data-builder-cart-add', $client);

        foreach (['data-publish-product-link', 'data-publish-store-link', 'data-publish-feed-link'] as $needle) {
            self::assertStringContainsString($needle, $page);
            self::assertStringContainsString($needle, $client);
        }
    }

    public function testBuilderUsesCanonicalPublishDistributionService(): void
    {
        $endpoint = $this->read('api/catalog/builder-draft.php');
        $service = $this->read('api/catalog/_publish_distribution.php');

        self::assertStringContainsString("require_once __DIR__ . '/_publish_distribution.php'", $endpoint);
        self::assertStringContainsString('mg_catalog_publish_distribution(', $endpoint);
        self::assertStringContainsString('mg_catalog_product_type_from_payload(', $endpoint);
        self::assertStringContainsString('mg_catalog_merchant_locations(', $endpoint);

        foreach (['mg_catalog_publish_to_storefront', 'mg_catalog_publish_feed_post', 'mg_catalog_publish_microgift_definition', 'mg_catalog_publish_locations'] as $function) {
            self::assertStringContainsString('function ' . $function, $service);
        }
    }

    public function testCheckoutConsumesPublishedCanonicalMicrogiftDefinition(): void
    {
        $fulfillment = $this->read('api/payments/_fulfillment.php');
        self::assertStringContainsString('catalog_pppm_templates', $fulfillment);
        self::assertStringContainsString('microgift_template_version_id', $fulfillment);
        self::assertStringContainsString('canonical published PPPM voucher definition', $fulfillment);
    }

    public function testDiscoveryIncludesProductLevelLocationResults(): void
    {
        $helper = $this->read('api/profiles/_discovery.php');
        $page = $this->read('discover.php');
        $client = $this->read('assets/js/profile-discovery.js');

        self::assertStringContainsString('function mg_product_discovery_search', $helper);
        self::assertStringContainsString('catalog_product_version_locations', $helper);
        self::assertStringContainsString("'products' => mg_product_discovery_search", $helper);
        self::assertStringContainsString('data-product-results-grid', $page);
        self::assertStringContainsString('data-product-results-grid', $client);
    }

    public function testFocusedBehaviorValidationIsRegistered(): void
    {
        $composer = $this->read('composer.json');
        $baseline = $this->read('scripts/recovery_baseline.sh');
        self::assertStringContainsString('test-pppm-publish-distribution', $composer);
        self::assertStringContainsString('test-pppm-publish-distribution', $baseline);
    }
}
