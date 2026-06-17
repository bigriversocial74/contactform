<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GiftActionCenterConsolidatedLayoutTest extends TestCase
{
    public function testInboxSentClaimedUseAgentShellVariant(): void
    {
        $root=dirname(__DIR__,2);
        foreach(['inbox.php'=>'inbox','sent.php'=>'sent','claimed.php'=>'claimed'] as $file=>$tab){
            $source=file_get_contents($root.'/'.$file);
            self::assertIsString($source);
            $compact=preg_replace('/\s+/', '', $source);
            self::assertIsString($compact);
            self::assertStringContainsString("\$header_mode='agent'",$compact);
            self::assertStringContainsString("\$agent_tab='{$tab}'",$compact);
            self::assertStringContainsString('/assets/css/agent-workspace-layout.css',$source);
            self::assertStringContainsString('/assets/css/gift-action-center.css',$source);
            self::assertStringContainsString('/assets/js/gift-action-center.js',$source);
            self::assertStringNotContainsString('account-sidebar.js',$source);
        }
    }

    public function testGiftActionCenterUsesAgentSidebarAndNoInnerFolderTabs(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/includes/gift-action-center.php');
        self::assertIsString($source);
        self::assertStringContainsString('agent-sidebar.php',$source);
        self::assertStringContainsString('mg-gift-feed-column',$source);
        self::assertStringContainsString('data-gift-drawer',$source);
        self::assertStringNotContainsString('account-sidebar.php',$source);
        self::assertStringNotContainsString('mg-gift-folder-tabs',$source);
        self::assertStringNotContainsString('data-gift-folder="inbox"',$source);
        self::assertStringNotContainsString('mg-gift-center-header',$source);
        self::assertStringNotContainsString('mg-gift-center-header-actions',$source);
    }

    public function testMessagesAndCreateGiftLiveInMerchantSidebar(): void
    {
        $sidebar=file_get_contents(dirname(__DIR__,2).'/includes/agent-sidebar.php');
        self::assertIsString($sidebar);
        self::assertStringContainsString('mg-merchant-side-actions',$sidebar);
        self::assertStringContainsString('href="/messages.php"',$sidebar);
        self::assertStringContainsString('href="/build.php"',$sidebar);
        self::assertStringContainsString('Create gift',$sidebar);
    }

    public function testHeaderTabsExposeGiftBadgesAsOnlyFolderNavigation(): void
    {
        $header=file_get_contents(dirname(__DIR__,2).'/includes/header-components/app-header.php');
        self::assertIsString($header);
        self::assertStringContainsString("['inbox','Inbox','/inbox.php']",$header);
        self::assertStringContainsString("['sent','Sent','/sent.php']",$header);
        self::assertStringContainsString("['claimed','Claimed','/claimed.php']",$header);
        self::assertStringContainsString('mg-agent-tab-badge',$header);
        self::assertStringContainsString('data-gift-nav-count="<?= $tab[0] ?>"',$header);
        self::assertStringContainsString('data-gift-nav-unread="<?= $tab[0] ?>"',$header);
    }

    public function testFolderSpecificRowActionsAndMetadataAreRenderedByJavascript(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center.js');
        self::assertIsString($script);
        self::assertMatchesRegularExpression('/function\s+rowActions\s*\([^)]*\)/',$script);
        foreach(['send','claim','load','message','tip'] as $action){
            self::assertStringContainsString('data-gift-action="'.$action.'"',$script);
        }
        self::assertMatchesRegularExpression('/function\s+metadata\s*\(item\)/',$script);
        foreach(['From: ','Sent: ','Redeemed: ','Type: ','Value: ','Status: '] as $label){
            self::assertStringContainsString($label,$script);
        }
    }

    public function testLoadOpensPppmDrawerWithCouponAndPosts(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center.js');
        self::assertIsString($script);
        self::assertMatchesRegularExpression('/function\s+openContent\s*\(item\)/',$script);
        self::assertStringContainsString('couponCard(item)',$script);
        self::assertStringContainsString('mg-pppm-post-stack',$script);
        self::assertStringContainsString('mg-pppm-post',$script);
        self::assertStringContainsString('Protected voucher',$script);
    }
}
