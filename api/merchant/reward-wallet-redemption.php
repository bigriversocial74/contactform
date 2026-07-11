<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/account/_reward_wallet.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_merchant_require_permission($method === 'GET' ? 'merchant.claims.view' : 'merchant.claims.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo,$user);

if ($method === 'GET') {
    $tokenStmt = $pdo->prepare("SELECT wrct.public_id,wrct.token_last4,wrct.status,wrct.expires_at,wrct.used_at,wrct.created_at,wi.public_id reward_id,wi.title_snapshot,wi.status reward_status,wi.value_cents_snapshot,wi.currency_snapshot,COALESCE(cc.name,cc.email,u.display_name,u.full_name,'Customer') customer_name,cc.email customer_email FROM wallet_reward_claim_tokens wrct INNER JOIN wallet_items wi ON wi.id=wrct.wallet_item_id AND wi.merchant_user_id=wrct.merchant_user_id INNER JOIN users u ON u.id=wrct.user_id LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id WHERE wrct.merchant_user_id=? ORDER BY wrct.created_at DESC,wrct.id DESC LIMIT 100");
    $tokenStmt->execute([$merchantId]);
    $tokens = $tokenStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $supportStmt = $pdo->prepare("SELECT wrsc.public_id,wrsc.category,wrsc.status,wrsc.subject,wrsc.message,wrsc.resolution_note,wrsc.resolved_at,wrsc.created_at,wi.public_id reward_id,wi.title_snapshot,COALESCE(cc.name,cc.email,u.display_name,u.full_name,'Customer') customer_name,cc.email customer_email FROM wallet_reward_support_cases wrsc INNER JOIN wallet_items wi ON wi.id=wrsc.wallet_item_id AND wi.merchant_user_id=wrsc.merchant_user_id INNER JOIN users u ON u.id=wrsc.user_id LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id WHERE wrsc.merchant_user_id=? ORDER BY FIELD(wrsc.status,'open','in_progress','resolved','closed'),wrsc.created_at ASC LIMIT 100");
    $supportStmt->execute([$merchantId]);
    $cases = $supportStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $totals = ['active_codes'=>0,'redeemed'=>0,'expired'=>0,'open_support'=>0];
    foreach($tokens as $token){$effective=(string)$token['status'];if($effective==='active'&&strtotime((string)$token['expires_at'])<=time())$effective='expired';if($effective==='active')$totals['active_codes']++;if($effective==='used')$totals['redeemed']++;if($effective==='expired')$totals['expired']++;$token['effective_status']=$effective;}
    foreach($cases as $case)if(in_array((string)$case['status'],['open','in_progress'],true))$totals['open_support']++;
    mg_ok(['claim_tokens'=>$tokens,'support_cases'=>$cases,'totals'=>$totals,'schema_ready'=>true]);
}
if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string)($input['action'] ?? 'redeem')));

if ($action === 'resolve_support') {
    $caseId = strtolower(trim((string)($input['support_case_id'] ?? '')));
    $resolution = trim((string)($input['resolution_note'] ?? ''));
    if (strlen($caseId)!==36 || preg_match('/^[a-f0-9-]{36}$/',$caseId)!==1 || mb_strlen($resolution)<3 || mb_strlen($resolution)>5000) mg_fail('Complete the support resolution.',422);
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare("SELECT wrsc.*,wi.public_id reward_id,wi.campaign_id,wi.contact_id FROM wallet_reward_support_cases wrsc INNER JOIN wallet_items wi ON wi.id=wrsc.wallet_item_id AND wi.merchant_user_id=wrsc.merchant_user_id WHERE wrsc.public_id=? AND wrsc.merchant_user_id=? LIMIT 1 FOR UPDATE");
        $stmt->execute([$caseId,$merchantId]);$case=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$case)mg_fail('Support case not found.',404);
        if(in_array((string)$case['status'],['resolved','closed'],true))mg_fail('Support case is already resolved.',409);
        $pdo->prepare("UPDATE wallet_reward_support_cases SET status='resolved',resolution_note=?,resolved_by_user_id=?,resolved_at=NOW(),updated_at=NOW() WHERE id=? AND merchant_user_id=?")->execute([$resolution,$merchantId,(int)$case['id'],$merchantId]);
        if(!empty($case['campaign_id'])){
            $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')->execute([mg_rw_uuid(),$merchantId,(int)$case['campaign_id'],(int)$case['wallet_item_id'],$case['contact_id']??null,'wallet_item.support_resolved',json_encode(['support_case_id'=>$caseId,'resolution_note'=>$resolution],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
        }
        mg_audit('merchant.reward_support_resolved','wallet_reward_support_case',['support_case_id'=>$caseId,'reward_id'=>(string)$case['reward_id']],$merchantId);
        $pdo->commit();mg_ok(['support_case_id'=>$caseId,'status'=>'resolved'],'Support case resolved.');
    } catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','merchant.reward_support_resolution_failed','Unable to resolve reward support case.',['exception_class'=>$error::class],$merchantId);mg_fail('Unable to resolve support case.',500);}
}

if ($action !== 'redeem') mg_fail('Invalid redemption action.',422);
$code = strtoupper(preg_replace('/[^A-Z0-9]/','',trim((string)($input['claim_code'] ?? ''))) ?? '');
if (strlen($code)!==12) mg_fail('Enter the 12-character claim code.',422);
$hash = hash('sha256',$code);
$pdo->beginTransaction();
try {
    $stmt=$pdo->prepare("SELECT wrct.*,wi.public_id reward_id,wi.status reward_status,wi.expires_at reward_expires_at,wi.campaign_id,wi.contact_id,wi.title_snapshot FROM wallet_reward_claim_tokens wrct INNER JOIN wallet_items wi ON wi.id=wrct.wallet_item_id AND wi.merchant_user_id=wrct.merchant_user_id WHERE wrct.token_hash=? AND wrct.merchant_user_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$hash,$merchantId]);$token=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$token)mg_fail('Claim code is invalid for this merchant.',404);
    if((string)$token['status']==='used')mg_fail('Claim code has already been redeemed.',409);
    if((string)$token['status']!=='active')mg_fail('Claim code is no longer active.',409);
    if(strtotime((string)$token['expires_at'])<=time()){
        $pdo->prepare("UPDATE wallet_reward_claim_tokens SET status='expired',updated_at=NOW() WHERE id=? AND merchant_user_id=?")->execute([(int)$token['id'],$merchantId]);
        $pdo->commit();
        mg_fail('Claim code has expired. Ask the customer to generate a new code.',409);
    }
    if(!empty($token['reward_expires_at'])&&strtotime((string)$token['reward_expires_at'])<=time())mg_fail('Reward has expired.',409);
    if(in_array((string)$token['reward_status'],['redeemed','expired','cancelled'],true))mg_fail('Reward is not redeemable.',409);
    $pdo->prepare("UPDATE wallet_items SET status='redeemed',updated_at=NOW() WHERE id=? AND merchant_user_id=? AND status IN ('claimed','viewed','issued')")->execute([(int)$token['wallet_item_id'],$merchantId]);
    $pdo->prepare("UPDATE wallet_reward_claim_tokens SET status='used',used_at=NOW(),updated_at=NOW() WHERE id=? AND merchant_user_id=? AND status='active'")->execute([(int)$token['id'],$merchantId]);
    $pdo->prepare("UPDATE wallet_reward_claim_tokens SET status='revoked',revoked_at=NOW(),updated_at=NOW() WHERE wallet_item_id=? AND merchant_user_id=? AND id<>? AND status='active'")->execute([(int)$token['wallet_item_id'],$merchantId,(int)$token['id']]);
    if(!empty($token['campaign_id']))$pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')->execute([mg_rw_uuid(),$merchantId,(int)$token['campaign_id'],(int)$token['wallet_item_id'],$token['contact_id']??null,'wallet_item.redeemed',json_encode(['reward_id'=>(string)$token['reward_id'],'claim_token_id'=>(string)$token['public_id']],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    mg_audit('merchant.reward_redeemed','wallet_item',['reward_id'=>(string)$token['reward_id'],'claim_token_id'=>(string)$token['public_id']],$merchantId);
    $pdo->commit();
    mg_ok(['reward_id'=>(string)$token['reward_id'],'status'=>'redeemed','title'=>(string)$token['title_snapshot'],'redeemed_at'=>gmdate('c')],'Reward redeemed.');
} catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','merchant.reward_redemption_failed','Reward redemption failed.',['exception_class'=>$error::class],$merchantId);mg_fail('Unable to redeem reward.',500);}
