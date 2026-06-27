<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ClaimVoucherQrScannerContractTest extends TestCase
{
    public function testClaimModalCreatesSignedVoucherQrAndScannerAcceptsActionItemPayloads(): void
    {
        $qrSource=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center-claim-qr.js');
        $scannerSource=file_get_contents(dirname(__DIR__,2).'/api/merchant/scanner-claim.php');
        $tokenEndpoint=file_get_contents(dirname(__DIR__,2).'/api/account/action-center-voucher-token.php');
        $tokenHelper=file_get_contents(dirname(__DIR__,2).'/api/account/_claim_voucher_token.php');
        self::assertIsString($qrSource);
        self::assertIsString($scannerSource);
        self::assertIsString($tokenEndpoint);
        self::assertIsString($tokenHelper);

        foreach([
            '/api/account/action-center-voucher-token.php?action_item_id=',
            'scan_payload',
            'signed, short-lived voucher token',
            'Customer voucher QR',
            'qr_image_url',
            'data-copy-voucher-id',
        ] as $needle){
            self::assertStringContainsString($needle,$qrSource);
        }

        foreach([
            "'t'",
            "'token'",
            "'action_item'",
            "'action_item_id'",
            "'voucher'",
            'mg_claim_voucher_decode_token',
            'function mg_scanner_claim_microgift_lookup',
            'microgift_inbox_items ac',
            'function mg_scanner_claim_notify_many',
            "'microgift_redeemed'",
            "'gift_claimed'",
            "'notifications' => true",
        ] as $needle){
            self::assertStringContainsString($needle,$scannerSource);
        }

        foreach([
            'mg_claim_voucher_issue_token',
            'microgifter_claim_voucher',
            'hash_hmac',
            'hash_equals',
            'Claim voucher token has expired',
        ] as $needle){
            self::assertStringContainsString($needle,$tokenHelper.$tokenEndpoint);
        }
    }
}
