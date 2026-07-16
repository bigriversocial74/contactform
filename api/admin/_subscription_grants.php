<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once dirname(__DIR__) . '/subscriptions/_package_billing.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-provisioning.php';

final class MgAdminSubscriptionGrantException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus=422){parent::__construct($message);}
}

function mg_admin_subscription_grant_packages(PDO $pdo): array
{
    mg_platform_package_sync_defaults($pdo);
    $rows=$pdo->query("SELECT package_id,name,billing_cycle,monthly_amount_cents,yearly_amount_cents,currency,requires_admin_review FROM platform_subscription_packages WHERE status='active' ORDER BY FIELD(package_id,'starter','growth','pro','enterprise'),name")->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row):array=>[
        'package_id'=>(string)$row['package_id'],'name'=>(string)$row['name'],'billing_cycle'=>(string)$row['billing_cycle'],
        'monthly_amount_cents'=>(int)$row['monthly_amount_cents'],'yearly_amount_cents'=>(int)$row['yearly_amount_cents'],
        'currency'=>(string)$row['currency'],'requires_admin_review'=>(bool)$row['requires_admin_review'],
    ],$rows);
}

function mg_admin_subscription_grant_current(PDO $pdo,int $userId): ?array
{
    $stmt=$pdo->prepare('SELECT s.*,p.name package_name FROM platform_account_subscriptions s LEFT JOIN platform_subscription_packages p ON p.package_id=s.package_id WHERE s.user_id=? LIMIT 1');
    $stmt->execute([$userId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)return null;
    $meta=mg_platform_package_json($row['metadata_json']??null);
    return [
        'subscription_id'=>(string)($row['public_id']??''),'package_id'=>(string)($row['package_id']??''),
        'package_name'=>(string)($row['package_name']??$row['package_id']??''),'status'=>(string)($row['status']??''),
        'provider_key'=>(string)($row['provider_key']??''),'billing_cycle'=>(string)($row['billing_cycle']??''),
        'amount_cents'=>(int)($row['amount_cents']??0),'currency'=>(string)($row['currency']??'USD'),
        'current_period_start'=>$row['current_period_start']??null,'current_period_end'=>$row['current_period_end']??null,
        'cancel_at_period_end'=>(bool)($row['cancel_at_period_end']??false),
        'is_complimentary'=>(string)($row['provider_key']??'')==='admin_grant'||!empty($meta['complimentary']),
        'grant_reason'=>$meta['grant_reason']??null,
        'granted_by_user_id'=>isset($meta['granted_by_user_id'])?(int)$meta['granted_by_user_id']:null,
    ];
}

function mg_admin_subscription_grant_history(PDO $pdo,int $userId): array
{
    $stmt=$pdo->prepare('SELECT g.public_id,g.package_id,g.status,g.starts_at,g.ends_at,g.reason,g.granted_by_user_id,g.revoked_by_user_id,g.revoked_at,g.created_at,g.updated_at,p.name package_name FROM platform_complimentary_subscription_grants g LEFT JOIN platform_subscription_packages p ON p.package_id=g.package_id WHERE g.user_id=? ORDER BY g.created_at DESC,g.id DESC LIMIT 25');
    $stmt->execute([$userId]);
    return array_map(static fn(array $row):array=>[
        'grant_id'=>(string)$row['public_id'],'package_id'=>(string)$row['package_id'],'package_name'=>(string)($row['package_name']??$row['package_id']),
        'status'=>(string)$row['status'],'starts_at'=>(string)$row['starts_at'],'ends_at'=>$row['ends_at']!==null?(string)$row['ends_at']:null,
        'reason'=>(string)$row['reason'],'granted_by_user_id'=>(int)$row['granted_by_user_id'],
        'revoked_by_user_id'=>$row['revoked_by_user_id']!==null?(int)$row['revoked_by_user_id']:null,
        'revoked_at'=>$row['revoked_at']!==null?(string)$row['revoked_at']:null,'created_at'=>(string)$row['created_at'],'updated_at'=>(string)$row['updated_at'],
    ],$stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_admin_subscription_grant_snapshot(PDO $pdo,int $userId): array
{
    $user=mg_admin_user_detail_read($pdo,$userId);
    if(!$user)throw new MgAdminSubscriptionGrantException('User not found.',404);
    return [
        'user'=>['id'=>(int)$user['id'],'email'=>(string)$user['email'],'display_name'=>(string)($user['display_name']??$user['full_name']??$user['email']),'roles'=>array_values(array_map(static fn(array $role):string=>(string)$role['slug'],$user['roles']??[]))],
        'packages'=>mg_admin_subscription_grant_packages($pdo),
        'current_subscription'=>mg_admin_subscription_grant_current($pdo,$userId),
        'grant_history'=>mg_admin_subscription_grant_history($pdo,$userId),
    ];
}

function mg_admin_subscription_grant_end_date(string $term,?string $customEnd): ?string
{
    $term=strtolower(trim($term));
    $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    return match($term){
        '30_days'=>$now->modify('+30 days')->format('Y-m-d H:i:s'),
        '90_days'=>$now->modify('+90 days')->format('Y-m-d H:i:s'),
        '1_year'=>$now->modify('+1 year')->format('Y-m-d H:i:s'),
        'permanent'=>null,
        'custom'=>(function()use($customEnd,$now):string{
            $value=trim((string)$customEnd);
            if($value==='')throw new MgAdminSubscriptionGrantException('Choose an expiration date for a custom grant.');
            try{$end=new DateTimeImmutable($value,new DateTimeZone('UTC'));}catch(Throwable){throw new MgAdminSubscriptionGrantException('The custom expiration date is invalid.');}
            if($end<=$now)throw new MgAdminSubscriptionGrantException('The custom expiration date must be in the future.');
            return $end->format('Y-m-d H:i:s');
        })(),
        default=>throw new MgAdminSubscriptionGrantException('Choose a valid complimentary subscription term.'),
    };
}

function mg_admin_subscription_grant_apply(PDO $pdo,array $actor,int $targetUserId,string $packageId,string $term,?string $customEnd,string $reason): array
{
    $target=mg_admin_account_target($pdo,$targetUserId,true);
    mg_admin_account_assert_target_access($actor,$target);
    $reason=mg_admin_account_reason($reason);
    $packageId=mg_platform_package_slug($packageId);
    $package=mg_platform_package_get($pdo,$packageId);
    if(!$package)throw new MgAdminSubscriptionGrantException('Selected subscription package is unavailable.',404);
    if($packageId==='free')throw new MgAdminSubscriptionGrantException('Use revoke to return an account to the Free Wallet.');
    $endsAt=mg_admin_subscription_grant_end_date($term,$customEnd);
    $existing=mg_platform_account_subscription_snapshot($pdo,$targetUserId,true);
    if($existing&&in_array((string)$existing['status'],['active','trialing','cancel_pending','past_due'],true)){
        $provider=strtolower(trim((string)($existing['provider_key']??'')));
        if($provider!==''&&$provider!=='admin_grant')throw new MgAdminSubscriptionGrantException('This account already has an active provider-backed subscription. Cancel or expire it before applying a complimentary grant.',409);
    }

    $pdo->prepare("UPDATE platform_complimentary_subscription_grants SET status='replaced',revoked_by_user_id=?,revoked_at=NOW(),updated_at=NOW() WHERE user_id=? AND status='active'")->execute([(int)$actor['id'],$targetUserId]);
    $grantId=mg_public_uuid();
    $startsAt=gmdate('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO platform_complimentary_subscription_grants (public_id,user_id,package_id,status,starts_at,ends_at,reason,granted_by_user_id,created_at,updated_at) VALUES (?,?,?,'active',?,?,?,?,NOW(),NOW())")
        ->execute([$grantId,$targetUserId,$packageId,$startsAt,$endsAt,$reason,(int)$actor['id']]);

    $metadata=['source'=>'admin_complimentary_subscription','complimentary'=>true,'grant_id'=>$grantId,'grant_reason'=>$reason,'granted_by_user_id'=>(int)$actor['id'],'granted_at'=>gmdate('c'),'expires_at'=>$endsAt];
    $billingCycle=$endsAt===null?'year':'month';
    if($existing){
        $pdo->prepare("UPDATE platform_account_subscriptions SET package_id=?,billing_cycle=?,status='active',amount_cents=0,currency=?,provider_key='admin_grant',provider_customer_id=NULL,provider_subscription_id=NULL,provider_session_reference=NULL,provider_price_id=NULL,current_period_start=?,current_period_end=?,next_billing_at=NULL,cancel_at_period_end=0,package_change_request_public_id=NULL,metadata_json=?,activated_at=COALESCE(activated_at,NOW()),canceled_at=NULL,updated_at=NOW() WHERE id=?")
            ->execute([$packageId,$billingCycle,strtoupper((string)($package['currency']??'USD')),$startsAt,$endsAt,json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),(int)$existing['id']]);
        $accountSubscriptionId=(int)$existing['id'];
        $fromStatus=(string)$existing['status'];
    }else{
        $subscriptionId=mg_public_uuid();
        $pdo->prepare("INSERT INTO platform_account_subscriptions (public_id,user_id,package_id,billing_cycle,status,amount_cents,currency,provider_key,current_period_start,current_period_end,next_billing_at,cancel_at_period_end,metadata_json,activated_at,created_at,updated_at) VALUES (?,?,?,?,'active',0,?,'admin_grant',?,?,NULL,0,?,NOW(),NOW(),NOW())")
            ->execute([$subscriptionId,$targetUserId,$packageId,$billingCycle,strtoupper((string)($package['currency']??'USD')),$startsAt,$endsAt,json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        $accountSubscriptionId=(int)$pdo->lastInsertId();
        $fromStatus=null;
    }

    mg_platform_account_subscription_grant_merchant_role($pdo,$targetUserId);
    mg_subscription_provision_merchant_workspace($pdo,$targetUserId,(string)($target['display_name']??$target['full_name']??$target['email']),'admin_complimentary_subscription');
    mg_platform_account_subscription_event($pdo,$accountSubscriptionId,'platform_subscription.complimentary_granted',$fromStatus,'active',(int)$actor['id'],$metadata);
    return ['grant_id'=>$grantId,'package_id'=>$packageId,'status'=>'active','starts_at'=>$startsAt,'ends_at'=>$endsAt,'reason'=>$reason];
}

function mg_admin_subscription_grant_revoke(PDO $pdo,array $actor,int $targetUserId,string $reason): array
{
    $target=mg_admin_account_target($pdo,$targetUserId,true);
    mg_admin_account_assert_target_access($actor,$target);
    $reason=mg_admin_account_reason($reason);
    $existing=mg_platform_account_subscription_snapshot($pdo,$targetUserId,true);
    if(!$existing||strtolower((string)($existing['provider_key']??''))!=='admin_grant')throw new MgAdminSubscriptionGrantException('This account does not have an active complimentary subscription.',409);
    if(!in_array((string)$existing['status'],['active','trialing','cancel_pending','past_due'],true))throw new MgAdminSubscriptionGrantException('The complimentary subscription is already inactive.',409);
    $metadata=mg_platform_package_json($existing['metadata_json']??null);
    $metadata['revoked_reason']=$reason;$metadata['revoked_by_user_id']=(int)$actor['id'];$metadata['revoked_at']=gmdate('c');
    $pdo->prepare("UPDATE platform_account_subscriptions SET status='canceled',cancel_at_period_end=0,current_period_end=NOW(),next_billing_at=NULL,canceled_at=NOW(),metadata_json=?,updated_at=NOW() WHERE id=?")
        ->execute([json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),(int)$existing['id']]);
    $pdo->prepare("UPDATE platform_complimentary_subscription_grants SET status='revoked',revoked_by_user_id=?,revoked_at=NOW(),updated_at=NOW() WHERE user_id=? AND status='active'")->execute([(int)$actor['id'],$targetUserId]);
    mg_platform_account_subscription_event($pdo,(int)$existing['id'],'platform_subscription.complimentary_revoked',(string)$existing['status'],'canceled',(int)$actor['id'],['reason'=>$reason,'provider_key'=>'admin_grant']);
    return ['subscription_id'=>(string)$existing['public_id'],'package_id'=>(string)$existing['package_id'],'status'=>'canceled','reason'=>$reason,'role_assignments_preserved'=>true];
}
