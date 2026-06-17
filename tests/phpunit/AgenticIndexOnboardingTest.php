<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class AgenticIndexOnboardingTest extends TestCase
{
    public function testLoggedOutIndexLoadsDirectPresentationScript(): void
    {
        $root=dirname(__DIR__,2);$index=file_get_contents($root.'/index-content.php');$script=file_get_contents($root.'/assets/js/auth-state.js');
        self::assertIsString($index);self::assertIsString($script);self::assertStringContainsString('/assets/js/auth-state.js',$index);self::assertStringContainsString('/assets/js/auth-state-core.js',$script);self::assertStringNotContainsString('/assets/js/public-index-bootstrap.js',$script);self::assertStringContainsString('initDirectPresentation',$script);
    }
    public function testHeaderProvidesPresentationAndLearnMoreControls(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/auth-state.js');self::assertIsString($script);self::assertStringContainsString('data-index-presentation-toggle',$script);self::assertStringContainsString("brand.insertAdjacentElement('afterend',group)",$script);self::assertStringContainsString('Pause automated presentation',$script);self::assertStringContainsString('mg-index-learn-more',$script);
    }
    public function testEveryNormalSectionSlideRunsBeforeTheNextSection(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/auth-state.js');self::assertIsString($script);self::assertStringContainsString('function slideCount(section)',$script);self::assertStringContainsString('state.slideIndex<count-1',$script);self::assertStringContainsString('state.slideIndex+=1;advance()',$script);self::assertStringContainsString('state.sectionIndex+=1;advance()',$script);self::assertStringContainsString('(slideIndex+.5)/count',$script);
    }
    public function testRevenueSectionUsesItsOwnMasterTimeline(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/auth-state.js');self::assertIsString($script);self::assertStringContainsString('var REVENUE_TIMELINE',$script);foreach(['intro','chartReveal','chartBuild','totals','complete'] as $phase){self::assertStringContainsString("name:'{$phase}'",$script);}self::assertStringContainsString('function runRevenueTimeline(section)',$script);self::assertStringContainsString("section.classList.contains('revenue-sticky')",$script);self::assertStringNotContainsString('revenueStops:5',$script);
    }
    public function testOnboardingUsesFullWidthStickySections(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/auth-state.js');self::assertIsString($script);self::assertStringContainsString('class="mg-onboarding-section"',$script);self::assertStringContainsString('class="mg-onboarding-pin"',$script);self::assertStringContainsString('class="mg-onboarding-grid"',$script);self::assertStringNotContainsString('mg-direct-onboarding-inner',$script);self::assertStringContainsString('min-height:320vh',$script);self::assertStringContainsString('position:sticky',$script);
    }
    public function testOnboardingEndsWithLearnMoreCallToAction(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/auth-state.js');self::assertIsString($script);self::assertStringContainsString('class="mg-onboarding-actions"',$script);self::assertStringContainsString('class="mg-onboarding-learn-more"',$script);self::assertStringContainsString('href="/learn-more.php"',$script);self::assertStringContainsString('>Learn More<',$script);
    }
    public function testNormalSectionsUseFasterConfigurableRandomHolds(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/auth-state.js');self::assertIsString($script);self::assertStringContainsString('var PRESENTATION_CONFIG',$script);self::assertStringContainsString('slideHoldMinMs:2600',$script);self::assertStringContainsString('slideHoldMaxMs:4000',$script);self::assertStringContainsString('focusHoldMs:900',$script);self::assertStringContainsString('scrollDurationMs:780',$script);self::assertStringContainsString('Math.random()',$script);self::assertStringContainsString("section.dataset.requiresInput==='true'",$script);self::assertStringContainsString('mgOnboardingPulse',$script);self::assertStringContainsString('mgOnboardingShake',$script);
    }
    public function testBusinessWebsiteScanEndpointRemainsProtected(): void
    {
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/public/website-product-ideas.php');self::assertIsString($endpoint);self::assertStringContainsString('mg_onboarding_validate_url',$endpoint);self::assertStringContainsString('FILTER_FLAG_NO_PRIV_RANGE',$endpoint);self::assertStringContainsString('FILTER_FLAG_NO_RES_RANGE',$endpoint);self::assertStringContainsString('CURLOPT_FOLLOWLOCATION=>false',$endpoint);self::assertStringContainsString('LIBXML_NONET',$endpoint);self::assertStringContainsString('mg_verify_csrf',$endpoint);
    }
}
