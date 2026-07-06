<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AgenticIndexOnboardingTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function source(string $path): string
    {
        $full = self::root() . '/' . ltrim($path, '/');
        self::assertFileExists($full, $path);
        $content = file_get_contents($full);
        self::assertIsString($content, $path);
        return $content;
    }

    public function testCanonicalPublicIndexUsesUniversalHeaderAndHomepageAssets(): void
    {
        $index = self::source('index.php');
        self::assertStringContainsString("require __DIR__ . '/includes/header.php';", $index);
        self::assertStringContainsString("'header_mode' => 'public'", $index);
        self::assertStringContainsString('/assets/css/homepage-drm.css', $index);
        self::assertStringContainsString('/assets/js/homepage-drm.js', $index);
        self::assertStringContainsString('The social gifting CRM', $index);
    }

    public function testDirectPresentationLandingLoadsAuthStateScript(): void
    {
        $index = self::source('index-content.php');
        $script = self::source('assets/js/auth-state.js');
        self::assertStringContainsString('/assets/js/auth-state.js', $index);
        self::assertStringContainsString('/assets/js/auth-state-core.js', $script);
        self::assertStringContainsString('initDirectPresentation', $script);
    }

    public function testFileReferenceDiagnosticsRemainReadOnly(): void
    {
        $endpoint = self::source('api/admin/legacy-file-diagnostics.php');
        self::assertStringContainsString('index-content.php', $endpoint);
        self::assertStringContainsString('index.php', $endpoint);
        self::assertStringContainsString("'read_only' => true", $endpoint);
        self::assertStringContainsString('mg_legacy_file_diag_scan_references', $endpoint);
    }

    public function testPresentationControlsAndTimelineRemainWired(): void
    {
        $script = self::source('assets/js/auth-state.js');
        foreach (['data-index-presentation-toggle', 'Pause automated presentation', 'mg-index-learn-more', 'function slideCount(section)', 'var REVENUE_TIMELINE', 'function runRevenueTimeline(section)'] as $needle) {
            self::assertStringContainsString($needle, $script);
        }
    }

    public function testBusinessWebsiteScanEndpointRemainsProtected(): void
    {
        $endpoint = self::source('api/public/website-product-ideas.php');
        foreach (['mg_onboarding_validate_url', 'FILTER_FLAG_NO_PRIV_RANGE', 'FILTER_FLAG_NO_RES_RANGE', 'CURLOPT_FOLLOWLOCATION=>false', 'LIBXML_NONET', 'mg_verify_csrf'] as $needle) {
            self::assertStringContainsString($needle, $endpoint);
        }
    }
}
