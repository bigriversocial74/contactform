<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BuilderAddToCartIntegrationContractTest extends TestCase
{
    public function testBuilderPublishReturnsPublicDistributionDestinations(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/catalog/builder-draft.php');
        self::assertIsString($source);

        foreach([
            'INSERT INTO catalog_product_versions',
            "VALUES (?,?,?,'published'",
            "'version_id'=>\$versionId",
            "'version_number'=>\$versionNumber",
            "'product_url'=>\$distribution['product_url']",
            "'store_url'=>\$distribution['store_url']",
            "'feed_url'=>\$distribution['feed_url']",
            "'status'=>'published'",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testBuilderPageLoadsDistributionControls(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/build.php');
        self::assertIsString($source);

        foreach([
            '$page_scripts = [\'/assets/js/builder-stage4b.js\',\'/assets/js/product-builder-shell.js\']',
            'data-builder-app',
            'data-product-id',
            'data-builder-toast',
            'data-publish-product-link',
            'data-publish-store-link',
            'data-publish-feed-link',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testBuilderJavascriptNeverAddsMerchantProductToOwnCart(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/builder-stage4b.js');
        self::assertIsString($source);

        foreach([
            "product: root.querySelector('[data-publish-product-link]')",
            "store: root.querySelector('[data-publish-store-link]')",
            "feed: root.querySelector('[data-publish-feed-link]')",
            'function showPublishDestinations(data)',
            'showPublishDestinations(data);',
            "setStatus('Published to store, feed, and locations')",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('Add published product to cart',$source);
        self::assertStringNotContainsString('addPublishedProductToCart',$source);
        self::assertStringNotContainsString('data-builder-cart-add',$source);
    }

    public function testCustomerFacingCartStillAcceptsProductVersionEvents(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/cart.js');
        self::assertIsString($source);
        foreach([
            "document.addEventListener('mg:cart:add'",
            'detail.product_version_id||detail.productVersionId',
            'C().addProductVersion(id,detail.quantity||1).then(refresh)',
            'window.Microgifter.cart={refresh:refresh,open:openDrawer,close:closeDrawer,addProductVersion:function(id,itemQuantity){return C().addProductVersion(id,itemQuantity).then(refresh);}}',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
