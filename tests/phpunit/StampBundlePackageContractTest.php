<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampBundlePackageContractTest extends TestCase
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

    public function testStampServiceSupportsBundlesAndMonthlyAllowance(): void
    {
        $source = $this->read('api/stamps/_stamps.php');
        foreach([
            'function mg_stamp_bundle_rows',
            'function mg_stamp_bundle_save',
            'function mg_stamp_credit_monthly_allowance',
            'monthly_package_allowance',
            'stamp:monthly:',
            'included_monthly_stamps',
            'purchased_stamps',
        ] as $needle){
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testBundleAndMonthlyCreditEndpointsExist(): void
    {
        $bundles = $this->read('api/stamps/bundles.php');
        $monthly = $this->read('api/stamps/monthly-credit.php');
        self::assertStringContainsString('mg_stamp_bundle_rows', $bundles);
        self::assertStringContainsString('mg_stamp_bundle_save', $bundles);
        self::assertStringContainsString('admin.stamps.manage', $bundles);
        self::assertStringContainsString('mg_stamp_credit_monthly_allowance', $monthly);
        self::assertStringContainsString('account_user_id', $monthly);
        self::assertStringContainsString('plan_id', $monthly);
    }

    public function testAdminPackagePageHasStampBundleTab(): void
    {
        $page = $this->read('admin/package-moderation.php');
        $panel = $this->read('includes/admin-stamp-bundles-panel.php');
        $js = $this->read('assets/js/admin-stamp-bundles.js');
        self::assertStringContainsString('pkg-tab-bundles', $page);
        self::assertStringContainsString('Stamp bundles', $page);
        self::assertStringContainsString('admin-stamp-bundles-panel.php', $page);
        self::assertStringContainsString('data-admin-stamp-bundle-form', $panel);
        self::assertStringContainsString('data-admin-monthly-stamps-form', $panel);
        self::assertStringContainsString('/api/stamps/bundles.php', $js);
        self::assertStringContainsString('/api/stamps/monthly-credit.php', $js);
    }
}
