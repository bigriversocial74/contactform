<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class Stage7BMoneyEngineTest extends TestCase
{
    public function testSchemaDefinesCanonicalMoneyEngineTables(): void
    {
        $sql=file_get_contents(dirname(__DIR__,2).'/database/stage_7b_money_engine.sql');
        self::assertIsString($sql);
        foreach(['wallets','ledger_accounts','ledger_transaction_groups','ledger_entries','ledger_reversal_links','wallet_balance_snapshots','cashout_requests','cashout_payout_links','payout_holds'] as $table){self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);}
        self::assertStringContainsString('uq_ledger_groups_idempotency',$sql);
        self::assertStringContainsString('chk_ledger_entries_positive',$sql);
        self::assertStringContainsString('chk_cashout_positive',$sql);
        self::assertStringNotContainsString('CREATE TRIGGER',$sql);
    }

    public function testLedgerServiceRequiresBalancedIdempotentGroups(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/finance/_money.php');
        self::assertIsString($source);
        self::assertStringContainsString('Ledger idempotency key is required.',$source);
        self::assertStringContainsString('Ledger transaction is not balanced.',$source);
        self::assertStringContainsString("status='posted'",$source);
        self::assertStringContainsString('ledger.transaction_posted',$source);
        self::assertStringContainsString('ledger_reversal_links',$source);
        self::assertStringNotContainsString('UPDATE ledger_entries',$source);
        self::assertStringNotContainsString('DELETE FROM ledger_entries',$source);
    }

    public function testPaidOrdersUseGroupedLedgerInsteadOfLegacyPair(): void
    {
        $capture=file_get_contents(dirname(__DIR__,2).'/api/payments/_capture.php');
        self::assertIsString($capture);
        self::assertStringContainsString('mg_stage7_post_paid_order',$capture);
        self::assertStringNotContainsString('mg_ledger_pair(',$capture);
        $posting=file_get_contents(dirname(__DIR__,2).'/api/finance/_posting.php');
        self::assertStringContainsString("'order:paid:'",$posting);
        self::assertStringContainsString('processor_clearing',$posting);
    }

    public function testRefundsUseGroupedIdempotentLedgerPosting(): void
    {
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/merchant/refund.php');
        $service=file_get_contents(dirname(__DIR__,2).'/api/payments/_refund.php');
        self::assertIsString($endpoint);
        self::assertIsString($service);
        self::assertStringContainsString('mg_finance_refund_order(',$endpoint);
        self::assertStringContainsString('mg_stage7_post_refund',$service);
        self::assertStringNotContainsString('mg_ledger_pair(',$service);
        self::assertStringContainsString('idempotency_key',$service);
    }

    public function testCashoutServiceChecksBalanceHoldsAndReservesFunds(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/finance/_cashouts.php');
        self::assertIsString($source);
        self::assertStringContainsString('Insufficient available balance.',$source);
        self::assertStringContainsString('Wallet has an active payout hold.',$source);
        self::assertStringContainsString("'cashout_reservation'",$source);
        self::assertStringContainsString("'cashout_pending'",$source);
        self::assertStringContainsString('payout.created',$source);
        self::assertStringContainsString('payout.paid',$source);
        self::assertStringContainsString('payout.failed',$source);
    }

    public function testWalletAndCashoutApisRequireAuthenticationOrPermission(): void
    {
        foreach(['api/wallet/summary.php','api/wallet/ledger.php','api/wallet/cashouts.php'] as $file){$source=file_get_contents(dirname(__DIR__,2).'/'.$file);self::assertStringContainsString('mg_require_api_user()',$source);}
        foreach(['api/admin/cashouts.php','api/admin/payout-holds.php'] as $file){$source=file_get_contents(dirname(__DIR__,2).'/'.$file);self::assertStringContainsString('mg_require_permission(',$source);self::assertStringContainsString('mg_require_csrf_for_write',$source);}
    }

    public function testPayoutWebhookIsSignedAndProviderEventIdempotent(): void
    {
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/payments/payout-webhook.php');
        $service=file_get_contents(dirname(__DIR__,2).'/api/finance/_cashouts.php');
        self::assertIsString($endpoint);
        self::assertIsString($service);
        self::assertStringContainsString('mg_payment_verify_signature',$endpoint);
        self::assertStringContainsString('mg_payout_process_event(',$endpoint);
        self::assertStringContainsString('payment_webhook_events',$service);
        self::assertStringContainsString('Payout webhook event conflicts with the recorded payload.',$service);
        self::assertStringContainsString('Payout terminal event conflicts with the recorded outcome.',$service);
        self::assertStringContainsString('mg_payout_finalize',$service);
    }

    public function testStage7MigrationAndSmokeScriptsExist(): void
    {
        $migration=file_get_contents(dirname(__DIR__,2).'/scripts/stage7b.php');
        $smoke=file_get_contents(dirname(__DIR__,2).'/scripts/stage7b_smoke.php');
        self::assertIsString($migration);self::assertIsString($smoke);
        self::assertStringContainsString('stage_7b_money_engine.sql',$migration);
        self::assertStringContainsString('chk_ledger_entries_positive',$smoke);
    }
}
