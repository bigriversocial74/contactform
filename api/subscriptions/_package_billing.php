<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/pricing-packages.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-provisioning.php';

function mg_platform_package_billing_schema(PDO $pdo): void { /* ordered migrations own schema */ }
function mg_platform_package_slug(mixed $value): string { $value=strtolower(trim((string)$value)); $value=preg_replace('/[^a-z0-9]+/','-',$value)?:''; return trim($value,'-'); }
function mg_platform_package_json(mixed $value): array { if(is_array($value))return $value; if(!is_string($value)||trim($value)==='')return []; $decoded=json_decode($value,true); return is_array($decoded)?$decoded:[]; }
function mg_platform_package_default_monthly_amount(string $packageId): int { return match(mg_platform_package_slug($packageId)){ 'starter'=>2900,'growth'=>7900,'pro'=>19900,'enterprise'=>49900,default=>0 }; }
function mg_platform_package_yearly_amount(int $monthlyAmountCents): int { return $monthlyAmountCents>0?(int)round($monthlyAmountCents*12*.8):0; }
function mg_platform_package_interval_unit(string $billingCycle): string { return in_array(strtolower(trim($billingCycle)),['year','yearly','annual','annually'],true)?'year':'month'; }
function mg_platform_package_env_key(string $packageId,string $type,string $mode,?string $cycle=null): string
{
    $parts=['MG','STRIPE',strtoupper($type),strtoupper(str_replace('-','_',mg_platform_package_slug($packageId)))];
    if($cycle!==null&&$cycle!=='')$parts[]=strtoupper(mg_platform_package_interval_unit($cycle));
    $parts[]=strtoupper($mode);
    return implode('_',$parts);
}

function mg_platform_package_default_catalog(): array
{
    $catalog=[];
    foreach(mg_pricing_packages() as $plan){
        $packageId=mg_platform_package_slug((string)($plan['id']??$plan['name']??''));
        if($packageId==='')continue;
        $monthly=isset($plan['monthly_amount_cents'])?(int)$plan['monthly_amount_cents']:mg_platform_package_default_monthly_amount($packageId);
        $yearly=isset($plan['yearly_amount_cents'])?(int)$plan['yearly_amount_cents']:mg_platform_package_yearly_amount($monthly);
        $legacyTest=trim((string)($plan['stripe_price_id_test']??getenv(mg_platform_package_env_key($packageId,'PRICE','TEST'))?:''));
        $legacyLive=trim((string)($plan['stripe_price_id_live']??getenv(mg_platform_package_env_key($packageId,'PRICE','LIVE'))?:''));
        $catalog[$packageId]=[
            'package_id'=>$packageId,
            'name'=>(string)($plan['name']??ucwords(str_replace('-',' ',$packageId))),
            'billing_cycle'=>'month',
            'monthly_amount_cents'=>$monthly,
            'yearly_amount_cents'=>$yearly,
            'currency'=>strtoupper((string)($plan['currency']??'USD')),
            'stripe_price_id_test'=>$legacyTest,
            'stripe_price_id_live'=>$legacyLive,
            'stripe_monthly_price_id_test'=>trim((string)($plan['stripe_monthly_price_id_test']??getenv(mg_platform_package_env_key($packageId,'PRICE','TEST','month'))?:$legacyTest)),
            'stripe_monthly_price_id_live'=>trim((string)($plan['stripe_monthly_price_id_live']??getenv(mg_platform_package_env_key($packageId,'PRICE','LIVE','month'))?:$legacyLive)),
            'stripe_yearly_price_id_test'=>trim((string)($plan['stripe_yearly_price_id_test']??getenv(mg_platform_package_env_key($packageId,'PRICE','TEST','year'))?:'')),
            'stripe_yearly_price_id_live'=>trim((string)($plan['stripe_yearly_price_id_live']??getenv(mg_platform_package_env_key($packageId,'PRICE','LIVE','year'))?:'')),
            'stripe_product_id_test'=>trim((string)($plan['stripe_product_id_test']??getenv(mg_platform_package_env_key($packageId,'PRODUCT','TEST'))?:'')),
            'stripe_product_id_live'=>trim((string)($plan['stripe_product_id_live']??getenv(mg_platform_package_env_key($packageId,'PRODUCT','LIVE'))?:'')),
            'is_self_serve'=>$packageId==='enterprise'?0:1,
            'requires_admin_review'=>$packageId==='enterprise'?1:0,
            'features'=>array_values((array)($plan['included_features']??[])),
            'limits'=>is_array($plan['limits']??null)?$plan['limits']:[],
            'metadata'=>['pricing_source'=>'includes/pricing-packages.php','price_label'=>(string)($plan['price_label']??''),'billing_label'=>(string)($plan['billing_label']??''),'sort_order'=>(int)($plan['sort_order']??0)],
        ];
    }
    return $catalog;
}

function mg_platform_package_sync_defaults(PDO $pdo): void
{
    $sql="INSERT INTO platform_subscription_packages
      (package_id,name,billing_cycle,monthly_amount_cents,yearly_amount_cents,currency,
       stripe_price_id_test,stripe_price_id_live,stripe_monthly_price_id_test,stripe_monthly_price_id_live,
       stripe_yearly_price_id_test,stripe_yearly_price_id_live,stripe_product_id_test,stripe_product_id_live,
       is_self_serve,requires_admin_review,features_json,limits_json,metadata_json,status,created_at,updated_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active',NOW(),NOW())
      ON DUPLICATE KEY UPDATE
       name=VALUES(name),monthly_amount_cents=VALUES(monthly_amount_cents),yearly_amount_cents=VALUES(yearly_amount_cents),currency=VALUES(currency),
       stripe_price_id_test=COALESCE(NULLIF(platform_subscription_packages.stripe_price_id_test,''),VALUES(stripe_price_id_test)),
       stripe_price_id_live=COALESCE(NULLIF(platform_subscription_packages.stripe_price_id_live,''),VALUES(stripe_price_id_live)),
       stripe_monthly_price_id_test=COALESCE(NULLIF(platform_subscription_packages.stripe_monthly_price_id_test,''),VALUES(stripe_monthly_price_id_test)),
       stripe_monthly_price_id_live=COALESCE(NULLIF(platform_subscription_packages.stripe_monthly_price_id_live,''),VALUES(stripe_monthly_price_id_live)),
       stripe_yearly_price_id_test=COALESCE(NULLIF(platform_subscription_packages.stripe_yearly_price_id_test,''),VALUES(stripe_yearly_price_id_test)),
       stripe_yearly_price_id_live=COALESCE(NULLIF(platform_subscription_packages.stripe_yearly_price_id_live,''),VALUES(stripe_yearly_price_id_live)),
       stripe_product_id_test=COALESCE(NULLIF(platform_subscription_packages.stripe_product_id_test,''),VALUES(stripe_product_id_test)),
       stripe_product_id_live=COALESCE(NULLIF(platform_subscription_packages.stripe_product_id_live,''),VALUES(stripe_product_id_live)),
       is_self_serve=VALUES(is_self_serve),requires_admin_review=VALUES(requires_admin_review),features_json=VALUES(features_json),limits_json=VALUES(limits_json),metadata_json=VALUES(metadata_json),status='active',updated_at=NOW()";
    $stmt=$pdo->prepare($sql);
    foreach(mg_platform_package_default_catalog() as $package){
        $stmt->execute([
            $package['package_id'],$package['name'],$package['billing_cycle'],$package['monthly_amount_cents'],$package['yearly_amount_cents'],$package['currency'],
            $package['stripe_price_id_test']?:null,$package['stripe_price_id_live']?:null,
            $package['stripe_monthly_price_id_test']?:null,$package['stripe_monthly_price_id_live']?:null,
            $package['stripe_yearly_price_id_test']?:null,$package['stripe_yearly_price_id_live']?:null,
            $package['stripe_product_id_test']?:null,$package['stripe_product_id_live']?:null,
            (int)$package['is_self_serve'],(int)$package['requires_admin_review'],
            json_encode($package['features'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            json_encode($package['limits'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            json_encode($package['metadata'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        ]);
    }
}

function mg_platform_package_get(PDO $pdo,string $packageId): ?array
{
    mg_platform_package_sync_defaults($pdo);
    $stmt=$pdo->prepare("SELECT * FROM platform_subscription_packages WHERE package_id=? AND status='active' LIMIT 1");
    $stmt->execute([mg_platform_package_slug($packageId)]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}
function mg_platform_package_amount_cents(array $package,string $billingCycle='month'): int { $cycle=mg_platform_package_interval_unit($billingCycle); return $cycle==='year'?(int)($package['yearly_amount_cents']??0):(int)($package['monthly_amount_cents']??0); }
function mg_platform_package_stripe_product_id(array $package,?string $mode=null): string { $mode=$mode?: (function_exists('mg_payment_mode')?mg_payment_mode():'test'); return trim((string)($package[$mode==='live'?'stripe_product_id_live':'stripe_product_id_test']??'')); }
function mg_platform_package_stripe_price_id(array $package,string $billingCycle='month',?string $mode=null): string
{
    $mode=$mode?: (function_exists('mg_payment_mode')?mg_payment_mode():'test');
    $cycle=mg_platform_package_interval_unit($billingCycle);
    $field='stripe_'.$cycle.'ly_price_id_'.$mode;
    $value=trim((string)($package[$field]??''));
    if($value!==''||$cycle==='year')return $value;
    return trim((string)($package[$mode==='live'?'stripe_price_id_live':'stripe_price_id_test']??''));
}
function mg_platform_package_find_by_price_id(PDO $pdo,string $priceId): ?array
{
    $priceId=trim($priceId); if($priceId==='')return null;
    mg_platform_package_sync_defaults($pdo);
    $stmt=$pdo->prepare("SELECT * FROM platform_subscription_packages WHERE status='active' AND ? IN (stripe_price_id_test,stripe_price_id_live,stripe_monthly_price_id_test,stripe_monthly_price_id_live,stripe_yearly_price_id_test,stripe_yearly_price_id_live) LIMIT 1");
    $stmt->execute([$priceId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}
function mg_platform_package_cycle_for_price_id(array $package,string $priceId): string
{
    $priceId=trim($priceId);
    if($priceId!==''&&in_array($priceId,[trim((string)($package['stripe_yearly_price_id_test']??'')),trim((string)($package['stripe_yearly_price_id_live']??''))],true))return 'year';
    return 'month';
}

function mg_platform_account_subscription_grant_merchant_role(PDO $pdo,int $userId): void
{
    if($userId<1)return;
    try{$pdo->prepare("INSERT IGNORE INTO user_roles (user_id,role_id,created_at) SELECT ?,id,NOW() FROM roles WHERE slug='merchant'")->execute([$userId]);}
    catch(Throwable $error){if(function_exists('mg_security_log'))mg_security_log('warning','platform_subscription.role_grant_failed','Unable to grant merchant role for active platform package.',['exception'=>get_class($error)],$userId);}
}
function mg_platform_account_subscription_event(PDO $pdo,int $accountSubscriptionId,string $eventType,?string $fromStatus,?string $toStatus,?int $actorUserId,array $payload=[]): void
{
    try{$pdo->prepare('INSERT INTO platform_subscription_events (public_id,account_subscription_id,event_type,from_status,to_status,actor_user_id,provider_key,provider_event_id,payload_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())')->execute([mg_public_uuid(),$accountSubscriptionId,$eventType,$fromStatus,$toStatus,$actorUserId,$payload['provider_key']??null,$payload['provider_event_id']??null,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);}
    catch(Throwable $error){if(function_exists('mg_security_log'))mg_security_log('warning','platform_subscription.event_failed','Platform subscription event write failed.',['exception'=>$error->getMessage()],$actorUserId);}
}
function mg_platform_account_subscription_snapshot(PDO $pdo,int $userId,bool $forUpdate=false): ?array
{
    if($userId<1)return null;
    $stmt=$pdo->prepare('SELECT s.*,p.name package_name,p.monthly_amount_cents,p.yearly_amount_cents,p.currency package_currency,p.is_self_serve,p.requires_admin_review FROM platform_account_subscriptions s LEFT JOIN platform_subscription_packages p ON p.package_id=s.package_id WHERE s.user_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':''));
    $stmt->execute([$userId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}
function mg_platform_account_subscription_upsert(PDO $pdo,array $requestRow,array $package,array $providerRefs=[]): array
{
    $userId=(int)($requestRow['user_id']??0); $packageId=mg_platform_package_slug($requestRow['requested_package_id']??$package['package_id']??'');
    if($userId<1||$packageId==='')throw new RuntimeException('Platform subscription user and package are required.');
    $billingCycle=mg_platform_package_interval_unit((string)($requestRow['billing_cycle']??$package['billing_cycle']??'month'));
    $providerKey=trim((string)($providerRefs['provider_key']??'stripe'))?:'stripe';
    $status=(string)($providerRefs['status']??'active'); if(!in_array($status,['incomplete','pending_admin_review','active','trialing','past_due','paused','cancel_pending','canceled','expired'],true))$status='active';
    $amount=(int)($requestRow['amount_cents']??0); if($amount<1&&$providerKey!=='admin_grant')$amount=mg_platform_package_amount_cents($package,$billingCycle);
    $currency=strtoupper((string)($requestRow['currency']??$package['currency']??'USD'));
    $start=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $end=$billingCycle==='year'?$start->modify('+1 year'):$start->modify('+1 month');
    $periodStart=trim((string)($providerRefs['current_period_start']??''))?:$start->format('Y-m-d H:i:s');
    $periodEnd=trim((string)($providerRefs['current_period_end']??''))?:$end->format('Y-m-d H:i:s');
    $providerPriceId=trim((string)($providerRefs['provider_price_id']??mg_platform_package_stripe_price_id($package,$billingCycle)))?:null;
    $cancelAtPeriodEnd=!empty($providerRefs['cancel_at_period_end'])?1:0; if($cancelAtPeriodEnd&&in_array($status,['active','trialing'],true))$status='cancel_pending';
    $metadata=['source'=>'subscription_package_change','package_change_request_id'=>(string)($requestRow['public_id']??''),'checkout_session_id'=>$providerRefs['provider_session_reference']??null,'stripe_subscription_id'=>$providerRefs['provider_subscription_id']??null,'stripe_customer_id'=>$providerRefs['provider_customer_id']??null,'completed_at'=>gmdate('c')];
    $existing=mg_platform_account_subscription_snapshot($pdo,$userId,true);
    if($existing){
        $stmt=$pdo->prepare("UPDATE platform_account_subscriptions SET package_id=?,billing_cycle=?,status=?,amount_cents=?,currency=?,provider_key=?,provider_customer_id=COALESCE(NULLIF(?,''),provider_customer_id),provider_subscription_id=COALESCE(NULLIF(?,''),provider_subscription_id),provider_session_reference=COALESCE(NULLIF(?,''),provider_session_reference),provider_price_id=COALESCE(?,provider_price_id),current_period_start=?,current_period_end=?,next_billing_at=CASE WHEN ? IN ('canceled','expired','paused') THEN NULL ELSE ? END,cancel_at_period_end=?,scheduled_package_id=NULL,scheduled_billing_cycle=NULL,scheduled_effective_at=NULL,package_change_request_public_id=?,metadata_json=?,activated_at=COALESCE(activated_at,NOW()),canceled_at=CASE WHEN ?='canceled' THEN COALESCE(canceled_at,NOW()) ELSE NULL END,updated_at=NOW() WHERE id=?");
        $stmt->execute([$packageId,$billingCycle,$status,$amount,$currency,$providerKey,(string)($providerRefs['provider_customer_id']??''),(string)($providerRefs['provider_subscription_id']??''),(string)($providerRefs['provider_session_reference']??''),$providerPriceId,$periodStart,$periodEnd,$status,$periodEnd,$cancelAtPeriodEnd,(string)($requestRow['public_id']??''),json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$status,(int)$existing['id']]);
        $subscriptionId=(int)$existing['id']; mg_platform_account_subscription_event($pdo,$subscriptionId,'platform_subscription.package_changed',(string)$existing['status'],$status,$userId,$metadata+['provider_key'=>$providerKey]);
    }else{
        $publicId=mg_public_uuid();
        $stmt=$pdo->prepare("INSERT INTO platform_account_subscriptions (public_id,user_id,package_id,billing_cycle,status,amount_cents,currency,provider_key,provider_customer_id,provider_subscription_id,provider_session_reference,provider_price_id,current_period_start,current_period_end,next_billing_at,cancel_at_period_end,package_change_request_public_id,metadata_json,activated_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())");
        $stmt->execute([$publicId,$userId,$packageId,$billingCycle,$status,$amount,$currency,$providerKey,trim((string)($providerRefs['provider_customer_id']??''))?:null,trim((string)($providerRefs['provider_subscription_id']??''))?:null,trim((string)($providerRefs['provider_session_reference']??''))?:null,$providerPriceId,$periodStart,$periodEnd,in_array($status,['canceled','expired','paused'],true)?null:$periodEnd,$cancelAtPeriodEnd,(string)($requestRow['public_id']??''),json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        $subscriptionId=(int)$pdo->lastInsertId(); mg_platform_account_subscription_event($pdo,$subscriptionId,'platform_subscription.activated',null,$status,$userId,$metadata+['provider_key'=>$providerKey]);
    }
    if(in_array($status,['active','trialing','cancel_pending','past_due'],true)){mg_platform_account_subscription_grant_merchant_role($pdo,$userId);mg_subscription_provision_merchant_workspace($pdo,$userId,'','platform_subscription_activation');}
    $snapshot=mg_platform_account_subscription_snapshot($pdo,$userId,true); if(!$snapshot)throw new RuntimeException('Platform subscription record was not created.'); return $snapshot;
}
