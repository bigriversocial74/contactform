<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class BuilderAddToCartIntegrationContractTest extends TestCase
{
    public function testBuilderPublishReturnsPublishedVersionIdForCart(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/catalog/builder-draft.php');
        self::assertIsString($source);

        foreach([
            'INSERT INTO catalog_product_versions',
            "VALUES (?,?,?,'published'",
            "'version_id'=>\$versionId",
            "'version_number'=>\$versionNumber",
            "'status'=>'published'",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testBuilderPageLoadsCartAwareScripts(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/build.php');
        self::assertIsString($source);

        foreach([
            '$page_scripts = [\'/assets/js/builder-stage4b.js\',\'/assets/js/product-builder-shell.js\']',
            'data-builder-app',
            'data-product-id',
            'data-builder-toast',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testBuilderJavascriptAddsPublishedVersionToServerCart(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/builder-stage4b.js');
        self::assertIsString($source);

        foreach([
            'var publishedVersionId = \'\';',
            'publishedVersionId = data.version_id || \'\';',
            'function addPublishedProductAction(versionId)',
            'button.dataset.builderCartAdd = versionId',
            'button.dataset.productVersionId = versionId',
            'button.textContent = \'Add published product to cart\'',
            'function addPublishedProductToCart(versionId)',
            'window.Microgifter.cart.addProductVersion(versionId, 1)',
            "document.dispatchEvent(new CustomEvent('mg:cart:add'",
            'root.addEventListener(\'click\', function (event) {',
            'event.target.closest(\'[data-builder-cart-add]\')',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testCartJavascriptStillAcceptsProductVersionEvents(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/cart.js');
        self::assertIsString($source);

        foreach([
            'document.addEventListener(\'mg:cart:add\'',
            'detail.product_version_id||detail.productVersionId',
            'C().addProductVersion(id,detail.quantity||1).then(refresh)',
            'window.Microgifter.cart={refresh:refresh,open:openDrawer,close:closeDrawer,addProductVersion:function(id,itemQuantity){return C().addProductVersion(id,itemQuantity).then(refresh);}}',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
