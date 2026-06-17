<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionCheckoutCaptureBehaviorTest extends TestCase
{
    public function testCheckoutCaptureIssuanceAndFulfillmentAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed checkout capture validation requires MG_DB_HOST.');
        $root=dirname(__DIR__,2);
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/validate_checkout_capture_behavior.php').' 2>&1';
        $output=[];$exitCode=0;exec($command,$output,$exitCode);$raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);
        $result=json_decode($raw,true);self::assertIsArray($result,$raw);
        self::assertSame('checkout_capture_issuance_fulfillment_behavior',$result['suite']??null);
        foreach([
            'checkout_created','checkout_replay','checkout_conflict_rejected','session_created','session_replay',
            'session_conflict_rejected','capture_completed','capture_replay','capture_conflict_rejected',
            'ledger_balanced','merchant_balance_reconciled','pppm_issued','entitlements_granted','asset_access_gated',
            'microgifts_issued','microgifts_linked_to_pppm','action_center_projected','fulfillment_replay_safe',
            'lifecycle_ready','receipt_consistent','audit_consistent','notifications_once','failed_capture_no_fulfillment',
            'post_ledger_failure_rolled_back','post_fulfillment_failure_rolled_back','fixtures_clean',
        ] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
    }

    public function testEndpointsUseCanonicalCheckoutAndCaptureAuthorities(): void
    {
        $orders=file_get_contents(dirname(__DIR__,2).'/api/commerce/orders.php');
        $checkout=file_get_contents(dirname(__DIR__,2).'/api/commerce/_checkout.php');
        $sessionEndpoint=file_get_contents(dirname(__DIR__,2).'/api/payments/order-checkout-session.php');
        $sessionService=file_get_contents(dirname(__DIR__,2).'/api/payments/_checkout_session.php');
        $capture=file_get_contents(dirname(__DIR__,2).'/api/payments/_capture.php');
        $fulfillment=file_get_contents(dirname(__DIR__,2).'/api/payments/_fulfillment.php');
        foreach([$orders,$checkout,$sessionEndpoint,$sessionService,$capture,$fulfillment] as $source)self::assertIsString($source);
        self::assertStringContainsString('mg_checkout_create_order(',$orders);
        self::assertStringContainsString('Order idempotency key is already bound to a different checkout draft.',$checkout);
        self::assertStringContainsString('mg_payment_create_checkout_session(',$sessionEndpoint);
        self::assertStringContainsString('Payment session idempotency key is already bound to this order.',$sessionService);
        self::assertStringContainsString('Capture replay conflicts with the recorded provider payment.',$capture);
        self::assertStringContainsString("\$failureHook('after_ledger'",$capture);
        self::assertStringContainsString("\$failureHook('after_fulfillment'",$capture);
        self::assertStringContainsString('mg_payment_issue_order_microgifts(',$capture);
        self::assertStringContainsString('PPPM item not found for commerce Microgift issuance.',$fulfillment);
        self::assertStringContainsString('UPDATE microgift_instances SET pppm_item_id=?',$fulfillment);
        self::assertStringContainsString('mg_action_center_project_lifecycle(',$fulfillment);
    }

    public function testComposerAndFocusedWorkflowRunPaidCheckoutFulfillment(): void
    {
        $composer=file_get_contents(dirname(__DIR__,2).'/composer.json');
        $workflow=file_get_contents(dirname(__DIR__,2).'/.github/workflows/paid-checkout-fulfillment-validation.yml');
        self::assertIsString($composer);
        self::assertIsString($workflow);
        self::assertStringContainsString('"test-checkout-fulfillment": "php scripts/validate_checkout_capture_behavior.php"',$composer);
        self::assertStringContainsString('Run paid checkout fulfillment behavior',$workflow);
        self::assertStringContainsString('composer test-checkout-fulfillment',$workflow);
        self::assertStringContainsString('bash scripts/apply_lifecycle_schema.sh',$workflow);
    }
}
