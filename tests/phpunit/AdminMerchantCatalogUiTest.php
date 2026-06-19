<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminMerchantCatalogUiTest extends TestCase
{
    public function testWorkspaceAssetsAndSafeClients(): void
    {
        $root=dirname(__DIR__,2);
        $page=file_get_contents($root.'/merchant-catalog-operations.php');
        $footer=file_get_contents($root.'/includes/footer.php');
        $snippet=file_get_contents($root.'/includes/footer-mc-snippet.php');
        $queue=file_get_contents($root.'/assets/js/admin-merchant-catalog.js');
        $inspector=file_get_contents($root.'/assets/js/admin-mc-inspector.js');
        $workflow=file_get_contents($root.'/assets/js/admin-mc-workflow.js');
        foreach([$page,$footer,$snippet,$queue,$inspector,$workflow] as $source)self::assertIsString($source);
        self::assertStringContainsString('/assets/css/admin-merchant-catalog.css',$page);
        self::assertStringContainsString('/assets/js/admin-merchant-catalog.js',$page);
        self::assertStringContainsString('/assets/js/admin-mc-inspector.js',$snippet);
        self::assertStringContainsString('/assets/js/admin-mc-workflow.js',$snippet);
        self::assertStringContainsString('/assets/css/admin-merchant-catalog-drawer.css',$snippet);
        self::assertStringContainsString('mg-admin-merchant-catalog-page',$footer);
        self::assertStringContainsString('/api/admin/merchant-catalog/queue.php',$queue);
        self::assertStringContainsString('/api/admin/merchant-catalog/detail.php',$inspector);
        self::assertStringContainsString('/api/admin/merchant-catalog/operate.php',$workflow);
        self::assertStringContainsString('AbortController',$queue.$inspector);
        self::assertStringContainsString('confirm(',$workflow);
        self::assertStringContainsString('Microgifter.post',$workflow);
        self::assertStringContainsString('textarea:not([disabled])',$inspector);
        self::assertStringNotContainsString('innerHTML',$queue.$inspector.$workflow);
    }

    public function testDashboardLinksWorkspace(): void
    {
        $dashboard=file_get_contents(dirname(__DIR__,2).'/includes/account/admin-dashboard.php');
        self::assertIsString($dashboard);
        self::assertStringContainsString('/merchant-catalog-operations.php',$dashboard);
        self::assertStringContainsString("'admin.merchants.view'",$dashboard);
        self::assertStringContainsString("'admin.catalog.view'",$dashboard);
    }
}
