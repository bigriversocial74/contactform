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
            self::assertStringContainsString('/assets/js/gift-action-center.js',$source);
            self::assertMatchesRegularExpression('/\$agent_tab\s*=\s*[\'\"]'.preg_quote($folder,'/').'[\'\"]/',$source);
        }
    }

    public function testGiftActionCenterGatesDemoContentToSuperAdmin(): void
    {
        $root=dirname(__DIR__,2);
        $workspace=file_get_contents($root.'/includes/gift-action-center.php');
        $script=file_get_contents($root.'/assets/js/gift-action-center.js');
        self::assertIsString($workspace);
        self::assertIsString($script);
        self::assertStringContainsString("mg_has_role('super_admin')",$workspace);
        self::assertStringContainsString('data-demo-enabled',$workspace);
        self::assertStringContainsString("var demoEnabled=app.dataset.demoEnabled==='true'",$script);
        self::assertStringContainsString('if(demoEnabled&&!state.folders[folder].length)',$script);
        self::assertStringNotContainsString("if(folder==='inbox'&&!state.folders.inbox.length)",$script);
    }

    public function testSuperAdminDemoDatasetCoversAllFoldersAndCannotMutate(): void
    {
        $root=dirname(__DIR__,2);
        $script=file_get_contents($root.'/assets/js/gift-action-center.js');
        self::assertIsString($script);
        self::assertStringContainsString("action_item_id:'demo-coffee-001'",$script);
        self::assertStringContainsString("action_item_id:'demo-sent-001'",$script);
        self::assertStringContainsString("action_item_id:'demo-claimed-001'",$script);
        self::assertStringContainsString('Super Admin demo content cannot execute real transactional actions.',$script);
        self::assertStringContainsString('No real payment, ownership transfer, send, resend, claim, message, tip, notification, ledger entry, payout, or webhook was created.',$script);
    }

    public function testLoadDrawerRendersContentBeforeProtectedVoucher(): void
    {
        $root=dirname(__DIR__,2);
        $workspace=file_get_contents($root.'/includes/gift-action-center.php');
        $header=file_get_contents($root.'/includes/header-components/app-header.php');
        $script=file_get_contents($root.'/assets/js/gift-action-center.js');
        self::assertIsString($workspace);
        self::assertIsString($header);
        self::assertIsString($script);
        self::assertStringNotContainsString('mg-gift-folder-tabs',$workspace);
        self::assertStringContainsString('mg-agent-tab-badge',$header);
        self::assertStringContainsString('data-gift-drawer',$workspace);
        $contentStackPosition=strpos($script,'mg-pppm-post-stack');
        $voucherPosition=strpos($script,'Protected voucher');
        self::assertNotFalse($contentStackPosition);
        self::assertNotFalse($voucherPosition);
        self::assertTrue($contentStackPosition<$voucherPosition);
        self::assertStringContainsString('/api/account/action-center.php',$script);
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
