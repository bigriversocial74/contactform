<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminPaymentsCleanLayoutContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testPaymentAdminUsesAlignedFourTabLayout(): void
    {
        $page = $this->source('admin-payments.php');
        self::assertStringContainsString('Payment Methods', $page);
        self::assertStringContainsString('Stripe Configuration', $page);
        self::assertStringContainsString('Secret Storage', $page);
        self::assertStringContainsString('Readiness', $page);
        self::assertStringContainsString('/assets/css/admin-payments-cleanup.css', $page);
        self::assertStringNotContainsString('/assets/js/admin-payments-persistence.js', $page);
    }

    public function testSecretStorageShowsMaskedDatabaseReferences(): void
    {
        $fields = $this->source('includes/admin-payment-credential-fields.php');
        $script = $this->source('assets/js/admin-payments.js');

        self::assertStringContainsString('data-payment-secret-display', $fields);
        self::assertStringContainsString('data-payment-secret-replace', $fields);
        self::assertStringContainsString('Saved in database · ', $script);
        self::assertStringContainsString('Saved and database-verified for ', $script);
        self::assertStringContainsString("allowedPages = ['methods', 'stripe', 'secrets', 'readiness']", $script);
    }
}
