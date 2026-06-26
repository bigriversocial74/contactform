<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/execution-gate.php';

final class ShareMarketExecutionGateTest extends TestCase
{
    public function testGateCheckShape(): void
    {
        $check = mg_share_market_gate_check('feature_flag', false, 'Feature flag', 'Disabled.');

        self::assertSame('feature_flag', $check['key']);
        self::assertFalse($check['passed']);
        self::assertSame('blocker', $check['severity']);
    }

    public function testEnvironmentGateOnlyAcceptsExplicitEnabledValues(): void
    {
        putenv('MG_TEST_GATE_FLAG');
        self::assertFalse(mg_share_market_gate_env_enabled('MG_TEST_GATE_FLAG'));

        putenv('MG_TEST_GATE_FLAG=1');
        self::assertTrue(mg_share_market_gate_env_enabled('MG_TEST_GATE_FLAG'));

        putenv('MG_TEST_GATE_FLAG=false');
        self::assertFalse(mg_share_market_gate_env_enabled('MG_TEST_GATE_FLAG'));

        putenv('MG_TEST_GATE_FLAG');
    }

    public function testBalanceInvariantPassesValidMintProjection(): void
    {
        $check = mg_share_market_gate_balance_check(
            ['action' => 'mint_platform_shares'],
            ['type' => 'balance', 'current_balance' => 100, 'delta' => 25, 'projected_balance' => 125]
        );

        self::assertTrue($check['passed']);
        self::assertSame(125, $check['expected_balance']);
    }

    public function testBalanceInvariantRejectsBadMath(): void
    {
        $check = mg_share_market_gate_balance_check(
            ['action' => 'mint_platform_shares'],
            ['type' => 'balance', 'current_balance' => 100, 'delta' => 25, 'projected_balance' => 124]
        );

        self::assertFalse($check['passed']);
        self::assertSame(125, $check['expected_balance']);
    }

    public function testBalanceInvariantRejectsWrongBurnDirection(): void
    {
        $check = mg_share_market_gate_balance_check(
            ['action' => 'burn_platform_shares'],
            ['type' => 'balance', 'current_balance' => 100, 'delta' => 25, 'projected_balance' => 125]
        );

        self::assertFalse($check['passed']);
    }

    public function testBalanceInvariantAcceptsNoBalanceProjectionAsInfo(): void
    {
        $check = mg_share_market_gate_balance_check(
            ['action' => 'approve_series'],
            ['type' => 'state_change']
        );

        self::assertTrue($check['passed']);
        self::assertSame('info', $check['severity']);
    }

    public function testExecutionPreviewWithGateContractFieldsStayLocked(): void
    {
        $row = [
            'public_id' => '11111111-1111-1111-1111-111111111111',
            'payload_hash' => str_repeat('b', 64),
        ];
        $manifest = [
            'manifest_id' => 'manifest_abc',
            'action' => 'mint_platform_shares',
            'target_type' => 'platform_pool',
            'target_id' => 'platform-master',
        ];
        $key = mg_share_market_execution_idempotency_key($row, $manifest);

        self::assertStringStartsWith('sm-exec-', $key);
        self::assertSame(72, strlen($key));
    }
}
