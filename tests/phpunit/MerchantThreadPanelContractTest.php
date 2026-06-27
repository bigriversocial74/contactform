<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantThreadPanelContractTest extends TestCase
{
    public function testMerchantThreadPanelFilesExist(): void
    {
        $root=dirname(__DIR__,2);
        self::assertFileExists($root.'/api/merchant/customer-thread.php');
        self::assertStringContainsString('wallet_item.merchant_reply_sent',(string)file_get_contents($root.'/api/merchant/customer-thread.php'));
        self::assertStringContainsString('data-merchant-customer-thread-panel',(string)file_get_contents($root.'/includes/merchant-notifications-view.php'));
        self::assertStringContainsString('loadThread',(string)file_get_contents($root.'/assets/js/merchant-notifications.js'));
    }
}
