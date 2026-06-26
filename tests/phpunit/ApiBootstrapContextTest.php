<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/api/bootstrap.php';

final class ApiBootstrapContextTest extends TestCase
{
    public function testApiSecurityBoundaryMatchesOnlyDirectApiScripts(): void
    {
        self::assertTrue(mg_is_direct_api_request('/api/public/product.php'));
        self::assertTrue(mg_is_direct_api_request('/microgifter-main/api/health.php'));
        self::assertTrue(mg_is_direct_api_request('C:\\app\\api\\commerce\\cart.php'));

        self::assertFalse(mg_is_direct_api_request('/product.php'));
        self::assertFalse(mg_is_direct_api_request('/includes/market/public-market-ticker.php'));
        self::assertFalse(mg_is_direct_api_request('/account-share-market-admin.php'));
        self::assertFalse(mg_is_direct_api_request(''));
    }
}
