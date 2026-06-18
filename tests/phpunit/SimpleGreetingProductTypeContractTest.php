<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/api/catalog/_builder_product_types.php';

final class SimpleGreetingProductTypeContractTest extends TestCase
{
    private function source(string $path): string
    {
        $value=file_get_contents(dirname(__DIR__,2).'/'.$path);
        self::assertIsString($value,$path);
        return $value;
    }

    public function testSimpleProductAllowsOnlyCoverMedia(): void
    {
        self::assertSame(['cover'],mg_builder_allowed_asset_roles('simple_product'));
        mg_builder_validate_publish_type('simple_product',['headline'=>'Direct voucher'],['cover'=>'asset']);
        $this->expectException(InvalidArgumentException::class);
        mg_builder_validate_publish_type('simple_product',[],['inside_cover'=>'asset']);
    }

    public function testGreetingCardRequiresHeadlineAndInsideMessage(): void
    {
        mg_builder_validate_publish_type('greeting_card',[
            'headline'=>'A gift for you',
            'message'=>'Enjoy this local gift.',
        ],['cover'=>'asset','inside_cover'=>'asset']);
        self::assertTrue(true);
    }

    public function testGreetingCardRejectsMissingMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('inside greeting-card message');
        mg_builder_validate_publish_type('greeting_card',['headline'=>'A gift for you'],[]);
    }

    public function testPublicCatalogHasSeparateSimpleAndGreetingRenderers(): void
    {
        $source=$this->source('assets/js/public-catalog.js');
        foreach([
            'function renderSimpleProduct(product)',
            'function renderGreetingCard(product)',
            "builderType === 'greeting_card'",
            'data-greeting-card-open',
            'aria-expanded',
            'data-greeting-card-inside',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testPublicProductApiReturnsNormalizedBuilderTypeAndMediaRoles(): void
    {
        $source=$this->source('api/public/product.php');
        self::assertStringContainsString("\$product['builder_type']",$source);
        self::assertStringContainsString("\$product['media_by_role']",$source);
    }

    public function testBuilderShowsOnlyRelevantFieldsForSelectedType(): void
    {
        $sidebar=$this->source('includes/product-builder-sidebar.php');
        $client=$this->source('assets/js/builder-product-types.js');
        self::assertStringContainsString('data-builder-types="greeting_card multimedia_greeting_card"',$sidebar);
        self::assertStringContainsString('data-builder-types="multimedia_greeting_card"',$sidebar);
        self::assertStringContainsString('function updateTypeControls()', $client);
    }
}
