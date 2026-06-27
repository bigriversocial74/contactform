<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ClaimVoucherQrScannerContractTest extends TestCase
{
    public function testClaimModalCreatesVoucherQrAndScannerAcceptsActionItemPayloads(): void
    {
        $qrSource=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center-claim-qr.js');
        $scannerSource=file_get_contents(dirname(__DIR__,2).'/api/merchant/scanner-claim.php');
        self::assertIsString($qrSource);
        self::assertIsString($scannerSource);

        foreach([
            '/claim-voucher.php?gift=',
            'data-action-form="claim"',
            'Customer voucher QR',
            'api.qrserver.com/v1/create-qr-code',
            'data-copy-voucher-id',
        ] as $needle){
            self::assertStringContainsString($needle,$qrSource);
        }

        foreach([
            "'action_item'",
            "'action_item_id'",
            "'voucher'",
            'function mg_scanner_claim_microgift_lookup',
            'microgift_inbox_items ac',
            'function mg_scanner_claim_notify_many',
            "'microgift_redeemed'",
            "'gift_claimed'",
            "'notifications' => true",
        ] as $needle){
            self::assertStringContainsString($needle,$scannerSource);
        }
    }
}
