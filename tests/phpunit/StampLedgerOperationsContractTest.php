<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampLedgerOperationsContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testAdjustmentApiExists(): void
    {
        $source = $this->read('api/stamps/adjustment.php');
        foreach(['admin.stamps.manage','mg_stamp_post_entry','admin_adjustment','reason_code','stamp:adjustment:'] as $needle){
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testReturnApiExists(): void
    {
        $source = $this->read('api/stamps/void.php');
        foreach(['mg_stamp_credit','failed_send_void','voided_entry_id','stamp:void:','entry_id'] as $needle){
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testAdminOperationControlsExist(): void
    {
        $panel = $this->read('includes/admin-stamp-bundles-panel.php');
        $js = $this->read('assets/js/admin-stamp-bundles.js');
        foreach(['data-admin-stamp-adjustment-form','data-admin-stamp-void-form','Ledger operations','Record adjustment'] as $needle){
            self::assertStringContainsString($needle, $panel);
        }
        self::assertStringContainsString('/api/stamps/adjustment.php', $js);
        self::assertStringContainsString('/api/stamps/void.php', $js);
    }
}
