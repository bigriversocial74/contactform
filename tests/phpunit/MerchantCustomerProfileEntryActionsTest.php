<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCustomerProfileEntryActionsTest extends TestCase
{
    private function source(string $path): string
    {
        $source=file_get_contents(dirname(__DIR__,2).'/'.$path);
        self::assertIsString($source,$path.' should be readable.');
        return $source;
    }

    public function testMerchantCrmContactRowsLinkToCustomerProfileAndSupportEntryActions(): void
    {
        $crm=$this->source('assets/js/merchant-crm.js');
        foreach([
            'data-crm-view-customer',
            '/merchant-customer.php?campaign_contact_id=',
            'action=message',
            'action=reward',
            'mg:crm:open-reward',
            'campaign_contact_id',
        ] as $needle) self::assertStringContainsString($needle,$crm);
    }

    public function testCustomerProfileHeaderButtonsAreRealEntryActions(): void
    {
        $view=$this->source('includes/merchant-customer-profile-view.php');
        $js=$this->source('assets/js/merchant-customer-profile.js');
        foreach(['data-cp-send-reward','data-cp-message-customer','data-cp-note-trigger','data-cp-notes-card'] as $needle) self::assertStringContainsString($needle,$view);
        foreach(['actionQuery','action=reward','action=message','data-cp-note-trigger','scrollIntoView'] as $needle) self::assertStringContainsString($needle,$js);
    }

    public function testNotificationsAndRewardInviteRowsExposeCustomerProfileLinks(): void
    {
        $notifications=$this->source('assets/js/merchant-notifications.js');
        $invites=$this->source('assets/js/merchant-crm-reward-invite-operations.js');
        foreach(['/merchant-customer.php?wallet_item_id=','View customer','data-view-customer'] as $needle) self::assertStringContainsString($needle,$notifications);
        foreach(['/merchant-customer.php?campaign_contact_id=','View customer'] as $needle) self::assertStringContainsString($needle,$invites);
    }

    public function testClaimsDashboardExposesCustomerUserIdForProfileLinks(): void
    {
        $api=$this->source('api/merchant/claims-dashboard.php');
        foreach(['owner_user_id','recipient_user_id','customer_user_id'] as $needle) self::assertStringContainsString($needle,$api);
    }
}
