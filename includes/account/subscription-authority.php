<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/package-entitlements.php';

if(!function_exists('mg_subscription_view_free_plan')){
function mg_subscription_view_free_plan(): array
{
    return [
        'id'=>'free','name'=>'Free Wallet','price_label'=>'$0','billing_label'=>'',
        'description'=>'A secure wallet for buying, sending, claiming, and tracking local gifts and rewards.',
        'included_features'=>['Gift Inbox','Sent and Claimed tracking','Purchase and send gifts','Claim and regift eligible value','Personal gifting tools'],
        'excluded_features'=>['Merchant campaigns and CRM','Products and business locations','Merchant automation and team tools'],
        'limits'=>['max_active_campaigns'=>0,'max_rewards'=>0,'monthly_stamps_included'=>0],
        'featured'=>false,'fit'=>'Included with every Microgifter account.',
    ];
}
}

if(!function_exists('mg_subscription_view_plan_by_id')){
function mg_subscription_view_plan_by_id(array $plans,string $planId,?array $fallback=null): array
{
    $planId=strtolower(trim($planId));
    if($planId==='free')return mg_subscription_view_free_plan();
    foreach($plans as $plan){
        $id=strtolower(trim((string)($plan['id']??$plan['name']??'')));
        if($id===$planId)return $plan;
    }
    return $fallback??mg_subscription_view_free_plan();
}
}

if(!function_exists('mg_subscription_view_known_plan_id')){
function mg_subscription_view_known_plan_id(mixed $value,array $plans,string $fallback='free'): string
{
    $value=strtolower(trim((string)$value));
    $value=trim(preg_replace('/[^a-z0-9]+/','-',$value)?:'','-');
    if($value==='free'||$value==='free-wallet')return 'free';
    foreach($plans as $plan){
        $id=trim(preg_replace('/[^a-z0-9]+/','-',strtolower((string)($plan['id']??'')))?:'','-');
        $name=trim(preg_replace('/[^a-z0-9]+/','-',strtolower((string)($plan['name']??'')))?:'','-');
        if($value===$id||$value===$name)return $id!==''?$id:$name;
    }
    return $fallback;
}
}

if(!function_exists('mg_subscription_view_load_platform_subscription')){
function mg_subscription_view_load_platform_subscription(?PDO $pdo,int $userId,array $plans): array
{
    $user=$GLOBALS['user']??null;
    $context=mg_user_package_context($pdo,is_array($user)?$user:null);
    $subscription=is_array($context['subscription']??null)?$context['subscription']:[];
    $packageId=(string)($context['package_id']??'free');
    $isFree=empty($context['merchant_access'])||$packageId==='free';
    $isComplimentary=!empty($context['is_complimentary']);
    if($isFree){
        return [
            'subscription_id'=>null,'package_id'=>'free','status'=>'Active','billing_cycle'=>'No billing','billing_cycle_key'=>'free',
            'renews_on'=>'No renewal','current_period_end'=>null,'next_charge_label'=>'No charge','source'=>'free_wallet',
            'merchant_access'=>false,'is_complimentary'=>false,'provider_key'=>null,'portal_available'=>false,
            'cancel_at_period_end'=>false,'scheduled_package_id'=>null,'scheduled_billing_cycle'=>null,'scheduled_effective_at'=>null,
            'latest_invoice_url'=>null,'latest_invoice_pdf'=>null,'latest_invoice_status'=>null,
        ];
    }
    $periodEnd=trim((string)($subscription['current_period_end']??''));
    $billingCycle=(string)($context['billing_cycle']??$subscription['billing_cycle']??'month');
    $cycleKey=in_array(strtolower($billingCycle),['year','yearly'],true)?'year':'month';
    $cancelPending=!empty($subscription['cancel_at_period_end'])||(string)($context['status']??'')==='cancel_pending';
    $renews=$periodEnd!==''&&strtotime($periodEnd)!==false?date('M j, Y',strtotime($periodEnd)):($isComplimentary?'No expiration':'Pending provider sync');
    $status=$isComplimentary?'Complimentary':ucwords(str_replace('_',' ',(string)($context['status']??'active')));
    if($cancelPending&&!$isComplimentary)$status='Cancel Pending';
    $providerKey=trim((string)($subscription['provider_key']??''));
    return [
        'subscription_id'=>$subscription['public_id']??null,
        'package_id'=>$packageId,
        'status'=>$status,
        'billing_cycle'=>$isComplimentary?'Admin grant':($cycleKey==='year'?'Yearly':'Monthly'),
        'billing_cycle_key'=>$isComplimentary?'complimentary':$cycleKey,
        'renews_on'=>$renews,
        'current_period_end'=>$periodEnd!==''?$periodEnd:null,
        'next_charge_label'=>$isComplimentary?'No charge':'$'.number_format(((int)($context['amount_cents']??$subscription['amount_cents']??0))/100,2),
        'source'=>(string)($context['entitlement_source']??'platform_account_subscriptions'),
        'merchant_access'=>true,
        'is_complimentary'=>$isComplimentary,
        'provider_key'=>$providerKey!==''?$providerKey:null,
        'portal_available'=>$providerKey==='stripe'&&trim((string)($subscription['provider_customer_id']??''))!=='',
        'cancel_at_period_end'=>$cancelPending,
        'scheduled_package_id'=>$subscription['scheduled_package_id']??null,
        'scheduled_billing_cycle'=>$subscription['scheduled_billing_cycle']??null,
        'scheduled_effective_at'=>$subscription['scheduled_effective_at']??null,
        'latest_invoice_url'=>$subscription['provider_latest_invoice_url']??null,
        'latest_invoice_pdf'=>$subscription['provider_latest_invoice_pdf']??null,
        'latest_invoice_status'=>$subscription['provider_latest_invoice_status']??null,
    ];
}
}

if(!function_exists('mg_subscription_view_usage')){
function mg_subscription_view_usage(?PDO $pdo,int $userId,array $currentPlan): array
{
    $usage=['promotions_used'=>0,'promotions_limit'=>max(0,(int)($currentPlan['limits']['max_active_campaigns']??0)),'rewards_distributed'=>0,'customer_engagements'=>0,'revenue_cents'=>0,'stamps_available'=>null,'stamps_used'=>null,'data_source'=>'empty'];
    if(!$pdo||$userId<1)return $usage;
    try{
        if(function_exists('mg_subscription_view_table_exists')&&mg_subscription_view_table_exists($pdo,'account_stamp_balances')){
            $stmt=$pdo->prepare('SELECT balance,used_stamps FROM account_stamp_balances WHERE account_user_id=? ORDER BY current_period_key DESC,updated_at DESC LIMIT 1');
            $stmt->execute([$userId]);
            $row=$stmt->fetch(PDO::FETCH_ASSOC);
            if($row){$usage['stamps_available']=(int)($row['balance']??0);$usage['stamps_used']=(int)($row['used_stamps']??0);$usage['customer_engagements']=(int)($row['used_stamps']??0);$usage['data_source']='database';}
        }
    }catch(Throwable){}
    try{$stmt=$pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND status='active'");$stmt->execute([$userId]);$usage['promotions_used']=(int)$stmt->fetchColumn();if($usage['promotions_used']>0)$usage['data_source']='database';}catch(Throwable){}
    try{$stmt=$pdo->prepare("SELECT COUNT(*) FROM microgift_instances WHERE (merchant_user_id=? OR sender_user_id=?) AND status IN ('issued','delivered','claim_pending','claimed','redeemable','redeemed')");$stmt->execute([$userId,$userId]);$usage['rewards_distributed']=(int)$stmt->fetchColumn();if($usage['rewards_distributed']>0)$usage['data_source']='database';}catch(Throwable){}
    try{$stmt=$pdo->prepare("SELECT COALESCE(SUM(total_cents),0) FROM commerce_orders WHERE merchant_user_id=? AND payment_status IN ('paid','partially_refunded')");$stmt->execute([$userId]);$usage['revenue_cents']=(int)$stmt->fetchColumn();if($usage['revenue_cents']>0)$usage['data_source']='database';}catch(Throwable){}
    return $usage;
}
}
