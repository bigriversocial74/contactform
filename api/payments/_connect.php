<?php
declare(strict_types=1);

require_once __DIR__ . '/_payments.php';
require_once __DIR__ . '/_stripe_connect_oauth.php';

function mg_payment_connect_account_payload(?array $account,string $provider='stripe',?string $mode=null): array
{
    $mode=$mode??mg_payment_mode();
    if(!$account){
        return [
            'provider_key'=>$provider,
            'mode'=>$mode,
            'account_id'=>'',
            'account_hint'=>'',
            'account_type'=>'',
            'connection_method'=>'',
            'oauth_scope'=>'',
            'status'=>'pending',
            'onboarding_status'=>'not_started',
            'charges_enabled'=>false,
            'payouts_enabled'=>false,
            'details_submitted'=>false,
            'requirements_due'=>[],
            'ready'=>false,
            'connected'=>false,
            'can_disconnect'=>false,
            'connected_at'=>null,
            'disconnected_at'=>null,
            'last_synced_at'=>null,
        ];
    }

    $requirements=[];
    try{
        $decoded=json_decode((string)($account['requirements_due_json']??'[]'),true,512,JSON_THROW_ON_ERROR);
        if(is_array($decoded))$requirements=$decoded;
    }catch(Throwable){}

    $accountId=trim((string)($account['provider_account_reference']??''));
    $connectionMethod=(string)($account['connection_method']??'express_account_link');
    $connected=$accountId!==''&&(string)($account['status']??'pending')!=='disabled';

    return [
        'provider_key'=>(string)$account['provider_key'],
        'mode'=>(string)$account['mode'],
        'account_id'=>$accountId,
        'account_hint'=>$accountId!==''?substr($accountId,0,10).'…'.substr($accountId,-4):'',
        'account_type'=>(string)($account['account_type']??''),
        'connection_method'=>$connectionMethod,
        'oauth_scope'=>(string)($account['oauth_scope']??''),
        'status'=>(string)$account['status'],
        'onboarding_status'=>(string)($account['onboarding_status']??'not_started'),
        'charges_enabled'=>(bool)$account['charges_enabled'],
        'payouts_enabled'=>(bool)$account['payouts_enabled'],
        'details_submitted'=>(bool)($account['details_submitted']??false),
        'requirements_due'=>$requirements,
        'ready'=>(string)$account['status']==='active'&&(int)$account['charges_enabled']===1&&(int)$account['payouts_enabled']===1,
        'connected'=>$connected,
        'can_disconnect'=>$connected&&$connectionMethod==='standard_oauth',
        'connected_at'=>$account['connected_at']??null,
        'disconnected_at'=>$account['disconnected_at']??null,
        'last_synced_at'=>$account['last_synced_at']??null,
    ];
}

function mg_payment_connect_update_readiness(PDO $pdo,int $merchantUserId,array $payload): void
{
    $workspaceStmt=$pdo->prepare('SELECT id FROM merchant_workspaces WHERE merchant_user_id=? LIMIT 1');
    $workspaceStmt->execute([$merchantUserId]);
    $workspaceId=(int)($workspaceStmt->fetchColumn()?:0);
    if($workspaceId<1)return;

    $stmt=$pdo->prepare('SELECT id,state_json FROM merchant_payment_readiness WHERE workspace_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$workspaceId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row){
        $pdo->prepare('INSERT INTO merchant_payment_readiness (workspace_id,created_at,updated_at) VALUES (?,NOW(),NOW())')->execute([$workspaceId]);
        $row=['id'=>(int)$pdo->lastInsertId(),'state_json'=>null];
    }

    $state=[];
    try{
        $decoded=json_decode((string)($row['state_json']??''),true,512,JSON_THROW_ON_ERROR);
        if(is_array($decoded))$state=$decoded;
    }catch(Throwable){}
    if(!isset($state['payment_methods'])||!is_array($state['payment_methods']))$state['payment_methods']=[];
    $existing=is_array($state['payment_methods']['stripe']??null)?$state['payment_methods']['stripe']:[];
    $state['payment_methods']['stripe']=$existing+['enabled'=>false];
    $state['payment_methods']['stripe']['mode']=!empty($payload['ready'])?'ready':(!empty($payload['connected'])?'pending_onboarding':'not_connected');
    $state['payment_methods']['stripe']['account_id']=(string)($payload['account_id']??'');
    $state['payment_methods']['stripe']['updated_at']=gmdate('c');

    $pdo->prepare('UPDATE merchant_payment_readiness SET provider_key=?,mode=?,account_connected=?,identity_verified=?,charges_enabled=?,payouts_enabled=?,state_json=?,updated_at=NOW() WHERE id=?')
        ->execute([
            'stripe',
            ($payload['mode']??mg_payment_mode())==='live'?'live':'test',
            !empty($payload['connected'])?1:0,
            !empty($payload['details_submitted'])?1:0,
            !empty($payload['charges_enabled'])?1:0,
            !empty($payload['payouts_enabled'])?1:0,
            json_encode($state,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            (int)$row['id'],
        ]);
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

    $pdo->prepare("UPDATE payment_provider_accounts
        SET status=?,charges_enabled=?,payouts_enabled=?,details_submitted=?,onboarding_status=?,
            account_type=COALESCE(NULLIF(?,''),account_type),capabilities_json=?,requirements_due_json=?,
            last_synced_at=NOW(),updated_at=NOW()
        WHERE id=? AND merchant_user_id=?")
        ->execute([
            $status,$charges,$payouts,$details,$onboarding,(string)($stripe['type']??''),
            json_encode($capabilities,JSON_THROW_ON_ERROR),json_encode($due,JSON_THROW_ON_ERROR),
            (int)$account['id'],$merchantUserId,
        ]);

    $updated=mg_payment_provider_account($pdo,$merchantUserId,'stripe',mg_payment_mode(),false);
    $payload=mg_payment_connect_account_payload($updated);
    mg_payment_connect_update_readiness($pdo,$merchantUserId,$payload);
    return $payload;
}

/** Legacy Express Account Link flow retained for existing integrations and regression coverage. */
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
        $pdo->prepare("INSERT INTO payment_provider_accounts
            (public_id,merchant_user_id,provider_key,provider_account_reference,mode,status,
             charges_enabled,payouts_enabled,details_submitted,onboarding_status,capabilities_json,
             requirements_due_json,last_synced_at,created_at,updated_at)
            VALUES (?,?,'stripe',?,?,'pending',0,0,0,'pending',?,?,NOW(),NOW(),NOW())")
            ->execute([
                $publicId,$merchantUserId,(string)$stripe['id'],mg_payment_mode(),
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

function mg_payment_connect_oauth_config(PDO $pdo,string $mode): array
{
    $mode=$mode==='live'?'live':'test';
    $config=mg_payment_platform_config($pdo,'stripe',$mode);
    $secret=trim((string)$config['secret_key']);
    if(empty($config['enabled']))throw new RuntimeException('Stripe is not enabled for '.$mode.' mode.');
    if($secret==='')throw new RuntimeException('Stripe API credentials are not configured for '.$mode.' mode.');
    if(!mg_payment_secret_matches_mode($secret,$mode))throw new RuntimeException('Stripe API credentials do not match '.$mode.' mode.');
    if(mg_payment_secret_key_type($secret)!=='secret')throw new RuntimeException('Stripe Connect OAuth requires the platform standard sk_'.$mode.'_ secret key. A restricted rk_'.$mode.'_ key cannot complete OAuth.');
    if(!str_starts_with(trim((string)$config['connect_client_id']),'ca_'))throw new RuntimeException('Stripe Connect client ID is not configured for '.$mode.' mode.');
    return $config;
}

function mg_payment_connect_oauth_state_token(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
}

function mg_payment_connect_oauth_start(PDO $pdo,int $merchantUserId,string $returnPath='/merchant-payments.php'): array
{
    $mode=mg_payment_mode();
    $config=mg_payment_connect_oauth_config($pdo,$mode);
    $callback=mg_payment_absolute_url('/api/merchant/stripe-connect-callback.php');
    $merchantStmt=$pdo->prepare(
        'SELECT u.id,u.email,u.full_name,u.display_name,mw.display_name business_name,mw.website_url
         FROM users u
         LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=u.id
         WHERE u.id=? LIMIT 1'
    );
    $merchantStmt->execute([$merchantUserId]);
    $merchant=$merchantStmt->fetch(PDO::FETCH_ASSOC);
    if(!$merchant)throw new RuntimeException('Merchant account not found.');

    $state=mg_payment_connect_oauth_state_token();
    $stateHash=hash('sha256',$state);
    $expiresAt=date('Y-m-d H:i:s',time()+600);
    $pdo->prepare("UPDATE payment_connect_oauth_states
        SET consumed_at=COALESCE(consumed_at,NOW())
        WHERE merchant_user_id=? AND provider_key='stripe' AND mode=? AND consumed_at IS NULL")
        ->execute([$merchantUserId,$mode]);
    $pdo->prepare('DELETE FROM payment_connect_oauth_states WHERE expires_at<DATE_SUB(NOW(),INTERVAL 1 DAY)')->execute();
    $pdo->prepare("INSERT INTO payment_connect_oauth_states
        (public_id,merchant_user_id,provider_key,mode,state_hash,redirect_uri,return_path,expires_at,created_at)
        VALUES (?,?,'stripe',?,?,?,?,?,NOW())")
        ->execute([mg_public_uuid(),$merchantUserId,$mode,$stateHash,$callback,$returnPath,$expiresAt]);

    $params=[
        'response_type'=>'code',
        'client_id'=>(string)$config['connect_client_id'],
        'scope'=>'read_write',
        'state'=>$state,
        'redirect_uri'=>$callback,
        'stripe_user[email]'=>(string)($merchant['email']??''),
        'stripe_user[business_name]'=>(string)($merchant['business_name']??$merchant['display_name']??$merchant['full_name']??''),
        'stripe_user[country]'=>(string)(getenv('MG_STRIPE_CONNECT_COUNTRY')?:'US'),
        'stripe_user[product_description]'=>'Local gifts, rewards, loyalty offers, and merchant products sold through Microgifter.',
    ];
    $website=trim((string)($merchant['website_url']??''));
    if($website!==''&&filter_var($website,FILTER_VALIDATE_URL))$params['stripe_user[url]']=$website;

    return [
        'authorization_url'=>'https://connect.stripe.com/oauth/authorize?'.http_build_query($params,'','&',PHP_QUERY_RFC3986),
        'expires_at'=>date('c',strtotime($expiresAt)),
        'mode'=>$mode,
        'flow'=>'standard_oauth',
    ];
}

function mg_payment_connect_oauth_consume_state(PDO $pdo,int $merchantUserId,string $state): array
{
    $hash=hash('sha256',trim($state));
    $stmt=$pdo->prepare("SELECT * FROM payment_connect_oauth_states
        WHERE merchant_user_id=? AND provider_key='stripe' AND state_hash=? AND consumed_at IS NULL
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$merchantUserId,$hash]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new InvalidArgumentException('Stripe connection state is invalid or has already been used.');
    if(strtotime((string)$row['expires_at'])<time())throw new InvalidArgumentException('Stripe connection request expired. Start again from Merchant Payments.');
    if((string)$row['mode']!==mg_payment_mode())throw new InvalidArgumentException('Stripe payment mode changed during connection. Start again.');
    $pdo->prepare('UPDATE payment_connect_oauth_states SET consumed_at=NOW() WHERE id=? AND consumed_at IS NULL')->execute([(int)$row['id']]);
    return $row;
}

function mg_payment_connect_oauth_complete(PDO $pdo,int $merchantUserId,string $code,string $scope='read_write'): array
{
    $mode=mg_payment_mode();
    mg_payment_connect_oauth_config($pdo,$mode);
    $token=mg_stripe_connect_exchange_code($pdo,$code,$mode);
    $accountId=(string)$token['stripe_user_id'];

    $duplicateStmt=$pdo->prepare("SELECT merchant_user_id FROM payment_provider_accounts
        WHERE provider_key='stripe' AND mode=? AND provider_account_reference=? AND merchant_user_id<>?
        LIMIT 1 FOR UPDATE");
    $duplicateStmt->execute([$mode,$accountId,$merchantUserId]);
    if($duplicateStmt->fetchColumn())throw new InvalidArgumentException('This Stripe account is already connected to another Microgifter merchant account.');

    $stripe=mg_stripe_retrieve_account($pdo,$accountId);
    $charges=!empty($stripe['charges_enabled'])?1:0;
    $payouts=!empty($stripe['payouts_enabled'])?1:0;
    $details=!empty($stripe['details_submitted'])?1:0;
    $due=is_array($stripe['requirements']['currently_due']??null)?array_values($stripe['requirements']['currently_due']):[];
    $status=$charges&&$payouts?'active':($details?'restricted':'pending');
    $onboarding=$charges&&$payouts?'complete':($details?'restricted':'pending');
    $capabilities=is_array($stripe['capabilities']??null)?$stripe['capabilities']:[];

    $existing=mg_payment_provider_account($pdo,$merchantUserId,'stripe',$mode,true);
    if($existing){
        $pdo->prepare("UPDATE payment_provider_accounts
            SET provider_account_reference=?,connection_method='standard_oauth',account_type=?,oauth_scope=?,
                status=?,charges_enabled=?,payouts_enabled=?,details_submitted=?,onboarding_status=?,
                capabilities_json=?,requirements_due_json=?,last_synced_at=NOW(),connected_at=NOW(),
                disconnected_at=NULL,updated_at=NOW()
            WHERE id=? AND merchant_user_id=?")
            ->execute([
                $accountId,(string)($stripe['type']??'standard'),$scope,$status,$charges,$payouts,$details,$onboarding,
                json_encode($capabilities,JSON_THROW_ON_ERROR),json_encode($due,JSON_THROW_ON_ERROR),
                (int)$existing['id'],$merchantUserId,
            ]);
    }else{
        $pdo->prepare("INSERT INTO payment_provider_accounts
            (public_id,merchant_user_id,provider_key,provider_account_reference,connection_method,account_type,
             oauth_scope,mode,status,charges_enabled,payouts_enabled,details_submitted,onboarding_status,
             capabilities_json,requirements_due_json,last_synced_at,connected_at,created_at,updated_at)
            VALUES (?,?,'stripe',?,'standard_oauth',?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW(),NOW())")
            ->execute([
                mg_public_uuid(),$merchantUserId,$accountId,(string)($stripe['type']??'standard'),$scope,$mode,$status,
                $charges,$payouts,$details,$onboarding,json_encode($capabilities,JSON_THROW_ON_ERROR),
                json_encode($due,JSON_THROW_ON_ERROR),
            ]);
    }

    $updated=mg_payment_provider_account($pdo,$merchantUserId,'stripe',$mode,false);
    $payload=mg_payment_connect_account_payload($updated);
    mg_payment_connect_update_readiness($pdo,$merchantUserId,$payload);
    return $payload;
}

function mg_payment_connect_oauth_disconnect(PDO $pdo,int $merchantUserId): array
{
    $mode=mg_payment_mode();
    $config=mg_payment_connect_oauth_config($pdo,$mode);
    $account=mg_payment_provider_account($pdo,$merchantUserId,'stripe',$mode,true);
    if(!$account||trim((string)($account['provider_account_reference']??''))==='')throw new InvalidArgumentException('No Stripe account is connected.');
    if((string)($account['connection_method']??'')!=='standard_oauth')throw new InvalidArgumentException('This Stripe account was not connected through OAuth and cannot be disconnected here.');

    mg_stripe_connect_deauthorize($pdo,(string)$config['connect_client_id'],(string)$account['provider_account_reference'],$mode);
    $pdo->prepare("UPDATE payment_provider_accounts
        SET status='disabled',charges_enabled=0,payouts_enabled=0,onboarding_status='disabled',
            disconnected_at=NOW(),updated_at=NOW()
        WHERE id=? AND merchant_user_id=?")
        ->execute([(int)$account['id'],$merchantUserId]);

    $updated=mg_payment_provider_account($pdo,$merchantUserId,'stripe',$mode,false);
    $payload=mg_payment_connect_account_payload($updated);
    mg_payment_connect_update_readiness($pdo,$merchantUserId,$payload);
    return $payload;
}

function mg_payment_connect_status(PDO $pdo,int $merchantUserId,bool $sync=false): array
{
    $account=mg_payment_provider_account($pdo,$merchantUserId,'stripe',mg_payment_mode(),$sync);
    if($sync&&$account&&trim((string)$account['provider_account_reference'])!==''&&(string)($account['status']??'')!=='disabled'){
        return mg_payment_connect_sync($pdo,$merchantUserId,$account);
    }
    return mg_payment_connect_account_payload($account);
}
