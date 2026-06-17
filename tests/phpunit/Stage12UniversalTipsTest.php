<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage12UniversalTipsTest extends TestCase
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

    public function testSchemaDefinesTipLifecycleVelocityAndReversals(): void
    {
        $sql=$this->read('database/stage_12_universal_tips.sql');
        foreach(['CREATE TABLE IF NOT EXISTS tips','CREATE TABLE IF NOT EXISTS tip_velocity_counters','CREATE TABLE IF NOT EXISTS tip_reversals'] as $needle){
            self::assertStringContainsString($needle,$sql);
        }
        foreach(['pending','funded','posted','failed','reversed'] as $state){
            self::assertStringContainsString("'{$state}'",$sql);
        }
        self::assertStringContainsString('uq_tips_sender_idempotency',$sql);
        self::assertStringContainsString('fee_snapshot_json',$sql);
        self::assertStringContainsString('stage_12_universal_tips',$sql);
    }

    public function testUniversalTargetsResolveThroughExistingAuthorities(): void
    {
        $source=$this->read('api/tips/_tips.php');
        foreach(['profile','creator','merchant','location','product','post','gift','claim'] as $target){
            self::assertStringContainsString("'{$target}'",$source);
        }
        foreach(['merchant_workspaces','merchant_locations','catalog_products','feed_posts','microgift_instances','microgift_claims','microgift_redemptions'] as $table){
            self::assertStringContainsString($table,$source);
        }
    }

    public function testWalletAndStripeFundingUseCanonicalStage7Ledger(): void
    {
        $source=$this->read('api/tips/_tips.php');
        foreach(['mg_wallet_resolve(','mg_wallet_balances(','mg_wallet_account_id(','mg_ledger_platform_account(','mg_ledger_post(',"'processor_clearing'","'tip_fee_revenue'"] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('INSERT INTO ledger_entries',$source);
    }

    public function testTipRequestsBindExactIdempotencyAndRejectSelfTips(): void
    {
        $source=$this->read('api/tips/_tips.php');
        foreach(['Idempotency key is already bound to a different tip request.','You cannot tip yourself.','amount_cents','target_type','target_reference','funding_type'] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testFeesAndVelocityAreSnapshottedAndBounded(): void
    {
        $source=$this->read('api/tips/_tips.php');
        foreach(['MG_TIP_FEE_BPS',"'fee_cents'","'net_cents'",'$count>=20','$amount+$amountCents>250000','Tip amount must be between $1.00 and $1,000.00.'] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testStripeSettlementUsesSignedIdempotentWebhookFoundation(): void
    {
        $webhook=$this->read('api/tips/payment-webhook.php');
        $router=$this->read('api/tips/_recovery_webhook.php');
        $events=$this->read('api/tips/_payment_events.php');
        $tips=$this->read('api/tips/_tips.php');

        foreach(['mg_payment_verify_signature(','payment_webhook_events','mg_tip_route_payment_event(',"'Duplicate'"] as $needle){
            self::assertStringContainsString($needle,$webhook);
        }
        self::assertStringContainsString('mg_tip_process_payment_event_result(',$router);
        self::assertStringContainsString('mg_tip_notify_recipient(',$router);
        self::assertStringContainsString('mg_tip_process_payment_event(',$events);
        self::assertStringContainsString('mg_tip_finalize_stripe(',$tips);
    }

    public function testActionCenterEnforcesCanonicalTipAvailability(): void
    {
        $source=$this->read('api/account/action-center-tip.php');
        foreach(['ac.can_tip',"folder']!=='claimed'","state']!=='redeemed'","'target_type'=>'gift'",'mg_tip_create(','mg_require_csrf_for_write('] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testRecipientNotificationsUseCommunicationsFoundation(): void
    {
        $source=$this->read('api/tips/_notifications.php');
        foreach(['mg_create_operational_alert(',"'tip_received'","mg_event('tip.received'"] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testAdministrativeReversalUsesCanonicalLedgerReversal(): void
    {
        $service=$this->read('api/tips/_tips.php');
        $endpoint=$this->read('api/admin/tip-reverse.php');
        self::assertStringContainsString("mg_require_permission('tips.reverse')",$endpoint);
        self::assertStringContainsString('mg_tip_reverse(',$endpoint);
        self::assertStringContainsString('mg_ledger_reverse(',$service);
        self::assertStringContainsString('INSERT INTO tip_reversals',$service);
        self::assertStringContainsString("status='reversed'",$service);
        self::assertStringNotContainsString('INSERT INTO ledger_entries',$service);
        self::assertStringNotContainsString('INSERT INTO tip_reversals',$endpoint);
    }

    public function testActionCenterFrontendPostsTipContract(): void
    {
        $source=$this->read('assets/js/gift-action-center-actions.js');
        foreach(["'tip'",'amount_cents',"request.funding_type='wallet'","'/api/account/action-center-'",'Microgifter.post('] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
