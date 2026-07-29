<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RegiftFreeStampBypassTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testFreeRegiftReturnsBeforeStampLedgerWrite(): void
    {
        $source = file_get_contents($this->root . '/api/stamps/_stamps.php');
        self::assertIsString($source);

        $functionStart = strpos($source, 'function mg_stamp_debit(');
        $freeGuard = strpos($source, 'if ($stampValue === 0)', $functionStart);
        $ledgerWrite = strpos($source, 'return mg_stamp_post_entry(', $functionStart);

        self::assertNotFalse($functionStart);
        self::assertNotFalse($freeGuard);
        self::assertNotFalse($ledgerWrite);
        self::assertLessThan($ledgerWrite, $freeGuard, 'The zero-cost return must execute before any Stamp ledger write.');
        self::assertStringContainsString("$stampValue = $actionKey === 'regift_send' ? 0 : $configuredStampValue;", $source);
        self::assertStringContainsString("'debit_status' => 'free_action'", $source);
        self::assertStringContainsString("'debit_applied' => false", $source);
    }

    public function testRegiftBypassesMissingBalanceAndLedgerTablesWhenSqliteIsAvailable(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite is not available in this PHP job.');
        }

        require_once $this->root . '/api/stamps/_stamps.php';

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE stamp_debit_actions (
            action_key TEXT PRIMARY KEY,
            label TEXT NOT NULL,
            channel TEXT NOT NULL,
            scope TEXT NOT NULL,
            stamp_value INTEGER NOT NULL,
            description TEXT,
            status TEXT NOT NULL
        )");
        $pdo->exec("INSERT INTO stamp_debit_actions (action_key,label,channel,scope,stamp_value,description,status)
            VALUES ('regift_send','Regift send','Direct','Microgift',9,'Stale database value','active')");

        $result = mg_stamp_debit($pdo, 4001, 3001, 'regift_send', 2, 'regift-free-test');

        self::assertSame('free_action', $result['debit_status'] ?? null);
        self::assertFalse((bool)($result['debit_applied'] ?? true));
        self::assertTrue((bool)($result['free_action'] ?? false));
        self::assertSame(0, $result['stamp_value'] ?? null);
        self::assertSame(0, $result['required'] ?? null);
        self::assertSame(9, $result['configured_stamp_value'] ?? null);
        self::assertNull($result['entry'] ?? null);

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['stamp_debit_actions'], $tables, 'Free regifts must not create or require balance/ledger tables.');
    }
}
