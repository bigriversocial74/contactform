<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PublicHeaderRoutesTest extends TestCase
{
    public function testRestoredPublicHeaderRoutesExist(): void
    {
        $root=dirname(__DIR__,2);
        foreach (['corporate-gifting.php','retail.php','locations.php'] as $route) {
            self::assertFileExists($root.'/'.$route);
        }
    }

    public function testHomeHeaderLinksToRestoredRoutes(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/includes/header-components/public-header.php');
        self::assertIsString($source);
        self::assertStringContainsString('/corporate-gifting.php',$source);
        self::assertStringContainsString('/retail.php',$source);
        self::assertStringContainsString('/locations.php',$source);
        self::assertStringContainsString('Book A Demo',$source);
        self::assertStringContainsString('Search Microgifter',$source);
    }
}
