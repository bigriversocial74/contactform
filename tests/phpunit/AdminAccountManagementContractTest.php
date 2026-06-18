<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminAccountManagementContractTest extends TestCase
{
    public function testMigrationRegistersDedicatedManagementPermissions(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root . '/database/stage_18k_admin_account_management.sql');
        $manifest = file_get_contents($root . '/config/migrations.php');

        self::assertIsString($migration);
        self::assertIsString($manifest);
        self::assertStringContainsString('stage_18k_admin_account_management.sql', $manifest);
        self::assertStringContainsString("'admin.users.manage'", $migration);
        self::assertStringContainsString("'admin.roles.manage'", $migration);
        self::assertStringContainsString("'admin.user_models.manage'", $migration);
        self::assertStringContainsString("'admin.sessions.view'", $migration);
        self::assertStringContainsString("'admin.sessions.revoke'", $migration);
        self::assertStringContainsString("r.slug IN ('admin', 'super_admin')", $migration);
    }

    public function testManagementEndpointIsCsrfProtectedAndPermissionMapped(): void
    {
        $root = dirname(__DIR__, 2);
        $endpoint = file_get_contents($root . '/api/admin/user-management.php');
        $helper = file_get_contents($root . '/api/admin/_user_management.php');

        self::assertIsString($endpoint);
        self::assertIsString($helper);
        self::assertStringContainsString("mg_require_method('POST')", $endpoint);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $endpoint);
        self::assertStringContainsString("mg_rate_limit('admin.user_management.write'", $endpoint);
        self::assertStringContainsString("'set_status' => 'admin.users.manage'", $helper);
        self::assertStringContainsString("'add_role', 'remove_role' => 'admin.roles.manage'", $helper);
        self::assertStringContainsString("'set_model_status' => 'admin.user_models.manage'", $helper);
        self::assertStringContainsString("'revoke_session', 'revoke_sessions' => 'admin.sessions.revoke'", $helper);
    }

    public function testManagementRequiresReasonsAndUsesTransactions(): void
    {
        $root = dirname(__DIR__, 2);
        $endpoint = file_get_contents($root . '/api/admin/user-management.php');
        $helper = file_get_contents($root . '/api/admin/_user_management.php');

        self::assertIsString($endpoint);
        self::assertIsString($helper);
        self::assertStringContainsString('between 8 and 240 characters', $helper);
        self::assertStringContainsString('$pdo->beginTransaction()', $endpoint);
        self::assertStringContainsString('$pdo->commit()', $endpoint);
        self::assertStringContainsString('$pdo->rollBack()', $endpoint);
        self::assertStringContainsString("'reason' => \$reason", $endpoint);
    }

    public function testSelfAndElevatedAccountProtectionsAreEnforced(): void
    {
        $root = dirname(__DIR__, 2);
        $helper = file_get_contents($root . '/api/admin/_user_management.php');

        self::assertIsString($helper);
        self::assertStringContainsString('You cannot perform this action on your own account.', $helper);
        self::assertStringContainsString('Only a super administrator can manage an administrative account.', $helper);
        self::assertStringContainsString('The last active super administrator cannot be deactivated.', $helper);
        self::assertStringContainsString('The last active super administrator role cannot be removed.', $helper);
        self::assertStringContainsString('FOR UPDATE', $helper);
        self::assertStringContainsString("['admin', 'super_admin']", $helper);
    }

    public function testStatusRoleModelAndSessionActionsAreAudited(): void
    {
        $root = dirname(__DIR__, 2);
        $endpoint = file_get_contents($root . '/api/admin/user-management.php');
        $helper = file_get_contents($root . '/api/admin/_user_management.php');

        self::assertIsString($endpoint);
        self::assertIsString($helper);
        self::assertStringContainsString('mg_admin_account_set_status', $endpoint);
        self::assertStringContainsString('mg_admin_account_change_role', $endpoint);
        self::assertStringContainsString('mg_admin_account_set_model_status', $endpoint);
        self::assertStringContainsString('mg_admin_account_revoke_session', $endpoint);
        self::assertStringContainsString('mg_admin_account_revoke_sessions', $endpoint);
        self::assertStringContainsString("mg_audit('admin_user_' . \$action", $endpoint);
        self::assertStringContainsString("mg_event('admin.user.' . \$action", $endpoint);
        self::assertStringContainsString('admin.user_management.completed', $endpoint);
        self::assertStringContainsString('UPDATE user_sessions SET revoked_at = NOW()', $helper);
    }

    public function testDetailContextIsPermissionAwareAndExcludesSecrets(): void
    {
        $root = dirname(__DIR__, 2);
        $helper = file_get_contents($root . '/api/admin/_user_management.php');
        $detail = file_get_contents($root . '/api/admin/_user_detail.php');

        self::assertIsString($helper);
        self::assertIsString($detail);
        self::assertStringContainsString("'capabilities' => \$capabilities", $helper);
        self::assertStringContainsString("'available_roles'", $helper);
        self::assertStringContainsString("'available_models'", $helper);
        self::assertStringContainsString("'sessions'", $helper);
        self::assertStringContainsString("'view_sessions'", $helper);
        self::assertStringNotContainsString('password_hash', $helper . $detail);
        self::assertStringNotContainsString('token_hash', $helper . $detail);
    }
}
