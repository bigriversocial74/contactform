<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage4ProductAssetFoundationTest extends TestCase
{
    public function testSchemaDefinesCatalogProductsVersionsAssetsAndPppmTemplates(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_4_product_asset_foundation.sql');
        self::assertIsString($sql);
        foreach (['catalog_products','catalog_product_versions','catalog_assets','catalog_product_version_assets','catalog_pppm_templates'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString('checksum CHAR(64) NOT NULL', $sql);
        self::assertStringContainsString('storage_provider VARCHAR(80) NOT NULL', $sql);
        self::assertStringContainsString('storage_key VARCHAR(500) NOT NULL', $sql);
    }

    public function testCatalogProductsAreMerchantScopedAndPublishedVersionsAreImmutable(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/catalog/products.php');
        self::assertIsString($source);
        self::assertStringContainsString('merchant_user_id = ?', $source);
        self::assertStringContainsString("mg_require_permission('catalog.products.manage')", $source);
        self::assertStringContainsString("mg_require_permission('catalog.products.publish')", $source);
        self::assertStringContainsString("version_status = 'published'", $source);
        self::assertStringContainsString("version_status = 'retired'", $source);
        self::assertStringContainsString('Only a draft version can be published.', $source);
    }

    public function testPublishingCreatesPppmTemplateInsteadOfIssuedUnit(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/catalog/products.php');
        self::assertIsString($source);
        self::assertStringContainsString('INSERT INTO catalog_pppm_templates', $source);
        self::assertStringNotContainsString('INSERT INTO pppm_items', $source);
        self::assertStringNotContainsString('INSERT INTO pppm_issuance_requests', $source);
    }

    public function testAssetsStoreMetadataAndRejectTraversalKeys(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/catalog/assets.php');
        self::assertIsString($source);
        self::assertStringContainsString("mg_require_permission('catalog.assets.manage')", $source);
        self::assertStringContainsString('str_contains($storageKey, \'..\')', $source);
        self::assertStringContainsString("'status' => 'pending'", $source);
        self::assertStringNotContainsString('file_put_contents', $source);
    }

    public function testStageFourReconciliationPreservesPppmBoundary(): void
    {
        $notes = file_get_contents(dirname(__DIR__, 2) . '/docs/stage-4-plan-reconciliation-and-kickoff.md');
        self::assertIsString($notes);
        self::assertStringContainsString('Stage 4 product records define what may be issued.', $notes);
        self::assertStringContainsString('PPPM items represent individual issued units.', $notes);
        self::assertStringContainsString('4A Product Catalog and Asset Foundation', $notes);
    }

    public function testUnifiedCiRunsStageFourMigrationForPrAndMain(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/pr-validation.yml');

        self::assertIsString($workflow);
        self::assertStringContainsString('pull_request:', $workflow);
        self::assertStringContainsString('push:', $workflow);
        self::assertStringContainsString('branches: [main]', $workflow);
        self::assertStringContainsString('php scripts/run_stage4_product_assets.php', $workflow);
    }
}
