<?php
declare(strict_types=1);
require_once __DIR__ . '/_public.php';
require_once dirname(__DIR__, 2) . '/distribution/_developer_webhooks.php';

function mg_identity_redirect(string $url,array $params):never{
    header('Cache-Control: no-store, private');
    header('Location: '.$url.(str_contains($url,'?')?'&':'?').http_build_query($params),true,302);
    exit;
}

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
$requestId=strtolower(trim((string)($input['request']??'')));
$action=trim((string)($input['action']??'approve'));
if(strlen($requestId)!==36||preg_match('/^[a-f0-9-]{36}$/',$requestId)!==1||!in_array($action,['approve','cancel'],true))mg_fail('Invalid identity authorization request.',422);
$pdo=mg_db();$pdo->beginTransaction();
try{
    $stmt=$pdo->prepare("SELECT dia.*,mda.public_id app_public_id,mda.name app_name,mda.status app_status FROM developer_identity_authorizations dia INNER JOIN merchant_developer_apps mda ON mda.id=dia.app_id WHERE dia.public_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$requestId]);$request=$stmt->fetch();
    if(!$request)mg_fail('Authorization request not found.',404);
    if((string)$request['status']!=='pending')mg_fail('Authorization request already completed.',409);
    if(strtotime((string)$request['expires_at'])<time()){
        $pdo->prepare("UPDATE developer_identity_authorizations SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$request['id']]);
        $pdo->commit();mg_identity_redirect((string)$request['return_url'],['status'=>'expired','state'=>(string)($request['state']??'')]);
    }
    if((string)$request['app_status']!=='active')mg_fail('Developer app is not active.',409);
    if($action==='cancel'){
        $pdo->prepare("UPDATE developer_identity_authorizations SET status='cancelled',completed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$request['id']]);
        $pdo->commit();mg_identity_redirect((string)$request['return_url'],['status'=>'cancelled','state'=>(string)($request['state']??'')]);
    }
    $requestedRole=(string)$request['requested_role'];
    $roles=is_array($user['roles']??null)?array_map('strval',$user['roles']):[];
    $isMerchant=in_array('merchant',$roles,true)||in_array('admin',$roles,true)||in_array('super_admin',$roles,true);
    if($requestedRole==='merchant'&&!$isMerchant)mg_fail('A Microgifter merchant account is required.',403);
    $grantedRole=$requestedRole==='merchant'?'merchant':'participant';
    $code=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
    $profile=['user_id'=>(int)$user['id'],'display_name'=>(string)($user['display_name']??$user['full_name']??''),'email'=>(string)($user['email']??''),'role'=>$grantedRole];
    $pdo->prepare("UPDATE developer_identity_authorizations SET microgifter_user_id=?,authorization_code_hash=?,granted_role=?,status='approved',profile_json=?,completed_at=NOW(),updated_at=NOW() WHERE id=?")
        ->execute([(int)$user['id'],hash('sha256',$code),$grantedRole,json_encode($profile,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),(int)$request['id']]);
    $pdo->commit();
    mg_audit('developer_identity_authorized','developer_identity_authorization',['app_id'=>(string)$request['app_public_id'],'authorization_request_id'=>$requestId,'granted_role'=>$grantedRole],(int)$user['id']);
    mg_identity_redirect((string)$request['return_url'],['status'=>'approved','code'=>$code,'state'=>(string)($request['state']??'')]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to complete identity authorization.',500);}
