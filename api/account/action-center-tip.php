<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once dirname(__DIR__) . '/tips/_tips.php';
require_once dirname(__DIR__) . '/tips/_notifications.php';

function mg_action_center_tip_wallet_action_id(string $actionItemId): ?string
{
    $value=trim($actionItemId);
    if(!str_starts_with($value,'wallet-'))return null;
    $walletId=strtolower(substr($value,7));
    return preg_match('/^[a-f0-9-]{36}$/',$walletId)===1?$walletId:null;
}

function mg_action_center_tip_load_wallet_item(PDO $pdo,string $walletId,int $userId,string $userEmail): ?array
{
    $stmt=$pdo->prepare("SELECT wi.*,cc.email contact_email,c.public_id campaign_public_id,c.title campaign_title,rt.public_id reward_template_public_id,rt.title reward_template_title,u.display_name merchant_display_name,u.full_name merchant_full_name
        FROM wallet_items wi
        LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id
        LEFT JOIN campaigns c ON c.id=wi.campaign_id
        LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id
        LEFT JOIN users u ON u.id=wi.merchant_user_id
        WHERE wi.public_id=? AND wi.status<>'cancelled'
          AND (wi.user_id=? OR (?<>'' AND (LOWER(cc.email)=? OR LOWER(wi.source_id)=?)))
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$walletId,$userId,$userEmail,$userEmail,$userEmail]);
    $item=$stmt->fetch(PDO::FETCH_ASSOC);
    return $item?:null;
}

function mg_action_center_tip_wallet_target(PDO $pdo,array $item): array
{
    $merchantUserId=(int)($item['merchant_user_id']??0);
    if($merchantUserId<1)throw new RuntimeException('Tip merchant is unavailable.');
    try{
        $stmt=$pdo->prepare("SELECT public_id FROM merchant_workspaces WHERE merchant_user_id=? AND status='active' ORDER BY id ASC LIMIT 1");
        $stmt->execute([$merchantUserId]);
        $workspacePublicId=trim((string)($stmt->fetchColumn()?:''));
        if($workspacePublicId!=='')return ['target_type'=>'merchant','target_reference'=>$workspacePublicId];
    }catch(Throwable){}
    return ['target_type'=>'profile','target_reference'=>(string)$merchantUserId];
}

function mg_action_center_tip_wallet_event(PDO $pdo,array $item,string $eventType,array $context=[]): void
{
    if(empty($item['campaign_id']))return;
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([
            mg_public_uuid(),
            (int)$item['merchant_user_id'],
            (int)$item['campaign_id'],
            (int)$item['id'],
            $item['contact_id']===null?null:(int)$item['contact_id'],
            $eventType,
            json_encode($context+['wallet_item_id'=>(string)$item['public_id']],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
        ]);
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
$pdo->beginTransaction();
try{
    $walletId=mg_action_center_tip_wallet_action_id($actionItemId);
    if($walletId!==null){
        $walletItem=mg_action_center_tip_load_wallet_item($pdo,$walletId,(int)$user['id'],strtolower(trim((string)($user['email']??''))));
        if(!$walletItem)throw new RuntimeException('Action Center wallet reward not found.');
        if(!in_array((string)$walletItem['status'],['claimed','redeemed'],true))throw new RuntimeException('This wallet reward is not eligible for tipping yet.');
        $target=mg_action_center_tip_wallet_target($pdo,$walletItem);
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
                'message'=>trim((string)($input['message']??'')),
            ],
        ]);
        if((string)$tip['status']==='posted'&&empty($tip['duplicate'])){
            $tip['alert_id']=mg_tip_notify_recipient($pdo,$tip);
            mg_action_center_tip_wallet_event($pdo,$walletItem,'wallet_item.tip_posted',['tip_id'=>(string)$tip['public_id'],'sender_user_id'=>(int)$user['id'],'amount_cents'=>(int)$tip['amount_cents'],'currency'=>(string)$tip['currency']]);
        }
        $pdo->commit();
        mg_ok(['tip_id'=>$tip['public_id'],'status'=>$tip['status'],'provider_payment_id'=>$tip['provider_payment_id'],'amount_cents'=>(int)$tip['amount_cents'],'fee_cents'=>(int)$tip['fee_cents'],'net_cents'=>(int)$tip['net_cents'],'alert_id'=>$tip['alert_id']??null,'duplicate'=>(bool)$tip['duplicate'],'wallet_item_id'=>$walletId],$tip['duplicate']?'Existing tip returned.':'Tip created.',$tip['duplicate']?200:201);
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
    if((string)$tip['status']==='posted'&&empty($tip['duplicate']))$tip['alert_id']=mg_tip_notify_recipient($pdo,$tip);
    $pdo->commit();
    mg_ok(['tip_id'=>$tip['public_id'],'status'=>$tip['status'],'provider_payment_id'=>$tip['provider_payment_id'],'amount_cents'=>(int)$tip['amount_cents'],'fee_cents'=>(int)$tip['fee_cents'],'net_cents'=>(int)$tip['net_cents'],'alert_id'=>$tip['alert_id']??null,'duplicate'=>(bool)$tip['duplicate']],$tip['duplicate']?'Existing tip returned.':'Tip created.',$tip['duplicate']?200:201);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','action_center.tip_failed','Action Center tip failed.',['exception'=>$e->getMessage()],(int)$user['id']);mg_fail('Unable to create this tip.',500);}