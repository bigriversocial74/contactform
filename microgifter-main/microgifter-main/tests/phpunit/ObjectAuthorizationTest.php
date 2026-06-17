<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/authorization.php';

if (!function_exists('mg_fail')) {
    function mg_fail(string $message, int $status = 400, array $data = []): never
    {
        throw new RuntimeException($status . ':' . $message);
    }
}

final class ObjectAuthorizationTest extends TestCase
{
    public function testOwnerCanAccessOwnedObject(): void
    {
        $user = ['id' => 42, 'permissions' => []];
        $this->assertTrue(mg_user_can_access_owner($user, 42, 'admin.objects.manage'));
    }

    public function testUserWithScopedPermissionCanAccessObject(): void
    {
        $user = ['id' => 42, 'permissions' => ['admin.objects.manage'], 'roles' => []];
        $this->assertTrue(mg_user_can_access_owner($user, 99, 'admin.objects.manage'));
    }

    public function testUnrelatedUserWithoutPermissionCannotAccessObject(): void
    {
        $user = ['id' => 42, 'permissions' => [], 'roles' => []];
        $this->assertFalse(mg_user_can_access_owner($user, 99, 'admin.objects.manage'));
    }
}
