<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class CheckoutSuccessOrderConfirmationContractTest extends TestCase
{
    public function testCheckoutSuccessPageLoadsOrderConfirmationRenderer(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/checkout-success.php');
        self::assertIsString($source);

        foreach([
            '$page_title = \'Order Complete | Microgifter\'',
            '\'/assets/js/order-success.js\'',
            'data-order-success',
            'data-order-id',
            'data-order-success-receipt',
            'Open commerce center',
            'Open inbox',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testOrderConfirmationEndpointRequiresBuyerOwnedOrder(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/commerce/order-confirmation.php');
        self::assertIsString($source);

        foreach([
            'mg_require_method(\'GET\')',
            '$user=mg_require_api_user()',
            '$orderId=trim((string)($_GET[\'order_id\']??$_GET[\'order\']??\'\'))',
            'FROM commerce_orders WHERE public_id=? AND buyer_user_id=? LIMIT 1',
            'if(!$order)mg_fail(\'Order not found.\',404)',
            '$orderPayload=mg_order_payload($pdo,$order)',
            'FROM receipts WHERE order_id=? LIMIT 1',
            'SELECT event_type,actor_user_id,payload_json AS metadata_json,created_at FROM order_audit_events WHERE order_id=? ORDER BY id DESC LIMIT 12',
            'SELECT status_domain AS domain,from_status AS old_status,to_status AS new_status,reason_code AS reason,created_at FROM order_status_history WHERE order_id=? ORDER BY id DESC LIMIT 12',
            '\'action_center\'=>\'/inbox.php\'',
            'mg_ok([',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testOrderSuccessJavascriptRendersConfirmationAndFollowupLinks(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/order-success.js');
        self::assertIsString($source);

        foreach([
            '/api/commerce/order-confirmation.php?order_id=',
            'Order confirmation',
            'Payment ',
            'Fulfillment ',
            'mg-order-confirmation-grid',
            'mg-order-followup',
            'Open Inbox',
            'Status history',
            "document.title='Order '",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testLegacyReceiptEndpointRemainsAvailable(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/commerce/receipt.php');
        self::assertIsString($source);

        foreach([
            'mg_require_method(\'GET\')',
            '$user=mg_require_api_user()',
            'FROM receipts r INNER JOIN commerce_orders o ON o.id=r.order_id WHERE o.public_id=? AND o.buyer_user_id=? LIMIT 1',
            'mg_ok([\'order_id\'=>$orderId,\'receipt\'=>$receipt])',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}