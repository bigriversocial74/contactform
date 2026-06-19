<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class V1ProductContractTest extends TestCase
{
    private function source(string $path): string
    {
        $value = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($value, $path);
        return $value;
    }

    public function testCanonicalV1RoutesAndLifecycleAreDocumented(): void
    {
        $contract = $this->source('docs/V1_CORE_ROUTES.md');

        foreach ([
            '/api/catalog/builder-draft.php',
            '/api/commerce/cart-items.php',
            '/api/payments/sandbox-confirm.php',
            '/api/account/action-center-send.php',
            '/api/merchant/microgift-claim.php',
            'Regift',
            'Follow Up',
            'Claimed',
        ] as $required) {
            self::assertStringContainsString($required, $contract);
        }
    }

    public function testProductVisibilityUsesOneSharedVocabulary(): void
    {
        $editor = $this->source('includes/merchant-product-detail-view.php');
        $client = $this->source('assets/js/merchant-products.js');
        $endpoint = $this->source('api/catalog/builder-draft.php');

        foreach (['public', 'unlisted', 'private'] as $visibility) {
            self::assertStringContainsString('value="' . $visibility . '"', $editor);
            self::assertStringContainsString("'" . $visibility . "'", $client);
            self::assertStringContainsString("'" . $visibility . "'", $endpoint);
        }

        self::assertStringContainsString('function mg_builder_visibility', $endpoint);
        self::assertStringContainsString("\$payload['visibility'] = mg_builder_visibility", $endpoint);
        self::assertStringContainsString("\$payload['visibility'] !== 'public'", $endpoint);
        self::assertStringNotContainsString("(\$payload['visibility'] ?? 'published') !== 'published'", $endpoint);
    }

    public function testPublishingIsAnActionAndNotAVisibilityValue(): void
    {
        $client = $this->source('assets/js/merchant-products.js');
        $endpoint = $this->source('api/catalog/builder-draft.php');

        self::assertStringContainsString("action: action", $client);
        self::assertStringContainsString("if (\$action !== 'publish')", $endpoint);
        self::assertStringContainsString("mg_require_permission('catalog.products.publish')", $endpoint);
        self::assertStringContainsString("status'=>'published'", $endpoint);
    }

    public function testPublishedProductsRequirePositiveValue(): void
    {
        $editor = $this->source('includes/merchant-product-detail-view.php');
        $endpoint = $this->source('api/catalog/builder-draft.php');

        self::assertStringContainsString('name="price" type="number" min="0.01"', $editor);
        self::assertStringContainsString("'minimum_value_cents'=>1", $endpoint);
        self::assertStringContainsString('if ($valueCents < 1)', $endpoint);
    }

    public function testPrivateAndUnlistedProductsRemainDraftOnly(): void
    {
        $editor = $this->source('includes/merchant-product-detail-view.php');
        $endpoint = $this->source('api/catalog/builder-draft.php');

        self::assertStringContainsString('Unlisted draft', $editor);
        self::assertStringContainsString('Private draft', $editor);
        self::assertStringContainsString('Only Public products can be published', $editor);
        self::assertStringContainsString('Set visibility to Public before publishing', $endpoint);
    }
}
