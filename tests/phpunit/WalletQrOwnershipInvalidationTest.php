<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WalletQrOwnershipInvalidationTest extends TestCase
{
    public function testWalletRewardRegiftRevokesOldQrAndEveryUseRechecksCurrentOwner(): void
    {
        $root = dirname(__DIR__, 2);
        $sendSource = file_get_contents($root . '/api/account/action-center-send.php');
        $tokenSource = file_get_contents($root . '/api/account/_claim_voucher_token.php');
        $scannerSource = file_get_contents($root . '/api/merchant/scanner-claim-trust.php');
        $tokenEndpoint = file_get_contents($root . '/api/account/action-center-voucher-token.php');
        $qrEndpoint = file_get_contents($root . '/api/account/action-center-voucher-qr.php');

        self::assertIsString($sendSource);
        self::assertIsString($tokenSource);
        self::assertIsString($scannerSource);
        self::assertIsString($tokenEndpoint);
        self::assertIsString($qrEndpoint);

        foreach ([
            "require_once __DIR__ . '/_claim_voucher_token.php';",
            'mg_wallet_claim_voucher_revoke_stale_owner_tokens($pdo, (int)$item[\'id\'], $recipientUserId)',
            "UPDATE wallet_items SET user_id=?",
            "'revoked_qr_tokens' => $revokedVoucherTokens",
        ] as $needle) {
            self::assertStringContainsString($needle, $sendSource);
        }

        $revokePosition = strpos($sendSource, 'mg_wallet_claim_voucher_revoke_stale_owner_tokens($pdo, (int)$item[\'id\'], $recipientUserId)');
        $ownerUpdatePosition = strpos($sendSource, 'UPDATE wallet_items SET user_id=?');
        self::assertIsInt($revokePosition);
        self::assertIsInt($ownerUpdatePosition);
        self::assertLessThan($ownerUpdatePosition, $revokePosition, 'Old wallet-backed reward QR tokens must be revoked before ownership is changed in the same transaction.');

        foreach ([
            'function mg_wallet_claim_voucher_revoke_stale_owner_tokens(',
            "SET status='revoked',revoked_at=COALESCE(revoked_at,NOW()),updated_at=NOW()",
            "user_id<>? AND status IN ('issued','scanned')",
            'mg_wallet_claim_voucher_revoke_stale_owner_tokens($pdo, $walletItemId, $userId);',
            '$tokenUserId = (int)($row[\'user_id\'] ?? 0);',
            '$walletUserId = (int)($row[\'wallet_user_id\'] ?? 0);',
            '$tokenUserId !== $walletUserId',
            '$tokenMerchantUserId !== $walletMerchantUserId',
            'mg_wallet_claim_voucher_revoke_token($pdo, (int)($row[\'id\'] ?? 0));',
            'Wallet voucher QR token no longer belongs to the current reward owner.',
        ] as $needle) {
            self::assertStringContainsString($needle, $tokenSource);
        }

        self::assertStringContainsString('mg_wallet_claim_voucher_require_active', $scannerSource);
        self::assertStringContainsString('mg_wallet_claim_voucher_require_active', $qrEndpoint);
        self::assertStringContainsString('mg_wallet_claim_voucher_issue_token', $tokenEndpoint);
        self::assertStringContainsString("'wallet-' . $walletPublicId", $tokenSource);
    }
}
