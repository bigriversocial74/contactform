<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PublicIndexRuntimeSmokeTest extends TestCase
{
    public function testLoggedOutIndexServesStableShellEntrypoints(): void
    {
        [$status,$body]=mg_test_request('GET','/index.php');
        self::assertSame(200,$status);
        $html=(string)($body['raw']??'');
        self::assertStringContainsString('/assets/js/auth-state.js',$html);
        self::assertStringContainsString('/assets/js/cart.js',$html);
        self::assertStringContainsString('Sell, Purchase, Send &amp; Claim Local Gifts.',$html);
    }

    public function testAgenticOnboardingFragmentServesSemanticMarkup(): void
    {
        [$status,$body]=mg_test_request('GET','/api/public/agentic-onboarding-fragment.php');
        self::assertSame(200,$status);
        $html=(string)($body['raw']??'');
        self::assertStringContainsString('data-agentic-onboarding',$html);
        self::assertStringContainsString('data-agentic-stage',$html);
        self::assertStringContainsString('data-agentic-progress-bar',$html);
        self::assertStringContainsString('data-agentic-skip',$html);
    }

    public function testPublicIndexBootstrapReferencesOnlyDedicatedModules(): void
    {
        $root=dirname(__DIR__,2);
        $bootstrap=file_get_contents($root.'/assets/js/public-index-bootstrap.js');
        $cart=file_get_contents($root.'/assets/js/cart.js');
        self::assertIsString($bootstrap);
        self::assertIsString($cart);
        self::assertStringContainsString('/assets/js/index-agentic-onboarding.js',$bootstrap);
        self::assertStringContainsString('/assets/css/index-agentic-onboarding.css',$bootstrap);
        self::assertStringNotContainsString('agentic-onboarding',$cart);
    }
}
