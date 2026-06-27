<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmActionHistoryContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }
    private function read(string $path): string { return (string) file_get_contents($this->root . '/' . $path); }

    public function testActionHistoryFilesExist(): void
    {
        self::assertStringContainsString('crm-action-history.php', $this->read('api/merchant/crm-action-history.php'));
        self::assertStringContainsString('mg_crm_action_history_record_result', $this->read('includes/merchant-crm-action-history.php'));
        self::assertStringContainsString('data-crm-action-history', $this->read('assets/js/merchant-crm-command-center.js'));
    }

    public function testBulkMessageHistoryWiring(): void
    {
        $endpoint = $this->read('api/merchant/crm-bulk-message.php');
        $js = $this->read('assets/js/merchant-crm-command-center.js');
        self::assertStringContainsString('merchant-crm-action-history.php', $endpoint);
        self::assertStringContainsString('mg_crm_action_history_record_result', $endpoint);
        self::assertStringContainsString("'message'", $endpoint);
        self::assertStringContainsString('message_length', $endpoint);
        self::assertStringContainsString('value="message"', $js);
        self::assertStringContainsString('Bulk message', $this->read('includes/merchant-crm-action-history.php'));
    }
}
