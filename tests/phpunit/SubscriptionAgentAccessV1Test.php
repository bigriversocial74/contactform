<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SubscriptionAgentAccessV1Test extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function source(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testAgentDestinationsPreserveUpgradeIntent(): void
    {
        self::assertStringContainsString('/account-subscriptions.php?agent=personal',$this->source('agent.php'));
        self::assertStringContainsString('/account-subscriptions.php?agent=merchant',$this->source('merchant-agent-chat.php'));
    }

    public function testSubscriptionPageLoadsIntentRuntimeAfterCheckoutRuntime(): void
    {
        $source=$this->source('account-subscriptions.php');
        self::assertStringContainsString('/assets/css/subscription-agent-access-v1.css?v=1.0.0',$source);
        self::assertStringContainsString('/assets/js/subscription-agent-access-v1.js?v=1.0.0',$source);
        self::assertGreaterThan(strpos($source,'subscription-checkout-completion-v1.js'),strpos($source,'subscription-agent-access-v1.js'));
    }

    public function testRuntimeUsesAllowlistedAgentTargetsAndSessionStorage(): void
    {
        $source=$this->source('assets/js/subscription-agent-access-v1.js');
        foreach([
            "target: '/agent.php'",
            "target: '/merchant-agent-chat.php'",
            'Object.prototype.hasOwnProperty.call(config, value)',
            'window.sessionStorage.setItem',
            'window.sessionStorage.getItem',
            'window.location.replace(target)',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('Microgifter.post(',$source);
        self::assertStringNotContainsString('fetch(',$source);
    }

    public function testStarterIsTheMinimumRecommendedEligiblePackage(): void
    {
        $source=$this->source('assets/js/subscription-agent-access-v1.js');
        self::assertStringContainsString("packageId === 'starter'",$source);
        self::assertStringContainsString('is-agent-recommended',$source);
        self::assertStringContainsString('is-agent-eligible',$source);
        self::assertStringContainsString('Recommended starting plan for',$source);
        self::assertStringContainsString('Includes ',$source);
    }

    public function testActivatedAndCancelledStatesRemainDistinct(): void
    {
        $js=$this->source('assets/js/subscription-agent-access-v1.js');
        $css=$this->source('assets/css/subscription-agent-access-v1.css');
        self::assertStringContainsString("checkout === 'activated'",$js);
        self::assertStringContainsString("checkout === 'cancelled'",$js);
        self::assertStringContainsString("store('')",$js);
        self::assertStringContainsString('Resume with Starter',$js);
        self::assertStringContainsString('.mg-agent-access-banner.is-activated',$css);
        self::assertStringContainsString('.mg-agent-access-banner.is-cancelled',$css);
    }
}
