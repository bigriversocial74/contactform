<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ActionCenterReadinessTest extends TestCase
{
    public function testNotificationPreferencesUsesSharedSidebar(): void
    {
        $root=dirname(__DIR__,2);
        $page=file_get_contents($root.'/notification-preferences.php');
        $sidebar=file_get_contents($root.'/includes/account-sidebar.php');
        self::assertIsString($page);
        self::assertIsString($sidebar);
        self::assertStringContainsString('includes/account-sidebar.php',$page);
        self::assertStringContainsString("accountView = 'preferences'",$page);
        self::assertStringContainsString('/notification-preferences.php',$sidebar);
        self::assertStringContainsString('/messages.php',$sidebar);
        self::assertStringContainsString('/notifications.php',$sidebar);
    }

    public function testActionCenterLoadsFoldersIndependentlyAndUsesApiCounts(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center.js');
        self::assertIsString($script);
        self::assertMatchesRegularExpression('/function\s+loadFolder\s*\(folder,\s*force\)/',$script);
        self::assertStringContainsString('setCounts(data.counts || state.counts)',$script);
        self::assertStringContainsString('/api/account/action-center.php?folder=',$script);
    }

    public function testDemoClaimCodeIsNotAppliedToProductionItems(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center.js');
        self::assertIsString($script);
        self::assertMatchesRegularExpression("/claim_code:\s*'123456'/",$script);
        self::assertMatchesRegularExpression('/is_demo:\s*true/',$script);
        self::assertStringNotContainsString("claim_code: item.claim_code || '123456'",$script);
    }
}
