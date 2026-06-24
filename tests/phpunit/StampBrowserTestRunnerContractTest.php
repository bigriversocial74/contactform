<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampBrowserTestRunnerContractTest extends TestCase
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

    public function testTestRunnerApiExists(): void
    {
        $source = $this->read('api/stamps/test-runner.php');
        foreach(['mg_stamp_test_response','assign_package','renewal_preview','renewal_run','purchase_sandbox','test_debit','delivery_failure'] as $needle){
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testPricingPackageTabIsInjected(): void
    {
        $source = $this->read('assets/js/admin-stamp-test-runner.js');
        foreach(['pkg-tab-stamp-tests','Stamp tests','/api/stamps/test-runner.php','data-stamp-test="health"','data-stamp-test="purchase_sandbox"'] as $needle){
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testPricingPackagePageLoadsRunnerScript(): void
    {
        $source = $this->read('includes/admin-stamp-bundles-panel.php');
        self::assertStringContainsString('/assets/js/admin-stamp-test-runner.js', $source);
    }
}
