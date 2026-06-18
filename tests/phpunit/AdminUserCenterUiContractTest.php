<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminUserCenterUiContractTest extends TestCase
{
    public function testProtectedUserCenterPageAndDashboardNavigationAreRegistered(): void
    {
        $root = dirname(__DIR__, 2);
        $page = file_get_contents($root . '/admin/users.php');
        $dashboard = file_get_contents($root . '/includes/account/admin-dashboard.php');

        self::assertIsString($page);
        self::assertIsString($dashboard);
        self::assertStringContainsString("mg_has_permission('admin.users.view')", $page);
        self::assertStringContainsString('/assets/css/admin-users.css', $page);
        self::assertStringContainsString('/assets/js/admin-users.js', $page);
        self::assertStringContainsString('/admin/users.php', $dashboard);
        self::assertStringContainsString('data-admin-users', $page);
    }

    public function testUserCenterIncludesFiltersStatesAndCursorPagination(): void
    {
        $root = dirname(__DIR__, 2);
        $page = file_get_contents($root . '/admin/users.php');
        $client = file_get_contents($root . '/assets/js/admin-users.js');

        self::assertIsString($page);
        self::assertIsString($client);
        self::assertStringContainsString('name="q"', $page);
        self::assertStringContainsString('name="status"', $page);
        self::assertStringContainsString('name="role"', $page);
        self::assertStringContainsString('name="verification"', $page);
        self::assertStringContainsString('data-users-loading', $page);
        self::assertStringContainsString('data-users-error', $page);
        self::assertStringContainsString('data-users-empty', $page);
        self::assertStringContainsString('data-users-more', $page);
        self::assertStringContainsString('/api/admin/users.php?', $client);
        self::assertStringContainsString("params.set('cursor'", $client);
    }

    public function testUserCenterRendersApiDataWithoutHtmlInjection(): void
    {
        $root = dirname(__DIR__, 2);
        $client = file_get_contents($root . '/assets/js/admin-users.js');

        self::assertIsString($client);
        self::assertStringContainsString('.textContent =', $client);
        self::assertStringNotContainsString('innerHTML', $client);
        self::assertStringContainsString("credentials: 'same-origin'", $client);
        self::assertStringContainsString("headers: { Accept: 'application/json' }", $client);
        self::assertStringContainsString("link.rel = 'noopener'", $client);
    }

    public function testUserCenterRemainsReadOnly(): void
    {
        $root = dirname(__DIR__, 2);
        $page = file_get_contents($root . '/admin/users.php');
        $client = file_get_contents($root . '/assets/js/admin-users.js');

        self::assertIsString($page);
        self::assertIsString($client);
        self::assertStringContainsString('Read only', $page);
        self::assertStringNotContainsString("method: 'POST'", $client);
        self::assertStringNotContainsString("method: 'PATCH'", $client);
        self::assertStringNotContainsString("method: 'DELETE'", $client);
    }
}
