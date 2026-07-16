<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;
$read = static function (string $path) use ($root): string {
    $file = $root . '/' . ltrim($path, '/');
    if (!is_file($file)) throw new RuntimeException('Missing required file: ' . $path);
    $content = file_get_contents($file);
    if (!is_string($content)) throw new RuntimeException('Unable to read required file: ' . $path);
    return $content;
};
$expect = static function (bool $condition, string $label) use (&$failures, &$passes): void {
    if ($condition) {
        $passes++;
        echo "PASS: {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "FAIL: {$label}\n";
};

try {
    $migration = $read('database/20260715_ai_user_credit_packages.sql');
    $service = $read('includes/ai/user-credit-service.php');
    $pricing = $read('includes/pricing-packages.php');
    $pricingCompact = preg_replace('/\s+/', '', $pricing) ?? $pricing;
    $adminApi = $read('api/admin/ai-user-limits.php');
    $adminJs = $read('assets/js/admin-ai-user-limits.js');
    $adminCss = $read('assets/css/admin-ai-user-access.css');
    $adminPage = $read('admin/users.php');
    $anthropic = $read('includes/ai/anthropic-client.php');
    $creditResponse = $read('includes/personal-agent/credit-response.php');
    $personalLoader = $read('includes/personal-gifting-agent.php');
    $chatEndpoint = $read('api/user-agent/chat.php');
    $creditEndpoint = $read('api/user-agent/ai-credits.php');
    $apiBootstrap = $read('api/user-agent/_bootstrap.php');
    $agent = $read('agent.php');
    $workspace = $read('includes/agent-workspace.php');
    $creditJs = $read('assets/js/personal-agent-ai-credits.js');
    $creditCss = $read('assets/css/personal-agent-ai-credits.css');

    $expect(
        str_contains($migration, 'CREATE TABLE IF NOT EXISTS ai_user_credit_accounts')
        && str_contains($migration, 'CREATE TABLE IF NOT EXISTS ai_credit_ledger')
        && str_contains($migration, 'ALTER TABLE ai_usage_events MODIFY block_scope VARCHAR(80) NULL')
        && str_contains($migration, "'20260715_ai_user_credit_packages'"),
        'Migration owns user credit accounts, token ledger, usage scopes, and registration'
    );
    foreach ([
        'starter'=>50000,
        'growth'=>250000,
        'pro'=>1000000,
        'enterprise'=>5000000,
    ] as $package => $tokens) {
        $expect(
            str_contains($pricingCompact, "'id'=>'{$package}'")
            && str_contains($pricingCompact, "'ai_tokens_monthly_included'=>{$tokens}"),
            ucfirst($package) . ' package includes its monthly AI token allowance'
        );
    }
    $expect(
        str_contains($service, 'final class MgAiCreditException')
        && str_contains($service, 'function mg_ai_credit_preflight')
        && str_contains($service, 'function mg_ai_credit_consume')
        && str_contains($service, 'function mg_ai_credit_grant')
        && str_contains($service, 'function mg_ai_credit_save_policy'),
        'Shared service supports credit gating, actual usage debits, grants, and custom policy'
    );
    $expect(
        str_contains($service, "'entry_type'=>'usage_debit'")
        && str_contains($service, "'input_tokens'=>\$inputTokens")
        && str_contains($service, "'output_tokens'=>\$outputTokens")
        && str_contains($service, 'idempotency_key'),
        'Token debits record actual input/output usage with idempotency'
    );
    $expect(
        str_contains($adminApi, "'save_policy'")
        && str_contains($adminApi, "'grant_tokens'")
        && str_contains($adminApi, "'assign_package'")
        && str_contains($adminApi, 'platform_account_subscriptions')
        && str_contains($adminApi, 'mg_ai_credit_recent_ledger'),
        'Admin API manages package assignment, token grants, user policy, and ledger history'
    );
    $expect(
        str_contains($adminApi, 'mg_admin_account_assert_target_access')
        && str_contains($adminApi, 'mg_require_csrf_for_write')
        && str_contains($adminApi, 'mg_audit(')
        && str_contains($adminApi, 'mg_security_log('),
        'Admin AI access writes are permission-scoped, CSRF protected, and audited'
    );
    $expect(
        str_contains($adminJs, 'data-ai-package-form')
        && str_contains($adminJs, 'data-ai-grant-form')
        && str_contains($adminJs, 'data-ai-policy-form')
        && str_contains($adminJs, 'mg:admin-user-detail-loaded')
        && !str_contains($adminJs, 'window.alert(')
        && !str_contains($adminJs, 'window.confirm('),
        'Admin alert is replaced by one package, grant, policy, and history modal'
    );
    $expect(
        str_contains($adminCss, '.mg-admin-ai-access-modal')
        && str_contains($adminCss, 'width:min(1080px,calc(100vw - 36px))')
        && str_contains($adminCss, '@media(max-width:520px)'),
        'Admin AI access modal is desktop-wide and mobile responsive'
    );
    $expect(
        str_contains($adminPage, '/assets/css/admin-ai-user-access.css?v=1.0.0')
        && str_contains($adminPage, '/assets/js/admin-ai-user-limits.js?v=2.0.0')
        && str_contains($adminPage, 'subscriptions.admin'),
        'User Center loads versioned AI access assets for authorized admins'
    );
    $expect(
        str_contains($anthropic, "\$GLOBALS['mg_last_anthropic_response'] = \$decoded")
        && str_contains($anthropic, 'function mg_anthropic_last_response'),
        'Anthropic client exposes raw response usage to the credit boundary'
    );
    $expect(
        str_contains($creditResponse, 'mg_ai_credit_preflight')
        && str_contains($creditResponse, 'mg_anthropic_last_response')
        && str_contains($creditResponse, 'mg_ai_credit_consume')
        && str_contains($creditResponse, "\$result['ai_credits']")
        && str_contains($creditResponse, "\$result['ai_tokens_used']"),
        'Personal Agent preflights allowance and returns post-response token usage and balance'
    );
    $expect(
        str_contains($personalLoader, "require_once __DIR__ . '/personal-agent/credit-response.php';")
        && str_contains($chatEndpoint, 'mg_personal_agent_chat_with_credit_response')
        && str_contains($apiBootstrap, 'catch (MgAiCreditException'),
        'Personal Agent runtime routes through credit accounting and returns structured credit errors'
    );
    $expect(
        str_contains($creditEndpoint, 'mg_ai_credit_snapshot')
        && str_contains($workspace, 'data-personal-agent-credit-chip')
        && str_contains($agent, '/assets/js/personal-agent-ai-credits.js?v=1.0.0')
        && str_contains($agent, '/assets/css/personal-agent-ai-credits.css?v=1.0.0')
        && str_contains($agent, '/assets/js/personal-agent-design-studio.js?v=1.1.0'),
        'Personal Agent keeps Design Studio and loads its live package and token balance assets'
    );
    $expect(
        str_contains($creditJs, '/api/user-agent/ai-credits.php')
        && str_contains($creditJs, 'aiCreditBlocked')
        && str_contains($creditCss, '.mg-personal-agent-credit-chip.is-blocked'),
        'Customer credit indicator refreshes balances and clearly blocks exhausted accounts'
    );
    foreach (['claim_code','qr_code_token','payment_method_id','password_hash','api_key'] as $secret) {
        $expect(!str_contains(mb_strtolower($creditResponse), $secret), 'Personal Agent credit response excludes sensitive marker: ' . $secret);
    }
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("AI user credit package validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "AI user credit package validation passed: {$passes} checks.\n";
