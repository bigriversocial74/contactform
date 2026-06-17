<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthenticatedSurfaceSecurityTest extends TestCase
{
    public function testApplicationHeaderRequiresAuthenticationBeforeOutput(): void
    {
        $root=dirname(__DIR__,2);
        $header=file_get_contents($root.'/includes/header.php');
        $auth=file_get_contents($root.'/includes/auth.php');
        self::assertIsString($header);
        self::assertIsString($auth);
        self::assertStringContainsString("in_array(\$header_mode, ['agent', 'account', 'crm', 'builder'], true)",$header);
        self::assertStringContainsString('$user = $is_app_page ? mg_require_auth() : mg_current_user();',$header);
        self::assertStringContainsString("header('Cache-Control: no-store, private')",$header);
        self::assertStringContainsString('function mg_safe_return_path',$auth);
        self::assertStringContainsString("header('Location: ' . \$location, true, 302)",$auth);
    }

    public function testCoreUserPagesUseProtectedApplicationModes(): void
    {
        $root=dirname(__DIR__,2);
        $protected=[
            'agent.php'=>'agent',
            'build.php'=>'builder',
            'account-commerce.php'=>'account',
            'inbox.php'=>'agent',
            'sent.php'=>'agent',
            'claimed.php'=>'agent',
            'messages.php'=>'agent',
            'notifications.php'=>'account',
            'notification-preferences.php'=>'account',
        ];

        foreach($protected as $file=>$mode){
            self::assertFileExists($root.'/'.$file);
            $source=file_get_contents($root.'/'.$file);
            self::assertIsString($source);
            self::assertStringContainsString('includes/header.php',$source,$file.' must render through the protected shared header.');
            self::assertMatchesRegularExpression('/\$header_mode\s*=\s*[\'\"]'.preg_quote($mode,'/').'[\'\"]/',$source,$file.' must use a protected header mode.');
        }
    }

    public function testPublicEntryPagesDoNotAccidentallyRequireAnAccount(): void
    {
        $root=dirname(__DIR__,2);
        foreach(['index.php','signin.php','signup.php'] as $file){
            if(!is_file($root.'/'.$file))continue;
            $source=file_get_contents($root.'/'.$file);
            self::assertIsString($source);
            self::assertStringNotContainsString("\$header_mode = 'agent'",$source);
            self::assertStringNotContainsString("\$header_mode = 'account'",$source);
            self::assertStringNotContainsString("\$header_mode = 'crm'",$source);
            self::assertStringNotContainsString("\$header_mode = 'builder'",$source);
        }
    }

    public function testSensitiveWriteApisRequirePermissionAndCsrf(): void
    {
        $root=dirname(__DIR__,2);
        $writeApis=[
            'api/messages/send.php',
            'api/communications/preferences.php',
        ];
        foreach($writeApis as $file){
            self::assertFileExists($root.'/'.$file);
            $source=file_get_contents($root.'/'.$file);
            self::assertIsString($source);
            self::assertStringContainsString('mg_require_permission(',$source,$file.' must require a named permission.');
            self::assertStringContainsString('mg_require_csrf_for_write',$source,$file.' must require CSRF protection.');
        }
    }
}
