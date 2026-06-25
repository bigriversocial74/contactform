<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampPurchaseFlowContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testStampPurchaseMigrationExists(): void
    {
        $source = $this->read('database/stage_17b_stamp_purchases.sql');
        foreach(['CREATE TABLE IF NOT EXISTS stamp_purchases','bundle_key','stamps_snapshot','price_cents_snapshot','checkout_reference','credited_ledger_entry_public_id','stage_17b_stamp_purchases'] as $needle){
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testPurchaseEndpointsExist(): void
    {
        $purchase = $this->read('api/stamps/purchase.php');
        $helper = $this->read('api/stamps/_purchases.php');
        $history = $this->read('api/stamps/purchases.php');
        foreach(['stamp_purchases','stamp:purchase:','bulk_stamp_purchase','sandbox_confirm','mg_stamp_credit'] as $needle){
            self::assertStringContainsString($needle, $purchase . $helper);
        }
        self::assertStringContainsString('checkout_url', $helper);
        self::assertStringContainsString('stamp_purchases', $history);
        self::assertStringContainsString('purchases', $history);
    }

    public function testMerchantStampPageHasBundlePurchaseUi(): void
    {
        $view = $this->read('includes/merchant-stamps-view.php');
        $js = $this->read('assets/js/merchant-stamps.js');
        foreach(['data-stamp-bundle-list','data-stamp-purchase-status','data-stamp-purchase-history','merchant-stamps.js'] as $needle){
            self::assertStringContainsString($needle, $view);
        }
        foreach(['/api/stamps/bundles.php','/api/stamps/purchase.php','/api/stamps/purchases.php','sandbox_confirm','data-buy-stamps','data-confirm-stamps'] as $needle){
            self::assertStringContainsString($needle, $js);
        }
    }
}