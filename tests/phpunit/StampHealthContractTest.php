<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampHealthContractTest extends TestCase
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

    public function testStampHealthServiceAndApiExist(): void
    {
        $service = $this->read('api/stamps/_health.php');
        $api = $this->read('api/stamps/health.php');
        foreach(['mg_stamp_health_check_table','mg_stamp_health_check_file','mg_stamp_system_health','stage_17_stamp_ledger.sql','delivery_webhook_token_configured'] as $needle){
            self::assertStringContainsString($needle, $service);
        }
        self::assertStringContainsString('mg_stamp_system_health', $api);
        self::assertStringContainsString('admin.commerce.view', $api);
    }

    public function testStampHealthAdminPageExists(): void
    {
        $page = $this->read('admin/stamp-health.php');
        $js = $this->read('assets/js/admin-stamp-health.js');
        foreach(['Stamp system health','data-run-stamp-health','scripts/stamp_health_check.php'] as $needle){
            self::assertStringContainsString($needle, $page);
        }
        self::assertStringContainsString('/api/stamps/health.php', $js);
        self::assertStringContainsString('data-stamp-health-list', $js);
    }

    public function testStampHealthNavAndCliExist(): void
    {
        $nav = $this->read('includes/admin-sidebar.php');
        $script = $this->read('scripts/stamp_health_check.php');
        self::assertStringContainsString('stamp-health', $nav);
        self::assertStringContainsString('/admin/stamp-health.php', $nav);
        self::assertStringContainsString('mg_stamp_system_health', $script);
    }
}
