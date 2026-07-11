<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/payments/_readiness.php';

$user = mg_require_permission('admin.settings.manage');
$userId = (int)$user['id'];
$pdo = mg_db();

function mg_admin_payment_key_mode(string $value): ?string
{
    $value=trim($value);
    foreach(['live','test'] as $mode){
        if(
            str_starts_with($value,'pk_'.$mode.'_')
            ||str_starts_with($value,'sk_'.$mode.'_')
            ||str_starts_with($value,'rk_'.$mode.'_')
        )return $mode;
    }
    return null;
}

function mg_admin_payment_database_snapshot(PDO $pdo,string $mode): array
{
    $mode=$mode==='live'?'live':'test';
    $row=mg_payment_platform_credential_row($pdo,'stripe',$mode,false);
    if(!$row){
        return [
            'exists'=>false,
            'mode'=>$mode,
            'public_id'=>'',
            'publishable_key'=>'',
            'connect_client_id'=>'',
            'platform_fee_bps'=>1500,
            'fixed_fee_cents'=>0,
            'enabled'=>false,
            'secret_configured'=>false,
            'secret_hint'=>'',
            'secret_key_type'=>'unknown',
            'webhook_configured'=>false,
            'webhook_hint'=>'',
            'updated_at'=>null,
            'decryption_error'=>false,
        ];
    }

    $secretCipher=trim((string)($row['secret_key_ciphertext']??''));
    $webhookCipher=trim((string)($row['webhook_secret_ciphertext']??''));
    $secret='';
    $webhook='';
    $decryptionError=false;
    try{
        if($secretCipher!=='')$secret=mg_payment_decrypt_secret($secretCipher);
        if($webhookCipher!=='')$webhook=mg_payment_decrypt_secret($webhookCipher);
    }catch(Throwable){
        $decryptionError=true;
    }

    return [
        'exists'=>true,
        'mode'=>$mode,
        'public_id'=>(string)($row['public_id']??''),
        'publishable_key'=>(string)($row['publishable_key']??''),
        'connect_client_id'=>(string)($row['connect_client_id']??''),
        'platform_fee_bps'=>(int)($row['platform_fee_bps']??1500),
        'fixed_fee_cents'=>(int)($row['fixed_fee_cents']??0),
        'enabled'=>!empty($row['enabled']),
        'secret_configured'=>$secretCipher!=='',
        'secret_hint'=>$secret!==''?substr($secret,0,7).'…'.substr($secret,-4):'',
        'secret_key_type'=>$secret!==''?mg_payment_secret_key_type($secret):'unknown',
        'webhook_configured'=>$webhookCipher!=='',
        'webhook_hint'=>$webhook!==''?substr($webhook,0,6).'…'.substr($webhook,-4):'',
        'updated_at'=>$row['updated_at']??null,
        'decryption_error'=>$decryptionError,
    ];
}

function mg_admin_payment_configured_modes(PDO $pdo): array
{
    $result=[
        'test'=>['configured'=>false,'enabled'=>false,'updated_at'=>null],
        'live'=>['configured'=>false,'enabled'=>false,'updated_at'=>null],
    ];
    $stmt=$pdo->prepare(
        "SELECT mode,enabled,publishable_key,secret_key_ciphertext,webhook_secret_ciphertext,connect_client_id,updated_at
         FROM payment_platform_credentials
         WHERE provider_key='stripe' AND mode IN ('test','live')"
    );
    $stmt->execute();
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $mode=(string)$row['mode']==='live'?'live':'test';
        $result[$mode]=[
            'configured'=>trim((string)($row['publishable_key']??''))!==''
                ||trim((string)($row['secret_key_ciphertext']??''))!==''
                ||trim((string)($row['webhook_secret_ciphertext']??''))!==''
                ||trim((string)($row['connect_client_id']??''))!=='',
            'enabled'=>!empty($row['enabled']),
            'updated_at'=>$row['updated_at']??null,
        ];
    }
    return $result;
}

function mg_admin_payment_default_mode(PDO $pdo): string
{
    $rows=$pdo->query(
        "SELECT mode,publishable_key,secret_key_ciphertext,webhook_secret_ciphertext,connect_client_id,updated_at
         FROM payment_platform_credentials
         WHERE provider_key='stripe'
           AND mode IN ('test','live')
           AND (
             publishable_key IS NOT NULL
             OR secret_key_ciphertext IS NOT NULL
             OR webhook_secret_ciphertext IS NOT NULL
             OR connect_client_id IS NOT NULL
           )
         ORDER BY updated_at DESC,id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach($rows as $row){
        $detected=mg_admin_payment_key_mode((string)($row['publishable_key']??''));
        if($detected!==null)return $detected;
        $cipher=trim((string)($row['secret_key_ciphertext']??''));
        if($cipher!==''){
            try{
                $detected=mg_admin_payment_key_mode(mg_payment_decrypt_secret($cipher));
                if($detected!==null)return $detected;
            }catch(Throwable){
                // Readiness will report encryption problems without blocking mode selection.
            }
        }
    }

    foreach(['PUBLISHABLE_KEY','SECRET_KEY'] as $field){
        $detected=mg_admin_payment_key_mode((string)(getenv('MG_STRIPE_'.$field)?:''));
        if($detected!==null)return $detected;
    }

    if($rows!==[]){
        return (string)$rows[0]['mode']==='live'?'live':'test';
    }
    return mg_payment_mode();
}

function mg_admin_payment_mode_storage_warning(PDO $pdo): ?string
{
    $stmt=$pdo->prepare(
        "SELECT mode,publishable_key,secret_key_ciphertext
         FROM payment_platform_credentials
         WHERE provider_key='stripe' AND mode IN ('test','live')
         ORDER BY updated_at DESC,id DESC"
    );
    $stmt->execute();
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $storedMode=(string)$row['mode']==='live'?'live':'test';
        $detected=mg_admin_payment_key_mode((string)($row['publishable_key']??''));
        if($detected===null&&trim((string)($row['secret_key_ciphertext']??''))!==''){
            try{$detected=mg_admin_payment_key_mode(mg_payment_decrypt_secret((string)$row['secret_key_ciphertext']));}
            catch(Throwable){$detected=null;}
        }
        if($detected!==null&&$detected!==$storedMode){
            return ucfirst($detected).' Stripe credentials appear to be stored in the '.ucfirst($storedMode).' record. Select '.ucfirst($detected).' and save them again. Test credentials are not required for a live-only setup.';
        }
    }
    return null;
}

function mg_admin_payment_settings_payload(PDO $pdo, string $mode): array
{
    $payload = mg_payment_readiness($pdo, 'stripe', $mode);
    $config = mg_payment_platform_config($pdo, 'stripe', $mode);
    $storage=mg_admin_payment_database_snapshot($pdo,$mode);

    $payload['provider']['effective_publishable_key']=(string)$config['publishable_key'];
    $payload['provider']['effective_connect_client_id']=(string)$config['connect_client_id'];
    $payload['provider']['publishable_key']=$storage['exists']?(string)$storage['publishable_key']:(string)$config['publishable_key'];
    $payload['provider']['connect_client_id']=$storage['exists']?(string)$storage['connect_client_id']:(string)$config['connect_client_id'];
    $payload['selected_mode']=$mode;
    $payload['runtime_mode']=mg_payment_mode();
    $payload['runtime_provider']=mg_payment_provider_key();
    $payload['configured_modes']=mg_admin_payment_configured_modes($pdo);
    $payload['mode_storage_warning']=mg_admin_payment_mode_storage_warning($pdo);
    $payload['storage']=$storage;
    $payload['environment_override']=$config['credential_source']==='environment';
    $payload['activation_notice']=mg_payment_mode()===$mode
        ? ucfirst($mode).' is the active server runtime mode.'
        : ucfirst($mode).' credentials can be saved now. They will not process payments until MG_PAYMENT_MODE='.$mode.'.';
    return $payload;
}

function mg_admin_payment_readiness_blockers(array $readiness): array
{
    $blockers = [];
    foreach (['publishable_key', 'secret_key', 'webhook_secret'] as $key) {
        $check = $readiness['checks'][$key] ?? null;
        if (is_array($check) && empty($check['ok'])) {
            $blockers[] = (string)($check['label'] ?? $key) . ': ' . (string)($check['detail'] ?? 'Not ready.');
        }
    }
    return $blockers;
}

function mg_admin_payment_verify_persistence(PDO $pdo,array $input,string $mode): array
{
    $snapshot=mg_admin_payment_database_snapshot($pdo,$mode);
    $errors=[];
    if(!$snapshot['exists'])$errors[]='No database credential record was created.';

    $expectedPublishable=trim((string)($input['publishable_key']??''));
    $expectedClientId=trim((string)($input['connect_client_id']??''));
    $expectedEnabled=!empty($input['enabled']);
    $expectedFee=max(0,min(10000,(int)($input['platform_fee_bps']??1500)));
    $expectedFixed=max(0,(int)($input['fixed_fee_cents']??0));

    if(!hash_equals($expectedPublishable,(string)$snapshot['publishable_key']))$errors[]='Publishable key was not retained.';
    if(!hash_equals($expectedClientId,(string)$snapshot['connect_client_id']))$errors[]='Connect client ID was not retained.';
    if($expectedEnabled!==(bool)$snapshot['enabled'])$errors[]='Stripe enabled state was not retained.';
    if($expectedFee!==(int)$snapshot['platform_fee_bps'])$errors[]='Platform share was not retained.';
    if($expectedFixed!==(int)$snapshot['fixed_fee_cents'])$errors[]='Fixed platform fee was not retained.';

    $row=mg_payment_platform_credential_row($pdo,'stripe',$mode,false);
    $newSecret=trim((string)($input['secret_key']??''));
    $newWebhook=trim((string)($input['webhook_secret']??''));
    if($newSecret!==''){
        $stored='';
        try{$stored=mg_payment_decrypt_secret($row['secret_key_ciphertext']??null);}catch(Throwable){}
        if($stored===''||!hash_equals($newSecret,$stored))$errors[]='Stripe API key was not retained.';
    }
    if($newWebhook!==''){
        $stored='';
        try{$stored=mg_payment_decrypt_secret($row['webhook_secret_ciphertext']??null);}catch(Throwable){}
        if($stored===''||!hash_equals($newWebhook,$stored))$errors[]='Webhook signing secret was not retained.';
    }

    return [
        'ok'=>$errors===[],
        'mode'=>$mode,
        'verified_at'=>gmdate('c'),
        'updated_at'=>$snapshot['updated_at'],
        'errors'=>$errors,
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    mg_rate_limit('admin.payment_settings.read', 'user:' . $userId, 120, 60);
    $requested=strtolower(trim((string)($_GET['mode']??'auto')));
    $mode=in_array($requested,['test','live'],true)?$requested:mg_admin_payment_default_mode($pdo);
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok(mg_admin_payment_settings_payload($pdo, $mode));
}

mg_require_method('POST');
mg_rate_limit('admin.payment_settings.write', 'user:' . $userId, 30, 300);
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $mode = (string)($input['mode'] ?? mg_admin_payment_default_mode($pdo)) === 'live' ? 'live' : 'test';
    $input['mode']=$mode;
    $hasNewSecret = trim((string)($input['secret_key'] ?? '')) !== '' || trim((string)($input['webhook_secret'] ?? '')) !== '';
    if ($hasNewSecret && mg_payment_credential_master_key() === null) {
        mg_fail('Payment credential encryption is not configured on the server. Nothing was saved. Configure MG_PAYMENT_CREDENTIAL_KEY, reload this page, and save again.', 422);
    }

    $pdo->beginTransaction();
    $input['provider_key'] = 'stripe';
    $saved = mg_payment_save_platform_config($pdo, $input, $userId);
    $verification=mg_admin_payment_verify_persistence($pdo,$input,$mode);
    if(empty($verification['ok'])){
        throw new RuntimeException('Stripe settings failed database verification: '.implode(' ',(array)$verification['errors']));
    }
    $pdo->commit();

    $readiness = mg_admin_payment_settings_payload($pdo, (string)$saved['mode']);
    $readiness['persistence']=$verification;
    $readiness['persistence_verified']=true;
    $blockers = mg_admin_payment_readiness_blockers($readiness);
    if ($blockers) {
        $readiness['save_warning'] = ucfirst($mode).' settings were saved and database-verified, but these '.$mode.' credentials still need attention: ' . implode(' ', $blockers);
    }
    mg_audit('admin.payment_settings_updated', 'payment_platform_credentials', [
        'provider' => 'stripe',
        'mode' => $saved['mode'],
        'enabled' => (bool)$saved['enabled'],
        'platform_fee_bps' => (int)$saved['platform_fee_bps'],
        'credential_source' => $saved['credential_source'],
        'secret_key_type'=>$readiness['provider']['secret_key_type']??'unknown',
        'persistence_verified'=>true,
        'ready' => $readiness['ready'],
    ], $userId);
    mg_security_log('info', 'admin.payment_settings.updated', 'Payment settings updated and database-verified.', [
        'provider' => 'stripe',
        'mode' => $saved['mode'],
        'enabled' => (bool)$saved['enabled'],
        'persistence_verified'=>true,
        'ready' => $readiness['ready'],
    ], $userId);

    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    $message=$blockers
        ? ucfirst($mode).' Stripe settings saved and verified, but readiness still needs attention.'
        : ucfirst($mode).' Stripe credentials saved and verified after a database read-back.';
    mg_ok($readiness,$message);
} catch (InvalidArgumentException|MgPaymentCredentialException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'admin.payment_settings.failed', 'Payment settings save failed.', [
        'exception_class' => $error::class,
        'reason'=>$error->getMessage(),
    ], $userId);
    mg_fail('Unable to save and verify payment settings right now. No unverified update was accepted.', 500);
}
