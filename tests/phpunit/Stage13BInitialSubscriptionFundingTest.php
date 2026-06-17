<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage13BInitialSubscriptionFundingTest extends TestCase
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

    public function testMigrationAddsPendingFundingStateAndCompatibilityFlag(): void
    {
        $base=$this->read('database/stage_13_subscriptions_monetization.sql');
        $repair=$this->read('database/stage_13b_generated_initial_subscription_funding.sql');
        foreach([$base,$repair] as $sql){
            self::assertStringContainsString("'pending_payment'",$sql);
            self::assertStringContainsString('initial_payment_required',$sql);
            self::assertStringContainsString('funded_at',$sql);
            self::assertStringContainsString('activated_at',$sql);
        }
        self::assertStringContainsString('DEFAULT 0',$base);
    }

    public function testNewSubscriptionsRequireFundingBeforeActivation(): void
    {
        $source=$this->read('api/subscriptions/_subscriptions.php');
        self::assertStringContainsString("'trialing':'pending_payment'",$source);
        self::assertStringContainsString('initial_payment_required,funded_at,activated_at',$source);
        self::assertStringContainsString('mg_subscription_attempt($pdo,$subscription)',$source);
        self::assertStringContainsString('mg_subscription_activate_initial',$source);
    }

    public function testInitialFundingUsesCanonicalTipAuthority(): void
    {
        $source=$this->read('api/subscriptions/_subscriptions.php');
        self::assertStringContainsString('mg_tip_create(',$source);
        self::assertStringContainsString('mg_tip_notify_recipient(',$source);
        self::assertStringNotContainsString('INSERT INTO ledger_entries',$source);
        self::assertStringNotContainsString('INSERT INTO wallet_',$source);
    }

    public function testWebhookSeparatesActivationFromRenewal(): void
    {
        $funding=$this->read('api/subscriptions/_funding.php');
        $webhook=$this->read('api/subscriptions/payment-webhook.php');
        self::assertStringContainsString('mg_subscription_initial_payment_required($row)',$funding);
        self::assertStringContainsString('mg_subscription_activate_initial',$funding);
        self::assertStringContainsString('mg_subscription_advance',$funding);
        self::assertStringContainsString("attempt_status']==='succeeded'",$funding);
        self::assertStringContainsString('initial_payment_required',$webhook);
    }

    public function testProcessorIncludesPendingInitialPayments(): void
    {
        $source=$this->read('scripts/process_subscriptions.php');
        self::assertStringContainsString("'pending_payment','trialing','active','past_due','cancel_pending'",$source);
        self::assertStringContainsString('FOR UPDATE',$source);
        self::assertStringContainsString('mg_subscription_attempt(',$source);
    }

    public function testManagementCannotGrantAccessBeforeFunding(): void
    {
        $source=$this->read('api/subscriptions/manage.php');
        self::assertStringContainsString('mg_subscription_initial_payment_required($subscription)',$source);
        self::assertStringContainsString("?'pending_payment':'active'",$source);
        self::assertStringContainsString("['pending_payment','canceled','expired']",$source);
    }

    public function testFundingStateIsExposedByReadApi(): void
    {
        $source=$this->read('api/subscriptions/index.php');
        foreach(['initial_payment_required','funded_at','activated_at'] as $field)self::assertStringContainsString($field,$source);
    }

    public function testStage13RunnerAppliesCompatibilityMigration(): void
    {
        $source=$this->read('scripts/stage13.php');
        self::assertStringContainsString('stage_13_subscriptions_monetization.sql',$source);
        self::assertStringContainsString('stage_13b_generated_initial_subscription_funding.sql',$source);
    }
}
