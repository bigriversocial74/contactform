<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/execution-audit.php';

final class ShareMarketExecutionAuditScaffoldingTest extends TestCase
{
    public function testAuditStatusUsesSimulatorAndGateResults(): void
    {
        self::assertSame('reconciliation_mismatch', mg_share_market_execution_audit_status([
            'reconciliation' => ['status' => 'mismatch'],
            'release_gate' => ['blocked' => true],
        ]));

        self::assertSame('blocked_by_gate', mg_share_market_execution_audit_status([
            'reconciliation' => ['status' => 'reconciled_dry_run'],
            'release_gate' => ['blocked' => true],
        ]));

        self::assertSame('preflight_ready', mg_share_market_execution_audit_status([
            'reconciliation' => ['status' => 'reconciled_dry_run'],
            'release_gate' => ['blocked' => false],
        ]));
    }

    public function testAuditPayloadIsExplicitlyNonMutating(): void
    {
        $row = [
            'id' => 77,
            'public_id' => '11111111-1111-1111-1111-111111111111',
            'manifest_json' => json_encode(['target_type' => 'platform_pool', 'target_id' => 'pool-main'], JSON_THROW_ON_ERROR),
        ];
        $simulation = [
            'idempotency_key' => 'sm-exec-' . str_repeat('a', 64),
            'release_gate' => ['blocked' => true],
            'reconciliation' => ['status' => 'reconciled_dry_run'],
            'target_snapshot' => ['exists' => true],
        ];

        $payload = mg_share_market_execution_audit_payload($row, $simulation, ['id' => 5], 'live');

        self::assertSame(77, $payload['approval_request_id']);
        self::assertSame('live_requested', $payload['run_mode']);
        self::assertSame('blocked_by_gate', $payload['status']);
        self::assertSame('platform_pool', $payload['target_type']);
        self::assertFalse($payload['metadata']['writes_value']);
        self::assertFalse($payload['metadata']['moves_balance']);
        self::assertFalse($payload['metadata']['launches_market']);
    }

    public function testSnapshotPayloadIncludesAttemptAndTargetContext(): void
    {
        $attempt = [
            'public_id' => '22222222-2222-2222-2222-222222222222',
            'approval_request_id' => 77,
            'target_type' => 'market_series',
            'target_public_id' => 'sm_abc',
        ];

        $payload = mg_share_market_execution_audit_snapshot_payload('target_snapshot', ['exists' => true], $attempt);

        self::assertSame('target_snapshot', $payload['snapshot_type']);
        self::assertSame('22222222-2222-2222-2222-222222222222', $payload['execution_attempt_public_id']);
        self::assertSame(77, $payload['approval_request_id']);
        self::assertSame('market_series', $payload['target_type']);
        self::assertSame('sm_abc', $payload['target_public_id']);
    }

    public function testStageTwentyOneMigrationIsRegistered(): void
    {
        $manifest = require dirname(__DIR__, 2) . '/config/migrations.php';

        self::assertContains('stage_21_buy_in_execution_audit_scaffolding.sql', $manifest['ordered_files']);
    }

    public function testAuditScaffoldApiUsesLockedConfirmationPhrase(): void
    {
        $api = (string)file_get_contents(dirname(__DIR__, 2) . '/api/admin/share-market/execution-audit-scaffold.php');

        self::assertStringContainsString('AUDIT SCAFFOLD', $api);
        self::assertStringContainsString('No Share Market value action was executed', $api);
    }

    public function testNoLiveRunnerEndpointWasAddedInPhaseEleven(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileDoesNotExist($root . '/api/admin/share-market/live-runner.php');
        self::assertFileDoesNotExist($root . '/api/admin/share-market/finalize-runner.php');
        self::assertFileDoesNotExist($root . '/includes/share-market/live-runner.php');
    }
}
