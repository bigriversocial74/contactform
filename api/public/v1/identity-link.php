<?php
declare(strict_types=1);
require_once __DIR__ . '/_public.php';
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$context=mg_public_context($method==='POST'?'identity:unlink':'identity:read');
$pdo=$context['pdo'];
$input=$method==='POST'?mg_input():$_GET;
$externalUserId=mb_substr(trim((string)($input['external_user_id']??'')),0,255);
if($externalUserId==='')mg_fail('External user ID is required.',422);
$externalHash=hash('sha256',strtolower($externalUserId));
if($method==='GET'){
    $stmt=$pdo->prepare("SELECT public_id,external_user_id,granted_role,status,linked_at,revoked_at,updated_at FROM developer_identity_links WHERE app_id=? AND external_user_hash=? LIMIT 1");
    $stmt->execute([(int)$context['app_id'],$externalHash]);
    $link=$stmt->fetch();
    mg_ok(['linked'=>is_array($link)&&(string)$link['status']==='active','identity_link'=>$link?:null]);
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$stmt=$pdo->prepare("SELECT id,public_id,status FROM developer_identity_links WHERE app_id=? AND external_user_hash=? LIMIT 1");
$stmt->execute([(int)$context['app_id'],$externalHash]);
$link=$stmt->fetch();
if(!$link)mg_fail('Identity link not found.',404);
if((string)$link['status']!=='revoked'){
    $pdo->prepare("UPDATE developer_identity_links SET status='revoked',revoked_at=NOW(),updated_at=NOW() WHERE id=? AND app_id=?")->execute([(int)$link['id'],(int)$context['app_id']]);
}
mg_public_log($pdo,$context,200,'identity_link_revoked');
mg_ok(['identity_link_id'=>(string)$link['public_id'],'status'=>'revoked'],'Identity link revoked.');
