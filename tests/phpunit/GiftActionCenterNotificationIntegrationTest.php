<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GiftActionCenterNotificationIntegrationTest extends TestCase
{
    public function testGiftRoutesUseCanonicalActionCenterWorkspace(): void
    {
        $root=dirname(__DIR__,2);
        foreach(['inbox.php'=>'inbox','sent.php'=>'sent','claimed.php'=>'claimed'] as $file=>$folder){
            $source=file_get_contents($root.'/'.$file);
            self::assertIsString($source);
            self::assertStringContainsString('includes/gift-action-center.php',$source);
            self::assertStringNotContainsString('/assets/js/gift-action-center.js',$source);
            self::assertMatchesRegularExpression('/\$agent_tab\s*=\s*[\'\"]'.preg_quote($folder,'/').'[\'\"]/',$source);
        }
        $workspace=file_get_contents($root.'/includes/gift-action-center.php');
        self::assertIsString($workspace);
        self::assertStringContainsString('gift-action-center-runtime-v4.js?v=4.0.0',$workspace);
        self::assertStringContainsString('gift-action-center-user-search-v2.js?v=2.0.0',$workspace);
    }

    public function testGiftActionCenterGatesDemoContentToSuperAdmin(): void
    {
        $root=dirname(__DIR__,2);
        $workspace=file_get_contents($root.'/includes/gift-action-center.php');
        $runtime=file_get_contents($root.'/assets/js/gift-action-center-runtime-v4.js');
        self::assertIsString($workspace);
        self::assertIsString($runtime);
        self::assertStringContainsString("mg_has_role('super_admin')",$workspace);
        self::assertStringContainsString('data-demo-enabled',$workspace);
        self::assertStringContainsString("demoEnabled:app.dataset.demoEnabled==='true'",$runtime);
        self::assertStringContainsString('if(reset&&!state.order.length&&state.demoEnabled)',$runtime);
    }

    public function testDemoDatasetCoversAllFolders(): void
    {
        $runtime=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center-runtime-v4.js');
        self::assertIsString($runtime);
        self::assertStringContainsString("demoContract('demo-coffee-001'",$runtime);
        self::assertStringContainsString("demoContract('demo-sent-001'",$runtime);
        self::assertStringContainsString("demoContract('demo-claimed-001'",$runtime);
        self::assertStringContainsString('Demo preview only',$runtime);
        self::assertStringNotContainsString('data-gift-action="resend"',$runtime);
    }

    public function testLoadDrawerComposesContentBeforeProtectedVoucher(): void
    {
        $root=dirname(__DIR__,2);
        $workspace=file_get_contents($root.'/includes/gift-action-center.php');
        $header=file_get_contents($root.'/includes/header-components/app-header.php');
        $runtime=file_get_contents($root.'/assets/js/gift-action-center-runtime-v4.js');
        self::assertIsString($workspace);
        self::assertIsString($header);
        self::assertIsString($runtime);
        self::assertStringNotContainsString('mg-gift-folder-tabs',$workspace);
        self::assertStringContainsString('mg-agent-tab-badge',$header);
        self::assertStringContainsString('data-gift-drawer',$workspace);
        self::assertStringContainsString('posts.map((post,i)=>mediaPostMarkup',$runtime);
        self::assertStringContainsString(".join('')+'</div>'+voucherMarkup(c)",$runtime);
        self::assertStringContainsString('/api/account/action-center.php',$runtime);
    }

    public function testMessagesNotificationsAndPreferencesHaveDedicatedPages(): void
    {
        $root=dirname(__DIR__,2);
        foreach(['messages.php','notifications.php','notification-preferences.php'] as $file){
            self::assertFileExists($root.'/'.$file);
        }
        $signals=file_get_contents($root.'/assets/js/header-signals.js');
        self::assertIsString($signals);
        self::assertStringContainsString('/messages.php?thread=',$signals);
        self::assertStringContainsString('/notifications.php',$signals);
        self::assertStringContainsString('/notification-preferences.php',$signals);
    }
}
