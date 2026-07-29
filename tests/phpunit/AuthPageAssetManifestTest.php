<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthPageAssetManifestTest extends TestCase
{
    public function testAuthPagesDoNotLoadPresentationAssets(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/page.php';

        foreach (['signin', 'signup', 'forgot-password', 'reset-password'] as $pageId) {
            $manifest = mg_page_manifest(['id' => $pageId]);
            self::assertContains('universal-header', $manifest['assets'], "{$pageId} should load the public header.");
            self::assertContains('auth-pages', $manifest['assets'], "{$pageId} should load auth page layout CSS.");
            self::assertContains('auth-forms', $manifest['assets'], "{$pageId} should load auth form behavior.");
            self::assertNotContains('agent-presentation', $manifest['assets'], "{$pageId} must not load presentation assets.");
            self::assertSame('mg-auth-page', $manifest['body_class'], "{$pageId} should use the auth page layout class.");

            $assets = mg_resolve_page_assets($manifest);
            self::assertContains('/assets/css/auth-page.css?v=6.0.0', $assets['styles']);
            self::assertNotContains('/assets/css/agent-presentation.css', $assets['styles']);
            self::assertNotContains('/assets/css/agent-presentation-layout.css', $assets['styles']);
            self::assertNotContains('/assets/js/agent-presentation.js', $assets['scripts']);
        }
    }

    public function testAuthPageStylesUseTheHomepageWhiteBackgroundWithoutArtwork(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/assets/css/auth-page.css');
        self::assertIsString($css);
        self::assertStringContainsString('.mg-auth-page{', $css);
        self::assertStringContainsString('background:#fff!important', $css);
        self::assertStringContainsString('.mg-auth-page .mg-main{', $css);
        self::assertStringContainsString('background-image:none!important', $css);
        self::assertStringContainsString('content:none!important', $css);
        self::assertStringNotContainsString('background:#f4f7fb!important', $css);
        self::assertStringNotContainsString('/assets/images/mountains.png', $css);
        self::assertStringNotContainsString('/assets/images/foreground.png', $css);
    }
}
