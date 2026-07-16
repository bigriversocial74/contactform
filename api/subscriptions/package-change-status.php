<?php
declare(strict_types=1);

require_once __DIR__ . '/_package_changes.php';
require_once dirname(__DIR__, 2) . '/includes/package-entitlements.php';

mg_require_method('GET');
$user=mg_require_api_user();
try{
    $pdo=mg_db();
    $request=mg_subscription_package_change_latest($pdo,(int)$user['id'],false);
    $context=mg_user_package_context($pdo,$user);
    $subscription=is_array($context['subscription']??null)?$context['subscription']:[];
    $publicRequest=$request?mg_subscription_package_change_public($request):null;
    $state=!empty($context['merchant_access'])?'active_access':'free_access';
    if($publicRequest&&($publicRequest['status']??'')==='pending_payment')$state='payment_pending';
    if($publicRequest&&($publicRequest['status']??'')==='pending_admin_review')$state='review_pending';
    if($publicRequest&&($publicRequest['status']??'')==='approved')$state='change_scheduled';
    if(!empty($subscription['cancel_at_period_end']))$state='cancel_pending';
    mg_ok([
        'request'=>$publicRequest,
        'package'=>[
            'package_id'=>(string)($context['package_id']??'free'),
            'package_name'=>(string)($context['package_name']??'Free Wallet'),
            'status'=>(string)($context['status']??'free'),
            'merchant_access'=>!empty($context['merchant_access']),
            'is_paid'=>!empty($context['is_paid']),
            'is_complimentary'=>!empty($context['is_complimentary']),
            'entitlement_source'=>(string)($context['entitlement_source']??'free_wallet'),
            'workspace_role'=>$context['workspace_role']??null,
            'billing_cycle'=>(string)($subscription['billing_cycle']??$context['billing_cycle']??'month'),
            'current_period_start'=>$subscription['current_period_start']??null,
            'current_period_end'=>$subscription['current_period_end']??null,
            'next_billing_at'=>$subscription['next_billing_at']??null,
            'cancel_at_period_end'=>!empty($subscription['cancel_at_period_end']),
            'scheduled_package_id'=>$subscription['scheduled_package_id']??null,
            'scheduled_billing_cycle'=>$subscription['scheduled_billing_cycle']??null,
            'scheduled_effective_at'=>$subscription['scheduled_effective_at']??null,
            'portal_available'=>(string)($subscription['provider_key']??'')==='stripe'&&trim((string)($subscription['provider_customer_id']??''))!=='',
            'latest_invoice_status'=>$subscription['provider_latest_invoice_status']??null,
            'latest_invoice_url'=>$subscription['provider_latest_invoice_url']??null,
        ],
        'activation'=>[
            'state'=>$state,
            'workspace_access'=>!empty($context['merchant_access']),
        ],
    ],'Package change status loaded.');
}catch(Throwable $e){
    mg_security_log('error','subscription.package_change_status_failed','Subscription package change status failed.',['exception'=>$e->getMessage()],(int)($user['id']??0));
    mg_fail('Unable to load package change status.',500);
}
