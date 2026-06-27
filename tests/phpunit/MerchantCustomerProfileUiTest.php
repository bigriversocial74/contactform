<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCustomerProfileUiTest extends TestCase
{
    private function source(string $path): string
    {
        $source=file_get_contents(dirname(__DIR__,2).'/'.$path);
        self::assertIsString($source,$path.' should be readable.');
        return $source;
    }

    public function testCustomerProfilePageUsesMerchantShellAndAssets(): void
    {
        $page=$this->source('merchant-customer.php');
        foreach([
            'data-sidebar-contract="mg-app-sidebar"',
            'includes/app-sidebar.php',
            'merchant-customer-profile.css',
            'merchant-customer-profile.js',
            'Customer Profile | Microgifter',
            "'customer_profile'=>['Customer Profile'",
        ] as $needle) self::assertStringContainsString($needle,$page);
    }

    public function testCustomerProfileViewContainsMockupSections(): void
    {
        $view=$this->source('includes/merchant-customer-profile-view.php');
        foreach([
            'Expanded CRM record for wallet rewards, messages, tips, claims, and campaign history.',
            'Wallet Rewards Received',
            'Claimed Rewards',
            'Open Wallet Items',
            'Tips Sent to Merchant',
            'Estimated Customer Value',
            'Customer Snapshot',
            'Reward & Redemption Activity',
            'Recent Messages',
            'Recent Rewards',
            'Tips & Commerce Summary',
            'Campaign Source History',
            'CRM Notes',
            'Customer Timeline',
            'Reward Sent',
            'Wallet Item Opened',
            'Customer Sent Message',
            'Merchant Replied',
            'Reward Claimed',
            'Tip Received',
            'Follow-up Campaign Sent',
        ] as $needle) self::assertStringContainsString($needle,$view);
    }

    public function testCustomerProfileCssAndJsContractsExist(): void
    {
        $css=$this->source('assets/css/merchant-customer-profile.css');
        $js=$this->source('assets/js/merchant-customer-profile.js');
        foreach(['.mg-cp-grid','.mg-cp-timeline-card','.mg-cp-timeline','.mg-cp-kpis','.mg-cp-profile-card'] as $needle) self::assertStringContainsString($needle,$css);
        foreach(['data-profile-tab','Pass 2 action','data-customer-profile-page'] as $needle) self::assertStringContainsString($needle,$js);
    }
}
