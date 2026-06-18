<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class Stage5BProductManagementTest extends TestCase
{
    public function testMerchantProductApisAreOwnerScoped(): void
    {
        $list=file_get_contents(dirname(__DIR__,2).'/api/merchant/products.php');
        $detail=file_get_contents(dirname(__DIR__,2).'/api/merchant/product.php');
        self::assertIsString($list);
        self::assertIsString($detail);
        self::assertStringContainsString('p.merchant_user_id=?',$list);
        self::assertStringContainsString('p.merchant_user_id=?',$detail);
        self::assertStringContainsString("mg_require_permission('catalog.products.view')",$list);
    }

    public function testProductWorkspaceUsesExistingCatalogLifecycle(): void
    {
        $js=file_get_contents(dirname(__DIR__,2).'/assets/js/merchant-products.js');
        self::assertIsString($js);
        foreach([
            '/api/catalog/builder-draft.php',
            "save('publish'",
            "{ action: 'archive', id: productId }",
            '/build.php?id=',
        ] as $needle){
            self::assertStringContainsString($needle,$js);
        }
    }

    public function testProductDetailShowsImmutableVersionsAndAssets(): void
    {
        $api=file_get_contents(dirname(__DIR__,2).'/api/merchant/product.php');
        self::assertIsString($api);
        self::assertStringContainsString('catalog_product_versions',$api);
        self::assertStringContainsString('catalog_product_version_assets',$api);
        self::assertStringContainsString('ORDER BY v.version_number DESC',$api);
        self::assertStringContainsString("'versions'=>\$versions->fetchAll()",$api);
        self::assertStringContainsString("'assets'=>\$versionAssets",$api);
    }

    public function testAssetLibraryTracksProcessingAndUsage(): void
    {
        $api=file_get_contents(dirname(__DIR__,2).'/api/merchant/assets.php');
        self::assertIsString($api);
        self::assertStringContainsString('catalog_assets',$api);
        self::assertStringContainsString('usage_count',$api);
        self::assertStringContainsString('owner_user_id=?',$api);
    }

    public function testPublishedVersionImmutabilityUsesSharedDistributionService(): void
    {
        $root=dirname(__DIR__,2);
        $builder=file_get_contents($root.'/api/catalog/builder-draft.php');
        $distribution=file_get_contents($root.'/api/catalog/_publish_distribution.php');
        self::assertIsString($builder);
        self::assertIsString($distribution);
        self::assertStringContainsString("SET version_status='retired'",$builder);
        self::assertStringContainsString("VALUES (?,?,?,'published'",$builder);
        self::assertStringContainsString('mg_catalog_publish_distribution(',$builder);
        self::assertStringContainsString('catalog_pppm_templates',$distribution);
        self::assertStringContainsString("current_version_id=?,status='published'",$builder);
        self::assertStringContainsString("'version_id'=>\$versionId",$builder);
    }
}
