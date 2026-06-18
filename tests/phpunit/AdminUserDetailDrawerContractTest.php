<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminUserDetailDrawerContractTest extends TestCase
{
    public function testUserCenterLoadsDrawerAssetsOnlyForItsPage(): void
    {
        $root = dirname(__DIR__, 2);
        $footer = file_get_contents($root . '/includes/footer.php');

        self::assertIsString($footer);
        self::assertStringContainsString("mg-admin-users-page", $footer);
        self::assertStringContainsString('/assets/js/admin-user-detail-drawer.js', $footer);
        self::assertStringContainsString('/assets/css/admin-user-detail-drawer.css', $footer);
    }

    public function testDrawerUsesMergedReadOnlyDetailEndpoint(): void
    {
        $root = dirname(__DIR__, 2);
        $client = file_get_contents($root . '/assets/js/admin-user-detail-drawer.js');

        self::assertIsString($client);
        self::assertStringContainsString('/api/admin/user-detail.php?user_id=', $client);
        self::assertStringContainsString("credentials: 'same-origin'", $client);
        self::assertStringContainsString("headers: { Accept: 'application/json' }", $client);
        self::assertStringNotContainsString("method: 'POST'", $client);
        self::assertStringNotContainsString("method: 'PATCH'", $client);
        self::assertStringNotContainsString("method: 'DELETE'", $client);
    }

    public function testDrawerEnhancesDirectoryRowsWithoutReplacingListBehavior(): void
    {
        $root = dirname(__DIR__, 2);
        $client = file_get_contents($root . '/assets/js/admin-user-detail-drawer.js');

        self::assertIsString($client);
        self::assertStringContainsString("new MutationObserver", $client);
        self::assertStringContainsString("mg-admin-user-detail-trigger", $client);
        self::assertStringContainsString("View details", $client);
        self::assertStringContainsString("User #([1-9][0-9]*)", $client);
        self::assertStringNotContainsString('innerHTML', $client);
    }

    public function testDrawerIsAccessibleAndRestoresFocus(): void
    {
        $root = dirname(__DIR__, 2);
        $client = file_get_contents($root . '/assets/js/admin-user-detail-drawer.js');

        self::assertIsString($client);
        self::assertStringContainsString("setAttribute('role', 'dialog')", $client);
        self::assertStringContainsString("setAttribute('aria-modal', 'true')", $client);
        self::assertStringContainsString("setAttribute('aria-haspopup', 'dialog')", $client);
        self::assertStringContainsString("event.key === 'Escape'", $client);
        self::assertStringContainsString('state.previousFocus', $client);
        self::assertStringContainsString("link.rel = 'noopener'", $client);
    }

    public function testDrawerRendersIdentityRolesModelsAndProfile(): void
    {
        $root = dirname(__DIR__, 2);
        $client = file_get_contents($root . '/assets/js/admin-user-detail-drawer.js');

        self::assertIsString($client);
        self::assertStringContainsString("section('Identity'", $client);
        self::assertStringContainsString("section('Roles'", $client);
        self::assertStringContainsString("section('User models'", $client);
        self::assertStringContainsString("section('Public profile'", $client);
        self::assertStringContainsString('renderIdentity(user)', $client);
        self::assertStringContainsString('renderRoles(user.roles)', $client);
        self::assertStringContainsString('renderModels(user.models)', $client);
        self::assertStringContainsString('renderProfile(user.profile)', $client);
    }
}
