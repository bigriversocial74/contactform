<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductBundleRefundsReversalsV10ContractTest extends TestCase
{
    public function testPhaseTenFilesContainRequiredSafetyContracts(): void
    {
        $root = dirname(__DIR__, 2);
        $api = file_get_contents($root . '/api/admin/bundle-settlement-adjustments.php');
        $sql = file_get_contents($root . '/database/20260719_product_bundle_refunds_reversals_v10.sql');
        $page = file_get_contents($root . '/admin/bundle-settlement-adjustments.php');

        self::assertIsString($api);
        self::assertStringContainsString('mg_require_csrf_for_write', $api);
        self::assertStringContainsString('commerce.manage', $api);
        self::assertStringContainsString('idempotency_key', $api);
        self::assertStringContainsString('provider_dispatch_required', $api);
        self::assertStringNotContainsString("'/v1/transfers", $api);
        self::assertStringNotContainsString("'/v1/transfers/", $api);
        self::assertStringContainsString('gift_bundle_settlement_adjustments', $sql);
        self::assertStringContainsString('reversal_request', $sql);
        self::assertStringContainsString('Refunds, disputes and reversals', $page);
    }
}
