<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminUserControlSafeguardTest extends TestCase
{
    public function testProtectedTargetsAndManualPathsRemainEnforced(): void
    {
        $root = dirname(__DIR__, 2);
        $common = file_get_contents($root . '/api/admin/_user_management_common.php');
        $roles = file_get_contents($root . '/api/admin/_user_management_role_actions.php');
        $models = file_get_contents($root . '/api/admin/_user_management_model_actions.php');

        self::assertIsString($common);
        self::assertIsString($roles);
        self::assertIsString($models);
        self::assertStringContainsString('your own account', $common);
        self::assertStringContainsString('manual owner workflow', $roles);
        self::assertStringContainsString('manual owner workflow', $models);
        self::assertStringContainsString('model_default_roles', $roles);
        self::assertStringContainsString('user_model_assignments', $models);
    }

    public function testLifecycleUsesCanonicalServicesAndRequiredReasons(): void
    {
        $root = dirname(__DIR__, 2);
        $status = file_get_contents($root . '/api/admin/_user_management_status.php');
        $endpoint = file_get_contents($root . '/api/admin/user-management.php');

        self::assertIsString($status);
        self::assertIsString($endpoint);
        self::assertStringContainsString('mg_content_review_set_user_status', $status);
        self::assertStringContainsString('mg_revoke_user_sessions', $status);
        self::assertStringContainsString('mg_admin_management_reason', $endpoint);
        self::assertStringContainsString('mg_security_log(', $endpoint);
    }
}
