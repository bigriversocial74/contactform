<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_reward_wallet.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'GET' ? mg_require_api_user() : mg_require_api_user();
$pdo = mg_db();
$rewardId = strtolower(trim((string)($_GET['reward_id'] ?? '')));
if ($method === 'GET') {
    if ($rewardId === '') mg_fail('Reward is required.',422);
    $row = mg_rw_find($pdo,(int)$user['id'],$rewardId,false);
    mg_ok(['support_cases'=>mg_rw_support_cases($pdo,(int)$row['id'],(int)$user['id'])]);
}
if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input();
mg_require_csrf_for_write($input);
$rewardId = strtolower(trim((string)($input['reward_id'] ?? '')));
$category = strtolower(trim((string)($input['category'] ?? 'other')));
$subject = trim((string)($input['subject'] ?? ''));
$message = trim((string)($input['message'] ?? ''));
$allowed = ['claim_code','merchant_redemption','reward_missing','expired_reward','wrong_reward','regift','other'];
if (!in_array($category,$allowed,true) || mb_strlen($subject)<3 || mb_strlen($subject)>180 || mb_strlen($message)<10 || mb_strlen($message)>5000) mg_fail('Complete the support form.',422);
$pdo->beginTransaction();
try {
    $row = mg_rw_find($pdo,(int)$user['id'],$rewardId,true);
    $duplicate = $pdo->prepare("SELECT COUNT(*) FROM wallet_reward_support_cases WHERE wallet_item_id=? AND user_id=? AND status IN ('open','in_progress') AND category=?");
    $duplicate->execute([(int)$row['id'],(int)$user['id'],$category]);
    if ((int)$duplicate->fetchColumn()>0) mg_fail('An open support case already exists for this reward and issue type.',409);
    $publicId = mg_rw_uuid();
    $pdo->prepare("INSERT INTO wallet_reward_support_cases (public_id,wallet_item_id,user_id,merchant_user_id,category,status,subject,message,created_at,updated_at) VALUES (?,?,?,?,?,'open',?,?,NOW(),NOW())")
        ->execute([$publicId,(int)$row['id'],(int)$user['id'],(int)$row['merchant_user_id'],$category,$subject,$message]);
    mg_rw_event($pdo,$row,'wallet_item.support_opened',['support_case_id'=>$publicId,'category'=>$category]);
    mg_audit('account.reward_support_opened','wallet_reward_support_case',['support_case_id'=>$publicId,'reward_id'=>$rewardId,'category'=>$category],(int)$user['id']);
    $pdo->commit();
    mg_ok(['support_case_id'=>$publicId,'status'=>'open'],'Support case created.',201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','account.reward_support_failed','Reward support request failed.',['exception_class'=>$error::class],(int)$user['id']);
    mg_fail('Unable to create support case.',500);
}
