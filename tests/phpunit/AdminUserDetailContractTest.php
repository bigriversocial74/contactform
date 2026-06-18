<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminUserDetailContractTest extends TestCase
{
    public function testUserDetailEndpointIsProtectedBoundedAndReadOnly(): void
    {
        $root = dirname(__DIR__, 2);
        $endpoint = file_get_contents($root . '/api/admin/user-detail.php');
        $helper = file_get_contents($root . '/api/admin/_user_detail.php');

        self::assertIsString($endpoint);
        self::assertIsString($helper);
        self::assertStringContainsString("mg_require_method('GET')", $endpoint);
        self::assertStringContainsString('mg_admin_users_require_user()', $endpoint);
        self::assertStringContainsString("mg_rate_limit('admin.user_detail.read'", $endpoint);
        self::assertStringContainsString('Cache-Control: private, no-store, max-age=0', $endpoint);
        self::assertStringNotContainsString("method: 'POST'", $endpoint);
        self::assertStringNotContainsString('UPDATE users', $helper);
        self::assertStringNotContainsString('DELETE FROM users', $helper);
    }

    public function testUserDetailValidatesIdentifiersAndHandlesMissingUsers(): void
    {
        $root = dirname(__DIR__, 2);
        $endpoint = file_get_contents($root . '/api/admin/user-detail.php');
        $helper = file_get_contents($root . '/api/admin/_user_detail.php');

        self::assertIsString($endpoint);
        self::assertIsString($helper);
        self::assertStringContainsString("preg_match('/^[1-9][0-9]{0,19}$/'", $helper);
        self::assertStringContainsString("mg_fail('User not found.', 404)", $endpoint);
        self::assertStringContainsString("mg_fail(\$error->getMessage(), 422)", $endpoint);
    }

    public function testUserDetailReturnsOnlyRequiredIdentityContext(): void
    {
        $root = dirname(__DIR__, 2);
        $helper = file_get_contents($root . '/api/admin/_user_detail.php');

        self::assertIsString($helper);
        self::assertStringContainsString('FROM users u', $helper);
        self::assertStringContainsString('LEFT JOIN public_profiles pp', $helper);
        self::assertStringContainsString('FROM roles r', $helper);
        self::assertStringContainsString('FROM user_model_assignments uma', $helper);
        self::assertStringContainsString("'roles' => mg_admin_user_detail_roles", $helper);
        self::assertStringContainsString("'models' => mg_admin_user_detail_models", $helper);
        self::assertStringContainsString("'profile' => mg_admin_user_detail_profile", $helper);
        self::assertStringNotContainsString('password_hash', $helper);
        self::assertStringNotContainsString('token_hash', $helper);
        self::assertStringNotContainsString('metadata_json', $helper);
    }

    public function testUserDetailDoesNotCrossSessionOrAuditPermissions(): void
    {
        $root = dirname(__DIR__, 2);
        $helper = file_get_contents($root . '/api/admin/_user_detail.php');

        self::assertIsString($helper);
        self::assertStringNotContainsString('user_sessions', $helper);
        self::assertStringNotContainsString('audit_logs', $helper);
        self::assertStringNotContainsString('security_logs', $helper);
    }
}
