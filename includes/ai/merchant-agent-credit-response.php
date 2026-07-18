<?php
declare(strict_types=1);

require_once __DIR__ . '/user-credit-service.php';
require_once __DIR__ . '/ai-credit-reconciliation.php';

function mg_merchant_agent_ai_ineligible_statuses(): array
{
    return ['free','expired','past_due','paused','canceled','incomplete','pending_admin_review'];
}

function mg_merchant_agent_owner_context(array $context, int $userId): bool
{
    if ($userId < 1 || empty($context['merchant_access'])) return false;
    if (strtolower(trim((string)($context['entitlement_source'] ?? ''))) === 'workspace_subscription') return false;
    $entitlementUserId = (int)($context['entitlement_user_id'] ?? $userId);
    return $entitlementUserId === $userId;
}

function mg_merchant_agent_ai_package_eligible(array $context): bool
{
    $status = strtolower(trim((string)($context['status'] ?? 'free')));
    if (!empty($context['is_complimentary']) || $status === 'admin') return true;
    if (empty($context['is_paid'])) return false;
    return !in_array($status, mg_merchant_agent_ai_ineligible_statuses(), true);
}

function mg_merchant_agent_user_has_permission(array $user, string $permission): bool
{
    if (function_exists('mg_api_user_has_permission')) return mg_api_user_has_permission($user, $permission);
    if (function_exists('mg_has_permission')) return mg_has_permission($permission);
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    return in_array('admin', $roles, true) || in_array('super_admin', $roles, true);
}

function mg_merchant_agent_ai_credit_apply_package_gate(array $snapshot, array $context): array
{
    if (mg_merchant_agent_ai_package_eligible($context)) return $snapshot;
    $snapshot['enabled'] = false;
    $snapshot['can_use'] = false;
    $snapshot['unmetered'] = false;
    $snapshot['block_reason'] = 'subscription_required';
    $snapshot['package'] = [
        'id'=>(string)($context['package_id'] ?? 'free'),
        'name'=>(string)($context['package_name'] ?? 'Free Wallet'),
        'status'=>(string)($context['status'] ?? 'free'),
        'monthly_tokens'=>0,
        'unlimited'=>false,
    ];
    $snapshot['available_tokens'] = 0;
    $snapshot['package_tokens_allocated'] = 0;
    $snapshot['package_tokens_remaining'] = 0;
    $snapshot['message'] = 'Systematic Merchant Agent tools remain available. AI generation requires an active paid or complimentary package.';
    return $snapshot;
}

function mg_merchant_agent_ai_status(PDO $pdo, array $user, array $context): array
{
    $userId = (int)($user['id'] ?? 0);
    $hasPlan = mg_merchant_agent_user_has_permission($user, 'merchant.ai.plan');
    $hasReview = mg_merchant_agent_user_has_permission($user, 'merchant.ai.review');
    $credits = mg_merchant_agent_ai_credit_apply_package_gate(
        mg_ai_credit_snapshot($pdo, $userId, 'anthropic'),
        $context
    );
    $key = 'available';
    $label = 'AI available';
    $message = 'AI generation is available and will use the signed-in merchant owner’s token credits.';
    if (!$hasPlan) {
        $key = 'permission_unavailable';
        $label = 'AI permission unavailable';
        $message = 'Systematic tools remain available. AI generation requires the merchant.ai.plan permission.';
    } elseif (!mg_merchant_agent_ai_package_eligible($context)) {
        $key = 'subscription_required';
        $label = 'AI subscription required';
        $message = 'Systematic tools remain available. AI generation requires an active paid or complimentary package.';
    } elseif (empty($credits['can_use'])) {
        $key = 'credits_exhausted';
        $label = 'AI credits exhausted';
        $message = 'Systematic tools remain available. Add or renew AI token credits to generate new content.';
    }
    return [
        'key'=>$key,
        'label'=>$label,
        'message'=>$message,
        'can_generate'=>$key === 'available',
        'systematic_access'=>mg_merchant_agent_owner_context($context, $userId),
        'review_access'=>$hasReview,
        'plan_access'=>$hasPlan,
        'manage_url'=>'/account-subscriptions.php?agent=merchant',
        'ai_credits'=>$credits,
    ];
}

function mg_merchant_agent_require_owner_access(PDO $pdo): array
{
    $user = mg_require_api_user();
    $context = mg_user_package_context($pdo, $user);
    if (empty($context['merchant_access'])) {
        mg_fail('Merchant Agent access requires an active paid or complimentary merchant package.', 403, ['scope'=>'merchant_subscription_required','manage_url'=>'/account-subscriptions.php?agent=merchant']);
    }
    if (!mg_merchant_agent_owner_context($context, (int)$user['id'])) {
        mg_fail('This Merchant Agent build is available to the merchant workspace owner only.', 403, ['scope'=>'merchant_owner_required']);
    }
    return ['user'=>$user,'context'=>$context];
}

function mg_merchant_agent_require_owner_permission(array $user, string $permission): void
{
    if (mg_merchant_agent_user_has_permission($user, $permission)) return;
    if (function_exists('mg_audit')) mg_audit('permission_denied', 'security', ['permission'=>$permission,'merchant_agent_owner_only'=>true], (int)($user['id'] ?? 0));
    if (function_exists('mg_security_log')) mg_security_log('warning', 'permission.denied', 'Merchant Agent owner permission denied.', ['permission'=>$permission], (int)($user['id'] ?? 0));
    mg_fail('Merchant permission is not enabled for this account.', 403, ['scope'=>'merchant_permission','permission'=>$permission]);
}

function mg_merchant_agent_state_with_access(PDO $pdo, array $user, array $context, array $state): array
{
    $status = mg_merchant_agent_ai_status($pdo, $user, $context);
    if (empty($status['review_access']) && is_array($state['overview'] ?? null)) {
        $state['overview']['pending_reviews'] = null;
        $state['overview']['review_ready_plans'] = null;
        $state['overview']['executed_items'] = null;
        $state['overview']['latest'] = [];
        $state['overview']['review_permission_required'] = true;
    }
    $state['permissions'] = [
        'merchant.ai.plan'=>(bool)$status['plan_access'],
        'merchant.ai.review'=>(bool)$status['review_access'],
    ];
    $state['ai_status'] = $status;
    $state['ai_credits'] = $status['ai_credits'];
    return $state;
}

function mg_merchant_agent_ai_call_context(string $phase): array
{
    $call = $GLOBALS['mg_merchant_agent_ai_call'] ?? null;
    if (
        is_array($call)
        && ($call['pdo'] ?? null) instanceof PDO
        && is_array($call['user'] ?? null)
        && is_array($call['context'] ?? null)
        && str_starts_with((string)($call['source_type'] ?? ''), 'merchant_agent_')
    ) {
        return $call;
    }

    $userId = is_array($call) && is_array($call['user'] ?? null) ? (int)($call['user']['id'] ?? 0) : 0;
    if (function_exists('mg_security_log')) {
        mg_security_log('error', 'merchant_agent.ai_call_context_missing', 'Merchant Agent external AI call was blocked because its owner-credit context was missing or invalid.', ['phase'=>$phase], $userId > 0 ? $userId : null);
    }
    if (function_exists('mg_fail')) {
        mg_fail('Merchant Agent AI accounting context is required before contacting the model provider.', 500, ['scope'=>'merchant_ai_call_context_required','phase'=>$phase]);
    }
    throw new RuntimeException('Merchant Agent AI accounting context is required before contacting the model provider.');
}

function mg_merchant_agent_ai_begin_call(PDO $pdo, array $user, array $context, string $sourceType, array $metadata = []): void
{
    $sourceType = mb_substr(trim($sourceType), 0, 80) ?: 'merchant_agent';
    if (!str_starts_with($sourceType, 'merchant_agent_')) {
        throw new InvalidArgumentException('Merchant Agent AI source types must begin with merchant_agent_.');
    }
    $GLOBALS['mg_merchant_agent_ai_call'] = [
        'pdo'=>$pdo,
        'user'=>$user,
        'context'=>$context,
        'source_type'=>$sourceType,
        'metadata'=>$metadata,
        'preflighted'=>false,
    ];
    $GLOBALS['mg_merchant_agent_ai_last_result'] = [];
}

function mg_merchant_agent_ai_end_call(): void
{
    unset($GLOBALS['mg_merchant_agent_ai_call']);
}

function mg_merchant_agent_ai_before_anthropic_call(array $payload): void
{
    $call = mg_merchant_agent_ai_call_context('preflight');
    $pdo = $call['pdo'];
    $user = $call['user'];
    $context = $call['context'];
    $userId = (int)($user['id'] ?? 0);
    if (!mg_merchant_agent_owner_context($context, $userId)) {
        mg_fail('This Merchant Agent build is available to the merchant workspace owner only.', 403, ['scope'=>'merchant_owner_required']);
    }
    if (!mg_merchant_agent_user_has_permission($user, 'merchant.ai.plan')) {
        mg_fail('AI generation requires the merchant.ai.plan permission.', 403, ['scope'=>'merchant_ai_plan_permission','ai_status'=>mg_merchant_agent_ai_status($pdo, $user, $context)]);
    }
    $snapshot = mg_merchant_agent_ai_credit_apply_package_gate(mg_ai_credit_snapshot($pdo, $userId, 'anthropic'), $context);
    if (!mg_merchant_agent_ai_package_eligible($context)) {
        mg_fail('AI generation requires an active paid or complimentary package. Systematic Merchant Agent tools remain available.', 402, ['scope'=>'ai_subscription_required','ai_credits'=>$snapshot]);
    }
    $reserve = max(1, min(6000, (int)($payload['max_tokens'] ?? 1200)));
    try {
        mg_ai_credit_preflight($pdo, $userId, 'anthropic', $reserve, (string)$call['source_type']);
        $GLOBALS['mg_merchant_agent_ai_call']['preflighted'] = true;
    } catch (MgAiCreditException $error) {
        mg_fail($error->getMessage(), $error->httpStatus(), $error->details() + ['ai_status'=>mg_merchant_agent_ai_status($pdo, $user, $context)]);
    }
}

function mg_merchant_agent_ai_after_anthropic_call(array $payload, array $response): void
{
    $call = mg_merchant_agent_ai_call_context('debit');
    $pdo = $call['pdo'];
    $user = $call['user'];
    $context = $call['context'];
    $userId = (int)($user['id'] ?? 0);
    if (empty($call['preflighted']) && function_exists('mg_security_log')) {
        mg_security_log('error', 'merchant_agent.ai_preflight_state_missing', 'Merchant Agent model response arrived without a recorded credit preflight state.', ['source_type'=>(string)$call['source_type']], $userId);
    }
    $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];
    $inputTokens = max(0, (int)($usage['input_tokens'] ?? 0));
    $outputTokens = max(0, (int)($usage['output_tokens'] ?? 0));
    $responseId = trim((string)($response['id'] ?? ''));
    if ($responseId === '') $responseId = 'merchant-agent-' . substr(hash('sha256', json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: microtime(true)), 0, 40);
    $modelKey = trim((string)($payload['model'] ?? ''));
    $tokens = ['input'=>$inputTokens,'output'=>$outputTokens,'total'=>$inputTokens+$outputTokens];
    $accountingError = false;
    $callMetadata = is_array($call['metadata'] ?? null) ? $call['metadata'] : [];
    try {
        mg_ai_reconciliation_capture_provider_response($pdo, $userId, 'anthropic', $modelKey, (string)$call['source_type'], $response, $callMetadata);
    } catch (Throwable $captureError) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'merchant_agent.ai_provider_evidence_capture_failed', 'Merchant Agent provider response evidence could not be recorded.', ['exception_class'=>$captureError::class,'response_id'=>$responseId,'source_type'=>(string)$call['source_type']], $userId);
        }
    }
    try {
        $stmt = $pdo->prepare("SELECT m.id,m.provider_id,p.provider_key FROM ai_models m INNER JOIN ai_providers p ON p.id=m.provider_id WHERE m.model_key=? AND p.provider_key='anthropic' LIMIT 1");
        $stmt->execute([$modelKey]);
        $model = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($model)) throw new RuntimeException('Merchant Agent AI model could not be resolved for credit accounting.');
        $credits = mg_ai_credit_consume(
            $pdo,
            $userId,
            (int)$model['provider_id'],
            (int)$model['id'],
            'anthropic',
            $inputTokens,
            $outputTokens,
            (string)$call['source_type'],
            $responseId,
            $callMetadata + ['model_key'=>$modelKey,'anthropic_response_id'=>$responseId,'merchant_agent_owner_only'=>true]
        );
        mg_ai_reconciliation_mark_provider_accounting($pdo, $userId, 'anthropic', $responseId, true, ['accounting_hook'=>'merchant_agent_after_anthropic_call']);
    } catch (Throwable $error) {
        $accountingError = true;
        try {
            mg_ai_reconciliation_mark_provider_accounting($pdo, $userId, 'anthropic', $responseId, false, ['accounting_error_class'=>$error::class]);
        } catch (Throwable) {}
        if (function_exists('mg_security_log')) {
            mg_security_log('error', 'merchant_agent.ai_credit_debit_failed', 'Merchant Agent completed but its token debit could not be recorded.', ['exception_type'=>$error::class,'response_id'=>$responseId,'source_type'=>(string)$call['source_type']], $userId);
        }
        $credits = mg_ai_credit_snapshot($pdo, $userId, 'anthropic');
    }
    $credits = mg_merchant_agent_ai_credit_apply_package_gate($credits, $context);
    $GLOBALS['mg_merchant_agent_ai_last_result'] = [
        'ai_credits'=>$credits,
        'ai_tokens_used'=>$tokens,
        'ai_response_reference'=>$responseId,
        'ai_source'=>(string)$call['source_type'],
        'ai_credit_accounting_error'=>$accountingError,
    ];
}

function mg_merchant_agent_ai_last_result(PDO $pdo, array $user, array $context): array
{
    $result = $GLOBALS['mg_merchant_agent_ai_last_result'] ?? [];
    if (is_array($result) && $result !== []) return $result + ['ai_status'=>mg_merchant_agent_ai_status($pdo, $user, $context)];
    return [
        'ai_credits'=>mg_merchant_agent_ai_credit_apply_package_gate(mg_ai_credit_snapshot($pdo, (int)$user['id'], 'anthropic'), $context),
        'ai_tokens_used'=>['input'=>0,'output'=>0,'total'=>0],
        'ai_response_reference'=>'',
        'ai_source'=>'',
        'ai_credit_accounting_error'=>false,
        'ai_status'=>mg_merchant_agent_ai_status($pdo, $user, $context),
    ];
}