<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductBundleSettlementAccountingV7ContractTest extends TestCase
{
    public function testSettlementMigrationDefinesAccountingAuthority(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../database/20260719_product_bundle_settlement_accounting_v7.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('gift_bundle_component_settlements', $sql);
        self::assertStringContainsString('merchant_net_amount_cents', $sql);
        self::assertStringContainsString('payable_amount_cents', $sql);
        self::assertStringContainsString("'pending','eligible','held','blocked','released','reversed'", $sql);
        self::assertStringContainsString('gift_bundle_settlement_events', $sql);
    }

    public function testSettlementApiIsAccountingOnly(): void
    {
        $php = file_get_contents(__DIR__ . '/../../api/bundles/settlements.php');
        self::assertIsString($php);
        self::assertStringContainsString("transfer_execution_enabled'=>false", $php);
        self::assertStringContainsString('buyer_user_id', file_get_contents(__DIR__ . '/../../api/bundles/_checkout.php'));
        self::assertStringContainsString('merchant_user_id=?', $php);
        self::assertStringContainsString('mg_require_csrf_for_write', $php);
        self::assertStringContainsString('mg_fail_unexpected', $php);
        self::assertStringNotContainsString('stripe_transfers', $php);
        self::assertStringNotContainsString('transfer_data', $php);
    }

    public function testMerchantDashboardLoadsCanonicalSettlementApi(): void
    {
        $page = file_get_contents(__DIR__ . '/../../bundle-settlements.php');
        $js = file_get_contents(__DIR__ . '/../../assets/js/bundle-settlements-v7.js');
        self::assertStringContainsString('data-bundle-settlements', $page);
        self::assertStringContainsString('/api/bundles/settlements.php?action=summary', $js);
        self::assertStringContainsString("action:'reconcile'", $js);
    }
}
