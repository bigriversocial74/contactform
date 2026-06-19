<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class V1ProductPurchasePathTest extends TestCase
{
    private function source(string $path): string
    {
        $value = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($value, $path);
        return $value;
    }

    public function testPublishedProductUrlCarriesImmutableIdentity(): void
    {
        $catalog = $this->source('api/catalog/_catalog.php');
        $builder = $this->source('api/catalog/builder-draft.php');
        $storefront = $this->source('api/storefront/profile-v1.php');

        self::assertStringContainsString("'/product.php?id='", $catalog);
        self::assertStringContainsString('mg_catalog_public_product_url($productId,$slug)', $builder);
        self::assertStringContainsString("'/product.php?id='", $storefront);
        self::assertStringContainsString("'&p='", $storefront);
    }

    public function testPublicProductPageUsesTheImmutableIdWhenAvailable(): void
    {
        $page = $this->source('product.php');
        $identity = $this->source('api/catalog/_public_identity.php');
        $endpoint = $this->source('api/public/product.php');

        self::assertStringContainsString("\$product_lookup = \$product_id !== '' ? \$product_id : \$product_slug;", $page);
        self::assertStringContainsString('data-product-slug="<?= mg_e($product_lookup) ?>"', $page);
        self::assertStringContainsString('function mg_catalog_public_product_by_id', $identity);
        self::assertStringContainsString("preg_match('/^[0-9a-f]{8}-", $identity);
        self::assertStringContainsString('mg_catalog_resolve_public_product_identity', $endpoint);
        self::assertStringContainsString('WHERE cp.public_id = ?', $endpoint);
    }

    public function testLegacySlugLinksFailClosedWhenAmbiguous(): void
    {
        $identity = $this->source('api/catalog/_public_identity.php');

        self::assertStringContainsString('ORDER BY id ASC LIMIT 2', $identity);
        self::assertStringContainsString("'Product link is ambiguous.'", $identity);
        self::assertStringContainsString('count($matches) !== 1', $identity);
    }

    public function testStorefrontReturnsReadyAssetsAndCanonicalProductLinks(): void
    {
        $route = $this->source('api/storefront/profile.php');
        $wrapper = $this->source('api/storefront/profile-v1.php');

        self::assertStringContainsString("require __DIR__ . '/profile-v1.php';", $route);
        self::assertStringContainsString("status='ready'", $wrapper);
        self::assertStringContainsString("\$product['product_url']", $wrapper);
        self::assertStringContainsString("unset(\$product['cover_asset_id'])", $wrapper);
    }

    public function testCartAuthorityIsThePublishedImmutableVersion(): void
    {
        $catalogClient = $this->source('assets/js/public-catalog.js');
        $commerceClient = $this->source('assets/js/customer-commerce.js');
        $cartEndpoint = $this->source('api/commerce/cart-items.php');
        $foundation = $this->source('api/commerce/_foundation.php');

        self::assertStringContainsString('data-product-version-id=', $catalogClient);
        self::assertStringContainsString("'/api/commerce/cart-items.php'", $commerceClient);
        self::assertStringContainsString('product_version_id', $cartEndpoint);
        self::assertStringContainsString('mg_resolve_published_product_version', $cartEndpoint);
        self::assertStringContainsString("v.version_status='published'", $foundation);
        self::assertStringContainsString("p.status='published'", $foundation);
    }
}
