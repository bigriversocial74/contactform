<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminSystemHealthContractTest extends TestCase
{
    public function testSystemHealthPageAndNavigationAreRegistered(): void
    {
        $root=dirname(__DIR__,2);
        $page=file_get_contents($root.'/admin/system-health.php');
        $dashboard=file_get_contents($root.'/includes/account/admin-dashboard.php');
        $shortcut=file_get_contents($root.'/api/admin/_dashboard.php');
        self::assertIsString($page);
        self::assertIsString($dashboard);
        self::assertIsString($shortcut);
        self::assertStringContainsString("mg_require_admin_page_key('admin.system_health')",$page);
        self::assertStringContainsString('/assets/css/admin-system-health.css',$page);
        self::assertStringContainsString('/assets/js/admin-system-health.js',$page);
        self::assertStringContainsString('/admin/system-health.php',$dashboard);
        self::assertStringContainsString('/admin/system-health.php',$shortcut);
    }

    public function testReadOnlyHealthEndpointDoesNotCreateStorage(): void
    {
        $root=dirname(__DIR__,2);
        $endpoint=file_get_contents($root.'/api/admin/system-health.php');
        $helper=file_get_contents($root.'/api/admin/_system_health.php');
        $metrics=file_get_contents($root.'/api/admin/_system_health_metrics.php');
        self::assertIsString($endpoint);
        self::assertIsString($helper);
        self::assertIsString($metrics);
        self::assertStringContainsString("mg_require_permission('admin.health.view')",$helper);
        self::assertStringContainsString("mg_require_method('GET')",$endpoint);
        self::assertStringContainsString('Cache-Control: private, no-store, max-age=0',$endpoint);
        self::assertStringContainsString('mg_storage_root(false)',$metrics);
        self::assertStringContainsString('mg_admin_system_health_readonly_storage_path',$metrics);
        self::assertStringNotContainsString("mg_storage_resolve_asset_path('persistent_local'",$metrics);
        self::assertStringContainsString('LIMIT " . ($scanLimit + 1)',$metrics);
    }

    public function testRecoveryActionsAreSuperAdminOnlyAndBounded(): void
    {
        $root=dirname(__DIR__,2);
        $endpoint=file_get_contents($root.'/api/admin/system-health-action.php');
        $actions=file_get_contents($root.'/api/admin/_system_health_actions.php');
        self::assertIsString($endpoint);
        self::assertIsString($actions);
        self::assertStringContainsString("mg_require_method('POST')",$endpoint);
        self::assertStringContainsString('mg_require_csrf_for_write',$endpoint);
        self::assertStringContainsString('mg_rate_limit',$endpoint);
        self::assertStringContainsString("in_array('super_admin'",$actions);
        self::assertStringContainsString('mg_storage_assert_ready(false, true)',$actions);
        self::assertStringContainsString('attempt_count<5',$actions);
        self::assertStringContainsString('LIMIT {$limit}',$actions);
        self::assertStringContainsString('minimum_age_hours',$actions);
        self::assertStringContainsString('feed_post_assets',$actions);
        self::assertStringContainsString('mg_audit',$endpoint);
    }

    public function testDashboardRendersMetricsWarningsAndConfirmedActions(): void
    {
        $root=dirname(__DIR__,2);
        $client=file_get_contents($root.'/assets/js/admin-system-health.js');
        $style=file_get_contents($root.'/assets/css/admin-system-health.css');
        self::assertIsString($client);
        self::assertIsString($style);
        self::assertStringContainsString('/api/admin/system-health.php',$client);
        self::assertStringContainsString('/api/admin/system-health-action.php',$client);
        self::assertStringContainsString('window.confirm',$client);
        self::assertStringContainsString('data-health-action',$client);
        self::assertStringContainsString('missing_files',$client);
        self::assertStringContainsString('Failed notifications',$client);
        self::assertStringContainsString('.mg-system-health-warning',$style);
        self::assertStringContainsString('.mg-system-health-metrics',$style);
    }
}
