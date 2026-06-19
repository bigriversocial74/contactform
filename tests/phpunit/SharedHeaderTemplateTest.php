<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SharedHeaderTemplateTest extends TestCase
{
    public function testCanonicalHeaderRendersCurrentPublicDesignAndAppVariant(): void
    {
        $header=file_get_contents(dirname(__DIR__,2).'/includes/header.php');
        self::assertIsString($header);
        self::assertStringContainsString('app-header.php',$header);
        self::assertStringContainsString('data-page-id',$header);
        self::assertStringContainsString('data-mg-site-header',$header);
        self::assertStringContainsString('mg-site-header__search',$header);
        self::assertStringContainsString('data-mg-site-header-drawer',$header);
        self::assertStringNotContainsString('header-v2.php',$header);
    }

    public function testBothHeaderTemplatesExistAndExposeStableHooks(): void
    {
        $root=dirname(__DIR__,2).'/includes/header-templates/';
        $loggedOut=file_get_contents($root.'logged-out.php');
        $loggedIn=file_get_contents($root.'logged-in.php');
        self::assertIsString($loggedOut);
        self::assertIsString($loggedIn);
        self::assertStringContainsString('data-header-template="logged-out"',$loggedOut);
        self::assertStringContainsString('data-header-template="logged-in"',$loggedIn);
        self::assertStringContainsString('data-mg-auth-menu',$loggedOut);
        self::assertStringContainsString('data-mg-auth-menu',$loggedIn);
    }

    public function testPublicPagesUseSharedHeaderInclude(): void
    {
        $root=dirname(__DIR__,2);
        foreach(['learn-more.php','signin.php','signup.php','forgot-password.php','reset-password.php'] as $file){
            $page=file_get_contents($root.'/'.$file);
            self::assertIsString($page);
            self::assertStringContainsString('/includes/header.php',$page,$file);
        }
    }

    public function testHomeKeepsCurrentHeroAndSharedHeaderInclude(): void
    {
        $page=file_get_contents(dirname(__DIR__,2).'/index.php');
        self::assertIsString($page);
        self::assertStringContainsString("require __DIR__ . '/includes/header.php'",$page);
        self::assertStringContainsString("'id' => 'home'",$page);
        self::assertStringContainsString('Sell, Purchase, Send &amp; Claim Local Gifts.',$page);
        self::assertStringNotContainsString('$newActions',$page);
    }

    public function testLearnMoreKeepsCurrentSequentialDesign(): void
    {
        $page=file_get_contents(dirname(__DIR__,2).'/learn-more.php');
        self::assertIsString($page);
        self::assertStringNotContainsString('lm-top-control',$page);
        self::assertStringNotContainsString('data-lm-replay',$page);
        self::assertStringContainsString('const scrollToStage',$page);
        self::assertStringContainsString('class="mg-home-footer"',$page);
    }
}
