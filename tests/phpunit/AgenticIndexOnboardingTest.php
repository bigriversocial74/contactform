<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AgenticIndexOnboardingTest extends TestCase
{
    private static function appRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function readProjectFile(string $relativePath): string
    {
        $path = self::appRoot() . '/' . ltrim($relativePath, '/');
        self::assertFileExists($path, $relativePath . ' should exist.');
        self::assertFileIsReadable($path, $relativePath . ' should be readable.');
        $content = file_get_contents($path);
        self::assertIsString($content, $relativePath . ' should load as text.');
        return $content;
    }

    public function testCanonicalPublicIndexUsesUniversalHeaderAndHomepageAssets(): void
    {
        $index = self::readProjectFile('index.php');

        self::assertStringContainsString("require __DIR__ . '/includes/header.php';", $index);
        self::assertStringContainsString("'header_mode' => 'public'", $index);
        self::assertStringContainsString('/assets/css/homepage-drm.css', $index);
        self::assertStringContainsString('/assets/css/homepage-hero-search.css', $index);
        self::assertStringContainsString('/assets/js/homepage-drm.js', $index);
        self::assertStringContainsString('The social gifting CRM', $index);
        self::assertStringContainsString('/discover.php', $index);
    }

    public function testDirectPresentationLandingRemainsProtectedCandidate(): void
    {
        $legacyIndex = self::readProjectFile('index-content.php');
        $script = self::readProjectFile('assets/js/auth-state.js');

        self::assertStringContainsString('/assets/js/auth-state.js', $legacyIndex);
        self::assertStringContainsString('/assets/js/auth-state-core.js', $script);
        self::assertStringNotContainsString('/assets/js/public-index-bootstrap.js', $script);
        self::assertStringContainsString('initDirectPresentation', $script);
    }

    public function testFileReferenceDiagnosticsTracksLandingCandidatesReadOnly(): void
    {
        $endpoint = self::readProjectFile('api/admin/legacy-file-diagnostics.php');

        foreach (['index-content.php', 'microgifter-main/index.php', 'microgifter-main/index-content.php', 'includes/landing/index-v3', 'index.php'] as $candidate) {
            self::assertStringContainsString($candidate, $endpoint);
        }

        self::assertStringContainsString("'protected_active'", $endpoint);
        self::assertStringContainsString("'legacy_candidate'", $endpoint);
        self::assertStringContainsString('$deleteReady = array_values(array_filter', $endpoint);
        self::assertStringContainsString("'delete_ready' => $deleteReady", $endpoint);
        self::assertStringContainsString("'read_only' => true", $endpoint);
    }

    public function testHeaderProvidesPresentationAndLearnMoreControls(): void
    {
        $script = self::readProjectFile('assets/js/auth-state.js');

        self::assertStringContainsString('data-index-presentation-toggle', $script);
        self::assertStringContainsString("brand.insertAdjacentElement('afterend',group)", $script);
        self::assertStringContainsString('Pause automated presentation', $script);
        self::assertStringContainsString('mg-index-learn-more', $script);
    }

    public function testEveryNormalSectionSlideRunsBeforeTheNextSection(): void
    {
        $script = self::readProjectFile('assets/js/auth-state.js');

        self::assertStringContainsString('function slideCount(section)', $script);
        self::assertStringContainsString('state.slideIndex<count-1', $script);
        self::assertStringContainsString('state.slideIndex+=1;advance()', $script);
        self::assertStringContainsString('state.sectionIndex+=1;advance()', $script);
        self::assertStringContainsString('(slideIndex+.5)/count', $script);
    }

    public function testRevenueSectionUsesItsOwnMasterTimeline(): void
    {
        $script = self::readProjectFile('assets/js/auth-state.js');

        self::assertStringContainsString('var REVENUE_TIMELINE', $script);
        foreach (['intro', 'chartReveal', 'chartBuild', 'totals', 'complete'] as $phase) {
            self::assertStringContainsString("name:'{$phase}'", $script);
        }
        self::assertStringContainsString('function runRevenueTimeline(section)', $script);
        self::assertStringContainsString("section.classList.contains('revenue-sticky')", $script);
        self::assertStringNotContainsString('revenueStops:5', $script);
    }

    public function testOnboardingUsesFullWidthStickySections(): void
    {
        $script = self::readProjectFile('assets/js/auth-state.js');

        self::assertStringContainsString('class="mg-onboarding-section"', $script);
        self::assertStringContainsString('class="mg-onboarding-pin"', $script);
        self::assertStringContainsString('class="mg-onboarding-grid"', $script);
        self::assertStringNotContainsString('mg-direct-onboarding-inner', $script);
        self::assertStringContainsString('min-height:320vh', $script);
        self::assertStringContainsString('position:sticky', $script);
    }

    public function testOnboardingEndsWithLearnMoreCallToAction(): void
    {
        $script = self::readProjectFile('assets/js/auth-state.js');

        self::assertStringContainsString('class="mg-onboarding-actions"', $script);
        self::assertStringContainsString('class="mg-onboarding-learn-more"', $script);
        self::assertStringContainsString('href="/learn-more.php"', $script);
        self::assertStringContainsString('>Learn More<', $script);
    }

    public function testNormalSectionsUseFasterConfigurableRandomHolds(): void
    {
        $script = self::readProjectFile('assets/js/auth-state.js');

        self::assertStringContainsString('var PRESENTATION_CONFIG', $script);
        self::assertStringContainsString('slideHoldMinMs:2600', $script);
        self::assertStringContainsString('slideHoldMaxMs:4000', $script);
        self::assertStringContainsString('focusHoldMs:900', $script);
        self::assertStringContainsString('scrollDurationMs:780', $script);
        self::assertStringContainsString('Math.random()', $script);
        self::assertStringContainsString("section.dataset.requiresInput==='true'", $script);
        self::assertStringContainsString('mgOnboardingPulse', $script);
        self::assertStringContainsString('mgOnboardingShake', $script);
    }

    public function testBusinessWebsiteScanEndpointRemainsProtected(): void
    {
        $endpoint = self::readProjectFile('api/public/website-product-ideas.php');

        self::assertStringContainsString('mg_onboarding_validate_url', $endpoint);
        self::assertStringContainsString('FILTER_FLAG_NO_PRIV_RANGE', $endpoint);
        self::assertStringContainsString('FILTER_FLAG_NO_RES_RANGE', $endpoint);
        self::assertStringContainsString('CURLOPT_FOLLOWLOCATION=>false', $endpoint);
        self::assertStringContainsString('LIBXML_NONET', $endpoint);
        self::assertStringContainsString('mg_verify_csrf', $endpoint);
    }
}
