<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ClaimVoucherQrScannerContractTest extends TestCase
{
    public function testClaimModalCreatesFirstPartyDatabaseBackedVoucherQrAndScannerAcceptsTokenPayloads(): void
    {
        $root=dirname(__DIR__,2);
        $qrSource=file_get_contents($root.'/assets/js/gift-action-center-claim-qr.js');
        $scannerSource=file_get_contents($root.'/api/merchant/scanner-claim.php');
        $tokenEndpoint=file_get_contents($root.'/api/account/action-center-voucher-token.php');
        $qrEndpoint=file_get_contents($root.'/api/account/action-center-voucher-qr.php');
        $tokenHelper=file_get_contents($root.'/api/account/_claim_voucher_token.php');
        $migration=file_get_contents($root.'/database/stage_18ac_claim_voucher_tokens.sql');
        self::assertIsString($qrSource);
        self::assertIsString($scannerSource);
        self::assertIsString($tokenEndpoint);
        self::assertIsString($qrEndpoint);
        self::assertIsString($tokenHelper);
        self::assertIsString($migration);

        foreach([
            '/api/account/action-center-voucher-token.php?action_item_id=',
            'signed, short-lived voucher token',
            'qr_image_url',
            'data-voucher-scan-payload',
            'data-copy-voucher-id',
        ] as $needle){
            self::assertStringContainsString($needle,$qrSource);
        }
        self::assertStringNotContainsString('api.qrserver.com',$qrSource.$tokenEndpoint);

        foreach([
            'CREATE TABLE IF NOT EXISTS claim_voucher_tokens',
            'token_hash CHAR(64) NOT NULL',
            "status ENUM('issued','scanned','redeemed','revoked','expired')",
            'scanner_location_id BIGINT UNSIGNED NULL',
        ] as $needle){
            self::assertStringContainsString($needle,$migration);
        }

        foreach([
            'mg_claim_voucher_issue_token(PDO $pdo',
            'claim_voucher_tokens',
            'mg_claim_voucher_mark_scanned',
            'mg_claim_voucher_mark_redeemed',
            'mg_claim_voucher_scan_payload',
            'hash_equals',
        ] as $needle){
            self::assertStringContainsString($needle,$tokenHelper.$tokenEndpoint);
        }

        foreach([
            'function mg_qr_svg',
            'shape-rendering="crispEdges"',
            'mg_qr_rs_remainder',
            'Content-Type: image/svg+xml',
        ] as $needle){
            self::assertStringContainsString($needle,$qrEndpoint);
        }

        foreach([
            'MGFT-CLAIM-TOKEN|',
            'mg_claim_voucher_require_active($pdo',
            'mg_claim_voucher_mark_scanned',
            'mg_claim_voucher_mark_redeemed',
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
