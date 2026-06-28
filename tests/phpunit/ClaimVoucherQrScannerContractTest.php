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
        $claimEndpoint=file_get_contents($root.'/api/account/action-center-voucher-claim.php');
        $tokenHelper=file_get_contents($root.'/api/account/_claim_voucher_token.php');
        $migration=file_get_contents($root.'/database/stage_18ac_claim_voucher_tokens.sql');
        self::assertIsString($qrSource);
        self::assertIsString($scannerSource);
        self::assertIsString($tokenEndpoint);
        self::assertIsString($qrEndpoint);
        self::assertIsString($claimEndpoint);
        self::assertIsString($tokenHelper);
        self::assertIsString($migration);

        foreach([
            '/api/account/action-center-voucher-token.php?action_item_id=',
            '/api/account/action-center-voucher-claim.php',
            'signed, short-lived voucher token',
            'qr_image_url',
            'data-voucher-scan-payload',
            'data-copy-voucher-id',
            'data-voucher-claim-form',
            'merchant_claim_code',
            'Verify & claim',
            'unless a refund reverses this redemption',
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
            '_action_center_wallet.php',
            'MGFT-WALLET-CLAIM|',
            'is_wallet_reward',
            'This gift has already been claimed. A refund must be issued before it can be claimed again.',
        ] as $needle){
            self::assertStringContainsString($needle,$tokenEndpoint.$qrEndpoint.$scannerSource.$claimEndpoint);
        }

        foreach([
            'function mg_qr_svg',
            'shape-rendering="crispEdges"',
            'mg_qr_rs_remainder',
            'Content-Type: image/svg+xml',
            'action_item_id',
        ] as $needle){
            self::assertStringContainsString($needle,$qrEndpoint);
        }

        foreach([
            'MGFT-CLAIM-TOKEN|',
            'mg_claim_voucher_require_active($pdo',
            'mg_claim_voucher_mark_scanned',
            'mg_claim_voucher_mark_redeemed',
            'function mg_scanner_claim_microgift_lookup',
            'function mg_scanner_claim_process_wallet',
            'microgift_inbox_items ac',
            'function mg_scanner_claim_notify_many',
            "'microgift_redeemed'",
            "'gift_claimed'",
            "'notifications' => true",
            '$redemptionPublicId',
        ] as $needle){
            self::assertStringContainsString($needle,$scannerSource);
        }

        foreach([
            'function mg_ac_voucher_match_claim_code',
            'merchant_claim_code',
            'microgift_redemptions',
            "status='completed'",
            "UPDATE wallet_items SET status='redeemed'",
            'usage_count=usage_count+1',
            '$pdo->commit();',
        ] as $needle){
            self::assertStringContainsString($needle,$claimEndpoint);
        }
    }
}