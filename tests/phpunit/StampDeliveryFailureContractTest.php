<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampDeliveryFailureContractTest extends TestCase
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

    public function testDeliveryFailureServiceExists(): void
    {
        $source = $this->read('api/stamps/_delivery_failures.php');
        foreach(['mg_stamp_delivery_failure_find_debit','mg_stamp_delivery_failure_void','delivery_failure_void','stamp:delivery-failure:','voided_entry_id'] as $needle){
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testDeliveryFailureApiExists(): void
    {
        $source = $this->read('api/stamps/delivery-failure.php');
        foreach(['mg_stamp_delivery_failure_void','Delivery failure returned Stamps','account_user_id'] as $needle){
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testProviderWebhookExists(): void
    {
        $source = $this->read('api/stamps/provider-delivery-webhook.php');
        foreach(['MICROGIFTER_DELIVERY_WEBHOOK_TOKEN','mg_stamp_delivery_failure_void','provider_delivery_failure_void'] as $needle){
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testAdminDeliveryFailureReportExists(): void
    {
        $api = $this->read('api/stamps/delivery-failure-report.php');
        $panel = $this->read('includes/admin-stamp-bundles-panel.php');
        $js = $this->read('assets/js/admin-stamp-failures.js');
        foreach(['delivery_failure_void','failed_send_void','failures'] as $needle){
            self::assertStringContainsString($needle, $api);
        }
        self::assertStringContainsString('data-admin-stamp-failure-list', $panel);
        self::assertStringContainsString('/api/stamps/delivery-failure-report.php', $js);
    }
}
