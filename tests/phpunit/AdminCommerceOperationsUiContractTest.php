<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminCommerceOperationsUiContractTest extends TestCase
{
    public function testWorkspaceLoadsQueueInspectorWorkflowAndDrawerAssets(): void
    {
        $root = dirname(__DIR__, 2);
        $page = file_get_contents($root . '/commerce-operations.php');
        $footer = file_get_contents($root . '/includes/footer.php');

        self::assertIsString($page);
        self::assertIsString($footer);
        self::assertStringContainsString("'/assets/css/admin-commerce.css'", $page);
        self::assertStringContainsString("'/assets/js/admin-commerce.js'", $page);
        self::assertStringContainsString('/assets/js/admin-commerce-inspector.js', $footer);
        self::assertStringContainsString('/assets/js/admin-commerce-workflow.js', $footer);
        self::assertStringContainsString('/assets/css/admin-commerce-drawer.css', $footer);
        self::assertStringContainsString('data-commerce-root', $page);
        self::assertStringContainsString('data-commerce-drawer', $page);
    }

    public function testQueueClientUsesSafeRenderingAndBoundedFilters(): void
    {
        $root = dirname(__DIR__, 2);
        $client = file_get_contents($root . '/assets/js/admin-commerce.js');

        self::assertIsString($client);
        self::assertStringContainsString('/api/admin/commerce/queue.php', $client);
        self::assertStringContainsString("limit:'25'", $client);
        self::assertStringContainsString('textContent', $client);
        self::assertStringNotContainsString('innerHTML', $client);
        self::assertStringContainsString('AbortController', $client);
        self::assertStringContainsString('history.replaceState', $client);
    }

    public function testInspectorRendersTimelineAndTrapsDrawerFocus(): void
    {
        $root = dirname(__DIR__, 2);
        $inspector = file_get_contents($root . '/assets/js/admin-commerce-inspector.js');

        self::assertIsString($inspector);
        self::assertStringContainsString('/api/admin/commerce/inspect.php', $inspector);
        self::assertStringContainsString('mg-commerce-timeline-item', $inspector);
        self::assertStringContainsString("event.key==='Escape'", $inspector);
        self::assertStringContainsString("event.key!=='Tab'", $inspector);
        self::assertStringContainsString('textarea:not([disabled])', $inspector);
        self::assertStringContainsString('textContent', $inspector);
        self::assertStringNotContainsString('innerHTML', $inspector);
    }

    public function testWorkflowRequiresReasonsAndConfirmationForEveryAction(): void
    {
        $root = dirname(__DIR__, 2);
        $workflow = file_get_contents($root . '/assets/js/admin-commerce-workflow.js');

        self::assertIsString($workflow);
        self::assertStringContainsString('/api/admin/commerce/operate.php', $workflow);
        self::assertStringContainsString('between 8 and 500 characters', $workflow);
        self::assertStringContainsString('confirm(confirmation)', $workflow);
        foreach (['open_case','assign_case','add_case_note','resolve_case','dismiss_case','reopen_case','reverse_tip'] as $action) {
            self::assertStringContainsString("'{$action}'", $workflow);
        }
        self::assertStringContainsString('Microgifter.post', $workflow);
        self::assertStringNotContainsString('innerHTML', $workflow);
    }

    public function testAdminDashboardLinksAndAdvertisesCommerceOperations(): void
    {
        $root = dirname(__DIR__, 2);
        $dashboard = file_get_contents($root . '/includes/account/admin-dashboard.php');
        $service = file_get_contents($root . '/api/admin/_dashboard.php');

        self::assertIsString($dashboard);
        self::assertIsString($service);
        self::assertStringContainsString('/commerce-operations.php', $dashboard);
        self::assertStringContainsString("'admin.commerce.view'", $service);
        self::assertStringContainsString("'admin.commerce.manage'", $service);
        self::assertStringContainsString("'label'=>'Commerce operations'", $service);
    }
}
