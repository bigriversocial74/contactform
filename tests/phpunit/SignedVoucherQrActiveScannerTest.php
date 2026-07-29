<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SignedVoucherQrActiveScannerTest extends TestCase
{
    public function testSignedQrPayloadsReachTheActiveMerchantScannerWithoutTruncation(): void
    {
        $root = dirname(__DIR__, 2);
        $scannerClient = file_get_contents($root . '/assets/js/merchant-scanner-cleanup.js');
        $scannerOps = file_get_contents($root . '/api/merchant/scanner-claim-ops.php');
        $scannerTrust = file_get_contents($root . '/api/merchant/scanner-claim-trust.php');
        $tokenEndpoint = file_get_contents($root . '/api/account/action-center-voucher-token.php');
        $qrEndpoint = file_get_contents($root . '/api/account/action-center-voucher-qr.php');

        self::assertIsString($scannerClient);
        self::assertIsString($scannerOps);
        self::assertIsString($scannerTrust);
        self::assertIsString($tokenEndpoint);
        self::assertIsString($qrEndpoint);

        self::assertStringContainsString("window.MicrogifterMerchantScannerRuntime = 'cleanup-v3-signed-token-preservation'", $scannerClient);
        self::assertStringContainsString('function isSignedVoucherPayload(value)', $scannerClient);
        self::assertStringContainsString('if (isSignedVoucherPayload(value)) return value;', $scannerClient);
        self::assertStringContainsString('MGFT-(?:WALLET-)?CLAIM-TOKEN', $scannerClient);
        self::assertStringContainsString("url.searchParams.get('wt')", $scannerClient);
        self::assertStringContainsString("url.searchParams.get('wallet_token')", $scannerClient);
        self::assertStringContainsString("url.searchParams.get('wallet_voucher_token')", $scannerClient);
        self::assertStringContainsString("/(?:^|[^A-Z0-9])(GFT-[A-Z0-9-]+)/i", $scannerClient);
        self::assertStringNotContainsString("value.match(/GFT-[A-Z0-9-]+/i)", $scannerClient);

        $preservePosition = strpos($scannerClient, 'if (isSignedVoucherPayload(value)) return value;');
        $genericGiftPosition = strpos($scannerClient, 'var match = value.match(/(?:^|[^A-Z0-9])(GFT-[A-Z0-9-]+)/i);');
        self::assertIsInt($preservePosition);
        self::assertIsInt($genericGiftPosition);
        self::assertLessThan($genericGiftPosition, $preservePosition, 'Signed token payloads must be returned before generic gift-ID parsing runs.');

        self::assertStringContainsString("'/api/merchant/scanner-claim-ops.php'", $scannerClient);
        self::assertStringContainsString("require __DIR__ . '/scanner-claim-trust.php';", $scannerOps);

        foreach ([
            "_action_center_wallet.php",
            "['wt','wallet_token','wallet_voucher_token']",
            'MGFT-WALLET-CLAIM-TOKEN|',
            'mgwv1_',
            'mg_wallet_claim_voucher_require_active',
            'mg_wallet_claim_voucher_mark_scanned',
            'mg_wallet_claim_voucher_mark_redeemed',
            'function mg_scanner_trust_wallet(',
            'function mg_scanner_trust_wallet_completed_redemption(',
            'function mg_scanner_trust_wallet_redemption_cycle(',
            'wallet_item_redemptions',
            "UPDATE wallet_items SET status='redeemed'",
            "'is_wallet_reward' => true",
            "['type' => 'wallet_reward']",
        ] as $needle) {
            self::assertStringContainsString($needle, $scannerTrust);
        }

        foreach ([
            'MGFT-CLAIM-TOKEN|',
            'mgv1_',
            'mg_claim_voucher_require_active',
            'mg_claim_voucher_mark_scanned',
            'mg_claim_voucher_mark_redeemed',
        ] as $needle) {
            self::assertStringContainsString($needle, $scannerTrust);
        }

        self::assertStringContainsString("'scan_payload' => $issued['scan_payload']", $tokenEndpoint);
        self::assertStringContainsString("'qr_image_url' => '/api/account/action-center-voucher-qr.php?t='", $tokenEndpoint);
        self::assertStringContainsString("'qr_image_url' => '/api/account/action-center-voucher-qr.php?wt='", $tokenEndpoint);
        self::assertStringContainsString('mg_claim_voucher_scan_payload($token)', $qrEndpoint);
        self::assertStringContainsString('mg_wallet_claim_voucher_scan_payload($walletToken)', $qrEndpoint);
    }
}
