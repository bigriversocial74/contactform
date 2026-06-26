<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/ledger-simulator.php';

final class ShareMarketLedgerSimulatorTest extends TestCase
{
    public function testSourceTableMappingUsesStageTwentyTables(): void
    {
        self::assertSame('share_market_platform_pools', mg_share_market_simulator_source_table('platform_pool'));
        self::assertSame('share_market_credit_treasuries', mg_share_market_simulator_source_table('merchant_treasury'));
        self::assertSame('share_market_series', mg_share_market_simulator_source_table('market_series'));
        self::assertSame('share_market_holder_positions', mg_share_market_simulator_source_table('holder_position'));
        self::assertSame('dave_scores', mg_share_market_simulator_source_table('dave_score'));
    }

    public function testExpectedStatusMappingForStateActions(): void
    {
        self::assertSame('paused', mg_share_market_simulator_expected_status('pause_series'));
        self::assertSame('active', mg_share_market_simulator_expected_status('resume_platform_pool'));
        self::assertSame('frozen', mg_share_market_simulator_expected_status('freeze_holder_shares'));
        self::assertSame('approved', mg_share_market_simulator_expected_status('approve_participant'));
        self::assertNull(mg_share_market_simulator_expected_status('mint_platform_shares'));
    }

    public function testBalanceFieldMappingForLedgerActions(): void
    {
        self::assertSame('total_minted', mg_share_market_simulator_balance_field(['action' => 'mint_platform_shares']));
        self::assertSame('total_minted', mg_share_market_simulator_balance_field(['action' => 'burn_platform_shares']));
        self::assertSame('credits_allocated', mg_share_market_simulator_balance_field(['action' => 'allocate_merchant_credits']));
        self::assertNull(mg_share_market_simulator_balance_field(['action' => 'approve_series']));
    }

    public function testProjectEffectsReconcilesApprovalCurrentBalance(): void
    {
        $effects = mg_share_market_simulator_project_effects(
            ['action' => 'mint_platform_shares', 'amount' => 50],
            ['type' => 'balance', 'current_balance' => 100, 'delta' => 50, 'projected_balance' => 150],
            ['exists' => true, 'row' => ['total_minted' => 100]]
        );

        self::assertSame('balance_projection', $effects[0]['type']);
        self::assertSame('total_minted', $effects[0]['field']);
        self::assertSame(100, $effects[0]['actual_now']);
        self::assertSame(150, $effects[0]['simulated_after']);
        self::assertTrue($effects[0]['actual_equals_approval_current']);
    }

    public function testProjectEffectsDetectsCurrentBalanceDrift(): void
    {
        $effects = mg_share_market_simulator_project_effects(
            ['action' => 'allocate_merchant_credits', 'amount' => 50],
            ['type' => 'balance', 'current_balance' => 100, 'delta' => 50, 'projected_balance' => 150],
            ['exists' => true, 'row' => ['credits_allocated' => 120]]
        );

        self::assertFalse($effects[0]['actual_equals_approval_current']);
    }

    public function testProjectEffectsIncludesStateProjection(): void
    {
        $effects = mg_share_market_simulator_project_effects(
            ['action' => 'pause_series'],
            ['type' => 'state_only'],
            ['exists' => true, 'row' => ['state' => 'approved']]
        );

        self::assertSame('state_projection', $effects[0]['type']);
        self::assertSame('approved', $effects[0]['actual_now']);
        self::assertSame('paused', $effects[0]['simulated_after']);
        self::assertFalse($effects[0]['actual_already_matches']);
    }

    public function testReconcileReportsNoMutationsAndMismatches(): void
    {
        $result = mg_share_market_simulator_reconcile([
            [
                'type' => 'balance_projection',
                'field' => 'total_minted',
                'actual_now' => 120,
                'approval_current_balance' => 100,
                'actual_equals_approval_current' => false,
            ],
        ], ['exists' => true], ['blocked' => true]);

        self::assertSame('mismatch', $result['status']);
        self::assertFalse($result['mutations_performed']);
        self::assertFalse($result['assertions']['balances_changed']);
        self::assertSame('approval_current_balance_mismatch', $result['mismatches'][0]['key']);
    }

    public function testReconcileReportsDryRunWhenAligned(): void
    {
        $result = mg_share_market_simulator_reconcile([
            [
                'type' => 'balance_projection',
                'field' => 'total_minted',
                'actual_now' => 100,
                'approval_current_balance' => 100,
                'actual_equals_approval_current' => true,
            ],
        ], ['exists' => true], ['blocked' => true]);

        self::assertSame('reconciled_dry_run', $result['status']);
        self::assertFalse($result['mutations_performed']);
        self::assertSame(0, $result['assertions']['ledger_entries_inserted']);
    }
}
