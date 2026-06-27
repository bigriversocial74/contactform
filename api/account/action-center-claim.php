<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once dirname(__DIR__) . '/microgifts/_lifecycle.php';
require_once dirname(__DIR__) . '/microgifts/_idempotency.php';
require_once dirname(__DIR__) . '/microgifts/_golden_path_integrity.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';

function mg_action_center_wallet_action_id(string $actionItemId): ?string
{
    $value=trim($actionItemId);
    if(!str_starts_with($value,'wallet-'))return null;
    $walletId=strtolower(substr($value,7));
    return preg_match('/^[a-f0-9-]{36}$/',$walletId)===1?$walletId:null;
}

function mg_action_center_wallet_event(PDO $pdo,array $item,string $eventType,array $context=[]): void
{
    if(empty($item['campaign_id']))return;
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([
            mg_microgift_uuid(),
            (int)$item['merchant_user_id'],
            (int)$item['campaign_id'],
            (int)$item['id'],
            $item['contact_id']===null?null:(int)$item['contact_id'],
            $eventType,
            mg_microgift_json($context+['wallet_item_id'=>(string)$item['public_id']]),
        ]);
}

function mg_action_center_load_wallet_item_for_user(PDO $pdo,string $walletId,int $userId,string $userEmail): ?array
{
    $stmt=$pdo->prepare("SELECT wi.*,cc.email contact_email,c.public_id campaign_public_id,rt.public_id reward_template_public_id,rt.title reward_template_title,rt.description reward_template_description,rt.redemption_instructions
        FROM wallet_items wi
        LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id
        LEFT JOIN campaigns c ON c.id=wi.campaign_id
        LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id
        WHERE wi.public_id=? AND wi.status<>'cancelled'
          AND (wi.user_id=? OR (?<>'' AND (LOWER(cc.email)=? OR LOWER(wi.source_id)=?)))
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$walletId,$userId,$userEmail,$userEmail,$userEmail]);
    $item=$stmt->fetch(PDO::FETCH_ASSOC);
    return $item?:null;
}

function mg_action_center_claim_wallet_item(PDO $pdo,array $item,int $userId,string $actionItemId,string $idempotencyKey): array
{
    $status=(string)($item['status']??'issued');
    if(!empty($item['expires_at'])&&strtotime((string)$item['expires_at'])<time()&&!in_array($status,['redeemed','expired','cancelled'],true)){
        $pdo->prepare("UPDATE wallet_items SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$item['id']]);
        mg_action_center_wallet_event($pdo,$item,'wallet_item.expired',['action_item_id'=>$actionItemId]);
        throw new RuntimeException('Wallet item has expired.');
    }
    if(in_array($status,['redeemed','expired','cancelled'],true))throw new RuntimeException('Wallet item is not claimable.');
    $duplicate=$status==='claimed'&&(int)($item['user_id']??0)===$userId;
    if(!$duplicate){
        $pdo->prepare("UPDATE wallet_items SET user_id=?,status='claimed',viewed_at=COALESCE(viewed_at,NOW()),claimed_at=COALESCE(claimed_at,NOW()),updated_at=NOW() WHERE id=?")
            ->execute([$userId,(int)$item['id']]);
        mg_action_center_wallet_event($pdo,$item,'wallet_item.claimed',['action_item_id'=>$actionItemId,'idempotency_key'=>$idempotencyKey,'claimed_by_user_id'=>$userId]);
    }
    return [
        'wallet_item_id'=>(string)$item['public_id'],
        'action_item_id'=>$actionItemId,
        'status'=>'claimed',
        'duplicate'=>$duplicate,
        'claim_mode'=>'wallet_item',
        'action_center'=>[
            'recipient_item_id'=>$actionItemId,
            'wallet_item_id'=>(string)$item['public_id'],
        ],
    ];
}

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();

$actionItemId=trim((string)($input['action_item_id']??$input['id']??''));
$idempotencyKey=trim((string)($input['idempotency_key']??''));
if($actionItemId===''||$idempotencyKey==='')mg_fail('Action Center item id and idempotency key are required.',422);
if(mb_strlen($idempotencyKey)>190)mg_fail('A valid idempotency key is required.',422);

try{
    $pdo->beginTransaction();
    $walletId=mg_action_center_wallet_action_id($actionItemId);
    if($walletId!==null){
        $item=mg_action_center_load_wallet_item_for_user($pdo,$walletId,(int)$user['id'],strtolower(trim((string)($user['email']??''))));
        if(!$item)throw new RuntimeException('Action Center item not found.');
        $result=mg_action_center_claim_wallet_item($pdo,$item,(int)$user['id'],$actionItemId,$idempotencyKey);
        $pdo->commit();
        mg_audit('action_center.wallet_item_claimed','wallet_item',['wallet_item_id'=>$walletId,'action_item_id'=>$actionItemId,'duplicate'=>$result['duplicate'],'claim_mode'=>'wallet_item'],(int)$user['id']);
        mg_ok($result,'Wallet reward claimed.',$result['duplicate']?200:201);
    }

    $stmt=$pdo->prepare("SELECT ac.folder,ac.state,ac.user_id,i.* FROM microgift_inbox_items ac INNER JOIN microgift_instances i ON i.id=ac.instance_id WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL LIMIT 1 FOR UPDATE");
    $stmt->execute([$actionItemId,(int)$user['id']]);
    $instance=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$instance)throw new RuntimeException('Action Center item not found.');
    if((string)$instance['folder']!=='inbox')throw new RuntimeException('This Action Center item cannot be claimed.');
    if(!in_array((string)$instance['status'],['issued','delivered','claim_pending'],true))throw new RuntimeException('Microgift is not in a claimable lifecycle state.');
    if((string)$instance['recipient_policy']==='named_user'&&(int)$instance['recipient_user_id']!==(int)$user['id'])throw new RuntimeException('Microgift is assigned to another recipient.');
    if((int)($instance['recipient_user_id']??0)>0&&(int)$instance['recipient_user_id']!==(int)$user['id'])throw new RuntimeException('You are not the recipient of this Microgift.');

    $input['instance_id']=(string)$instance['public_id'];
    $existing=mg_microgift_assert_claim_replay($pdo,$idempotencyKey,(string)$instance['public_id'],(int)$user['id']);
    if($existing){
        $result=['claim_id'=>$existing['public_id'],'instance_id'=>(string)$instance['public_id'],'status'=>$existing['status'],'duplicate'=>true];
    }elseif(trim((string)($input['code']??''))===''){
        $result=mg_microgift_integrity_claim($pdo,(int)$user['id'],$input);
    }else{
        $result=mg_microgift_claim($pdo,(int)$user['id'],$input);
    }
    $instance=mg_microgift_load_instance($pdo,(string)$result['instance_id']);
    $result['action_center']=mg_action_center_project_lifecycle($pdo,$instance);
    $pdo->commit();

    mg_audit('action_center.microgift_claimed','microgift_instance',['instance_id'=>$result['instance_id'],'claim_id'=>$result['claim_id'],'action_item_id'=>$actionItemId,'duplicate'=>$result['duplicate'],'claim_mode'=>$result['claim_mode']??null],(int)$user['id']);
    mg_ok($result,'Microgift claim processed.',$result['duplicate']?200:201);
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),409);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','action_center.claim_failed','Action Center claim failed.',['exception'=>$error->getMessage()],(int)$user['id']);
    mg_fail('Unable to claim this Microgift.',500);
}
