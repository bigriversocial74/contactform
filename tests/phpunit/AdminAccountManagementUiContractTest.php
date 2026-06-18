<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminAccountManagementUiContractTest extends TestCase
{
    public function testManagementAssetsAndEventsAreRegistered(): void
    {
        $root = dirname(__DIR__, 2);
        $footer = file_get_contents($root . '/includes/footer.php');
        $drawer = file_get_contents($root . '/assets/js/admin-user-detail-drawer.js');
        $client = file_get_contents($root . '/assets/js/admin-user-management.js');

        self::assertIsString($footer);
        self::assertIsString($drawer);
        self::assertIsString($client);
        self::assertStringContainsString('/assets/js/admin-user-management.js', $footer);
        self::assertStringContainsString('/assets/css/admin-user-management.css', $footer);
        self::assertStringContainsString('mg:admin-user-detail-loaded', $drawer);
        self::assertStringContainsString('mg:admin-user-detail-refresh', $drawer);
        self::assertStringContainsString('mg:admin-user-detail-closed', $drawer);
    }

    public function testCombinedActionsUseProtectedApiClient(): void
    {
        $root = dirname(__DIR__, 2);
        $client = file_get_contents($root . '/assets/js/admin-user-management.js');

        self::assertIsString($client);
        foreach (['set_status','add_role','remove_role','set_model_status','revoke_session','revoke_sessions'] as $action) {
            self::assertStringContainsString("'" . $action . "'", $client);
        }
        self::assertStringContainsString('/api/admin/user-management.php', $client);
        self::assertStringContainsString('Microgifter.post', $client);
        self::assertStringContainsString('window.confirm', $client);
        self::assertStringContainsString('between 8 and 240 characters', $client);
        self::assertStringNotContainsString('innerHTML', $client);
    }

    public function testDirectoryReloadsAfterMutation(): void
    {
        $root = dirname(__DIR__, 2);
        $directory = file_get_contents($root . '/assets/js/admin-users.js');
        self::assertIsString($directory);
        self::assertStringContainsString('mg:admin-users-refresh', $directory);
    }
}
