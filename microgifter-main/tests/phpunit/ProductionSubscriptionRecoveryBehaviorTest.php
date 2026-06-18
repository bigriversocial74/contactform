<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionSubscriptionRecoveryBehaviorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function read(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testRefundDisputeAndChargebackReconciliationAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')===''){
            self::markTestSkipped('Database-backed subscription recovery validation requires MG_DB_HOST.');
        }

        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->root.'/scripts/validate_subscription_recovery_behavior.php').' 2>&1';
        $output=[];$exitCode=0;
        exec($command,$output,$exitCode);
        $raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);

        $result=json_decode($raw,true);
        self::assertIsArray($result,$raw);
        self::assertSame('subscription_refund_dispute_chargeback_reconciliation',$result['suite']??null);
        foreach([
            'dispute_suspends_access','dispute_replay_safe','dispute_win_restores_access',
            'full_refund_revokes_access','partial_refund_accumulates','dispute_loss_revokes_access',
            'chargeback_revokes_renewal_access','stage14_access_follows_recovery','billing_paused_during_recovery',
            'notifications_and_events_once','downstream_failure_rolls_back','fixtures_clean',
        ] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
    }

    public function testMigrationAddsDurableRecoveryStateWithoutParallelFinancialAuthority(): void
    {
        $sql=$this->read('database/stage_13c_generated_subscription_recovery_reconciliation.sql');
        foreach([
            "recovery_status ENUM('clear','disputed','refunded','chargeback')",
            "recovery_status ENUM('clear','disputed','partial_refund','refunded','chargeback')",
            'recovered_amount_cents BIGINT UNSIGNED',
            'CREATE TABLE IF NOT EXISTS subscription_payment_recoveries',
            'uq_subscription_recoveries_tip_recovery',
            'fk_subscription_recoveries_tip_recovery',
            'stage_13c_subscription_recovery_reconciliation',
        ] as $needle){
            self::assertStringContainsString($needle,$sql);
        }
        self::assertStringNotContainsString('CREATE TABLE IF NOT EXISTS wallets',$sql);
        self::assertStringNotContainsString('CREATE TABLE IF NOT EXISTS ledger_transaction_groups',$sql);
        self::assertStringNotContainsString('INSERT INTO ledger_entries',$sql);
    }

    public function testReconciliationDelegatesToExistingRecoverySubscriptionAndCommunicationsAuthorities(): void
    {
        $service=$this->read('api/subscriptions/_recovery.php');
        $router=$this->read('api/tips/_recovery_webhook.php');
        foreach([
            'function mg_subscription_reconcile_tip_recovery(',
            'tip_payment_recoveries',
            'subscription_attempts',
            'subscription_payment_recoveries',
            'mg_subscription_event(',
            'mg_subscription_notify(',
            "'after_subscription_state'",
            "'before_complete'",
        ] as $needle){
            self::assertStringContainsString($needle,$service);
        }
        self::assertStringContainsString('mg_subscription_reconcile_tip_recovery(',$router);
        self::assertStringContainsString("empty(\$result['duplicate'])",$router);
        self::assertStringNotContainsString('mg_ledger_post(',$service);
        self::assertStringNotContainsString('mg_ledger_reverse(',$service);
        self::assertStringNotContainsString('INSERT INTO ledger_entries',$service);
    }

    public function testStage14AndRenewalProcessingRequireClearRecoveryState(): void
    {
        $social=$this->read('api/social/_social.php');
        $processor=$this->read('scripts/process_subscriptions.php');
        $manage=$this->read('api/subscriptions/manage.php');
        self::assertStringContainsString("recovery_status='clear'",$social);
        self::assertStringContainsString("s.recovery_status='clear'",$processor);
        self::assertStringContainsString("recovery_status']??'clear'",$manage);
        self::assertStringContainsString('Subscription payment recovery must be resolved before this action.',$manage);
    }

    public function testBehaviorRunnerCoversReplayAccessNotificationsAndRollback(): void
    {
        $source=$this->read('scripts/validate_subscription_recovery_behavior.php');
        foreach([
            "'dispute_suspends_access'=>false",
            "'dispute_replay_safe'=>false",
            "'dispute_win_restores_access'=>false",
            "'full_refund_revokes_access'=>false",
            "'partial_refund_accumulates'=>false",
            "'dispute_loss_revokes_access'=>false",
            "'chargeback_revokes_renewal_access'=>false",
            "'stage14_access_follows_recovery'=>false",
            "'notifications_and_events_once'=>false",
            "'downstream_failure_rolls_back'=>false",
            'SAVEPOINT subscription_recovery_failure',
            'ROLLBACK TO SAVEPOINT subscription_recovery_failure',
            'mg_social_can_view(',
            'mg_tip_route_payment_event(',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testMigrationAndFocusedValidationAreRegistered(): void
    {
        $builder=$this->read('scripts/build_full_upgrade_sql.php');
        $canonical=$this->read('database/stage_13_subscriptions_monetization.sql');
        $composer=$this->read('composer.json');
        $stage13=$this->read('scripts/stage13.php');
        $workflow=$this->read('.github/workflows/subscription-recovery-validation.yml');
        $base=strpos($builder,"'stage_13_subscriptions_monetization.sql'");
        $social=strpos($builder,"'stage_14_posts_feed_social.sql'");
        self::assertIsInt($base);self::assertIsInt($social);
        self::assertLessThan($social,$base);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS subscription_payment_recoveries',$canonical);
        self::assertStringContainsString('stage_13c_generated_subscription_recovery_reconciliation.sql',$stage13);
        self::assertStringContainsString('"test-subscription-recovery-behavior": "php scripts/validate_subscription_recovery_behavior.php"',$composer);
        self::assertStringContainsString('composer test-subscription-recovery-behavior',$workflow);
        self::assertStringContainsString('php scripts/stage12d.php',$workflow);
        self::assertStringContainsString('php scripts/stage13.php',$workflow);
        self::assertStringContainsString('php scripts/stage14.php',$workflow);
    }
}
