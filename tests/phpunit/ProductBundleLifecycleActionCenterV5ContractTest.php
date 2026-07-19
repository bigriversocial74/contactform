<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductBundleLifecycleActionCenterV5ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testLifecycleApiUsesBuyerOwnershipAndSafeUnexpectedErrors(): void
    {
        $source = file_get_contents($this->root . '/api/bundles/lifecycle.php');
        self::assertIsString($source);
        self::assertStringContainsString('o.buyer_user_id=?', $source);
        self::assertStringContainsString('mg_fail_unexpected', $source);
        self::assertStringContainsString('microgift_instances', $source);
        self::assertStringContainsString('action_center_url', $source);
    }

    public function testParentAndComponentViewsArePresent(): void
    {
        $page = file_get_contents($this->root . '/bundle-orders.php');
        $detail = file_get_contents($this->root . '/bundle-order.php');
        $runtime = file_get_contents($this->root . '/assets/js/bundle-lifecycle-v5.js');
        self::assertStringContainsString('data-bundle-orders-page', (string)$page);
        self::assertStringContainsString('data-bundle-order', (string)$detail);
        self::assertStringContainsString('/api/bundles/lifecycle.php?action=list', (string)$runtime);
        self::assertStringContainsString('/api/bundles/lifecycle.php?action=detail', (string)$runtime);
        self::assertStringContainsString('Open Microgift', (string)$runtime);
    }

    public function testNoSettlementOrTransferExecutionIsAdded(): void
    {
        $files = [
            '/api/bundles/lifecycle.php',
            '/assets/js/bundle-lifecycle-v5.js',
        ];
        foreach ($files as $file) {
            $source = file_get_contents($this->root . $file);
            self::assertDoesNotMatchRegularExpression('/stripe_transfers|transfer_data|settlement_release/', (string)$source);
        }
    }
}
