<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage12ATipFinancialIntegrityTest extends TestCase
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

    public function testMigrationHardensExistingAuthoritiesWithoutCreatingParallelMoneySystems(): void
    {
        $sql=$this->read('database/stage_12a_tip_financial_integrity.sql');
        self::assertStringContainsString('ALTER TABLE payment_intents ADD COLUMN source_type',$sql);
        self::assertStringContainsString('ALTER TABLE payment_intents ADD COLUMN source_reference',$sql);
        self::assertStringContainsString('ALTER TABLE payment_intents MODIFY COLUMN order_id BIGINT UNSIGNED NULL',$sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS tip_events',$sql);
        foreach(['CREATE TABLE IF NOT EXISTS wallets','CREATE TABLE IF NOT EXISTS ledger_transaction_groups','CREATE TABLE IF NOT EXISTS payment_webhook_events','CREATE TABLE IF NOT EXISTS merchant_payouts'] as $parallelAuthority){
            self::assertStringNotContainsString($parallelAuthority,$sql);
        }
    }

    public function testTipRowsSnapshotCanonicalRecipientWalletAndRequestIdentity(): void
    {
        $sql=$this->read('database/stage_12a_tip_financial_integrity.sql');
        foreach(['recipient_wallet_owner_type','recipient_wallet_owner_user_id','request_fingerprint','target_snapshot_json','payment_intent_id','provider_key'] as $column){
            self::assertStringContainsString($column,$sql);
        }
        foreach(['fk_tips_recipient_wallet_owner','fk_tips_payment_intent','idx_tips_recipient_wallet_status','idx_tips_payment_intent'] as $needle){
            self::assertStringContainsString($needle,$sql);
        }
    }

    public function testTipLifecycleAddsDurableEventsAndFinancialTerminalStates(): void
    {
        $sql=$this->read('database/stage_12a_tip_financial_integrity.sql');
        foreach(['requires_action','processing','disputed','refunded'] as $state){
            self::assertStringContainsString("'{$state}'",$sql);
        }
        foreach(['settled_at','failed_at','disputed_at','refunded_at','uq_tip_events_tip_idempotency','idx_tip_events_source','fk_tip_events_tip'] as $needle){
            self::assertStringContainsString($needle,$sql);
        }
    }

    public function testTipReversalRowsPreserveImmutableMoneySnapshots(): void
    {
        $sql=$this->read('database/stage_12a_tip_financial_integrity.sql');
        $service=$this->read('api/tips/_tips.php');
        $endpoint=$this->read('api/admin/tip-reverse.php');
        foreach(["TABLE_NAME = 'tip_reversals' AND COLUMN_NAME = 'amount_cents'","TABLE_NAME = 'tip_reversals' AND COLUMN_NAME = 'currency'","TABLE_NAME = 'tip_reversals' AND COLUMN_NAME = 'metadata_json'"] as $needle){
            self::assertStringContainsString($needle,$sql);
        }
        self::assertStringContainsString('amount_cents,currency,reason,idempotency_key',$service);
        self::assertStringContainsString('Tip is already reversed by a different request.',$service);
        self::assertStringContainsString('mg_tip_event($pdo,(int)$tip[\'id\'],\'reversed\'',$service);
        self::assertStringContainsString('mg_tip_reverse($pdo',$endpoint);
        self::assertStringNotContainsString('INSERT INTO tip_reversals',$endpoint);
    }

    public function testRuntimePostsToResolvedRecipientWalletAuthority(): void
    {
        $source=$this->read('api/tips/_tips.php');
        foreach(['function mg_tip_wallet_owner_type','mg_wallet_resolve($pdo,(string)$tip[\'recipient_wallet_owner_type\'],(int)$tip[\'recipient_wallet_owner_user_id\'],$currency)','\'recipient_wallet_owner_type\'=>$target[\'recipient_wallet_owner_type\']','\'recipient_wallet_owner_user_id\'=>$target[\'recipient_wallet_owner_user_id\']','function mg_tip_request_fingerprint','&&(string)$row[\'currency\']===$currency'] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testRuntimeCreatesDurableIdempotentTipEvents(): void
    {
        $source=$this->read('api/tips/_tips.php');
        foreach(['function mg_tip_event','INSERT INTO tip_events','\'tip-created:\'.$public','\'tip-posted:\'.$public','\'tip-payment-failed:\'.$providerPaymentId','\'tip-payment-posted:\'.$providerPaymentId'] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testMigrationIsOrderedImmediatelyAfterStage12(): void
    {
        $builder=$this->read('scripts/build_full_upgrade_sql.php');
        $workflow=$this->read('.github/workflows/pr-validation.yml');
        $stage12=strpos($builder,"'stage_12_universal_tips.sql'");
        $stage12a=strpos($builder,"'stage_12a_tip_financial_integrity.sql'");
        $stage13=strpos($builder,"'stage_13_subscriptions_monetization.sql'");
        self::assertIsInt($stage12);
        self::assertIsInt($stage12a);
        self::assertIsInt($stage13);
        self::assertLessThan($stage12a,$stage12);
        self::assertLessThan($stage13,$stage12a);
        self::assertStringContainsString('php scripts/stage12a.php',$workflow);
        self::assertStringContainsString('php scripts/stage12a_smoke.php',$workflow);
        self::assertStringContainsString('-- BEGIN stage_12a_tip_financial_integrity.sql',$workflow);
    }

    public function testSmokeValidatorRequiresColumnsIndexesConstraintsAndMigrationRecord(): void
    {
        $source=$this->read('scripts/stage12a_smoke.php');
        foreach(['recipient_wallet_owner_type','payment_intent_id','request_fingerprint','target_snapshot_json','idx_payment_intents_source','fk_tips_recipient_wallet_owner','fk_tips_payment_intent','stage_12a_tip_financial_integrity'] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
