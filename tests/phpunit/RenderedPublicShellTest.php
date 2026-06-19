<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RenderedPublicShellTest extends TestCase
{
    private function fetchPage(string $path): string
    {
        $base=rtrim((string)getenv('MG_TEST_BASE_URL'),'/');
        if($base==='')self::markTestSkipped('MG_TEST_BASE_URL is not configured.');
        $context=stream_context_create(['http'=>['timeout'=>5,'ignore_errors'=>true,'header'=>"User-Agent: MicrogifterTest\r\n"]]);
        $html=@file_get_contents($base.$path,false,$context);
        if(!is_string($html))self::markTestSkipped('MG_TEST_BASE_URL is configured but not reachable: '.$base.$path);
        return $html;
    }

    /** @dataProvider publicPageProvider */
    public function testPublicPagesRenderOneCurrentPublicHeader(string $path,string $pageId): void
    {
        $html=$this->fetchPage($path);
        self::assertSame(1,substr_count($html,'<header class="mg-site-header" data-mg-site-header>'),$path);
        self::assertStringContainsString('class="mg-site-header__search"',$html,$path);
        self::assertStringContainsString('data-mg-site-header-drawer',$html,$path);
        self::assertStringContainsString('id="mg-page-manifest"',$html,$path);
        self::assertStringContainsString('id="mg-page-onboarding"',$html,$path);
        self::assertStringContainsString('data-page-id="'.$pageId.'"',$html,$path);
        self::assertSame(0,substr_count($html,'data-agent-presentation-control'),$path);
        self::assertStringNotContainsString('lm-top-control',$html,$path);
    }

    public static function publicPageProvider(): array
    {
        return [
            ['/learn-more.php','learn-more'],
            ['/signin.php','signin'],
            ['/signup.php','signup'],
            ['/forgot-password.php','forgot-password'],
            ['/reset-password.php','reset-password'],
        ];
    }

    public function testLearnMoreRendersCurrentSequentialQuestionnaire(): void
    {
        $html=$this->fetchPage('/learn-more.php');
        self::assertStringContainsString('/assets/css/universal-header.css',$html);
        self::assertStringContainsString('data-learn-more-agent',$html);
        self::assertStringContainsString('data-lm-next',$html);
        self::assertStringContainsString('data-lm-review',$html);
        self::assertStringNotContainsString('/assets/js/agent-presentation.js',$html);
    }

    /** @dataProvider authPageProvider */
    public function testAuthPagesRenderAuthShellWithoutPresentationAssets(string $path): void
    {
        $html=$this->fetchPage($path);
        self::assertStringNotContainsString('/assets/js/agent-presentation.js',$html,$path);
        self::assertStringNotContainsString('/assets/css/agent-presentation.css',$html,$path);
        self::assertStringContainsString('/assets/css/auth-page.css',$html,$path);
        self::assertStringContainsString('class="mg-page mg-section-core mg-auth-page"',$html,$path);
        self::assertStringContainsString('class="mg-auth-card"',$html,$path);
        self::assertStringContainsString('/assets/css/universal-header.css',$html,$path);
    }

    public static function authPageProvider(): array
    {
        return [['/signin.php'],['/signup.php'],['/forgot-password.php'],['/reset-password.php']];
    }
}
