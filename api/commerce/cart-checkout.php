<?php
declare(strict_types=1);

require_once __DIR__ . '/_cart_checkout.php';
require_once dirname(__DIR__,2) . '/includes/personal-agent/core.php';
require_once dirname(__DIR__,2) . '/includes/personal-agent/opportunity-attribution.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $pdo = mg_db();
    $result = mg_cart_checkout_run(
        $pdo,
        (int) $user['id'],
        (string) ($input['workflow_key'] ?? ''),
        (string) ($input['provider_key'] ?? '')
    );
    $agentToken = mg_personal_agent_text($input['agent_attribution_token'] ?? '',64);
    if ($agentToken !== '' && mg_personal_agent_opportunity_schema_ready($pdo)) {
        try {
            $opportunity = mg_personal_agent_opportunity_find($pdo,(int)$user['id'],'',$agentToken);
            mg_personal_agent_opportunity_event($pdo,$opportunity,'checkout_started',[
                'action_type'=>(string)($input['agent_action'] ?? 'buy_self'),
                'order_public_id'=>$result['order']['order_id'] ?? null,
                'checkout_session_id'=>$result['session']['checkout_session_id'] ?? null,
                'provider_key'=>$result['provider'] ?? null,
                'reused'=>(bool)($result['reused'] ?? false),
            ],'checkout-started:'.$agentToken.':'.(string)($result['order']['order_id'] ?? $input['workflow_key'] ?? ''));
        } catch (Throwable $attributionError) {
            mg_security_log('warning','commerce.cart_checkout_attribution_failed','Checkout was created but Personal Agent attribution could not be recorded.',['exception_type'=>$attributionError::class],(int)$user['id']);
        }
    }
    mg_audit('commerce.cart_checkout_ready', 'commerce_order', [
        'order_id' => $result['order']['order_id'] ?? null,
        'checkout_session_id' => $result['session']['checkout_session_id'] ?? null,
        'provider' => $result['provider'],
        'reused' => $result['reused'],
        'agent_attributed' => $agentToken !== '',
    ], (int) $user['id']);
    mg_ok($result, $result['reused'] ? 'Checkout resumed.' : 'Checkout ready.', $result['reused'] ? 200 : 201);
} catch (MgCartCheckoutException $error) {
    mg_fail($error->getMessage(), $error->httpStatus);
} catch (MgCheckoutWorkflowException $error) {
    mg_fail($error->getMessage(), $error->httpStatus);
} catch (MgCheckoutSessionException $error) {
    mg_fail($error->getMessage(), $error->httpStatus);
} catch (Throwable $error) {
    mg_security_log('error', 'commerce.cart_checkout_failed', 'Cart checkout workflow failed.', [
        'exception_type' => get_class($error),
    ], (int) $user['id']);
    mg_fail('Unable to prepare checkout.', 500);
}
