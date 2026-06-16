<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage13SubscriptionsMonetizationTest extends TestCase
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

    public function testSchemaDefinesPlansSubscriptionsAttemptsAndEvents(): void
    {
        $sql=$this->read('database/stage_13_subscriptions_monetization.sql');
        foreach(['subscription_plans','subscriptions','subscription_attempts','subscription_events'] as $table){
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        }
        foreach(['pending_payment','trialing','active','past_due','paused','cancel_pending','canceled','expired'] as $status){
            self::assertStringContainsString("'{$status}'",$sql);
        }
        self::assertStringContainsString('uq_subscriptions_subscriber_idempotency',$sql);
        self::assertStringContainsString('uq_subscription_attempts_cycle_attempt',$sql);
        self::assertStringContainsString('stage_13_subscriptions_monetization',$sql);
    }

    public function testPlansResolveThroughStage12UniversalTargets(): void
    {
        $source=$this->read('api/subscriptions/_subscriptions.php');
        self::assertStringContainsString('mg_tip_resolve_target(',$source);
        self::assertStringContainsString('You do not own this monetization target.',$source);
        self::assertStringContainsString("['week','month','year']",$source);
        self::assertStringContainsString("['wallet','stripe']",$source);
    }

    public function testRenewalsUseCanonicalTipAndLedgerAuthority(): void
    {
        $source=$this->read('api/subscriptions/_subscriptions.php');
        self::assertStringContainsString('mg_tip_create(',$source);
        self::assertStringContainsString('mg_tip_notify_recipient(',$source);
        self::assertStringNotContainsString('INSERT INTO ledger_entries',$source);
        self::assertStringNotContainsString('INSERT INTO wallet_',$source);
    }

    public function testSubscriptionAndAttemptIdempotencyAreBounded(): void
    {
        $source=$this->read('api/subscriptions/_subscriptions.php');
        self::assertStringContainsString('Idempotency key is already bound to another subscription.',$source);
        self::assertStringContainsString("'subscription:'",$source);
        self::assertStringContainsString('subscription_attempts WHERE idempotency_key=?',$source);
        self::assertStringContainsString('cycleKey',$source);
        self::assertStringContainsString('attemptNumber',$source);
    }

    public function testDunningMovesPastDueToPausedAfterRetries(): void
    {
        $source=$this->read('api/subscriptions/_subscriptions.php');
        self::assertStringContainsString('MG_SUBSCRIPTION_MAX_RETRIES',$source);
        self::assertStringContainsString("'paused':'past_due'",$source);
        self::assertStringContainsString('next_retry_at',$source);
        self::assertStringContainsString('subscription.payment_failed',$source);
    }

    public function testLifecycleControlsEnforceSubscriberOwnership(): void
    {
        $source=$this->read('api/subscriptions/manage.php');
        self::assertStringContainsString('s.subscriber_user_id=?',$source);
        foreach(['pause','resume','cancel','cancel_at_period_end'] as $action){
            self::assertStringContainsString("'{$action}'",$source);
        }
        self::assertStringContainsString('mg_require_csrf_for_write(',$source);
        self::assertStringContainsString('mg_subscription_event(',$source);
    }

    public function testWebhookUsesExistingPaymentAuthorityAndExactAttemptIdentity(): void
    {
        $webhook=$this->read('api/subscriptions/payment-webhook.php');
        $funding=$this->read('api/subscriptions/_funding.php');
        self::assertStringContainsString('mg_payment_verify_signature(',$webhook);
        self::assertStringContainsString('payment_webhook_events',$webhook);
        self::assertStringContainsString('attempt_id',$webhook);
        self::assertStringContainsString('subscription_public_id',$webhook);
        self::assertStringContainsString('mg_subscription_apply_payment_success(',$webhook);
        self::assertStringContainsString('mg_subscription_apply_payment_failure(',$webhook);
        self::assertStringContainsString('mg_tip_finalize_stripe(',$funding);
        self::assertStringContainsString('mg_subscription_advance(',$funding);
        self::assertStringContainsString('mg_subscription_mark_failure(',$funding);
    }

    public function testRenewalProcessorLocksDueSubscriptions(): void
    {
        $source=$this->read('scripts/process_subscriptions.php');
        self::assertStringContainsString("s.status IN ('pending_payment','trialing','active','past_due','cancel_pending')",$source);
        self::assertStringContainsString('FOR UPDATE',$source);
        self::assertStringContainsString('mg_subscription_attempt(',$source);
        self::assertStringContainsString('mg_subscription_mark_failure(',$source);
        self::assertStringContainsString('period_ended',$source);
    }

    public function testCommunicationsFoundationHandlesRenewalAndDunningAlerts(): void
    {
        $source=$this->read('api/subscriptions/_notifications.php');
        self::assertStringContainsString('mg_create_operational_alert(',$source);
        self::assertStringContainsString('subscription_payment_failed',$source);
        self::assertStringContainsString('/account.php?section=subscriptions',$source);
    }
}
