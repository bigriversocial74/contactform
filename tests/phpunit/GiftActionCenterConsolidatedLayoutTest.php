<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GiftActionCenterConsolidatedLayoutTest extends TestCase
{
    public function testInboxSentClaimedUseOneSharedRuntime(): void
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
            self::assertStringContainsString('includes/gift-action-center.php',$source);
            self::assertStringNotContainsString('/assets/js/gift-action-center.js',$source);
            self::assertStringNotContainsString('account-sidebar.js',$source);
        }
        $shared=file_get_contents($root.'/includes/gift-action-center.php');
        self::assertIsString($shared);
        self::assertStringContainsString('gift-action-center-runtime-v4.js?v=4.0.0',$shared);
        self::assertStringNotContainsString('gift-action-center-feed-v3.js',$shared);
        self::assertStringNotContainsString('gift-action-center-pagination.js',$shared);
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
    }

    public function testFolderActionsUseServerCapabilitiesFromContractV2(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center-runtime-v4.js');
        self::assertIsString($script);
        foreach(['send','follow-up','claim','load','message','tip'] as $action){
            self::assertStringContainsString("'{$action}'",$script);
        }
        self::assertStringContainsString('const capability=(c,n)=>bool(parts(c).capabilities[n])',$script);
        self::assertStringContainsString('capability_reasons',$script);
        self::assertStringNotContainsString('data-gift-action="resend"',$script);
        self::assertStringNotContainsString('metadata_json',$script);
    }

    public function testLoadUsesContractMediaBeforeProtectedVoucher(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center-runtime-v4.js');
        self::assertIsString($script);
        self::assertStringContainsString('function openDrawer(c)',$script);
        $content=strpos($script,'mg-pppm-post-stack');
        $voucher=strpos($script,'Protected voucher');
        self::assertNotFalse($content);
        self::assertNotFalse($voucher);
        self::assertTrue($content<$voucher);
        self::assertStringContainsString('p.media.posts',$script);
    }
}
