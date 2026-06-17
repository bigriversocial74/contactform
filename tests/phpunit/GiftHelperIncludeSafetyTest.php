<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GiftHelperIncludeSafetyTest extends TestCase
{
    public function testGiftHelperDoesNotRedeclareSharedUuidFunction(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/gifts/_gift.php');
        self::assertIsString($source);
        self::assertStringContainsString("if (!function_exists('mg_public_uuid'))",$source);
    }

    public function testMessagingCanLoadGiftAndApplicationFoundationsTogether(): void
    {
        $messaging=file_get_contents(dirname(__DIR__,2).'/api/messages/_messaging.php');
        $appCore=file_get_contents(dirname(__DIR__,2).'/includes/app-core.php');
        self::assertIsString($messaging);
        self::assertIsString($appCore);
        self::assertStringContainsString("gifts/_gift.php",$messaging);
        self::assertStringContainsString("if(!function_exists('mg_public_uuid'))",$appCore);
    }
}
