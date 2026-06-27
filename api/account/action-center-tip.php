<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once __DIR__ . '/_action_center_wallet.php';
require_once dirname(__DIR__) . '/tips/_tips.php';
require_once dirname(__DIR__) . '/tips/_notifications.php';

function mg_action_center_tip_response(array $tip,array $extra=[]): array
{
    return [
        'tip_id'=>(string)$tip['public_id'],
        'status'=>(string)$tip['status'],
        'provider_payment_id'=>$tip['provider_payment_id']??null,
        'amount_cents'=>(int)$tip['amount_cents'],
        'fee_cents'=>(int)$tip['fee_cents'],
        'net_cents'=>(int)$tip['net_cents'],
        'notification_id'=>$tip['notification_id']??$tip['alert_id']??null,
        'alert_id'=>$tip['alert_id']??null,
        'duplicate'=>(bool)$tip['duplicate'],
    ]+$extra;
}

mg_require_method('POST');
$user=mg_require_permission('tips.create');
$input=mg_input();
mg_require_csrf_for_write($input);
$actionItemId=trim((string)($input['action_item_id']??$input['id']??''));
$idempotencyKey=trim((string)($input['idempotency_key']??''));
$amountCents=(int)($input['amount_cents']??0);
if($actionItemId===''||$idempotencyKey===''||$amountCents<1)mg_fail('Action Center item, amount, and idempotency key are required.',422);
$pdo=mg_db();
try{
    $pdo->beginTransaction();
    $walletId=mg_ac_wallet_action_id($actionItemId);
    if($walletId!==null){
        $walletItem=mg_ac_wallet_load_for_user($pdo,$walletId,(int)$user['id'],mg_ac_wallet_user_email($user));
        if(!$walletItem)throw new RuntimeException('Action Center wallet reward not found.');
        if(!mg_ac_wallet_can_tip($walletItem,(int)$user['id']))throw new RuntimeException('This wallet reward is eligible for tipping after merchant redemption.');
        $target=mg_ac_wallet_merchant_target($pdo,$walletItem);
        $tip=mg_tip_create($pdo,(int)$user['id'],[
            'target_type'=>$target['target_type'],
            'target_reference'=>$target['target_reference'],
            'amount_cents'=>$amountCents,
            'currency'=>(string)($input['currency']??$walletItem['currency_snapshot']??'USD'),
            'funding_type'=>(string)($input['funding_type']??'wallet'),
            'provider_payment_id'=>$input['provider_payment_id']??null,
            'idempotency_key'=>$idempotencyKey,
            'metadata'=>[
                'action_item_id'=>$actionItemId,
                'wallet_item_id'=>(string)$walletItem['public_id'],
                'campaign_id'=>(string)($walletItem['campaign_public_id']??''),
                'merchant_user_id'=>$target['merchant_user_id'],
                'merchant_workspace_id'=>$target['merchant_workspace_id'],
                'message'=>trim((string)($input['message']??'')),
            ],
        ]);
        if((string)$tip['status']==='posted'&&empty($tip['duplicate'])){
            $tip['notification_id']=mg_tip_notify_recipient($pdo,$tip);
            $tip['alert_id']=$tip['notification_id'];
            mg_ac_wallet_event($pdo,$walletItem,'wallet_item.tip_posted',['tip_id'=>(string)$tip['public_id'],'sender_user_id'=>(int)$user['id'],'amount_cents'=>(int)$tip['amount_cents'],'currency'=>(string)$tip['currency'],'notification_id'=>$tip['notification_id']]);
        }
        $pdo->commit();
        mg_ok(mg_action_center_tip_response($tip,['wallet_item_id'=>$walletId]),$tip['duplicate']?'Existing tip returned.':'Tip created.',$tip['duplicate']?200:201);
    }

    $stmt=$pdo->prepare("SELECT ac.can_tip,ac.folder,ac.state,i.public_id instance_id
        FROM microgift_inbox_items ac
        INNER JOIN microgift_instances i ON i.id=ac.instance_id
        WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$actionItemId,(int)$user['id']]);
    $item=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$item)throw new RuntimeException('Action Center item not found.');
    if((int)$item['can_tip']!==1||(string)$item['folder']!=='claimed'||(string)$item['state']!=='redeemed')throw new RuntimeException('This Action Center item is not eligible for tipping.');
    $tip=mg_tip_create($pdo,(int)$user['id'],[
        'target_type'=>'gift',
        'target_reference'=>(string)$item['instance_id'],
        'amount_cents'=>$amountCents,
        'currency'=>(string)($input['currency']??'USD'),
        'funding_type'=>(string)($input['funding_type']??'wallet'),
        'provider_payment_id'=>$input['provider_payment_id']??null,
        'idempotency_key'=>$idempotencyKey,
        'metadata'=>['action_item_id'=>$actionItemId,'message'=>trim((string)($input['message']??''))],
    ]);
    if((string)$tip['status']==='posted'&&empty($tip['duplicate'])){
        $tip['notification_id']=mg_tip_notify_recipient($pdo,$tip);
        $tip['alert_id']=$tip['notification_id'];
    }
    $pdo->commit();
    mg_ok(mg_action_center_tip_response($tip),$tip['duplicate']?'Existing tip returned.':'Tip created.',$tip['duplicate']?200:201);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','action_center.tip_failed','Action Center tip failed.',['exception'=>$e->getMessage()],(int)$user['id']);mg_fail('Unable to create this tip.',500);}
