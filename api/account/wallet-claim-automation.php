<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/rewards/_wallet_lifecycle_automation.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
$pdo = mg_db();
$userId = (int)$user['id'];
$walletId = strtolower(trim((string)($input['wallet_item_id'] ?? '')));
if ($walletId === '' || strlen($walletId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $walletId)) mg_fail('Invalid wallet item.', 422);
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM wallet_items WHERE public_id=? AND user_id=? AND status='claimed' LIMIT 1 FOR UPDATE");
    $stmt->execute([$walletId,$userId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) { $pdo->rollBack(); mg_fail('Claimed wallet item not found.', 404); }
    $already = false;
    try {
        $check = $pdo->prepare("SELECT COUNT(*) FROM merchant_crm_contact_events WHERE merchant_user_id=? AND source_type='wallet_lifecycle' AND source_public_id=? AND event_type='reward.claimed'");
        $check->execute([(int)$item['merchant_user_id'],$walletId]);
        $already = (int)$check->fetchColumn() > 0;
    } catch (Throwable) { $already = false; }
    if ($already) { $pdo->commit(); mg_ok(['wallet_item_id'=>$walletId,'already_recorded'=>true], 'Claim automation already recorded.'); }
    $automation = mg_wallet_lifecycle_automation($pdo, $item, 'wallet_item.claimed', $userId, $user, ['source'=>'post_claim_automation']);
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(),(int)$item['merchant_user_id'],!empty($item['campaign_id'])?(int)$item['campaign_id']:null,(int)$item['id'],!empty($item['contact_id'])?(int)$item['contact_id']:null,'wallet_item.claimed.automation',json_encode(['wallet_item_id'=>$walletId,'lifecycle_automation'=>$automation],JSON_UNESCAPED_SLASHES)]);
    $pdo->commit();
    mg_ok(['wallet_item_id'=>$walletId,'lifecycle_automation'=>$automation], 'Claim automation recorded.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','wallet.claim_automation.failed','Unable to record wallet claim automation.',['exception_class'=>$error::class,'message'=>$error->getMessage()],$userId);
    mg_fail('Unable to record claim automation.',500);
}
