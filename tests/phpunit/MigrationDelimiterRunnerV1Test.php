<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/migrations.php';

final class MigrationDelimiterRecordingPdo extends PDO
{
    /** @var list<string> */
    public array $statements = [];

    public function __construct()
    {
    }

    public function exec(string $statement): int|false
    {
        $this->statements[] = $statement;
        return 0;
    }
}

final class MigrationDelimiterRunnerV1Test extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testProductBundleMigrationSplitsIntoServerExecutableStatements(): void
    {
        $path = $this->root . '/database/20260719_product_bundle_checkout_fulfillment_v3.sql';
        $sql = file_get_contents($path);
        self::assertIsString($sql);

        $statements = mg_migration_sql_statements($sql);

        self::assertCount(6, $statements);
        self::assertStringStartsWith('CREATE TABLE IF NOT EXISTS gift_bundle_checkout_attempts', $statements[0]);
        self::assertStringStartsWith('CREATE TABLE IF NOT EXISTS gift_bundle_fulfillment_dispatches', $statements[1]);
        self::assertSame('DROP PROCEDURE IF EXISTS mg_product_bundle_checkout_fulfillment_v3_upgrade', $statements[2]);
        self::assertStringStartsWith('CREATE PROCEDURE mg_product_bundle_checkout_fulfillment_v3_upgrade()', $statements[3]);
        self::assertStringContainsString('ALTER TABLE gift_bundle_orders ADD COLUMN payment_intent_id', $statements[3]);
        self::assertStringContainsString('ADD CONSTRAINT fk_gift_bundle_orders_payment_intent', $statements[3]);
        self::assertSame('CALL mg_product_bundle_checkout_fulfillment_v3_upgrade()', $statements[4]);
        self::assertSame('DROP PROCEDURE IF EXISTS mg_product_bundle_checkout_fulfillment_v3_upgrade', $statements[5]);

        foreach ($statements as $statement) {
            self::assertStringNotContainsString('DELIMITER', $statement);
        }
    }

    public function testDelimiterAwareExecutionNeverSendsClientDirectiveToPdo(): void
    {
        $sql = file_get_contents($this->root . '/database/20260719_product_bundle_checkout_fulfillment_v3.sql');
        self::assertIsString($sql);
        $pdo = new MigrationDelimiterRecordingPdo();

        mg_migration_execute_sql($pdo, $sql);

        self::assertCount(6, $pdo->statements);
        foreach ($pdo->statements as $statement) {
            self::assertStringNotContainsString('DELIMITER', $statement);
        }
    }

    public function testPlainMigrationRetainsSingleExecBehavior(): void
    {
        $pdo = new MigrationDelimiterRecordingPdo();
        $sql = "CREATE TABLE example_one (id INT);\nCREATE TABLE example_two (id INT);";

        mg_migration_execute_sql($pdo, $sql);

        self::assertSame([$sql], $pdo->statements);
    }

    public function testUnterminatedCustomDelimiterFailsClosed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unterminated migration SQL statement');

        mg_migration_sql_statements("DELIMITER $$\nCREATE PROCEDURE broken()\nBEGIN\nSELECT 1;\nEND\n");
    }
}
