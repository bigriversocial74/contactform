<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampPurchaseCompletionContractTest extends TestCase
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

    public function testSharedPurchaseCompletionServiceExists(): void
    {
        $source = $this->read('api/stamps/_purchases.php');
        foreach(['function mg_stamp_purchase_complete','function mg_stamp_purchase_load','bulk_stamp_purchase','bundle_purchase_payment_complete','credited_ledger_entry_public_id','stamp:purchase:'] as $needle){
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testPurchaseCompleteEndpointExists(): void
    {
        $source = $this->read('api/stamps/purchase-complete.php');
        foreach(['mg_stamp_purchase_load','mg_stamp_purchase_complete','provider_status','purchase_id','checkout_reference'] as $needle){
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testMerchantClientCompletesReturnedPurchase(): void
    {
        $source = $this->read('assets/js/merchant-stamps.js');
        foreach(['/api/stamps/purchase-complete.php','data-complete-purchase','new URLSearchParams','Stamps added to your balance'] as $needle){
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testAdminPurchaseReportExists(): void
    {
        $api = $this->read('api/stamps/purchase-report.php');
        $panel = $this->read('includes/admin-stamp-bundles-panel.php');
        $js = $this->read('assets/js/admin-stamp-sales.js');
        self::assertStringContainsString('stamp_purchases', $api);
        self::assertStringContainsString('data-admin-stamp-purchase-list', $panel);
        self::assertStringContainsString('/api/stamps/purchase-report.php', $js);
    }
}
