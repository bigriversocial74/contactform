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

        self::assertStringContainsString('/api/merchant/reward-templates.php?status=active', $js);
        self::assertStringContainsString('data-crm-reward-template', $js);
        self::assertStringContainsString('reward_template_id', $js);
        self::assertStringContainsString('idempotency_key', $js);
        self::assertStringContainsString('String.fromCharCode(103,105,102,116)', $js);
        self::assertStringContainsString('Microgifter.post(endpoint', $js);
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
        self::assertStringContainsString('Review send', $js);
        self::assertStringContainsString('Confirm send', $js);
        self::assertStringContainsString('Invite required', $js);
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
