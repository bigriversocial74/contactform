<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampMonthlyRenewalContractTest extends TestCase
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

    public function testPackageAssignmentMigrationExists(): void
    {
        $source = $this->read('database/stage_17c_stamp_package_assignments.sql');
        foreach(['CREATE TABLE IF NOT EXISTS account_package_assignments','package_id','status','stage_17c_stamp_package_assignments'] as $needle){
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testMonthlyRenewalServiceAndApiExist(): void
    {
        $service = $this->read('api/stamps/_renewals.php');
        $api = $this->read('api/stamps/monthly-renewals.php');
        $script = $this->read('scripts/stamp_monthly_renewal.php');
        foreach(['function mg_stamp_plan_allowance','function mg_stamp_active_package_assignments','function mg_stamp_monthly_renewal_preview','function mg_stamp_run_monthly_renewals','stamp:monthly:','monthly_package_allowance'] as $needle){
            self::assertStringContainsString($needle, $service);
        }
        self::assertStringContainsString('admin.stamps.manage', $api);
        self::assertStringContainsString('mg_stamp_monthly_renewal_preview', $api);
        self::assertStringContainsString('mg_stamp_run_monthly_renewals', $api);
        self::assertStringContainsString('dryRun', $script);
    }

    public function testAdminBundlePanelHasRenewalControls(): void
    {
        $panel = $this->read('includes/admin-stamp-bundles-panel.php');
        $js = $this->read('assets/js/admin-stamp-bundles.js');
        foreach(['data-admin-monthly-renewals-form','data-admin-monthly-renewals-list','Preview renewals','Run renewals'] as $needle){
            self::assertStringContainsString($needle, $panel);
        }
        self::assertStringContainsString('/api/stamps/monthly-renewals.php', $js);
        self::assertStringContainsString('previewRenewals', $js);
    }
}
