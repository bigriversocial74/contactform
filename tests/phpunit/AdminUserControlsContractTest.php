<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminUserControlsContractTest extends TestCase
{
    public function testControlAssetsAndEndpointsAreRegistered(): void
    {
        $root = dirname(__DIR__, 2);
        $footer = file_get_contents($root . '/includes/footer.php');
        $client = file_get_contents($root . '/assets/js/admin-user-controls.js');
        $endpoint = file_get_contents($root . '/api/admin/user-management.php');

        self::assertIsString($footer);
        self::assertIsString($client);
        self::assertIsString($endpoint);
        self::assertStringContainsString('/assets/js/admin-user-controls.js', $footer);
        self::assertStringContainsString('/assets/css/admin-user-controls.css', $footer);
        self::assertStringContainsString('/api/admin/user-management.php', $client);
        self::assertStringContainsString("mg_require_method('POST')", $endpoint);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $endpoint);
        self::assertStringNotContainsString('innerHTML', $client);
    }

    public function testMigrationAndSessionValidationArePresent(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = file_get_contents($root . '/config/migrations.php');
        $session = file_get_contents($root . '/api/admin/sessions.php');

        self::assertIsString($manifest);
        self::assertIsString($session);
        self::assertStringContainsString('stage_18k_admin_account_management.sql', $manifest);
        self::assertStringContainsString('mg_admin_management_reason', $session);
        self::assertStringContainsString('mg_current_session_hash()', $session);
        self::assertStringContainsString('mg_audit(', $session);
    }
}
