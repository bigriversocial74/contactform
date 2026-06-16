<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class PublicIndexPresentationControlTest extends TestCase
{
    public function testHeaderControlsLoadBesidePublicBrand(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/auth-state.js');
        self::assertIsString($script);
        self::assertStringContainsString("var brand=document.querySelector('.brand')",$script);
        self::assertStringContainsString("brand.insertAdjacentElement('afterend',group)",$script);
        self::assertStringContainsString('data-index-presentation-toggle',$script);
        self::assertStringContainsString('class="mg-index-learn-more" href="/learn-more.php"',$script);
    }

    public function testControlSupportsPlayPauseAndReplayStates(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/auth-state.js');
        self::assertIsString($script);
        self::assertStringContainsString("function setControl(mode)",$script);
        self::assertStringContainsString("mode==='playing'",$script);
        self::assertStringContainsString("mode==='replay'",$script);
        self::assertStringContainsString("label.textContent='Replay'",$script);
        self::assertStringContainsString("icon.textContent='↻'",$script);
        self::assertStringContainsString('function completePresentation()',$script);
        self::assertStringContainsString('function replay()',$script);
    }

    public function testReplayResetsPresentationAndReturnsToFirstSection(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/auth-state.js');
        self::assertIsString($script);
        self::assertStringContainsString('state.sectionIndex=0',$script);
        self::assertStringContainsString('state.slideIndex=0',$script);
        self::assertStringContainsString('state.completed=false',$script);
        self::assertStringContainsString('resetVisuals()',$script);
        self::assertStringContainsString('sections[0].offsetTop-headerOffset()',$script);
    }

    public function testManualNavigationPausesAndResumesFromCurrentStep(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/auth-state.js');
        self::assertIsString($script);
        self::assertStringContainsString('function locateCurrentStep()',$script);
        self::assertStringContainsString('function pauseForUser()',$script);
        self::assertStringContainsString("['wheel','touchstart']",$script);
        self::assertStringContainsString('state.slideIndex=Math.min',$script);
    }

    public function testDirectPresentationDoesNotDependOnOldBootstrap(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/auth-state.js');
        self::assertIsString($script);
        self::assertStringNotContainsString('/assets/js/public-index-bootstrap.js',$script);
        self::assertStringNotContainsString('/api/public/agentic-onboarding-fragment.php',$script);
        self::assertStringContainsString('mg-onboarding-section',$script);
        self::assertStringContainsString('data-direct-continue',$script);
    }
}
