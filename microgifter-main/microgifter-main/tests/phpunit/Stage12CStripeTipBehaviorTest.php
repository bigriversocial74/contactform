<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage12CStripeTipBehaviorTest extends TestCase
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

    public function testCanonicalPaymentIntentServiceSupportsTipSources(): void
    {
        $source=$this->read('api/payments/_payments.php');
        foreach([
            'function mg_payment_create_source_intent',
            'source_type',
            'source_reference',
            'provider_intent_reference',
            'Payment intent idempotency key is already bound to a different request.',
            'function mg_payment_record_intent_transaction',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('INSERT INTO financial_ledger_entries',$source);
    }

    public function testTipCreationLinksAndReturnsCanonicalIntentData(): void
    {
        $service=$this->read('api/tips/_tips.php');
        $endpoint=$this->read('api/tips/create.php');
        foreach([
            'mg_payment_create_source_intent(',
            "'source_type'=>'tip'",
            'payment_intent_id',
            'provider_payment_id',
            'client_secret',
            'function mg_tip_payment_payload',
        ] as $needle){
            self::assertStringContainsString($needle,$service.$endpoint);
        }
    }

    public function testWebhookProcessingVerifiesProviderSourceAmountCurrencyAndMetadata(): void
    {
        $source=$this->read('api/tips/_tips.php');
        foreach([
            'function mg_tip_process_payment_event',
            'Tip payment event does not match a known tip.',
            'Tip payment provider does not match.',
            'Tip payment intent source does not match.',
            'Tip payment amount or currency does not match.',
            "metadata['tip_id']",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testWebhookEndpointUsesCanonicalProcessorAndDurableWebhookQueue(): void
    {
        $source=$this->read('api/tips/payment-webhook.php');
        self::assertStringContainsString('mg_payment_verify_signature(',$source);
        self::assertStringContainsString('payment_webhook_events',$source);
        self::assertStringContainsString('mg_tip_process_payment_event_result(',$source);
        self::assertStringContainsString("status='processing'",$source);
        self::assertStringContainsString("status='failed'",$source);
    }

    public function testSubscriptionAttemptsBindToTheCanonicalTipProviderIntent(): void
    {
        $source=$this->read('api/subscriptions/_subscriptions.php');
        self::assertStringContainsString('$tip[\'provider_payment_id\']',$source);
        self::assertStringContainsString('Subscription tip payment intent is unavailable.',$source);
        self::assertStringNotContainsString('\'subpay_\'.mg_public_uuid()',$source);
    }

    public function testExecutableBehaviorSuiteCoversSettlementReplayFailureAndRollback(): void
    {
        $source=$this->read('scripts/validate_tip_payment_behavior.php');
        foreach([
            "'intent_created'=>false",
            "'intent_replay_safe'=>false",
            "'intent_conflict_rejected'=>false",
            "'provider_contract_verified'=>false",
            "'processing_transition'=>false",
            "'success_settled_once'=>false",
            "'failure_no_credit'=>false",
            "'failure_then_success_recovered'=>false",
            "'stale_failure_ignored'=>false",
            "'downstream_rollback'=>false",
            "'fixtures_clean'=>false",
            'SAVEPOINT intent_conflict',
            'SAVEPOINT provider_contract',
            'SAVEPOINT tip_payment_rollback',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testComposerAndCiRunTheStage12CPaymentSuite(): void
    {
        $composer=$this->read('composer.json');
        $workflow=$this->read('.github/workflows/pr-validation.yml');
        self::assertStringContainsString('"test-tip-payment-behavior": "php scripts/validate_tip_payment_behavior.php"',$composer);
        self::assertStringContainsString('Validate Stripe-funded tip payment behavior',$workflow);
        self::assertStringContainsString('composer test-tip-payment-behavior',$workflow);
    }
}
