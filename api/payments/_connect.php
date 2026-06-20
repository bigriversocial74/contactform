<?php
declare(strict_types=1);

require_once __DIR__ . '/_payments.php';

function mg_payment_connect_account_payload(?array $account,string $provider='stripe',?string $mode=null): array
{
    $mode=$mode??mg_payment_mode();
    if(!$account){
        return [
            'provider_key'=>$provider,
            'mode'=>$mode,
            'account_id'=>'',
            'status'=>'pending',
            'onboarding_status'=>'not_started',
            'charges_enabled'=>false,
            'payouts_enabled'=>false,
            'details_submitted'=>false,
            'requirements_due'=>[],
            'ready'=>false,
            'last_synced_at'=>null,
        ];
    }
    $requirements=[];
    try{$decoded=json_decode((string)($account['requirements_due_json']??'[]'),true,512,JSON_THROW_ON_ERROR);if(is_array($decoded))$requirements=$decoded;}catch(Throwable){}
    return [
        'provider_key'=>(string)$account['provider_key'],
        'mode'=>(string)$account['mode'],
        'account_id'=>(string)($account['provider_account_reference']??''),
        'status'=>(string)$account['status'],
        'onboarding_status'=>(string)($account['onboarding_status']??'not_started'),
        'charges_enabled'=>(bool)$account['charges_enabled'],
        'payouts_enabled'=>(bool)$account['payouts_enabled'],
        'details_submitted'=>(bool)($account['details_submitted']??false),
        'requirements_due'=>$requirements,
        'ready'=>(string)$account['status']==='active'&&(int)$account['charges_enabled']===1&&(int)$account['payouts_enabled']===1,
        'last_synced_at'=>$account['last_synced_at']??null,
    ];
}

function mg_payment_connect_sync(PDO $pdo,int $merchantUserId,array $account): array
{
    $reference=trim((string)($account['provider_account_reference']??''));
    if($reference==='')return mg_payment_connect_account_payload($account);
    $stripe=mg_stripe_retrieve_account($pdo,$reference);
    $charges=!empty($stripe['charges_enabled'])?1:0;
    $payouts=!empty($stripe['payouts_enabled'])?1:0;
    $details=!empty($stripe['details_submitted'])?1:0;
    $due=is_array($stripe['requirements']['currently_due']??null)?array_values($stripe['requirements']['currently_due']):[];
    $status=$charges&&$payouts?'active':($details?'restricted':'pending');
    $onboarding=$charges&&$payouts?'complete':($details?'restricted':'pending');
    $capabilities=is_array($stripe['capabilities']??null)?$stripe['capabilities']:[];
    $pdo->prepare('UPDATE payment_provider_accounts SET status=?,charges_enabled=?,payouts_enabled=?,details_submitted=?,onboarding_status=?,capabilities_json=?,requirements_due_json=?,last_synced_at=NOW(),updated_at=NOW() WHERE id=? AND merchant_user_id=?')
        ->execute([$status,$charges,$payouts,$details,$onboarding,json_encode($capabilities,JSON_THROW_ON_ERROR),json_encode($due,JSON_THROW_ON_ERROR),(int)$account['id'],$merchantUserId]);
    $updated=mg_payment_provider_account($pdo,$merchantUserId,'stripe',mg_payment_mode(),false);
    return mg_payment_connect_account_payload($updated);
}

function mg_payment_connect_start(PDO $pdo,int $merchantUserId): array
{
    $config=mg_payment_platform_config($pdo,'stripe',mg_payment_mode());
    if(!$config['enabled']||trim((string)$config['secret_key'])==='')throw new RuntimeException('Stripe is not configured for '.mg_payment_mode().' mode.');
    $merchantStmt=$pdo->prepare('SELECT id,email,full_name,display_name FROM users WHERE id=? LIMIT 1 FOR UPDATE');
    $merchantStmt->execute([$merchantUserId]);
    $merchant=$merchantStmt->fetch(PDO::FETCH_ASSOC);
    if(!$merchant)throw new RuntimeException('Merchant account not found.');

    $account=mg_payment_provider_account($pdo,$merchantUserId,'stripe',mg_payment_mode(),true);
    if(!$account){
        $stripe=mg_stripe_create_connected_account($pdo,$merchant,'connect-account:'.mg_payment_mode().':'.$merchantUserId);
        $publicId=mg_public_uuid();
        $pdo->prepare("INSERT INTO payment_provider_accounts (public_id,merchant_user_id,provider_key,provider_account_reference,mode,status,charges_enabled,payouts_enabled,details_submitted,onboarding_status,capabilities_json,requirements_due_json,last_synced_at,created_at,updated_at) VALUES (?,?, 'stripe',?,?, 'pending',0,0,0,'pending',?,?,NOW(),NOW(),NOW())")
            ->execute([
                $publicId,
                $merchantUserId,
                (string)$stripe['id'],
                mg_payment_mode(),
                json_encode($stripe['capabilities']??[],JSON_THROW_ON_ERROR),
                json_encode($stripe['requirements']['currently_due']??[],JSON_THROW_ON_ERROR),
            ]);
        $account=mg_payment_provider_account($pdo,$merchantUserId,'stripe',mg_payment_mode(),true);
    }

    $link=mg_stripe_create_account_link(
        $pdo,
        (string)$account['provider_account_reference'],
        '/merchant-payments.php?connect=refresh',
        '/merchant-payments.php?connect=return'
    );
    $payload=mg_payment_connect_account_payload($account);
    $payload['onboarding_url']=(string)$link['url'];
    $payload['onboarding_expires_at']=date('c',(int)($link['expires_at']??time()+1800));
    return $payload;
}

function mg_payment_connect_status(PDO $pdo,int $merchantUserId,bool $sync=false): array
{
    $account=mg_payment_provider_account($pdo,$merchantUserId,'stripe',mg_payment_mode(),$sync);
    if($sync&&$account&&trim((string)$account['provider_account_reference'])!==''){
        return mg_payment_connect_sync($pdo,$merchantUserId,$account);
    }
    return mg_payment_connect_account_payload($account);
}
