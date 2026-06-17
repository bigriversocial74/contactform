<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class BootstrapIdempotencyTest extends TestCase
{
public function testConfigHelperGuardsExist(): void
{
$root=dirname(__DIR__,2);
$core=file_get_contents($root.'/includes/app-core.php');
$security=file_get_contents($root.'/api/security.php');
$app=file_get_contents($root.'/includes/app.php');
self::assertIsString($core);
self::assertIsString($security);
self::assertIsString($app);
self::assertStringContainsString("function_exists('mg_config_value')",$core);
self::assertStringContainsString("function_exists('mg_config_value')",$security);
self::assertStringContainsString('app-core.php',$app);
}
}
