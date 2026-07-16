<?php
declare(strict_types=1);

require_once __DIR__ . '/_billing_lifecycle_v2.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

$packageId = trim((string)($input['package_id'] ?? $input['plan'] ?? ''));
$billingCycle = mg_subscription_billing_v2_cycle((string)($input['billing_cycle'] ?? 'month'));

try {
    $pdo = mg_db();
    $session = mg_subscription_billing_v2_portal_session($pdo, $user, $packageId !== '' ? $packageId : null, $billingCycle);
    mg_audit('subscription.billing_portal_opened','platform_account_subscription',[
        'target_package_id'=>$session['target_package_id'],
        'billing_cycle'=>$session['billing_cycle'],
    ],(int)$user['id']);
    mg_ok(['portal_url'=>$session['url'],'target_package_id'=>$session['target_package_id'],'billing_cycle'=>$session['billing_cycle']],'Secure billing portal ready.');
} catch (MgSubscriptionBillingV2Exception|MgStripeProviderException $error) {
    mg_fail($error->getMessage(), $error->httpStatus);
} catch (Throwable $error) {
    mg_security_log('error','subscription.billing_portal_failed','Unable to create Stripe billing portal session.',['exception_class'=>$error::class],(int)($user['id']??0));
    mg_fail('Unable to open billing management.',500);
}
