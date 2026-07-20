<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductBundleProviderReconciliationV11ContractTest extends TestCase
{
    public function testPhaseElevenContractsArePresent(): void
    {
        $root = dirname(__DIR__, 2);
        $files = [
            'database/20260720_product_bundle_provider_dispatch_reconciliation_v11.sql',
            'api/bundles/_provider_reconciliation.php',
            'scripts/process_bundle_provider_transfers.php',
            'api/webhooks/bundle-stripe-transfers.php',
            'api/admin/bundle-provider-reconciliation.php',
            'admin/bundle-provider-reconciliation.php',
        ];
        foreach ($files as $file) {
            self::assertFileExists($root . '/' . $file);
        }

        $worker = file_get_contents($root . '/scripts/process_bundle_provider_transfers.php');
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $worker);
        self::assertStringContainsString("'/v1/transfers'", $worker);
        self::assertStringContainsString('bundle-transfer-', $worker);

        $authority = file_get_contents($root . '/api/bundles/_provider_reconciliation.php');
        self::assertStringContainsString('MG_BUNDLE_PROVIDER_DISPATCH_ENABLED', $authority);
        self::assertStringContainsString('MG_BUNDLE_PROVIDER_LIVE_ENABLED', $authority);
        self::assertStringContainsString("readiness_status='released'", $authority);

        $webhook = file_get_contents($root . '/api/webhooks/bundle-stripe-transfers.php');
        self::assertStringContainsString("mg_payment_verify_signature('stripe'", $webhook);
        self::assertStringContainsString('gift_bundle_provider_events', $webhook);
        self::assertStringContainsString("transfer.reversed", $webhook);

        $manifest = file_get_contents($root . '/config/migrations.php');
        self::assertStringContainsString('20260720_product_bundle_provider_dispatch_reconciliation_v11.sql', $manifest);
    }
}
