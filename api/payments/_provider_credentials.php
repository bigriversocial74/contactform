<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

final class MgPaymentCredentialException extends RuntimeException {}

function mg_payment_mode(): string
{
    return strtolower((string)(getenv('MG_PAYMENT_MODE') ?: 'test')) === 'live' ? 'live' : 'test';
}

function mg_payment_sodium_available(): bool
{
    return function_exists('sodium_crypto_secretbox')
        && function_exists('sodium_crypto_secretbox_open')
        && defined('SODIUM_CRYPTO_SECRETBOX_KEYBYTES')
        && defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES');
}

function mg_payment_credential_master_key(): ?string
{
    if(!mg_payment_sodium_available())return null;
    $raw=trim((string)(getenv('MG_PAYMENT_CREDENTIAL_KEY') ?: ''));
    if($raw==='')return null;
    $decoded=base64_decode($raw,true);
    if(is_string($decoded)&&strlen($decoded)===SODIUM_CRYPTO_SECRETBOX_KEYBYTES)return $decoded;
    if(strlen($raw)===SODIUM_CRYPTO_SECRETBOX_KEYBYTES)return $raw;
    return null;
}

function mg_payment_encrypt_secret(string $plaintext): string
{
    if(!mg_payment_sodium_available())throw new MgPaymentCredentialException('The PHP Sodium extension is required before database payment credentials can be saved.');
    $key=mg_payment_credential_master_key();
    if($key===null)throw new MgPaymentCredentialException('MG_PAYMENT_CREDENTIAL_KEY must be a 32-byte key or base64-encoded 32-byte key before database credentials can be saved.');
    $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce.sodium_crypto_secretbox($plaintext,$nonce,$key));
}

function mg_payment_decrypt_secret(?string $encoded): string
{
    $encoded=trim((string)$encoded);
    if($encoded==='')return '';
    if(!mg_payment_sodium_available())throw new MgPaymentCredentialException('Encrypted payment credentials are present but the PHP Sodium extension is unavailable.');
    $key=mg_payment_credential_master_key();
    if($key===null)throw new MgPaymentCredentialException('Encrypted payment credentials are present but MG_PAYMENT_CREDENTIAL_KEY is unavailable.');
    $raw=base64_decode($encoded,true);
    if(!is_string($raw)||strlen($raw)<=SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)throw new MgPaymentCredentialException('Stored payment credential is invalid.');
    $nonce=substr($raw,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext=substr($raw,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plaintext=sodium_crypto_secretbox_open($ciphertext,$nonce,$key);
    if(!is_string($plaintext))throw new MgPaymentCredentialException('Stored payment credential could not be decrypted.');
    return $plaintext;
}

function mg_payment_env_key(string $provider,string $field,string $mode): string
{
    return 'MG_'.strtoupper(preg_replace('/[^A-Z0-9]+/i','_',$provider)).'_'.strtoupper($field).'_'.strtoupper($mode);
}

function mg_payment_env_value(string $provider,string $field,string $mode): string
{
    $modeSpecific=trim((string)(getenv(mg_payment_env_key($provider,$field,$mode)) ?: ''));
    if($modeSpecific!=='')return $modeSpecific;
    return trim((string)(getenv('MG_'.strtoupper($provider).'_'.strtoupper($field)) ?: ''));
}

function mg_payment_platform_credential_row(PDO $pdo,string $provider,string $mode,bool $forUpdate=false): ?array
{
    $sql='SELECT * FROM payment_platform_credentials WHERE provider_key=? AND mode=? LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);
    $stmt->execute([$provider,$mode]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}

function mg_payment_platform_config(PDO $pdo,?string $provider=null,?string $mode=null): array
{
    $provider=trim((string)($provider??(getenv('MG_PAYMENT_PROVIDER')?:'sandbox')))?:'sandbox';
    $mode=$mode??mg_payment_mode();
    $row=mg_payment_platform_credential_row($pdo,$provider,$mode,false);

    $publishableEnv=mg_payment_env_value($provider,'PUBLISHABLE_KEY',$mode);
    $secretEnv=mg_payment_env_value($provider,'SECRET_KEY',$mode);
    $webhookEnv=mg_payment_env_value($provider,'WEBHOOK_SECRET',$mode);
    $clientEnv=mg_payment_env_value($provider,'CONNECT_CLIENT_ID',$mode);
    $feeEnv=trim((string)(getenv('MG_PLATFORM_FEE_BPS')?:''));
    $fixedEnv=trim((string)(getenv('MG_PLATFORM_FIXED_FEE_CENTS')?:''));

    $secretDb='';
    $webhookDb='';
    if($row){
        $secretDb=mg_payment_decrypt_secret($row['secret_key_ciphertext']??null);
        $webhookDb=mg_payment_decrypt_secret($row['webhook_secret_ciphertext']??null);
    }

    $feeBps=$feeEnv!==''?(int)$feeEnv:(int)($row['platform_fee_bps']??1500);
    $fixedFee=$fixedEnv!==''?(int)$fixedEnv:(int)($row['fixed_fee_cents']??0);
    $feeBps=max(0,min(10000,$feeBps));
    $fixedFee=max(0,$fixedFee);

    return [
        'provider_key'=>$provider,
        'mode'=>$mode,
        'publishable_key'=>$publishableEnv!==''?$publishableEnv:(string)($row['publishable_key']??''),
        'secret_key'=>$secretEnv!==''?$secretEnv:$secretDb,
        'webhook_secret'=>$webhookEnv!==''?$webhookEnv:$webhookDb,
        'connect_client_id'=>$clientEnv!==''?$clientEnv:(string)($row['connect_client_id']??''),
        'platform_fee_bps'=>$feeBps,
        'fixed_fee_cents'=>$fixedFee,
        'enabled'=>$provider==='sandbox'||$publishableEnv!==''||$secretEnv!==''||$webhookEnv!==''
            ? true
            : (bool)($row['enabled']??false),
        'credential_source'=>($publishableEnv!==''||$secretEnv!==''||$webhookEnv!==''||$clientEnv!=='')?'environment':($row?'database':'missing'),
        'database_row'=>$row,
    ];
}

function mg_payment_save_platform_config(PDO $pdo,array $input,int $actorUserId): array
{
    $provider=strtolower(trim((string)($input['provider_key']??'stripe')));
    $mode=(string)($input['mode']??'test')==='live'?'live':'test';
    if(!preg_match('/^[a-z0-9_-]{2,80}$/',$provider))throw new InvalidArgumentException('Invalid payment provider.');
    $publishable=trim((string)($input['publishable_key']??''));
    $secret=trim((string)($input['secret_key']??''));
    $webhook=trim((string)($input['webhook_secret']??''));
    $clientId=trim((string)($input['connect_client_id']??''));
    $feeBps=max(0,min(10000,(int)($input['platform_fee_bps']??1500)));
    $fixedFee=max(0,(int)($input['fixed_fee_cents']??0));
    $enabled=!empty($input['enabled'])?1:0;
    $prefix=$mode==='live'?'live':'test';
    if($provider==='stripe'){
        if($publishable!==''&&!str_starts_with($publishable,'pk_'.$prefix.'_')){
            throw new InvalidArgumentException('Stripe publishable key must start with pk_'.$prefix.'_ for '.$mode.' mode.');
        }
        if($secret!==''&&!str_starts_with($secret,'sk_'.$prefix.'_')){
            throw new InvalidArgumentException('Stripe secret key must start with sk_'.$prefix.'_ for '.$mode.' mode.');
        }
        if($webhook!==''&&!str_starts_with($webhook,'whsec_')){
            throw new InvalidArgumentException('Stripe webhook signing secret must start with whsec_.');
        }
        if($clientId!==''&&!str_starts_with($clientId,'ca_')){
            throw new InvalidArgumentException('Stripe Connect client ID must start with ca_. If you copied a whsec_ value, paste it into Webhook signing secret instead.');
        }
    }

    $row=mg_payment_platform_credential_row($pdo,$provider,$mode,true);
    if($row){
        if($publishable==='')$publishable=(string)($row['publishable_key']??'');
    }
    $secretCipher=$secret!==''?mg_payment_encrypt_secret($secret):(string)($row['secret_key_ciphertext']??'');
    $webhookCipher=$webhook!==''?mg_payment_encrypt_secret($webhook):(string)($row['webhook_secret_ciphertext']??'');

    if($row){
        $pdo->prepare('UPDATE payment_platform_credentials SET publishable_key=?,secret_key_ciphertext=?,webhook_secret_ciphertext=?,connect_client_id=?,platform_fee_bps=?,fixed_fee_cents=?,enabled=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')
            ->execute([$publishable?:null,$secretCipher?:null,$webhookCipher?:null,$clientId?:null,$feeBps,$fixedFee,$enabled,$actorUserId,(int)$row['id']]);
        $publicId=(string)$row['public_id'];
    }else{
        $publicId=mg_public_uuid();
        $pdo->prepare('INSERT INTO payment_platform_credentials (public_id,provider_key,mode,publishable_key,secret_key_ciphertext,webhook_secret_ciphertext,connect_client_id,platform_fee_bps,fixed_fee_cents,enabled,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
            ->execute([$publicId,$provider,$mode,$publishable?:null,$secretCipher?:null,$webhookCipher?:null,$clientId?:null,$feeBps,$fixedFee,$enabled,$actorUserId]);
    }
    return mg_payment_platform_config($pdo,$provider,$mode)+['public_id'=>$publicId];
}

function mg_payment_config_public_status(PDO $pdo,string $provider='stripe',?string $mode=null): array
{
    $config=mg_payment_platform_config($pdo,$provider,$mode);
    $publishable=(string)$config['publishable_key'];
    $secret=(string)$config['secret_key'];
    $webhook=(string)$config['webhook_secret'];
    return [
        'provider_key'=>$provider,
        'mode'=>$config['mode'],
        'enabled'=>(bool)$config['enabled'],
        'credential_source'=>$config['credential_source'],
        'publishable_configured'=>$publishable!=='',
        'secret_configured'=>$secret!=='',
        'webhook_configured'=>$webhook!=='',
        'connect_client_configured'=>(string)$config['connect_client_id']!=='',
        'publishable_hint'=>$publishable!==''?substr($publishable,0,8).'…'.substr($publishable,-4):'',
        'secret_hint'=>$secret!==''?substr($secret,0,7).'…'.substr($secret,-4):'',
        'webhook_hint'=>$webhook!==''?substr($webhook,0,6).'…'.substr($webhook,-4):'',
        'platform_fee_bps'=>(int)$config['platform_fee_bps'],
        'fixed_fee_cents'=>(int)$config['fixed_fee_cents'],
        'database_encryption_ready'=>mg_payment_credential_master_key()!==null,
    ];
}
