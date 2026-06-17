<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage12DTipRecoveryBehaviorTest extends TestCase
{
    private string $root;
    protected function setUp(): void{$this->root=dirname(__DIR__,2);}
    private function read(string $path): string{$source=file_get_contents($this->root.'/'.$path);self::assertIsString($source,$path);return $source;}

    public function testMigrationGeneralizesCanonicalRefundDisputeAndHoldAuthorities(): void
    {
        $sql=$this->read('database/stage_12d_tip_recovery.sql');
        foreach([
            'CREATE TABLE IF NOT EXISTS tip_payment_recoveries',
            'ALTER TABLE payment_refunds MODIFY COLUMN order_id BIGINT UNSIGNED NULL',
            'ALTER TABLE payment_disputes MODIFY COLUMN order_id BIGINT UNSIGNED NULL',
            'source_type VARCHAR(80)',
            'tip_id BIGINT UNSIGNED NULL',
            'payout_hold_id BIGINT UNSIGNED NULL',
            'uq_tip_recoveries_provider_event',
            'stage_12d_tip_recovery',
        ] as $needle)self::assertStringContainsString($needle,$sql);
        self::assertStringNotContainsString('CREATE TABLE IF NOT EXISTS wallets',$sql);
        self::assertStringNotContainsString('CREATE TABLE IF NOT EXISTS ledger_transaction_groups',$sql);
    }

    public function testRecoveryRuntimeUsesCanonicalLedgerTransactionsAndPayoutHolds(): void
    {
        $source=$this->read('api/tips/_recovery.php');
        foreach([
            'function mg_tip_process_recovery_event',
            'function mg_tip_dispute_hold',
            'function mg_tip_release_dispute_hold',
            'function mg_tip_post_recovery',
            'mg_ledger_post(',
            'mg_ledger_reverse(',
            'mg_payment_record_intent_transaction(',
            "'refund':'chargeback'",
            'payout_holds',
        ] as $needle)self::assertStringContainsString($needle,$source);
        self::assertStringNotContainsString('INSERT INTO ledger_entries',$source);
        self::assertStringNotContainsString('financial_ledger_entries',$source);
    }

    public function testRecoveryEventsVerifyProviderSourceAmountAndCurrency(): void
    {
        $source=$this->read('api/tips/_recovery.php');
        foreach([
            'Tip recovery event does not match a known tip.',
            'Tip recovery provider does not match.',
            'Tip recovery payment source does not match.',
            'Tip recovery currency does not match.',
            'Tip recovery amount exceeds the original tip.',
            'Tip recovery amount exceeds the unrecovered balance.',
        ] as $needle)self::assertStringContainsString($needle,$source);
    }

    public function testWebhookRoutesRecoveryEventsAndNotifiesIdempotently(): void
    {
        $webhook=$this->read('api/tips/payment-webhook.php');
        $router=$this->read('api/tips/_recovery_webhook.php');
        $notifications=$this->read('api/tips/_notifications.php');
        self::assertStringContainsString('mg_tip_route_payment_event(',$webhook);
        self::assertStringContainsString('mg_tip_is_recovery_event(',$router);
        self::assertStringContainsString('mg_tip_process_recovery_event(',$router);
        self::assertStringContainsString('mg_tip_notify_recovery(',$router);
        self::assertStringContainsString('function mg_tip_recovery_alert_type',$notifications);
        self::assertStringContainsString("r.slug IN ('admin','super_admin')",$notifications);
    }

    public function testBehaviorValidatorCoversRefundDisputeChargebackAndRollback(): void
    {
        $source=$this->read('scripts/validate_tip_recovery_behavior.php');
        foreach([
            "'refund_recovered_once'=>false",
            "'refund_replay_safe'=>false",
            "'dispute_hold_blocks_cashout'=>false",
            "'dispute_won_releases_hold'=>false",
            "'dispute_lost_recovers_funds'=>false",
            "'chargeback_recovered_once'=>false",
            "'provider_contract_rollback'=>false",
            "'out_of_order_rejected'=>false",
            "'notification_idempotency'=>false",
            "'downstream_rollback'=>false",
            "'fixtures_clean'=>false",
            'SAVEPOINT invalid_provider',
            'SAVEPOINT out_of_order',
            'SAVEPOINT downstream',
        ] as $needle)self::assertStringContainsString($needle,$source);
    }

    public function testMigrationAndBehaviorSuiteAreRegistered(): void
    {
        $builder=$this->read('scripts/build_full_upgrade_sql.php');
        $composer=$this->read('composer.json');
        $workflow=$this->read('.github/workflows/stage12d-validation.yml');
        $a=strpos($builder,"'stage_12a_tip_financial_integrity.sql'");$d=strpos($builder,"'stage_12d_tip_recovery.sql'");$thirteen=strpos($builder,"'stage_13_subscriptions_monetization.sql'");
        self::assertIsInt($a);self::assertIsInt($d);self::assertIsInt($thirteen);self::assertLessThan($d,$a);self::assertLessThan($thirteen,$d);
        self::assertStringContainsString('"test-tip-recovery-behavior": "php scripts/validate_tip_recovery_behavior.php"',$composer);
        self::assertStringContainsString('php scripts/stage12d.php',$workflow);
        self::assertStringContainsString('php scripts/stage12d_smoke.php',$workflow);
        self::assertStringContainsString('composer test-tip-recovery-behavior',$workflow);
    }
}
