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
        foreach(['stamp_purchases','purchases','payment_intent','provider_intent_reference','receipt_url','owner_scoped'] as $needle){
            self::assertStringContainsString($needle, $history);
        }
    }

    public function testMerchantStampPageHasBundlePurchaseUi(): void
    {
        $view = $this->read('includes/merchant-stamps-view.php');
        $js = $this->read('assets/js/merchant-stamps.js');
        foreach(['data-stamp-bundle-list','data-stamp-purchase-status','data-stamp-purchase-history','stamp-purchase-history','History & Receipts','merchant-stamps.js'] as $needle){
            self::assertStringContainsString($needle, $view);
        }
        foreach(['/api/stamps/bundles.php','/api/stamps/purchase.php','/api/stamps/purchases.php','sandbox_confirm','data-buy-stamps','data-confirm-stamps','receipt_url','/stamp-receipt.php?purchase=','provider_intent_reference','Checkout status'] as $needle){
            self::assertStringContainsString($needle, $js);
        }
    }

    public function testMerchantReceiptIsOwnerScopedAndPrintable(): void
    {
        $receiptPage = $this->read('stamp-receipt.php');
        $receiptJs = $this->read('assets/js/stamp-receipt.js');
        $receiptApi = $this->read('api/stamps/purchase-receipt.php');

        foreach(['mg_require_auth','data-stamp-receipt-page','data-print-stamp-receipt','Print / Save PDF','data-purchase-id','stamp-receipt.js'] as $needle){
            self::assertStringContainsString($needle, $receiptPage);
        }

        foreach(['/api/stamps/purchase-receipt.php?purchase_id=','window.print','receipt_id','provider_intent_reference','credited_ledger_entry_id','owner-scoped','Ledger credit ID'] as $needle){
            self::assertStringContainsString($needle, $receiptJs);
        }

        foreach(['mg_require_api_user','mg_require_method(\'GET\')','mg_stamp_purchase_load($pdo, $accountUserId','mg_stamp_purchase_find_intent','stamp_ledger_entries','bulk_stamp_purchase','receipt_url','owner_scoped','admin_links'=>[],'Print / Save PDF'] as $needle){
            self::assertStringContainsString($needle, $receiptApi);
        }

        self::assertStringNotContainsString('/stamp-payment-reconciliation.php', $receiptPage . $receiptJs . $receiptApi, 'Merchant receipt must not expose admin reconciliation links.');
        self::assertStringNotContainsString('/api/stamps/audit-timeline.php', $receiptPage . $receiptJs . $receiptApi, 'Merchant receipt must not expose admin timeline links.');
    }
}
