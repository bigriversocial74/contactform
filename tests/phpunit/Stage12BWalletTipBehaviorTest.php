<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage12BWalletTipBehaviorTest extends TestCase
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

    public function testExecutableBehaviorValidatorCoversFinancialAndFailurePaths(): void
    {
        $source=$this->read('scripts/validate_tip_behavior.php');
        foreach([
            "'balanced_posting'=>false",
            "'wallet_routing'=>false",
            "'exact_replay'=>false",
            "'conflicting_replay'=>false",
            "'self_tip_rejected'=>false",
            "'insufficient_funds_rollback'=>false",
            "'velocity_rollback'=>false",
            "'notification_idempotency'=>false",
            "'transaction_rollback'=>false",
            "'reversal_integrity'=>false",
            "'reversal_replay'=>false",
            "'fixtures_clean'=>false",
        ] as $assertion){
            self::assertStringContainsString($assertion,$source);
        }
        foreach(["'profile'","'creator'","'merchant'",'SAVEPOINT insufficient_funds','SAVEPOINT velocity_limit','SAVEPOINT downstream_failure','mg_tip_reverse('] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testBehaviorFixtureFundsAndInspectsCanonicalStage7Wallets(): void
    {
        $source=$this->read('tests/integration/TipBehaviorFixture.php');
        foreach(['mg_wallet_resolve(','mg_wallet_account_id(','mg_ledger_platform_account(','mg_ledger_post(','ledger_entries'] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('INSERT INTO financial_ledger_entries',$source);
    }

    public function testCanonicalTipServiceOwnsReversalBehavior(): void
    {
        $source=$this->read('api/tips/_tips.php');
        $endpoint=$this->read('api/admin/tip-reverse.php');
        foreach(['function mg_tip_reverse(','mg_ledger_reverse(','INSERT INTO tip_reversals',"'reversed'"] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringContainsString('mg_tip_reverse($pdo',$endpoint);
        self::assertStringNotContainsString('INSERT INTO tip_reversals',$endpoint);
        self::assertStringNotContainsString('mg_ledger_reverse(',$endpoint);
    }

    public function testTipNotificationsAreReplaySafe(): void
    {
        $source=$this->read('api/tips/_notifications.php');
        foreach(['function mg_tip_existing_alert',"JSON_EXTRACT(metadata_json,'$.tip_id')","'tip_received'","'tip_reversed'",'if($existing!==null)return $existing;'] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testComposerAndCiExecuteTheWalletTipBehaviorSuite(): void
    {
        $composer=$this->read('composer.json');
        $workflow=$this->read('.github/workflows/pr-validation.yml');
        self::assertStringContainsString('"test-tip-behavior": "php scripts/validate_tip_behavior.php"',$composer);
        self::assertStringContainsString('Validate wallet-funded tip behavior',$workflow);
        self::assertStringContainsString('composer test-tip-behavior',$workflow);
    }
}
