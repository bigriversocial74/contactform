<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PublicHeaderRoutesTest extends TestCase
{
    public function testRestoredPublicHeaderRoutesExist(): void
    {
        $root=dirname(__DIR__,2);
        foreach (['corporate.php','retail.php','locations.php'] as $route) {
            self::assertFileExists($root.'/'.$route);
        }
    }

    public function testHomeHeaderLinksToRestoredRoutes(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/includes/header-components/public-header.php');
        self::assertIsString($source);
        self::assertStringContainsString('/corporate.php',$source);
        self::assertStringNotContainsString('/corporate-gifting.php',$source);
        self::assertStringContainsString('/retail.php',$source);
        self::assertStringContainsString('/locations.php',$source);
        self::assertStringContainsString('Book A Demo',$source);
        self::assertStringContainsString('Search Microgifter',$source);
    }

    public function testRetailSubscriptionsPageIsMultiSection(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/retail.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg-retail-hero',$source);
        self::assertStringContainsString('Retail subscriptions',$source);
        self::assertStringContainsString('What retailers can sell',$source);
        self::assertStringContainsString('How it works',$source);
        self::assertStringContainsString('Local revenue infrastructure',$source);
    }
}
