<?php
declare(strict_types=1);

require_once __DIR__ . '/_billing_lifecycle_v2.php';

mg_require_method('POST');
$user = mg_require_permission('subscriptions.manage_own');
$input = mg_input();
mg_require_csrf_for_write($input);

$publicId = trim((string)($input['subscription_id'] ?? ''));
$action = strtolower(trim((string)($input['action'] ?? '')));
if ($publicId === '' || !in_array($action, ['cancel_at_period_end','reactivate','resume','cancel'], true)) {
    mg_fail('Subscription and valid action are required.', 422);
}
if ($action === 'resume') $action = 'reactivate';

try {
    $pdo = mg_db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM platform_account_subscriptions WHERE public_id=? AND user_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$publicId, (int)$user['id']]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$subscription) throw new MgSubscriptionBillingV2Exception('Subscription not found.', 404);
    if ((string)($subscription['provider_key'] ?? '') !== 'stripe') {
        throw new MgSubscriptionBillingV2Exception('Complimentary subscriptions are managed by an administrator.', 409);
    }

    $providerSubscriptionId = trim((string)($subscription['provider_subscription_id'] ?? ''));
    if ($providerSubscriptionId === '') throw new MgSubscriptionBillingV2Exception('Stripe subscription reference is unavailable.', 409);
    $fromStatus = (string)$subscription['status'];
    $pdo->commit();

    if ($action === 'cancel_at_period_end') {
        if (in_array($fromStatus, ['canceled','expired'], true)) throw new MgSubscriptionBillingV2Exception('Subscription is already closed.', 409);
        mg_stripe_api_request($pdo, 'POST', '/v1/subscriptions/' . rawurlencode($providerSubscriptionId), [
            'cancel_at_period_end' => true,
            'metadata' => ['source_type'=>'microgifter_account_management','user_id'=>(string)$user['id']],
        ], 'subscription-cancel-period-end:' . $publicId);
        $toStatus = 'cancel_pending';
        $pdo->prepare("UPDATE platform_account_subscriptions SET status='cancel_pending',cancel_at_period_end=1,updated_at=NOW() WHERE id=?")
            ->execute([(int)$subscription['id']]);
    } elseif ($action === 'reactivate') {
        if (!in_array($fromStatus, ['cancel_pending','active','trialing'], true)) throw new MgSubscriptionBillingV2Exception('This subscription cannot be reactivated from its current state.', 409);
        mg_stripe_api_request($pdo, 'POST', '/v1/subscriptions/' . rawurlencode($providerSubscriptionId), [
            'cancel_at_period_end' => false,
            'metadata' => ['source_type'=>'microgifter_account_management','user_id'=>(string)$user['id']],
        ], 'subscription-reactivate:' . $publicId);
        $toStatus = 'active';
        $pdo->prepare("UPDATE platform_account_subscriptions SET status='active',cancel_at_period_end=0,canceled_at=NULL,reactivated_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([(int)$subscription['id']]);
    } else {
        if (in_array($fromStatus, ['canceled','expired'], true)) throw new MgSubscriptionBillingV2Exception('Subscription is already closed.', 409);
        mg_stripe_api_request($pdo, 'DELETE', '/v1/subscriptions/' . rawurlencode($providerSubscriptionId), [], 'subscription-cancel-now:' . $publicId);
        $toStatus = 'canceled';
        $pdo->prepare("UPDATE platform_account_subscriptions SET status='canceled',cancel_at_period_end=0,next_billing_at=NULL,canceled_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([(int)$subscription['id']]);
    }

    mg_platform_account_subscription_event($pdo, (int)$subscription['id'], 'platform_subscription.' . $action, $fromStatus, $toStatus, (int)$user['id'], [
        'provider_key'=>'stripe','provider_subscription_id'=>$providerSubscriptionId,'source'=>'account_management',
    ]);
    mg_audit('subscription.' . $action, 'platform_account_subscription', [
        'subscription_id'=>$publicId,'from_status'=>$fromStatus,'to_status'=>$toStatus,'provider_key'=>'stripe',
    ], (int)$user['id']);
    mg_ok(['subscription_id'=>$publicId,'status'=>$toStatus,'cancel_at_period_end'=>$toStatus==='cancel_pending'],'Subscription updated.');
} catch (MgSubscriptionBillingV2Exception|MgStripeProviderException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), $error->httpStatus);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','subscription.manage_v2_failed','Unable to update canonical subscription.',['exception_class'=>$error::class],(int)($user['id']??0));
    mg_fail('Unable to update subscription.',500);
}
