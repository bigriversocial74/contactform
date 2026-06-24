<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminValidationContractCleanupTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testAdminPageGuardUsesPermissionMatrix(): void
    {
        $auth = $this->read('includes/admin-auth.php');
        self::assertStringContainsString("admin-permission-matrix.php", $auth);
        self::assertStringContainsString('function mg_require_admin_page_key', $auth);
        self::assertStringContainsString('mg_admin_permission_user_has($user, $permission)', $auth);

        $matrix = $this->read('includes/admin-permission-matrix.php');
        foreach ([
            "'admin.pending_models' => ['admin.users.view']",
            "'admin.merchant_catalog' => ['admin.merchants.view', 'admin.catalog.view']",
            "'admin.commerce' => ['admin.commerce.view']",
            "'admin.moderation' => ['social.moderate', 'admin.profiles.moderation.view', 'admin.profiles.moderation.manage']",
            "'admin.system_health' => ['admin.health.view']",
            "'admin.lifecycle_health' => ['admin.health.view']",
        ] as $needle) {
            self::assertStringContainsString($needle, $matrix);
        }
        self::assertStringNotContainsString("'admin.commerce.view' => ['merchant.payments.view'", $matrix);
    }

    public function testServerRenderedAdminPagesUseRefreshedPageGuards(): void
    {
        $pageGuards = [
            'admin/pending-models.php' => "mg_require_admin_page_key('admin.pending_models')",
            'admin/system-health.php' => "mg_require_admin_page_key('admin.system_health')",
            'admin/lifecycle-health.php' => "mg_require_admin_page_key('admin.lifecycle_health')",
            'admin/moderation.php' => "mg_require_admin_page_key('admin.moderation')",
            'merchant-catalog-operations.php' => "mg_require_admin_page_key('admin.merchant_catalog')",
        ];

        foreach ($pageGuards as $path => $guard) {
            $source = $this->read($path);
            self::assertStringContainsString("includes/admin-auth.php", $source, $path);
            self::assertStringContainsString($guard, $source, $path);
            self::assertStringNotContainsString('mg_has_permission(', $source, $path);
        }

        $commerce = $this->read('commerce-operations.php');
        self::assertStringContainsString("includes/admin-auth.php", $commerce);
        self::assertStringContainsString('mg_require_admin_page_any(mg_admin_commerce_read_permissions())', $commerce);
        self::assertStringNotContainsString('mg_has_permission(', $commerce);
    }

    public function testCurrentTemplateContractsAvoidOldFileLocationAssertions(): void
    {
        $appHeader = $this->read('includes/header-components/app-header.php');
        $createMenu = $this->read('includes/header-templates/create-menu.php');
        $adminSidebar = $this->read('includes/admin-sidebar.php');
        $appSidebar = $this->read('includes/app-sidebar.php');

        self::assertStringContainsString('data-global-create', $appHeader);
        self::assertStringContainsString('data-create-menu-option="post"', $createMenu);
        self::assertStringContainsString('mg_admin_user_can_view_page', $adminSidebar);
        self::assertStringContainsString('appSidebarNav', $appSidebar);
    }
}
