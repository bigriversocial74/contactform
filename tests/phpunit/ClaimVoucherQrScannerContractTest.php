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
        $walletMigration=file_get_contents($root.'/database/stage_18ah_wallet_claim_integrity.sql');
        self::assertIsString($qrSource);
        self::assertIsString($scannerSource);
        self::assertIsString($tokenEndpoint);
        self::assertIsString($qrEndpoint);
        self::assertIsString($claimEndpoint);
        self::assertIsString($tokenHelper);
        self::assertIsString($migration);
        self::assertIsString($walletMigration);

        foreach([
            '/api/account/action-center-voucher-token.php?action_item_id=',
            '/api/account/action-center-voucher-claim.php',
            'signed, short-lived voucher token',
            'signed, short-lived wallet reward token',
            'qr_image_url',
            'data-voucher-scan-payload',
            'data-copy-voucher-id',
            'data-voucher-claim-form',
            'merchant_claim_code',
            'type="password"',
            'autocomplete="off"',
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
            'CREATE TABLE IF NOT EXISTS wallet_claim_voucher_tokens',
            'CREATE TABLE IF NOT EXISTS wallet_item_redemptions',
            'CREATE TABLE IF NOT EXISTS action_center_voucher_claim_attempts',
            'idx_merchant_claim_codes_lookup_hash',
        ] as $needle){
            self::assertStringContainsString($needle,$walletMigration);
        }

        foreach([
            'mg_claim_voucher_issue_token(PDO $pdo',
            'claim_voucher_tokens',
            'mg_claim_voucher_mark_scanned',
            'mg_claim_voucher_mark_redeemed',
            'mg_claim_voucher_scan_payload',
            'mg_wallet_claim_voucher_issue_token',
            'mg_wallet_claim_voucher_require_active',
            'MGFT-WALLET-CLAIM-TOKEN|',
            'hash_equals',
        ] as $needle){
            self::assertStringContainsString($needle,$tokenHelper.$tokenEndpoint);
        }

        foreach([
            '_action_center_wallet.php',
            'mgwv1_',
            'wallet_item_redemptions',
            'action_center_voucher_claim_attempts',
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
            'wallet_token',
        ] as $needle){
            self::assertStringContainsString($needle,$qrEndpoint);
        }

        foreach([
            'MGFT-CLAIM-TOKEN|',
            'MGFT-WALLET-CLAIM-TOKEN|',
            'mg_claim_voucher_require_active($pdo',
            'mg_wallet_claim_voucher_require_active',
            'mg_claim_voucher_mark_scanned',
            'mg_wallet_claim_voucher_mark_scanned',
            'mg_claim_voucher_mark_redeemed',
            'mg_wallet_claim_voucher_mark_redeemed',
            'function mg_scanner_claim_microgift_lookup',
            'function mg_scanner_claim_process_wallet',
            'wallet_item_redemptions',
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
            'function mg_ac_voucher_find_claim_code',
            'mg_ac_voucher_log_attempt',
            'mg_ac_voucher_recent_failed_attempts',
            'merchant_claim_code',
            'microgift_redemptions',
            'wallet_item_redemptions',
            "status='completed'",
            "UPDATE wallet_items SET status='redeemed'",
            'usage_count=usage_count+1',
            '$pdo->commit();',
        ] as $needle){
            self::assertStringContainsString($needle,$claimEndpoint);
        }
    }
}