<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminUserCenterContractTest extends TestCase
{
    public function testUserDirectoryRequiresDedicatedPermissionAndBoundedReads(): void
    {
        $root = dirname(__DIR__, 2);
        $helper = file_get_contents($root . '/api/admin/_users.php');
        $endpoint = file_get_contents($root . '/api/admin/users.php');

        self::assertIsString($helper);
        self::assertIsString($endpoint);
        self::assertStringContainsString("mg_require_permission('admin.users.view')", $helper);
        self::assertStringContainsString('MG_ADMIN_USERS_MAX_LIMIT = 50', $helper);
        self::assertStringContainsString("mg_require_method('GET')", $endpoint);
        self::assertStringContainsString("mg_rate_limit('admin.users.read'", $endpoint);
        self::assertStringContainsString('Cache-Control: private, no-store, max-age=0', $endpoint);
    }

    public function testUserDirectorySupportsValidatedFiltersAndCursorPagination(): void
    {
        $root = dirname(__DIR__, 2);
        $helper = file_get_contents($root . '/api/admin/_users.php');

        self::assertIsString($helper);
        self::assertStringContainsString("['active', 'disabled', 'pending']", $helper);
        self::assertStringContainsString("['verified', 'unverified']", $helper);
        self::assertStringContainsString("rf.slug=?", $helper);
        self::assertStringContainsString("u.id<?", $helper);
        self::assertStringContainsString("ORDER BY u.id DESC LIMIT", $helper);
        self::assertStringContainsString("'next_cursor'", $helper);
        self::assertStringContainsString("'has_more'", $helper);
    }

    public function testUserDirectoryReturnsOnlyAdministrativeAccountContext(): void
    {
        $root = dirname(__DIR__, 2);
        $helper = file_get_contents($root . '/api/admin/_users.php');

        self::assertIsString($helper);
        self::assertStringContainsString('LEFT JOIN public_profiles', $helper);
        self::assertStringContainsString('LEFT JOIN user_roles', $helper);
        self::assertStringContainsString("'roles' => $roles", $helper);
        self::assertStringContainsString("'profile' =>", $helper);
        self::assertStringNotContainsString('password_hash', $helper);
        self::assertStringNotContainsString('token_hash', $helper);
    }
}
