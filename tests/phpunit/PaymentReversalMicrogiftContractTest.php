<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PaymentReversalMicrogiftContractTest extends TestCase
{
    public function testRefundAndDisputeAuthoritiesCallCanonicalMicrogiftReconciliation(): void
    {
        $refund=file_get_contents(dirname(__DIR__,2).'/api/payments/_refund.php');
        $disputes=file_get_contents(dirname(__DIR__,2).'/api/payments/_disputes.php');
        self::assertIsString($refund);
        self::assertIsString($disputes);
        self::assertStringContainsString("microgifts/_payment_reconciliation.php",$refund);
        self::assertStringContainsString('mg_microgift_payment_reconcile_order(',$refund);
        self::assertStringContainsString("'after_microgift_reconciliation'",$refund);
        self::assertStringContainsString("microgifts/_payment_reconciliation.php",$disputes);
        self::assertGreaterThanOrEqual(3,substr_count($disputes,'mg_microgift_payment_reconcile_order('));
    }

    public function testReconciliationCoversRefundDisputeRestoreAndRecoveryRules(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/microgifts/_payment_reconciliation.php');
        self::assertIsString($source);
        foreach([
            'full_refund','dispute_opened','dispute_won','dispute_lost_full',
            'microgift_recovery','payment_dispute_suspended','payment_dispute_restored',
            "UPDATE microgift_credentials SET status='revoked'",
            'mg_microgift_payment_refresh_action_center(',
            'Microgift payment reconciliation requires the owning payment transaction.',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('mg_action_center_project_lifecycle(',$source);
    }

    public function testCleanDatabaseValidatorCoversCriticalReversalOutcomes(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/scripts/validate_payment_reversal_microgifts.php');
        self::assertIsString($source);
        foreach([
            'full_refund_revoked','refund_credentials_revoked','refund_action_center_reconciled',
            'partial_refund_reviewed','dispute_open_suspended','dispute_won_restored',
            'dispute_lost_revoked','redeemed_recovery_reviewed','replay_safe','rollback_safe','fixtures_clean',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
