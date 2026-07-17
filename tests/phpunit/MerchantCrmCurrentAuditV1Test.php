<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmCurrentAuditV1Test extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCanonicalDirectoryReplacesDuplicateSearchRuntimes(): void
    {
        $page = (string)file_get_contents($this->root . '/merchant-crm.php');
        $directory = (string)file_get_contents($this->root . '/includes/merchant-crm-directory.php');
        $runtime = (string)file_get_contents($this->root . '/assets/js/merchant-crm-directory.js');
        $mobile = (string)file_get_contents($this->root . '/assets/js/merchant-crm-mobile-dashboard.js');

        self::assertStringContainsString('merchant-crm-directory-data.js?v=1.0.0', $page);
        self::assertStringContainsString('merchant-crm-directory.js?v=1.0.0', $page);
        self::assertFileDoesNotExist($this->root . '/assets/js/merchant-crm-contact-rollup.js');
        self::assertFileDoesNotExist($this->root . '/assets/js/merchant-crm-desktop-search.js');
        self::assertStringContainsString('MG_MERCHANT_CRM_DIRECTORY_CONTRACT_VERSION = 1', $directory);
        self::assertStringContainsString('[desktopInput, mobileInput]', $runtime);
        self::assertStringNotContainsString('MutationObserver', $runtime);
        self::assertStringNotContainsString('applySearch', $mobile);
    }

    public function testDirectoryRemainsMerchantScopedAndReadOnly(): void
    {
        $api = (string)file_get_contents($this->root . '/api/merchant/merchant-crm.php');
        $directory = (string)file_get_contents($this->root . '/includes/merchant-crm-directory.php');
        $data = (string)file_get_contents($this->root . '/assets/js/merchant-crm-directory-data.js');

        self::assertStringContainsString("mg_require_permission('merchant.campaigns.view')", $api);
        self::assertStringContainsString('mg_merchant_ensure_workspace($pdo, $user)', $api);
        self::assertStringContainsString("'mc.merchant_user_id=?'", $directory);
        self::assertStringContainsString('merged_into_contact_id IS NULL', $directory);
        self::assertStringNotContainsString('CREATE TABLE', $directory);
        self::assertStringNotContainsString('ALTER TABLE', $directory);
        self::assertStringNotContainsString('Microgifter.post(', $data);
    }
}
