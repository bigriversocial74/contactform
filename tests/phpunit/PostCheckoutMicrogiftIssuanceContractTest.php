<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class PostCheckoutMicrogiftIssuanceContractTest extends TestCase
{
    public function testPaidOrderCaptureUsesCanonicalIssuanceReconciler(): void
    {
        $root=dirname(__DIR__,2);
        $capture=file_get_contents($root.'/api/payments/_capture.php');
        $reconciliation=file_get_contents($root.'/api/payments/_issuance_reconciliation.php');
        self::assertIsString($capture);self::assertIsString($reconciliation);
        foreach([
            "require_once __DIR__ . '/_issuance_reconciliation.php'",
            'mg_payment_reconcile_paid_order($pdo,$orderDbId',
            "'after_fulfillment',['order'=>\$order,'intent'=>\$intent,'reconciliation'=>\$reconciliation",
            "'issuance_complete'=>(bool)(\$reconciliation['complete']??false)",
        ] as $needle)self::assertStringContainsString($needle,$capture);
        foreach([
            'function mg_payment_reconcile_paid_order(',
            'mg_payment_issue_order_pppm(',
            'mg_payment_issue_order_microgifts(',
            'mg_order_issuance_summary(',
            "'fulfillment.reconciled'",
            "'microgift_delivery_ready'",
        ] as $needle)self::assertStringContainsString($needle,$reconciliation);
    }

    public function testCheckoutOrderItemsPersistMerchantWhenSchemaSupportsIt(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/commerce/_checkout.php');
        self::assertIsString($source);
        self::assertStringContainsString('function mg_checkout_order_items_have_merchant(PDO $pdo): bool',$source);
        self::assertStringContainsString("SHOW COLUMNS FROM commerce_order_items LIKE 'merchant_user_id'",$source);
        self::assertStringContainsString('if(mg_checkout_order_items_have_merchant($pdo))',$source);
        self::assertStringContainsString('product_version_id,merchant_user_id,title_snapshot',$source);
    }

    public function testFulfillmentUsesMerchantIssuerAndBuyerProjection(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/payments/_fulfillment.php');
        self::assertIsString($source);
        foreach([
            "require_once dirname(__DIR__) . '/microgifts/_engine.php'",
            "require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php'",
            '$issuerUserId=(int)$order[\'merchant_user_id\']',
            "'recipient_user_id'=>(int)\$order['buyer_user_id']",
            'mg_action_center_receive(',
            "mg_order_event(\$pdo,\$orderDbId,'microgift.issued_from_paid_order'",
        ] as $needle)self::assertStringContainsString($needle,$source);
        self::assertStringNotContainsString('mg_action_center_project_lifecycle($pdo,$instance)',$source);
    }

    public function testMigrationAddsMerchantColumnForCommerceOrderItems(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/database/stage_3_commerce_microgift_fulfillment.sql');
        self::assertIsString($source);
        foreach([
            "TABLE_NAME = 'commerce_order_items'",
            "COLUMN_NAME = 'merchant_user_id'",
            'ALTER TABLE commerce_order_items ADD COLUMN merchant_user_id BIGINT UNSIGNED NULL AFTER product_version_id',
            'UPDATE commerce_order_items oi','INNER JOIN commerce_orders o ON o.id = oi.order_id',
            'idx_commerce_order_items_merchant','fk_commerce_order_items_merchant',
        ] as $needle)self::assertStringContainsString($needle,$source);
        self::assertStringNotContainsString('MODIFY COLUMN merchant_user_id BIGINT UNSIGNED NOT NULL',$source);
    }

    public function testFullUpgradeRegistersCommerceMicrogiftFulfillmentMigration(): void
    {
        $manifest=require dirname(__DIR__,2).'/config/migrations.php';
        self::assertContains('stage_3_commerce_microgift_fulfillment.sql',$manifest['ordered_files']);
        self::assertContains('stage_5i_payments_checkout_reconciliation.sql',$manifest['ordered_files']);
        self::assertContains('stage_v1c_checkout_session_intent_authority.sql',$manifest['ordered_files']);
    }

    public function testFulfillmentCreatesOrReusesMicrogiftTemplateVersionForCatalogVersion(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/payments/_fulfillment.php');
        self::assertIsString($source);
        foreach([
            'function mg_payment_microgift_template_version_for_line(PDO $pdo, array $order, array $line): string',
            'catalog_pppm_templates cpt',
            'mg_microgift_create_template($pdo,(int)$order[\'merchant_user_id\']',
            "'owner_type'=>'merchant'","'gift_type'=>'product'",
            'mg_microgift_create_version($pdo,(int)$order[\'merchant_user_id\']',
            "'product_id'=>(int)\$line['product_id']","'product_version_id'=>(int)\$line['product_version_id']",
            "'recipient_policy'=>'purchaser'",
            'mg_microgift_publish_version($pdo,(int)$order[\'merchant_user_id\']',
        ] as $needle)self::assertStringContainsString($needle,$source);
    }

    public function testMicrogiftIssuanceIsIdempotentPerPaidOrderLineUnit(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/payments/_fulfillment.php');
        self::assertIsString($source);
        foreach([
            'for($sequence=1;$sequence<=(int)$line[\'quantity\'];$sequence++)',
            "\$idempotencyKey='commerce-order-item:'.\$line['public_id'].':microgift:'.\$sequence",
            'mg_microgift_existing_issue(',
            "'commerce_order_item'",
            "'recipient_user_id'=>(int)\$order['buyer_user_id']",
            "if(!empty(\$result['duplicate']))\$duplicates++;else\$issued++;",
            'SELECT * FROM microgift_instances WHERE public_id=? LIMIT 1 FOR UPDATE',
            'UPDATE microgift_instances SET pppm_item_id=?,updated_at=NOW() WHERE id=?',
            'mg_action_center_receive(',
        ] as $needle)self::assertStringContainsString($needle,$source);
    }

    public function testIssuanceSummaryCountsBuyerProjectionAcrossFolders(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/commerce/_order_issuance_summary.php');
        self::assertIsString($source);
        self::assertStringContainsString('COUNT(DISTINCT inbox.instance_id)',$source);
        self::assertStringContainsString('inbox.user_id=?',$source);
        self::assertStringNotContainsString("inbox.folder='inbox'",$source);
        self::assertStringContainsString("'action_center_items'=>\$projectionItems",$source);
    }
}
