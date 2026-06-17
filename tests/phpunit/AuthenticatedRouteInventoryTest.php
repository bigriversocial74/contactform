<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthenticatedRouteInventoryTest extends TestCase
{
    public function testRoutePolicyAndAuditScriptExist(): void
    {
        $root=dirname(__DIR__,2);
        self::assertFileExists($root.'/config/security-route-policy.php');
        self::assertFileExists($root.'/scripts/audit_authenticated_surfaces.php');
        $script=file_get_contents($root.'/scripts/audit_authenticated_surfaces.php');
        self::assertIsString($script);
        self::assertStringContainsString('private page lacks canonical auth protection',$script);
        self::assertStringContainsString('private API lacks an authentication or permission gate',$script);
        self::assertStringContainsString('session write lacks CSRF enforcement',$script);
        self::assertStringContainsString('object-ownership scope needs manual verification',$script);
    }

    public function testCriticalPrivatePagesAreClassified(): void
    {
        $policy=require dirname(__DIR__,2).'/config/security-route-policy.php';
        $patterns=$policy['private_page_patterns'];
        foreach(['account.php','account-security.php','agent.php','archived-agents.php','build.php','inbox.php','sent.php','claimed.php','messages.php','notifications.php','notification-preferences.php','sales-crm.php'] as $route){
            $matched=false;
            foreach($patterns as $pattern){if(preg_match($pattern,$route)===1){$matched=true;break;}}
            self::assertTrue($matched,$route.' must be classified as private.');
        }
    }

    public function testCriticalObjectApisUseIdentityAndOwnershipScope(): void
    {
        $root=dirname(__DIR__,2);
        $routes=[
            'api/account/action-center.php',
            'api/messages/send.php',
            'api/notifications/read.php',
            'api/communications/preferences.php',
        ];
        foreach($routes as $route){
            self::assertFileExists($root.'/'.$route);
            $source=file_get_contents($root.'/'.$route);
            self::assertIsString($source);
            self::assertMatchesRegularExpression('/mg_require_permission\(|mg_require_api_user\(/',$source,$route.' must require a named permission or authenticated API identity.');
            self::assertMatchesRegularExpression('/user_id|owner_user_id|recipient_user_id|sender_user_id|participant|\$user\[\'id\'\]/i',$source,$route.' must pass or enforce authenticated ownership scope.');
        }

        $actionCenterHelper=file_get_contents($root.'/api/account/_action_center.php');
        self::assertIsString($actionCenterHelper);
        self::assertMatchesRegularExpression('/user_id|owner_user_id|recipient_user_id/i',$actionCenterHelper,'Action Center helper must enforce user ownership scope.');
    }

    public function testCriticalWritesRequireCsrf(): void
    {
        $root=dirname(__DIR__,2);
        foreach(['api/messages/send.php','api/notifications/read.php','api/communications/preferences.php'] as $route){
            $source=file_get_contents($root.'/'.$route);
            self::assertIsString($source);
            self::assertStringContainsString('mg_require_csrf_for_write',$source,$route.' must retain CSRF protection.');
        }
    }
}
