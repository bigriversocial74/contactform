<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminSubscriptionRequestsUiContractTest extends TestCase
{
    public function testSubscriptionRequestAdminPageRequiresSubscriptionPermission(): void
    {
        $root = dirname(__DIR__, 2);
        $page = file_get_contents($root . '/admin/subscription-requests.php');

        self::assertIsString($page);
        self::assertStringContainsString("mg_require_admin_page_permission('subscriptions.admin')", $page);
        self::assertStringContainsString('data-admin-subscription-requests', $page);
        self::assertStringContainsString('/assets/js/admin-subscription-requests.js', $page);
        self::assertStringContainsString('/assets/css/admin-subscription-requests.css', $page);
    }

    public function testSubscriptionRequestAdminScriptUsesProtectedEndpoints(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root . '/assets/js/admin-subscription-requests.js');

        self::assertIsString($script);
        self::assertStringContainsString('/api/subscriptions/admin-package-requests.php?status=all&limit=100', $script);
        self::assertStringContainsString('/api/subscriptions/package-review.php', $script);
        self::assertStringContainsString('data-subreq-action', $script);
    }

    public function testAdminSidebarsLinkToSubscriptionRequests(): void
    {
        $root = dirname(__DIR__, 2);
        $adminSidebar = file_get_contents($root . '/includes/admin-sidebar.php');
        $account = file_get_contents($root . '/account.php');

        self::assertIsString($adminSidebar);
        self::assertIsString($account);
        self::assertStringContainsString('/admin/subscription-requests.php', $adminSidebar);
        self::assertStringContainsString('subscription-requests', $adminSidebar);
        self::assertStringContainsString('/admin/subscription-requests.php', $account);
        self::assertStringContainsString('subscriptions.admin', $account);
    }
}
