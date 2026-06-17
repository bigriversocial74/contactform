<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionCashoutBehaviorTest extends TestCase
{
    public function testCashoutPayoutWebhookAndHoldReconciliationAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')===''){
            self::markTestSkipped('Database-backed cashout validation requires MG_DB_HOST.');
        }

        $root=dirname(__DIR__,2);
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/validate_cashout_behavior.php').' 2>&1';
        $output=[];$exitCode=0;
        exec($command,$output,$exitCode);
        $raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);

        $result=json_decode($raw,true);
        self::assertIsArray($result,$raw);
        self::assertSame('cashout_payout_reconciliation_behavior',$result['suite']??null);
        foreach([
            'wallet_funded','hold_blocks_cashout','hold_release_restores_eligibility','cashout_reserved',
            'cashout_replay','cashout_conflict_rejected','reservation_balanced','payout_created_once',
            'paid_webhook_settled','webhook_replay','webhook_conflict_rejected','terminal_conflict_rejected',
            'failed_payout_restored_balance','notifications_once','forced_failure_rolled_back',
            'ledger_consistent','fixtures_clean',
        ] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
    }

    public function testEndpointsUseCanonicalCashoutAndPayoutAuthorities(): void
    {
        $wallet=file_get_contents(dirname(__DIR__,2).'/api/wallet/cashouts.php');
        $admin=file_get_contents(dirname(__DIR__,2).'/api/admin/cashouts.php');
        $holds=file_get_contents(dirname(__DIR__,2).'/api/admin/payout-holds.php');
        $webhook=file_get_contents(dirname(__DIR__,2).'/api/payments/payout-webhook.php');
        $service=file_get_contents(dirname(__DIR__,2).'/api/finance/_cashouts.php');
        foreach([$wallet,$admin,$holds,$webhook,$service] as $source)self::assertIsString($source);
        self::assertStringContainsString('mg_cashout_request(',$wallet);
        self::assertStringContainsString('mg_cashout_approve(',$admin);
        self::assertStringContainsString('mg_payout_hold_create(',$holds);
        self::assertStringContainsString('mg_payout_hold_release(',$holds);
        self::assertStringContainsString('mg_payout_process_event(',$webhook);
        self::assertStringContainsString('Payout webhook event conflicts with the recorded payload.',$service);
        self::assertStringContainsString('Payout terminal event conflicts with the recorded outcome.',$service);
        self::assertStringContainsString('Wallet has an active payout hold.',$service);
        self::assertStringContainsString("\$failureHook('after_ledger'",$service);
    }
}
