<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class PublicProductStorefrontCartContractTest extends TestCase
{
    public function testPublicProductPageUsesPublicCatalogRenderer(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/product.php');
        self::assertIsString($source);

        foreach([
            '$page_section = \'catalog-public\'',
            '$page_styles = [\'/assets/css/public-catalog.css\']',
            '$page_scripts = [\'/assets/js/public-catalog.js\']',
            'data-public-product',
            'data-product-id',
            'data-product-slug',
            '$product_lookup = $product_id !== \'\' ? $product_id : $product_slug',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testPublicProductApiReturnsPublishedVersionId(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/public/product.php');
        self::assertIsString($source);

        foreach([
            'mg_catalog_resolve_public_product_identity',
            'cpv.public_id AS version_id',
            "WHERE cp.public_id = ? AND cp.status = 'published' AND cpv.version_status = 'published'",
            '$product[\'public_url\'] = mg_catalog_public_product_url',
            '$product[\'assets\'] = $assets',
            '$product[\'elements\'] = $elements',
            'mg_ok([\'product\' => $product])',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testStorefrontApiReturnsPublishedVersionIds(): void
    {
        $route=file_get_contents(dirname(__DIR__,2).'/api/storefront/profile.php');
        $wrapper=file_get_contents(dirname(__DIR__,2).'/api/storefront/profile-v1.php');
        $query=file_get_contents(dirname(__DIR__,2).'/api/storefront/_profile_legacy.php');
        self::assertIsString($route);
        self::assertIsString($wrapper);
        self::assertIsString($query);

        self::assertStringContainsString("require __DIR__ . '/profile-v1.php';",$route);
        foreach([
            'cpv.public_id version_id',
            "cpv.version_status='published'",
            'mg_ok([\'storefront\'=>$profile,\'products\'=>$products])',
        ] as $needle){
            self::assertStringContainsString($needle,$query);
        }
        foreach([
            "'/product.php?id='",
            "'&p='",
            '$product[\'product_url\']',
            "unset(\$product['cover_asset_id'])",
        ] as $needle){
            self::assertStringContainsString($needle,$wrapper);
        }
    }

    public function testPublicCatalogJavascriptWiresAddToCartButtons(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/public-catalog.js');
        self::assertIsString($source);

        foreach([
            'function addToCartButton(versionId, label)',
            'data-cart-add data-product-version-id=',
            'data-cart-quantity="1"',
            "addToCartButton(product.version_id, 'Add to cart')",
            '<div class="mg-store-card-actions">',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testGlobalCartScriptConsumesPublicProductButtons(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/cart.js');
        self::assertIsString($source);

        foreach([
            "event.target.closest('[data-cart-add],[data-add-to-cart]')",
            'button.dataset.productVersionId||button.dataset.versionId||button.dataset.cartVersionId',
            'C().addProductVersion(productVersionId',
            'openDrawer(button)',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
