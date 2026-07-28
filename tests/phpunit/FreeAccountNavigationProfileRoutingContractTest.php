<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/admin-navigation-access.php';

final class FreeAccountNavigationProfileRoutingContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testOrdinaryProductPermissionsDoNotCreateAdminNavigation(): void
    {
        self::assertFalse(mg_admin_navigation_user_can_access([
            'roles' => ['customer'],
            'permissions' => [
                'agent.test',
                'operational.alerts.view',
                'demand.dashboard.view',
                'intelligence.dashboard.view',
                'merchant.payments.view',
                'microgift.operations.view',
                'tips.reverse',
            ],
        ]));

        self::assertTrue(mg_admin_navigation_user_can_access(['roles' => ['admin'], 'permissions' => []]));
        self::assertTrue(mg_admin_navigation_user_can_access(['roles' => ['super_admin'], 'permissions' => []]));
        self::assertTrue(mg_admin_navigation_user_can_access(['roles' => ['customer'], 'permissions' => ['admin.users.view']]));
        self::assertTrue(mg_admin_navigation_user_can_access(['roles' => ['customer'], 'permissions' => ['security.logs.view']]));
    }

    public function testCustomerSidebarAppliesPackageEntitlements(): void
    {
        $source = $this->source('includes/personal-agent-sidebar.php');
        foreach ([
            '$hasDesignAccess = $hasPersonalAgentAccess;',
            '$hasCalendarAccess = $hasMerchantAgentAccess;',
            '<?php if ($hasDesignAccess): ?>',
            '<?php if ($hasCalendarAccess): ?>',
        ] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testDesignStudioRejectsFreeDirectAccess(): void
    {
        $source = $this->source('design-studio.php');
        self::assertStringContainsString("!empty(\$packageContext['is_paid']) || !empty(\$packageContext['merchant_access'])", $source);
        self::assertStringContainsString('/account-subscriptions.php?agent=personal&feature=design', $source);
    }

    public function testAccountMenuAndAdminPageShareNarrowAdminAuthority(): void
    {
        $menu = $this->source('includes/header-templates/logged-in.php');
        $admin = $this->source('account-admin.php');
        self::assertStringContainsString("require_once dirname(__DIR__) . '/admin-navigation-access.php';", $menu);
        self::assertStringContainsString('$can_admin_dashboard = mg_admin_navigation_user_can_access', $menu);
        self::assertStringContainsString("require_once __DIR__ . '/includes/admin-navigation-access.php';", $admin);
        self::assertStringContainsString('$hasAdminAccess = $user ? mg_admin_navigation_user_can_access($user) : false;', $admin);
    }

    public function testSearchFallbackRendersMemberProfileInsteadOfBareProfileRedirect(): void
    {
        $source = $this->source('user-profile.php');
        self::assertStringContainsString("header('Location: /profile.php?slug='", $source);
        self::assertStringContainsString('This member has not published a public profile yet.', $source);
        self::assertStringContainsString('/feed.php?chat=', $source);
        self::assertStringNotContainsString("header('Location: /profile.php', true, 302);", $source);
        self::assertStringNotContainsString("? '/profile.php?slug='", $source);
    }
}
