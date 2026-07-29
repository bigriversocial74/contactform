<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ClaimManualFallbackRecoveryTest extends TestCase
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

    public function testInboxLoadsManualRecoveryAfterTheActiveClaimModal(): void
    {
        $inbox = $this->read('inbox.php');
        $modalPosition = strpos($inbox, '/assets/js/gift-action-center-claim-modal.js');
        $recoveryPosition = strpos($inbox, '/assets/js/gift-action-center-claim-recovery.js');

        self::assertNotFalse($modalPosition);
        self::assertNotFalse($recoveryPosition);
        self::assertGreaterThan($modalPosition, $recoveryPosition);
    }

    public function testQrPreparationFailureFallsBackToTheExistingManualClaimFlow(): void
    {
        $recovery = $this->read('assets/js/gift-action-center-claim-recovery.js');

        foreach ([
            'data-manual-claim-recovery',
            'data-claim-step="claim"',
            'name="merchant_claim_code"',
            'data-claim-retry',
            'Manual merchant claim code',
            'manual claim remains available',
            'MutationObserver',
        ] as $needle) {
            self::assertStringContainsString($needle, $recovery);
        }

        self::assertStringContainsString('NON_RECOVERABLE_ITEM_ERROR', $recovery);
        self::assertStringContainsString('RECOVERABLE_TOKEN_ERROR', $recovery);
        self::assertStringNotContainsString('/api/account/action-center-voucher-claim.php', $recovery, 'Recovery must reuse the canonical active claim engine instead of introducing a second submit authority.');
        self::assertStringNotContainsString('voucher-claimed', $recovery, 'Recovery must not manufacture a successful claim state.');
    }

    public function testBackendKeepsVoucherTokenOptionalAndRetainsClaimAuthorities(): void
    {
        $endpoint = $this->read('api/account/action-center-voucher-claim.php');
        $activeModal = $this->read('assets/js/gift-action-center-claim-modal.js');

        self::assertStringContainsString('mg_ac_voucher_mark_optional_micro_token', $endpoint);
        self::assertStringContainsString('mg_ac_voucher_mark_optional_wallet_token', $endpoint);
        self::assertStringContainsString("if (\$token === '' || !str_starts_with(\$token, 'mgv1_')) return;", $endpoint);
        self::assertStringContainsString("if (\$token === '' || !str_starts_with(\$token, 'mgwv1_')) return;", $endpoint);

        foreach ([
            'mg_ac_voucher_assert_not_locked',
            'mg_ac_voucher_find_claim_code',
            'mg_ac_voucher_completed_microgift_redemption',
            'mg_ac_voucher_completed_wallet_redemption',
            'owner_user_id',
            'expires_at',
            '$pdo->commit();',
        ] as $needle) {
            self::assertStringContainsString($needle, $endpoint);
        }

        self::assertStringContainsString('voucher_token: state.token', $activeModal);
        self::assertStringContainsString('/api/account/action-center-voucher-claim.php', $activeModal);
    }
}
