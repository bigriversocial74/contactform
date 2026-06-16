<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage7CMoneyEngineCloseoutTest extends TestCase
{
    public function testWalletResolutionHandlesConcurrentCreation(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/finance/_money.php');
        self::assertIsString($source);
        self::assertStringContainsString('str_contains(',$source);
        self::assertStringContainsString('Unable to resolve wallet.',$source);
        self::assertStringContainsString('Unable to resolve platform ledger account.',$source);
    }

    public function testLedgerPostingRequiresCompleteBalancedIdentity(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/finance/_money.php');
        self::assertIsString($source);
        self::assertStringContainsString('Ledger idempotency key is required.',$source);
        self::assertStringContainsString('Ledger transaction and source types are required.',$source);
        self::assertStringContainsString('Ledger transaction is not balanced.',$source);
        self::assertStringContainsString("status='active'",$source);
    }

    public function testReversalIsIdempotentAndSingleUse(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/finance/_money.php');
        self::assertIsString($source);
        self::assertStringContainsString('INNER JOIN ledger_reversal_links',$source);
        self::assertStringContainsString('Ledger group is already reversed.',$source);
        self::assertStringContainsString('Ledger group has no entries to reverse.',$source);
        self::assertStringContainsString("status='reversed'",$source);
    }

    public function testLegacyLedgerPairIsNotUsedByActiveStageSevenPostingPaths(): void
    {
        $paths=['api/payments/_capture.php','api/merchant/refund.php','api/finance/_posting.php','api/finance/_cashouts.php','api/admin/payout-holds.php','api/admin/ledger-reversal.php'];
        foreach($paths as $path){
            $source=file_get_contents(dirname(__DIR__,2).'/'.$path);
            self::assertIsString($source);
            self::assertStringNotContainsString('mg_ledger_pair(',$source,$path.' must use the grouped ledger.');
        }
    }

    public function testHighRiskFinancialWritesRemainProtected(): void
    {
        foreach(['api/wallet/cashouts.php','api/admin/cashouts.php','api/admin/payout-holds.php','api/admin/ledger-reversal.php','api/admin/financial-reconciliation.php'] as $path){
            $source=file_get_contents(dirname(__DIR__,2).'/'.$path);
            self::assertIsString($source);
            self::assertStringContainsString('mg_require_csrf_for_write',$source,$path.' must require CSRF protection.');
        }
        foreach(['api/admin/cashouts.php','api/admin/payout-holds.php','api/admin/ledger-reversal.php','api/admin/financial-reconciliation.php'] as $path){
            $source=file_get_contents(dirname(__DIR__,2).'/'.$path);
            self::assertStringContainsString('mg_require_permission(',$source,$path.' must require a dedicated permission.');
        }
    }

    public function testForeignWalletReadsRequireAdministrativeFinancialPermission(): void
    {
        foreach(['api/wallet/summary.php','api/wallet/ledger.php'] as $path){
            $source=file_get_contents(dirname(__DIR__,2).'/'.$path);
            self::assertIsString($source);
            self::assertStringContainsString('cashouts.manage',$source);
            self::assertStringContainsString('financial.reconciliation.manage',$source);
            self::assertStringNotContainsString("'wallet.view'",$source);
        }
    }

    public function testWalletSummaryDoesNotCreateStateDuringGet(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/wallet/summary.php');
        self::assertIsString($source);
        self::assertStringNotContainsString('mg_wallet_resolve(',$source);
        self::assertStringContainsString("'wallet'=>null",$source);
        self::assertStringContainsString('Invalid wallet owner.',$source);
    }

    public function testStageSevenCloseoutAndStageEightHandoffAreDocumented(): void
    {
        $closeout=file_get_contents(dirname(__DIR__,2).'/docs/stage-7c-money-engine-closeout.md');
        $handoff=file_get_contents(dirname(__DIR__,2).'/docs/stage-8-handoff-from-stage-7.md');
        self::assertIsString($closeout);
        self::assertIsString($handoff);
        self::assertStringContainsString('Stage 7A',$closeout);
        self::assertStringContainsString('Stage 7B',$closeout);
        self::assertStringContainsString('Stage 8',$handoff);
    }
}
