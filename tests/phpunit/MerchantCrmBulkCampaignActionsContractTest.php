<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmBulkCampaignActionsContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }
    private function read(string $path): string { $source = file_get_contents($this->root . '/' . $path); self::assertIsString($source, $path); return $source; }

    public function testBulkCampaignSelectionUiContract(): void
    {
        $view = $this->read('includes/merchant-crm-view.php');
        $js = $this->read('assets/js/merchant-crm.js');
        $css = $this->read('assets/css/merchant-crm-command-center.css');
        foreach (['data-crm-segments','data-crm-select-visible','data-crm-selected-count','data-crm-bulk-action="message"','data-crm-bulk-action="reward"','data-crm-bulk-action="followup"','data-crm-bulk-action="export"'] as $marker) self::assertStringContainsString($marker, $view);
        foreach (['accounts','no_accounts','verified','reward_issued','reward_claimed','invite_pending','no_recent_activity'] as $segment) self::assertStringContainsString('data-crm-segment="' . $segment . '"', $view);
        foreach (['data-crm-contact-check','selectedContacts()','visibleContacts()','contactMatchesSegment','/api/merchant/crm-bulk-message.php','/api/merchant/crm-bulk-reward.php','/api/merchant/crm-followup.php','function exportSelected()','microgifter-crm-selected-contacts.csv'] as $marker) self::assertStringContainsString($marker, $js);
        self::assertStringContainsString('position:sticky', $css);
        self::assertStringContainsString('mg-crm-selected-pill', $css);
    }

    public function testBulkCampaignEndpointContracts(): void
    {
        $message = $this->read('api/merchant/crm-bulk-message.php');
        $reward = $this->read('api/merchant/crm-bulk-reward.php');
        $followup = $this->read('api/merchant/crm-followup.php');
        foreach ([$message, $reward, $followup] as $endpoint) {
            self::assertStringContainsString("mg_require_permission('merchant.campaigns.manage')", $endpoint);
            self::assertStringContainsString('mg_require_csrf_for_write($input)', $endpoint);
            self::assertStringContainsString('mg_crm_bulk_contact_ids', $endpoint);
            self::assertStringContainsString('mg_crm_bulk_result_summary', $endpoint);
        }
        self::assertStringContainsString('mg_crm_bulk_queue_message', $message);
        self::assertStringContainsString('mg_crm_bulk_issue_direct_reward', $reward);
        self::assertStringContainsString('mg_crm_bulk_send_reward_invite', $reward);
        self::assertStringContainsString('account_contacts', $reward);
        self::assertStringContainsString('no_account_contacts', $reward);
        self::assertStringContainsString('crm.followup.created', $followup);
        self::assertStringContainsString('campaign_events', $followup);
    }

    public function testBulkCampaignHelperGuardsAndSegments(): void
    {
        $helper = $this->read('includes/merchant-crm-bulk.php');
        $contacts = $this->read('api/merchant/campaign-contacts.php');
        foreach (['function mg_crm_bulk_message_thread','INSERT INTO message_threads','INSERT IGNORE INTO message_thread_participants','INSERT INTO messages','merchant_crm_bulk_message','crm.message.sent','function mg_crm_bulk_issue_direct_reward','function mg_crm_bulk_send_reward_invite','mg_crm_bulk_assert_template_inventory','active_duplicate','cooldown','crm_reward_invites','merchant_crm_bulk_reward_invite','mg_zero_reward_issue_from_wallet'] as $marker) self::assertStringContainsString($marker, $helper);
        foreach (['crm_reward_invites','invite_pending_count','no_recent_activity','reward_claimed','no_accounts'] as $marker) self::assertStringContainsString($marker, $contacts);
    }
}
