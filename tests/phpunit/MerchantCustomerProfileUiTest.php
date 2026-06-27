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
        foreach(['data-sidebar-contract="mg-app-sidebar"','includes/app-sidebar.php','merchant-customer-profile.css','merchant-customer-profile.js','Customer Profile | Microgifter',"'customer_profile'=>['Customer Profile'"] as $needle) self::assertStringContainsString($needle,$page);
    }

    public function testCustomerProfileViewContainsLiveDataHooks(): void
    {
        $view=$this->source('includes/merchant-customer-profile-view.php');
        foreach(['Customer Profile Command Center','Manage rewards, messages, notes, follow-ups, redemptions, tips, and timeline activity','Wallet Rewards Received','Customer Snapshot','Reward & Redemption Activity','Recent Messages','Recent Rewards','Tips & Commerce Summary','Campaign Source History','CRM Notes','Customer Timeline','data-cp-name','data-cp-snapshot','data-cp-chart','data-cp-messages','data-cp-rewards','data-cp-tips','data-cp-sources','data-cp-note-form','data-cp-timeline'] as $needle) self::assertStringContainsString($needle,$view);
    }

    public function testCustomerProfileCssAndJsContractsExist(): void
    {
        $css=$this->source('assets/css/merchant-customer-profile.css');
        $js=$this->source('assets/js/merchant-customer-profile.js');
        foreach(['.mg-cp-grid','.mg-cp-section-card','.mg-cp-timeline','.mg-cp-kpis','.mg-cp-profile-card'] as $needle) self::assertStringContainsString($needle,$css);
        foreach(['data-profile-tab','data-profile-section','data-cp-action-panel','/api/merchant/customer-profile.php','data-customer-profile-page','data-cp-note-form','Microgifter.post'] as $needle) self::assertStringContainsString($needle,$js);
    }
}
