<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductBundleRecipientDeliveryV6ContractTest extends TestCase
{
    private function source(string $path): string
    {
        $value = file_get_contents(dirname(__DIR__,2) . '/' . $path);
        self::assertIsString($value);
        return $value;
    }

    public function testDeliveryEndpointEnforcesBuyerOwnershipAndCsrf(): void
    {
        $source = $this->source('api/bundles/delivery.php');
        self::assertStringContainsString('o.buyer_user_id=?', $source);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $source);
        self::assertStringContainsString('mg_authenticated_user()', $source);
    }

    public function testDeliveryRequiresIssuedMicrogiftAndRecipientEmail(): void
    {
        $source = $this->source('api/bundles/delivery.php');
        self::assertStringContainsString("microgift_public_id", $source);
        self::assertStringContainsString('FILTER_VALIDATE_EMAIL', $source);
        self::assertStringContainsString('still being prepared', $source);
    }

    public function testDeliveryIsRateLimitedAndAudited(): void
    {
        $source = $this->source('api/bundles/delivery.php');
        self::assertStringContainsString("attempts_last_hour", $source);
        self::assertStringContainsString("bundle.component.delivery", $source);
        self::assertStringContainsString('>= 3', $source);
    }

    public function testUnexpectedErrorsUseCentralHandler(): void
    {
        $source = $this->source('api/bundles/delivery.php');
        self::assertStringContainsString('mg_fail_unexpected(', $source);
        self::assertStringNotContainsString('catch (Throwable $e) {\n    mg_fail($e->getMessage()', $source);
    }

    public function testOrderPageLoadsDeliveryAssets(): void
    {
        $page = $this->source('bundle-order.php');
        self::assertStringContainsString('/assets/js/bundle-delivery-v6.js', $page);
        self::assertStringContainsString('/assets/css/bundle-delivery-v6.css', $page);
    }
}
