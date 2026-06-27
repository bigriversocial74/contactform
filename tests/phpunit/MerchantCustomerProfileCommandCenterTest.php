<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCustomerProfileCommandCenterTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source);
        return $source;
    }

    public function testViewHasCommandCenterPanelsAndSections(): void
    {
        $view = $this->source('includes/merchant-customer-profile-view.php');
        foreach (['data-cp-action-panel="reward"','data-cp-action-panel="message"','data-cp-action-panel="note"','data-cp-action-panel="followup"','data-cp-reward-form','data-cp-message-form','data-cp-note-form','data-cp-followup-form'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        foreach (['overview','timeline','rewards','messages','redemptions','tips','notes'] as $tab) {
            self::assertStringContainsString('data-profile-tab="' . $tab . '"', $view);
            self::assertStringContainsString('data-profile-section="' . $tab . '"', $view);
        }
    }

    public function testJavascriptSupportsTabsPanelsAndNativeActions(): void
    {
        $js = $this->source('assets/js/merchant-customer-profile.js');
        foreach (['function showTab','data-profile-section','data-cp-action-panel','data-cp-open-panel','/api/merchant/crm-message.php','/api/merchant/crm-send-gift.php','/api/merchant/crm-followup.php','/api/merchant/customer-profile.php','data-cp-open-wallet','data-cp-open-thread','data-cp-view-redemption','data-cp-view-tip'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
    }

    public function testApiReturnsScopedIdentifiersForUiActions(): void
    {
        $api = $this->source('api/merchant/customer-profile.php');
        foreach (['action_ids','actions','links','crm_contact_id','campaign_contact_id','campaign_contact_ids','wallet_item_id','thread_id','message_id','redemption_id','tip_id','action_url'] as $needle) {
            self::assertStringContainsString($needle, $api);
        }
        foreach (['WHERE public_id=? AND merchant_user_id=?','wi.merchant_user_id=?','WHERE merchant_user_id=? AND customer_user_id=?','WHERE n.merchant_user_id=? AND n.crm_contact_id=?','mt.created_by_user_id=?'] as $needle) {
            self::assertStringContainsString($needle, $api);
        }
    }

    public function testDrilldownRoutesRemainAvailable(): void
    {
        $source = $this->source('api/merchant/customer-profile.php') . $this->source('includes/merchant-customer-profile-view.php') . $this->source('assets/js/merchant-customer-profile.js');
        foreach (['/merchant-crm.php','/merchant-notifications.php','/merchant-claims.php','/merchant-campaigns.php','/merchant-notifications.php?filter=tips','Open reward / wallet item','Open thread','View claim / redemption','View tip activity'] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }
}
