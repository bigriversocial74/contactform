<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_reward_wallet.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);
$rewardId = strtolower(trim((string)($input['reward_id'] ?? '')));
$action = strtolower(trim((string)($input['action'] ?? '')));
$minutes = max(5,min(60,(int)($input['expires_minutes'] ?? 15)));
if (!in_array($action,['mark_viewed','claim','refresh_code'],true)) mg_fail('Invalid reward action.',422);
$pdo = mg_db();
$pdo->beginTransaction();
try {
    $row = mg_rw_find($pdo,(int)$user['id'],$rewardId,true);
    $effective = mg_rw_effective_status($row);
    if ($action === 'mark_viewed') {
        if ($effective === 'issued') {
            $pdo->prepare("UPDATE wallet_items SET status='viewed',updated_at=NOW() WHERE id=? AND user_id=? AND status='issued'")->execute([(int)$row['id'],(int)$user['id']]);
            mg_rw_event($pdo,$row,'wallet_item.viewed',['reward_id'=>$rewardId]);
            mg_audit('account.reward_viewed','wallet_item',['reward_id'=>$rewardId],(int)$user['id']);
        }
        $pdo->commit();
        mg_ok(['reward_id'=>$rewardId,'status'=>$effective==='issued'?'viewed':$effective],'Reward opened.');
    }

    if (in_array($effective,['redeemed','expired','cancelled'],true)) mg_fail('This reward can no longer generate a claim code.',409);
    $rate = $pdo->prepare('SELECT COUNT(*) total,MAX(created_at) latest FROM wallet_reward_claim_tokens WHERE wallet_item_id=? AND user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)');
    $rate->execute([(int)$row['id'],(int)$user['id']]);
    $rateRow = $rate->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'latest'=>null];
    if ((int)$rateRow['total'] >= 10) mg_fail('Too many claim-code requests. Try again later.',429);
    if (!empty($rateRow['latest']) && strtotime((string)$rateRow['latest']) > time()-5) mg_fail('Wait a few seconds before generating another claim code.',429);

    $pdo->prepare("UPDATE wallet_reward_claim_tokens SET status='revoked',revoked_at=NOW(),updated_at=NOW() WHERE wallet_item_id=? AND user_id=? AND status='active'")->execute([(int)$row['id'],(int)$user['id']]);
    $plain = mg_rw_plain_code();
    $normalized = strtoupper(preg_replace('/[^A-Z0-9]/','',$plain) ?? '');
    $publicId = mg_rw_uuid();
    $expiresAt = gmdate('Y-m-d H:i:s',time()+$minutes*60);
    $pdo->prepare("INSERT INTO wallet_reward_claim_tokens (public_id,wallet_item_id,user_id,merchant_user_id,token_hash,token_last4,status,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,'active',?,NOW(),NOW())")
        ->execute([$publicId,(int)$row['id'],(int)$user['id'],(int)$row['merchant_user_id'],hash('sha256',$normalized),substr($normalized,-4),$expiresAt]);
    $nextStatus = $effective === 'issued' || $effective === 'viewed' ? 'claimed' : $effective;
    if ($nextStatus === 'claimed' && (string)$row['status'] !== 'claimed') {
        $pdo->prepare("UPDATE wallet_items SET status='claimed',updated_at=NOW() WHERE id=? AND user_id=? AND status IN ('issued','viewed')")->execute([(int)$row['id'],(int)$user['id']]);
    }
    mg_rw_event($pdo,$row,$action==='refresh_code'?'wallet_item.claim_code_refreshed':'wallet_item.claimed',['reward_id'=>$rewardId,'claim_token_id'=>$publicId,'expires_at'=>$expiresAt]);
    mg_audit($action==='refresh_code'?'account.reward_claim_code_refreshed':'account.reward_claimed','wallet_item',['reward_id'=>$rewardId,'claim_token_id'=>$publicId,'expires_at'=>$expiresAt],(int)$user['id']);
    $pdo->commit();
    mg_ok(['reward_id'=>$rewardId,'status'=>'claimed','claim_code'=>$plain,'claim_token_id'=>$publicId,'expires_at'=>$expiresAt,'single_use'=>true],$action==='refresh_code'?'New claim code generated.':'Reward ready to redeem.',201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','account.reward_wallet_action_failed','Reward wallet action failed.',['exception_class'=>$error::class],(int)$user['id']);
    mg_fail('Unable to update this reward.',500);
}
