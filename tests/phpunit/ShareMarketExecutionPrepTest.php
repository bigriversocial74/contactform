<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/execution-prep.php';

final class ShareMarketExecutionPrepTest extends TestCase
{
    private function approvedRow(string $action = 'mint_platform_shares'): array
    {
        $manifest = [
            'manifest_id' => 'manifest_123',
            'action' => $action,
            'event_type' => 'platform_pool_minted',
            'target_type' => 'platform_pool',
            'target_id' => 'platform-master',
            'amount' => 1000,
            'payload_hash' => str_repeat('a', 64),
            'required_approvals' => 2,
        ];
        return [
            'id' => 10,
            'public_id' => '11111111-1111-1111-1111-111111111111',
            'requester_user_id' => 1,
            'status' => 'approved',
            'required_approvals' => 2,
            'approval_count' => 2,
            'manifest_json' => json_encode($manifest, JSON_THROW_ON_ERROR),
            'projection_json' => json_encode(['type' => 'balance', 'current_balance' => 5000, 'delta' => 1000, 'projected_balance' => 6000], JSON_THROW_ON_ERROR),
            'payload_hash' => str_repeat('b', 64),
            'created_at' => '2026-06-26 01:00:00',
        ];
    }

    public function testExecutionPreviewForApprovedRequestIsReadyButLocked(): void
    {
        $preview = mg_share_market_execution_preview_from_row($this->approvedRow(), ['id' => 99, 'roles' => ['super_admin']]);

        self::assertSame('approved_waiting_for_execution_layer', $preview['handoff_status']);
        self::assertTrue($preview['ready_to_execute']);
        self::assertSame('locked_stub', $preview['runner_state']);
        self::assertFalse($preview['execution_enabled']);
        self::assertFalse($preview['can_execute']);
        self::assertSame('ledger_mint', $preview['action_kind']);
        self::assertNotEmpty($preview['idempotency_key']);
    }

    public function testExecutionPreviewForPendingRequestIsNotReady(): void
    {
        $row = $this->approvedRow('approve_series');
        $row['status'] = 'awaiting_second_approval';
        $preview = mg_share_market_execution_preview_from_row($row, ['id' => 99, 'roles' => ['admin']]);

        self::assertSame('not_ready', $preview['handoff_status']);
        self::assertFalse($preview['ready_to_execute']);
        self::assertFalse($preview['execution_enabled']);
        self::assertFalse($preview['can_execute']);
    }

    public function testExecutionActionKindMapping(): void
    {
        self::assertSame('pool_create', mg_share_market_execution_action_kind('create_master_pool'));
        self::assertSame('ledger_mint', mg_share_market_execution_action_kind('mint_platform_shares'));
        self::assertSame('ledger_burn', mg_share_market_execution_action_kind('burn_platform_shares'));
        self::assertSame('treasury_allocation', mg_share_market_execution_action_kind('allocate_merchant_credits'));
        self::assertSame('series_state_change', mg_share_market_execution_action_kind('approve_series'));
        self::assertSame('hash_checkpoint', mg_share_market_execution_action_kind('publish_proof_hash'));
    }

    public function testDryRunEntriesNeverMutateBalance(): void
    {
        $row = $this->approvedRow('burn_platform_shares');
        $manifest = mg_share_market_sql_decode($row['manifest_json']);
        $projection = mg_share_market_sql_decode($row['projection_json']);
        $entries = mg_share_market_execution_dry_run_entries($row, $manifest, $projection);

        self::assertCount(1, $entries);
        self::assertSame('burn', $entries[0]['entry_type']);
        self::assertFalse($entries[0]['would_mutate_balance']);
        self::assertFalse($entries[0]['execution_enabled']);
        self::assertContains('share_market_credit_ledger', $entries[0]['would_write_tables']);
    }

    public function testTransactionPlanIncludesExplicitFutureOnlyMutationStep(): void
    {
        $row = $this->approvedRow('approve_series');
        $manifest = mg_share_market_sql_decode($row['manifest_json']);
        $manifest['target_type'] = 'market_series';
        $plan = mg_share_market_execution_transaction_plan($manifest);

        self::assertSame('begin_transaction', $plan[0]['name']);
        self::assertSame('apply_domain_mutation', $plan[4]['name']);
        self::assertStringContainsString('Future step only', $plan[4]['description']);
    }

    public function testRunnerStubDoesNotExecute(): void
    {
        $preview = mg_share_market_execution_preview_from_row($this->approvedRow(), ['id' => 99]);

        self::assertSame('locked_stub', $preview['runner_state']);
        self::assertFalse($preview['can_execute']);
        self::assertFalse($preview['execution_enabled']);
    }
}
