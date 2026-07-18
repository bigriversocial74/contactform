<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PostLoginAgentAccessContractTest extends TestCase
{
    public function testAuthenticationDefaultsRouteToAgentWorkspace(): void
    {
        $root = dirname(__DIR__, 2);
        $authJs = (string) file_get_contents($root . '/assets/js/auth.js');
        $signin = (string) file_get_contents($root . '/signin.php');
        $login = (string) file_get_contents($root . '/api/auth/login.php');
        $signup = (string) file_get_contents($root . '/signup.php');
        $register = (string) file_get_contents($root . '/api/auth/register.php');

        self::assertStringContainsString("return '/agent.php'", $authJs);
        self::assertStringContainsString('data-success-redirect="/agent.php"', $signin);
        self::assertStringContainsString("\$input['return']??'/agent.php'", $login);
        self::assertStringContainsString("\$isMerchant ? '/account-subscriptions.php' : '/agent.php'", $signup);
        self::assertStringContainsString("\$postVerifyRedirect='/agent.php'", $register);
    }

    public function testFreeAccountsCanOpenSystematicAgentWorkspace(): void
    {
        $root = dirname(__DIR__, 2);
        $agent = (string) file_get_contents($root . '/agent.php');

        self::assertStringContainsString("\$header_mode = 'agent'", $agent);
        self::assertStringContainsString("require __DIR__ . '/includes/header.php'", $agent);
        self::assertStringNotContainsString('account-subscriptions.php?agent=personal', $agent);
        self::assertStringNotContainsString('$hasPersonalAgentAccess', $agent);
    }

    public function testFreeAndUnpaidPackagesCannotUseAiApiCredits(): void
    {
        $root = dirname(__DIR__, 2);
        $creditResponse = (string) file_get_contents($root . '/includes/personal-agent/credit-response.php');
        $creditEndpoint = (string) file_get_contents($root . '/api/user-agent/ai-credits.php');
        $creditUi = (string) file_get_contents($root . '/assets/js/personal-agent-ai-credits.js');

        self::assertStringContainsString('mg_personal_agent_ai_package_eligible', $creditResponse);
        self::assertStringContainsString("['free','expired','past_due','paused','canceled','incomplete','pending_admin_review']", $creditResponse);
        self::assertStringContainsString("'scope'=>'ai_subscription_required'", $creditResponse);
        self::assertStringContainsString('Systematic agent flows remain available.', $creditResponse);
        self::assertStringContainsString('mg_personal_agent_ai_credit_apply_package_gate', $creditEndpoint);
        self::assertStringContainsString("'systematic_access'=>true", $creditEndpoint);
        self::assertStringContainsString("'ai_api_access'=>!empty(\$credits['can_use'])", $creditEndpoint);
        self::assertStringContainsString('Systematic mode', $creditUi);
        self::assertStringNotContainsString('submit.disabled = true', $creditUi);
        self::assertStringNotContainsString('event.stopImmediatePropagation()', $creditUi);
    }
}
