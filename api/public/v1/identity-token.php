<?php
declare(strict_types=1);
require_once __DIR__ . '/_public.php';
mg_require_method('POST');
$context=mg_public_context('identity:token');
$pdo=$context['pdo'];
$input=mg_input();
$code=trim((string)($input['code']??''));
$externalUserId=mb_substr(trim((string)($input['external_user_id']??'')),0,255);
if($code===''||strlen($code)<30)mg_fail('Authorization code is required.',422);
$pdo->beginTransaction();
try{
    $stmt=$pdo->prepare("SELECT * FROM developer_identity_authorizations WHERE app_id=? AND authorization_code_hash=? LIMIT 1 FOR UPDATE");
    $stmt->execute([(int)$context['app_id'],hash('sha256',$code)]);
    $authorization=$stmt->fetch();
    if(!$authorization)mg_fail('Authorization code is invalid.',404);
    if((string)$authorization['status']!=='approved')mg_fail('Authorization code has already been used or is unavailable.',409);
    if(strtotime((string)$authorization['expires_at'])<time())mg_fail('Authorization code has expired.',410);
    $resolvedExternal=$externalUserId!==''?$externalUserId:(string)($authorization['external_user_id']??'');
    if($resolvedExternal==='')mg_fail('External user ID is required.',422);
    $externalHash=hash('sha256',strtolower($resolvedExternal));
    $linkId=mg_distribution_uuid();
    $consent=['authorization_request_id'=>(string)$authorization['public_id'],'approved_at'=>(string)$authorization['completed_at'],'exchanged_at'=>gmdate('c'),'role'=>(string)$authorization['granted_role']];
    $pdo->prepare("INSERT INTO developer_identity_links (public_id,app_id,merchant_user_id,microgifter_user_id,external_user_id,external_user_hash,granted_role,status,consent_json,linked_at,updated_at) VALUES (?,?,?,?,?,?,?,'active',?,NOW(),NOW()) ON DUPLICATE KEY UPDATE microgifter_user_id=VALUES(microgifter_user_id),external_user_id=VALUES(external_user_id),granted_role=VALUES(granted_role),status='active',consent_json=VALUES(consent_json),linked_at=NOW(),revoked_at=NULL,updated_at=NOW()")
        ->execute([$linkId,(int)$context['app_id'],(int)$context['merchant_user_id'],(int)$authorization['microgifter_user_id'],$resolvedExternal,$externalHash,(string)$authorization['granted_role'],json_encode($consent,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    $fetch=$pdo->prepare("SELECT public_id FROM developer_identity_links WHERE app_id=? AND external_user_hash=? LIMIT 1");
    $fetch->execute([(int)$context['app_id'],$externalHash]);
    $linkPublicId=(string)($fetch->fetchColumn()?:$linkId);
    $pdo->prepare("UPDATE developer_identity_authorizations SET external_user_id=?,external_user_hash=?,status='exchanged',exchanged_at=NOW(),authorization_code_hash=NULL,updated_at=NOW() WHERE id=?")
        ->execute([$resolvedExternal,$externalHash,(int)$authorization['id']]);
    $profile=json_decode((string)($authorization['profile_json']??''),true);
    if(!is_array($profile))$profile=[];
    $pdo->commit();
    mg_public_log($pdo,$context,200,'identity_token_exchanged');
    mg_ok(['identity_link_id'=>$linkPublicId,'external_user_id'=>$resolvedExternal,'role'=>(string)$authorization['granted_role'],'profile'=>$profile],'Identity authorization exchanged.');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to exchange identity authorization.',500);}
