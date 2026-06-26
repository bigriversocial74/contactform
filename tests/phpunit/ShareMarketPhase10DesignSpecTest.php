<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ShareMarketPhase10DesignSpecTest extends TestCase
{
    private string $designDoc;
    private string $checklistDoc;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->designDoc = (string)file_get_contents($root . '/docs/share-market-phase-10-runner-design.md');
        $this->checklistDoc = (string)file_get_contents($root . '/docs/share-market-phase-10-operator-checklist.md');
    }

    public function testDesignSpecIsExplicitlyDocumentationOnly(): void
    {
        self::assertStringContainsString('documentation only', $this->designDoc);
        self::assertStringContainsString('does not add', $this->designDoc);
        self::assertStringContainsString('balance-changing code', $this->designDoc);
    }

    public function testDesignSpecRequiresPriorGateAndSimulator(): void
    {
        self::assertStringContainsString('evaluate release gate', $this->designDoc);
        self::assertStringContainsString('run simulator reconciliation', $this->designDoc);
        self::assertStringContainsString('reject on mismatch', $this->designDoc);
    }

    public function testDesignSpecDefinesLockOrderAndRollbackGuardrails(): void
    {
        self::assertStringContainsString('Lock order', $this->designDoc);
        self::assertStringContainsString('approval request', $this->designDoc);
        self::assertStringContainsString('latest hash-chain checkpoint', $this->designDoc);
        self::assertStringContainsString('transaction must roll back', $this->designDoc);
    }

    public function testDesignSpecDefinesIdempotencyAndAuditPayload(): void
    {
        self::assertStringContainsString('Idempotency rules', $this->designDoc);
        self::assertStringContainsString('idempotency key', $this->designDoc);
        self::assertStringContainsString('before snapshot', $this->designDoc);
        self::assertStringContainsString('after snapshot', $this->designDoc);
    }

    public function testChecklistRequiresLegalAndOperationalApprovals(): void
    {
        self::assertStringContainsString('Legal review approval', $this->checklistDoc);
        self::assertStringContainsString('Operations review approval', $this->checklistDoc);
        self::assertStringContainsString('production-backup confirmation', $this->checklistDoc);
        self::assertStringContainsString('rollback-plan confirmation', $this->checklistDoc);
    }

    public function testNoPhaseTenRunnerEndpointWasAdded(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertFileDoesNotExist($root . '/api/admin/share-market/live-runner.php');
        self::assertFileDoesNotExist($root . '/api/admin/share-market/finalize-runner.php');
        self::assertFileDoesNotExist($root . '/includes/share-market/live-runner.php');
    }
}
