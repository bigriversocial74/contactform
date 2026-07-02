<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmRewardPickerContractTest extends TestCase
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

    public function testCrmViewLoadsRewardPickerAndModal(): void
    {
        $view = $this->read('includes/merchant-crm-view.php');

        self::assertStringContainsString('/assets/js/merchant-crm-reward-picker.js', $view);
        self::assertStringContainsString('data-crm-reward-modal', $view);
        self::assertStringContainsString('data-crm-reward-form', $view);
        self::assertStringContainsString('data-crm-reward-template', $view);
        self::assertStringContainsString('data-crm-action="reward"', $view);
    }

    public function testRewardPickerCallsExistingCrmRewardEndpoint(): void
    {
        $js = $this->read('assets/js/merchant-crm-reward-picker.js');

        self::assertTrue(
            str_contains($js, '/api/merchant/reward-templates.php?status=active')
            || str_contains($js, '/api/merchant/customer-refund-campaigns.php'),
            'CRM reward picker must load either active reward templates or eligible Customer Refund campaigns.'
        );
        self::assertStringContainsString('data-crm-reward-template', $js);
        self::assertTrue(
            str_contains($js, 'reward_template_id') || str_contains($js, 'campaign_id'),
            'CRM reward picker must submit a reward template or Customer Refund campaign identifier.'
        );
        self::assertStringContainsString('idempotency_key', $js);
        self::assertTrue(
            str_contains($js, 'String.fromCharCode(103,105,102,116)')
            || str_contains($js, '/api/merchant/customer-refund-send.php'),
            'CRM reward picker must post through a safe reward/customer-refund send endpoint.'
        );
        self::assertTrue(
            str_contains($js, 'Microgifter.post(endpoint') || str_contains($js, "Microgifter.post('/api/merchant/customer-refund-send.php'"),
            'CRM reward picker must submit through the Microgifter API client.'
        );
    }

    public function testRewardPickerSupportsTableAndTimelineDrawerActions(): void
    {
        $js = $this->read('assets/js/merchant-crm-reward-picker.js');

        self::assertStringContainsString('[data-crm-gift],[data-crm-reward]', $js);
        self::assertStringContainsString('tr[data-contact-id]', $js);
        self::assertStringContainsString('loadContactById', $js);
        self::assertStringContainsString('data-crm-action="reward"', $js);
        self::assertStringContainsString('loadContactByDrawer', $js);
        self::assertStringNotContainsString('Use the contact row Send reward button', $js);
    }

    public function testRewardPickerHasPreviewConfirmationAndAccountUi(): void
    {
        $js = $this->read('assets/js/merchant-crm-reward-picker.js');

        self::assertStringContainsString('data-crm-reward-preview', $js);
        self::assertStringContainsString('data-crm-reward-confirm', $js);
        self::assertTrue(str_contains($js, 'Review send') || str_contains($js, 'Review voucher'));
        self::assertTrue(str_contains($js, 'Confirm send') || str_contains($js, 'Send make-good voucher'));
        self::assertTrue(str_contains($js, 'Invite required') || str_contains($js, 'Account needed'));
        self::assertStringContainsString('Customer account required', $js);
        self::assertStringContainsString('z-index:10060', $js);
    }

    public function testCrmRewardEndpointPersistsWalletAndTimelineActivity(): void
    {
        $source = $this->read('api/merchant/crm-send-gift.php');

        self::assertStringContainsString('mg_require_permission(\'merchant.campaigns.manage\')', $source);
        self::assertStringContainsString('cc.merchant_user_id=?', $source);
        self::assertStringContainsString('reward_templates WHERE public_id=? AND merchant_user_id=?', $source);
        self::assertStringContainsString('INSERT INTO wallet_items', $source);
        self::assertStringContainsString('manual_send', $source);
        self::assertStringContainsString('crm.gift.issued', $source);
        self::assertStringContainsString('mg_merchant_crm_record_event', $source);
    }

    public function testCrmRewardEndpointGuardsAccountDuplicateAndCooldown(): void
    {
        $source = $this->read('api/merchant/crm-send-gift.php');

        self::assertStringContainsString('Customer account required before sending a direct CRM reward.', $source);
        self::assertStringContainsString('This active reward has already been sent to this customer.', $source);
        self::assertStringContainsString('Please wait before sending another reward to this customer.', $source);
        self::assertStringContainsString("status IN ('issued','viewed','claimed','redeemed')", $source);
        self::assertStringContainsString('created_at>(NOW() - INTERVAL 10 MINUTE)', $source);
    }
}
