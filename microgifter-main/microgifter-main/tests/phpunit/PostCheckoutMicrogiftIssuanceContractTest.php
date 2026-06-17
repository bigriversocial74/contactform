<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class PostCheckoutMicrogiftIssuanceContractTest extends TestCase
{
    public function testPaidOrderCaptureTriggersPppmAndMicrogiftIssuance(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/payments/_capture.php');
        self::assertIsString($source);

        foreach([
            'require_once __DIR__ . \'/_fulfillment.php\'',
            '$issued=mg_payment_issue_order_pppm($pdo,$orderDbId,$actorUserId ?: (int)$order[\'buyer_user_id\'])',
            '$microgifts=mg_payment_issue_order_microgifts($pdo,$orderDbId,$actorUserId ?: (int)$order[\'buyer_user_id\'])',
            "'after_fulfillment',['order'=>\$order,'intent'=>\$intent,'issued'=>\$issued,'microgifts'=>\$microgifts]",
            '\'microgift_issued_count\'=>(int)($microgifts[\'issued_count\']??0)',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testCheckoutOrderItemsPersistMerchantWhenSchemaSupportsIt(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/commerce/_checkout.php');
        self::assertIsString($source);

        self::assertStringContainsString('function mg_checkout_order_items_have_merchant(PDO $pdo): bool', $source);
        self::assertStringContainsString("SHOW COLUMNS FROM commerce_order_items LIKE 'merchant_user_id'", $source);
        self::assertStringContainsString('if(mg_checkout_order_items_have_merchant($pdo))', $source);
        self::assertStringContainsString('INSERT INTO commerce_order_items (public_id,order_id,product_id,product_version_id,merchant_user_id,title_snapshot,quantity,unit_amount_cents,discount_cents,tax_cents,line_total_cents,currency,created_at)', $source);
        self::assertStringContainsString('INSERT INTO commerce_order_items (public_id,order_id,product_id,product_version_id,title_snapshot,quantity,unit_amount_cents,discount_cents,tax_cents,line_total_cents,currency,created_at)', $source);
    }

    public function testFulfillmentLayerIsSchemaAwareBeforeMicrogiftIssuance(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/payments/_fulfillment.php');
        self::assertIsString($source);

        foreach([
            "require_once dirname(__DIR__) . '/commerce/_foundation.php'",
            "require_once dirname(__DIR__) . '/microgifts/_engine.php'",
            "require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php'",
            'function mg_payment_order_items_have_merchant(PDO $pdo): bool',
            "SHOW COLUMNS FROM commerce_order_items LIKE 'merchant_user_id'",
            'function mg_payment_issue_order_microgifts(PDO $pdo, int $orderDbId, ?int $actorUserId = null): array',
            "return ['issued_count'=>0,'skipped'=>true,'reason'=>'commerce_order_items.merchant_user_id_missing']",
            "if(!\$order || (string)\$order['payment_status']!=='paid')return ['issued_count'=>0,'skipped'=>true]",
            'UPDATE commerce_order_items SET merchant_user_id=? WHERE order_id=? AND merchant_user_id IS NULL',
            'mg_action_center_project_lifecycle($pdo,$instance)',
            'mg_order_event($pdo,$orderDbId,\'microgift.issued_from_paid_order\'',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testMigrationAddsMerchantColumnForCommerceOrderItems(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/database/stage_3_commerce_microgift_fulfillment.sql');
        self::assertIsString($source);

        foreach([
            "TABLE_NAME = 'commerce_order_items'",
            "COLUMN_NAME = 'merchant_user_id'",
            'ALTER TABLE commerce_order_items ADD COLUMN merchant_user_id BIGINT UNSIGNED NULL AFTER product_version_id',
            'UPDATE commerce_order_items oi',
            'INNER JOIN commerce_orders o ON o.id = oi.order_id',
            'idx_commerce_order_items_merchant',
            'fk_commerce_order_items_merchant',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('MODIFY COLUMN merchant_user_id BIGINT UNSIGNED NOT NULL', $source);
    }

    public function testFullUpgradeBuilderRegistersCommerceMicrogiftFulfillmentMigration(): void
    {
        $fullUpgrade=file_get_contents(dirname(__DIR__,2).'/scripts/build_full_upgrade_sql.php');
        $earlyRunner=file_get_contents(dirname(__DIR__,2).'/scripts/run_migrations.php');
        self::assertIsString($fullUpgrade);
        self::assertIsString($earlyRunner);

        self::assertStringContainsString("'stage_3_commerce_microgift_fulfillment.sql'", $fullUpgrade);
        self::assertStringContainsString("'stage_5i_payments_checkout_reconciliation.sql'", $fullUpgrade);
        self::assertStringNotContainsString("'stage_3_commerce_microgift_fulfillment.sql'", $earlyRunner);
    }

    public function testFulfillmentCreatesOrReusesMicrogiftTemplateVersionForCatalogVersion(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/payments/_fulfillment.php');
        self::assertIsString($source);

        foreach([
            'function mg_payment_microgift_template_version_for_line(PDO $pdo, array $order, array $line): string',
            'FROM microgift_template_versions v INNER JOIN microgift_templates t ON t.id=v.template_id WHERE v.product_version_id=?',
            'mg_microgift_create_template($pdo,(int)$order[\'merchant_user_id\']',
            "'owner_type'=>'merchant'",
            "'gift_type'=>'product'",
            'mg_microgift_create_version($pdo,(int)$order[\'merchant_user_id\']',
            "'product_id'=>(int)\$line['product_id']",
            "'product_version_id'=>(int)\$line['product_version_id']",
            "'recipient_policy'=>'purchaser'",
            'mg_microgift_publish_version($pdo,(int)$order[\'merchant_user_id\']',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testMicrogiftIssuanceIsIdempotentPerPaidOrderLineUnit(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/payments/_fulfillment.php');
        self::assertIsString($source);

        foreach([
            'for($sequence=1;$sequence<=(int)$line[\'quantity\'];$sequence++)',
            'mg_microgift_issue($pdo,(int)$order[\'merchant_user_id\']',
            "'source_type'=>'commerce_order_item'",
            "'source_reference'=>(string)\$line['public_id']",
            "'idempotency_key'=>'commerce-order-item:'.\$line['public_id'].':microgift:'.\$sequence",
            "'recipient_user_id'=>(int)\$order['buyer_user_id']",
            "'commerce_order_id'=>(string)\$order['public_id']",
            "'commerce_order_item_id'=>(string)\$line['public_id']",
            "if(!empty(\$result['duplicate']))\$duplicates++;else\$issued++;",
            'SELECT * FROM microgift_instances WHERE public_id=? LIMIT 1 FOR UPDATE',
            'UPDATE microgift_instances SET pppm_item_id=?,updated_at=NOW() WHERE id=?',
            'mg_action_center_project_lifecycle($pdo,$instance)',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString("if(!empty(\$result['duplicate'])){\$duplicates++;continue;}",$source);
    }
}
