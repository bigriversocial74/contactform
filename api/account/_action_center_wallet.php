<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';

function mg_ac_wallet_action_id(string $actionItemId): ?string
{
    $value=trim($actionItemId);
    if(!str_starts_with($value,'wallet-'))return null;
    $walletId=strtolower(substr($value,7));
    return preg_match('/^[a-f0-9-]{36}$/',$walletId)===1?$walletId:null;
}

function mg_ac_wallet_user_email(array $user): string { return strtolower(trim((string)($user['email']??''))); }
function mg_ac_wallet_uuid(): string
{
    if(function_exists('mg_public_uuid'))return mg_public_uuid();
    if(function_exists('mg_microgift_uuid'))return mg_microgift_uuid();
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',random_int(0,0xffff),random_int(0,0xffff),random_int(0,0xffff),random_int(0,0x0fff)|0x4000,random_int(0,0x3fff)|0x8000,random_int(0,0xffff),random_int(0,0xffff),random_int(0,0xffff));
}
function mg_ac_wallet_json(array $value): string
{
    if(function_exists('mg_microgift_json'))return mg_microgift_json($value);
    return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}
function mg_ac_wallet_expired(array $row): bool
{
    $expiresAt=trim((string)($row['expires_at']??''));
    $status=(string)($row['status']??'issued');
    return $expiresAt!==''&&strtotime($expiresAt)!==false&&strtotime($expiresAt)<time()&&!in_array($status,['redeemed','cancelled'],true);
}
function mg_ac_wallet_state(array $row): string
{
    if(mg_ac_wallet_expired($row))return 'expired';
    $status=(string)($row['status']??'issued');
    if(in_array($status,['issued','viewed'],true))return 'claimable';
    if($status==='claimed')return 'redeemable';
    if($status==='redeemed')return 'redeemed';
    return $status!==''?$status:'received';
}
function mg_ac_wallet_folder(array $row): string
{
    $status=(string)($row['status']??'issued');
    if(mg_ac_wallet_expired($row)||in_array($status,['claimed','redeemed'],true))return 'claimed';
    return 'inbox';
}
function mg_ac_wallet_can_claim(array $row,int $userId): bool
{
    $status=(string)($row['status']??'issued');
    if(mg_ac_wallet_expired($row))return false;
    if(!in_array($status,['issued','viewed','claimed'],true))return false;
    return (int)($row['user_id']??0)<1||(int)($row['user_id']??0)===$userId;
}
function mg_ac_wallet_can_regift(array $row,int $userId): bool
{
    if(mg_ac_wallet_expired($row))return false;
    if((int)($row['user_id']??0)!==$userId)return false;
    return in_array((string)($row['status']??'issued'),['issued','viewed','claimed'],true);
}
function mg_ac_wallet_can_message(array $row,int $userId): bool
{
    if((int)($row['user_id']??0)!==$userId)return false;
    return in_array((string)($row['status']??''),['claimed','redeemed'],true)&&!mg_ac_wallet_expired($row);
}
function mg_ac_wallet_can_tip(array $row,int $userId): bool
{
    if((int)($row['user_id']??0)!==$userId)return false;
    return (string)($row['status']??'')==='redeemed';
}
function mg_ac_wallet_identity_where(string $email,array &$params): string
{
    $where='(wi.user_id=?';
    if($email!==''){
        $where.=' OR LOWER(cc.email)=? OR LOWER(wi.source_id)=?';
        $params[]=$email;
        $params[]=$email;
    }
    return $where.')';
}
function mg_ac_wallet_select_sql(): string
{
    return "SELECT wi.*,rt.public_id reward_template_public_id,rt.title reward_template_title,rt.description reward_template_description,rt.redemption_instructions,rt.reward_type reward_template_reward_type,rt.metadata_json reward_template_metadata_json,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type,u.display_name merchant_label,u.full_name merchant_full_name,u.email merchant_email,cc.email contact_email,cc.name contact_name
        FROM wallet_items wi
        LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id
        LEFT JOIN campaigns c ON c.id=wi.campaign_id
        LEFT JOIN users u ON u.id=wi.merchant_user_id
        LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id";
}
function mg_ac_wallet_load_for_user(PDO $pdo,string $walletId,int $userId,string $userEmail,bool $forUpdate=true): ?array
{
    $params=[$walletId,$userId];
    $where=["wi.public_id=?","wi.status<>'cancelled'",mg_ac_wallet_identity_where(strtolower(trim($userEmail)),$params)];
    $sql=mg_ac_wallet_select_sql().' WHERE '.implode(' AND ',$where).' LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    $item=$stmt->fetch(PDO::FETCH_ASSOC);
    return $item?:null;
}
function mg_ac_wallet_mark_expired(PDO $pdo,array $item,string $actionItemId=''): void
{
    $pdo->prepare("UPDATE wallet_items SET status='expired',updated_at=NOW() WHERE id=? AND status NOT IN ('redeemed','expired','cancelled')")->execute([(int)$item['id']]);
    mg_ac_wallet_event($pdo,$item,'wallet_item.expired',['action_item_id'=>$actionItemId]);
}
function mg_ac_wallet_event(PDO $pdo,array $item,string $eventType,array $context=[]): string
{
    if(empty($item['campaign_id']))return '';
    $publicId=mg_ac_wallet_uuid();
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')->execute([$publicId,(int)$item['merchant_user_id'],(int)$item['campaign_id'],(int)$item['id'],$item['contact_id']===null?null:(int)$item['contact_id'],$eventType,mg_ac_wallet_json($context+['wallet_item_id'=>(string)$item['public_id']])]);
    return $publicId;
}
function mg_ac_wallet_merchant_target(PDO $pdo,array $item): array
{
    $merchantUserId=(int)($item['merchant_user_id']??0);
    if($merchantUserId<1)throw new RuntimeException('Merchant is unavailable.');
    try{$stmt=$pdo->prepare("SELECT public_id FROM merchant_workspaces WHERE merchant_user_id=? AND status='active' ORDER BY id ASC LIMIT 1");$stmt->execute([$merchantUserId]);$workspacePublicId=trim((string)($stmt->fetchColumn()?:''));if($workspacePublicId!=='')return ['target_type'=>'merchant','target_reference'=>$workspacePublicId,'merchant_user_id'=>$merchantUserId,'merchant_workspace_id'=>$workspacePublicId];}catch(Throwable){}
    return ['target_type'=>'profile','target_reference'=>(string)$merchantUserId,'merchant_user_id'=>$merchantUserId,'merchant_workspace_id'=>null];
}
function mg_ac_wallet_type_label(string $type): string
{
    return match($type){'newsletter_signup'=>'Newsletter reward','contest_giveaway'=>'Contest reward','qr_reward_drop'=>'QR reward','referral_reward'=>'Referral reward','birthday_vip'=>'Birthday reward','agent_offer'=>'Agent offer',default=>'Campaign reward'};
}
function mg_ac_wallet_reward_metadata(array $row): array
{
    $decoded=json_decode((string)($row['reward_template_metadata_json']??''),true);
    return is_array($decoded)?$decoded:[];
}
function mg_ac_wallet_media_posts(array $row,string $title): array
{
    $metadata=mg_ac_wallet_reward_metadata($row);
    $pack=is_array($metadata['media_pack']??null)?$metadata['media_pack']:[];
    if($pack===[])return [];
    $posts=[];
    $cover=trim((string)($pack['cover_image_url']??''));
    if($cover!=='')$posts[]=['type'=>'cover','title'=>$title,'body'=>'Cover image for this reward pack.','meta'=>'Reward cover','url'=>$cover,'media_type'=>'image'];
    foreach((array)($pack['media_items']??[]) as $item){
        if(!is_array($item)||empty($item['url']))continue;
        $type=(string)($item['type']??'media');
        $posts[]=['type'=>$type==='audio'?'audio':($type==='video'?'video':($type==='image'?'image':'media')),'title'=>(string)($item['title']??$title),'body'=>(string)($item['url']??''),'meta'=>ucfirst($type).' content','url'=>(string)$item['url'],'media_type'=>$type];
    }
    return $posts;
}
function mg_ac_wallet_public_item(array $row): array
{
    $folder=mg_ac_wallet_folder($row);
    $state=mg_ac_wallet_state($row);
    $merchant=trim((string)($row['merchant_label']??'')) ?: trim((string)($row['merchant_full_name']??'')) ?: 'Participating merchant';
    $title=trim((string)($row['title_snapshot']??'')) ?: trim((string)($row['reward_template_title']??'')) ?: 'Microgifter reward';
    $message=trim((string)($row['reward_template_description']??'')) ?: trim((string)($row['campaign_title']??'')) ?: 'Campaign reward ready to open.';
    $campaignType=trim((string)($row['campaign_type']??''));
    $campaignTitle=trim((string)($row['campaign_title']??''));
    $sourceSystem='campaigns';$sourceType='campaign_reward';$sourceLabel=mg_action_center_source_label($sourceSystem,'Campaign Rewards');$sourceDetail=$campaignTitle!==''?$campaignTitle:($campaignType!==''?mg_ac_wallet_type_label($campaignType):'Campaign reward');$sourceReference=(string)($row['campaign_public_id']??$row['public_id']??'');
    $mediaPosts=mg_ac_wallet_media_posts($row,$title);
    $posts=array_merge($mediaPosts,[['type'=>'message','title'=>$title,'body'=>$message,'meta'=>$sourceLabel],['type'=>'offer','title'=>$title,'body'=>trim((string)($row['redemption_instructions']??'')) ?: 'Present this reward to the merchant when you are ready to redeem.','meta'=>$sourceDetail]]);
    $metadata=['wallet_item_id'=>(string)$row['public_id'],'source_system'=>$sourceSystem,'source_type'=>$sourceType,'source_label'=>$sourceLabel,'source_detail'=>$sourceDetail,'source_reference'=>$sourceReference,'campaign_id'=>(string)($row['campaign_public_id']??''),'campaign_title'=>$campaignTitle,'campaign_type'=>$campaignType,'reward_template_title'=>(string)($row['reward_template_title']??''),'redemption_instructions'=>$row['redemption_instructions']??null,'reward_template_metadata'=>mg_ac_wallet_reward_metadata($row),'pack_requires_load'=>true,'posts'=>$posts];
    return ['action_item_id'=>'wallet-'.(string)$row['public_id'],'folder'=>$folder,'state'=>$state,'can_tip'=>$state==='redeemed'?1:0,'can_message'=>in_array($state,['redeemable','redeemed'],true)?1:0,'read_at'=>null,'first_received_at'=>$row['issued_at']??$row['created_at']??null,'sent_at'=>$row['issued_at']??$row['created_at']??null,'claimed_at'=>$row['claimed_at']??null,'redeemed_at'=>$row['redeemed_at']??null,'updated_at'=>$row['updated_at']??$row['issued_at']??$row['created_at']??null,'instance_id'=>(string)$row['public_id'],'instance_status'=>$state,'face_value_cents'=>(int)($row['value_cents_snapshot']??0),'currency'=>(string)($row['currency_snapshot']??'USD'),'expires_at'=>$row['expires_at']??null,'metadata_json'=>mg_ac_wallet_json($metadata),'template_id'=>$row['reward_template_public_id']??null,'template_name'=>$title,'sender_id'=>(string)($row['merchant_user_id']??''),'sender_name'=>$merchant,'recipient_id'=>(string)($row['user_id']??''),'recipient_name'=>trim((string)($row['contact_name']??'')) ?: trim((string)($row['contact_email']??'')),'redemption_id'=>null,'redemption_status'=>(string)($row['status']??''),'merchant_redeemed_at'=>$row['redeemed_at']??null,'location_id'=>null,'location_name'=>'Participating merchant','merchant_name'=>$merchant,'product_type'=>$campaignType!==''?mg_ac_wallet_type_label($campaignType):'Campaign reward','message'=>$message,'received_at'=>$row['issued_at']??$row['created_at']??null,'created_at'=>$row['created_at']??null,'wallet_item_id'=>(string)$row['public_id'],'is_wallet_reward'=>true,'source_system'=>$sourceSystem,'source_type'=>$sourceType,'source_label'=>$sourceLabel,'source_detail'=>$sourceDetail,'source_reference'=>$sourceReference,'can_follow_up'=>false,'follow_up_count'=>0,'sandbox_mode'=>'','demo_scenario'=>'','is_demo_preview'=>false,'is_system_demo'=>false];
}
function mg_ac_wallet_items(PDO $pdo,int $userId,string $email,string $folder,int $limit=50,string $search=''): array
{
    $folder=mg_action_center_folder($folder);
    if($folder==='sent')return [];
    $limit=mg_action_center_limit($limit);
    $search=mg_action_center_search($search);
    $params=[$userId];
    $where=['wi.pppm_item_id IS NULL',"wi.status<>'cancelled'",mg_ac_wallet_identity_where(strtolower(trim($email)),$params)];
    if($folder==='inbox'){$where[]="(wi.status IN ('issued','viewed') AND (wi.expires_at IS NULL OR wi.expires_at>=NOW()))";}else{$where[]="(wi.status IN ('claimed','redeemed') OR (wi.expires_at IS NOT NULL AND wi.expires_at<NOW()))";}
    if($search!==''){$where[]="(wi.title_snapshot LIKE ? OR COALESCE(rt.title,'') LIKE ? OR COALESCE(c.title,'') LIKE ? OR COALESCE(u.display_name,u.full_name,'') LIKE ? OR COALESCE(cc.name,'') LIKE ? OR COALESCE(cc.email,'') LIKE ?)";$needle='%'.$search.'%';array_push($params,$needle,$needle,$needle,$needle,$needle,$needle);}
    $sql=mg_ac_wallet_select_sql().' WHERE '.implode(' AND ',$where)." ORDER BY wi.updated_at DESC,wi.id DESC LIMIT {$limit}";
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    return array_map('mg_ac_wallet_public_item',$stmt->fetchAll(PDO::FETCH_ASSOC));
}
function mg_ac_wallet_counts(PDO $pdo,int $userId,string $email): array
{
    $params=[$userId];$where=['wi.pppm_item_id IS NULL',"wi.status<>'cancelled'",mg_ac_wallet_identity_where(strtolower(trim($email)),$params)];$sql="SELECT wi.status,wi.expires_at FROM wallet_items wi LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id WHERE ".implode(' AND ',$where);$stmt=$pdo->prepare($sql);$stmt->execute($params);$counts=['inbox'=>['total'=>0,'unread'=>0],'sent'=>['total'=>0,'unread'=>0],'claimed'=>['total'=>0,'unread'=>0]];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){$folder=mg_ac_wallet_folder($row);if(!isset($counts[$folder]))continue;$counts[$folder]['total']++;if($folder==='inbox')$counts[$folder]['unread']++;}return $counts;
}
function mg_ac_wallet_counts_merge(array $base,array $wallet): array
{
    foreach($wallet as $folder=>$values){if(!isset($base[$folder]))$base[$folder]=['total'=>0,'unread'=>0];$base[$folder]['total']+=(int)($values['total']??0);$base[$folder]['unread']+=(int)($values['unread']??0);}return $base;
}
function mg_ac_wallet_page_merge(PDO $pdo,int $userId,string $email,string $folder,array $page,int $limit=50,string $search='',?array $cursor=null): array
{
    if($cursor!==null){$page['page']['wallet_fallback_count']=0;return $page;}
    $walletItems=mg_ac_wallet_items($pdo,$userId,$email,$folder,$limit,$search);
    if($walletItems===[]){$page['page']['wallet_fallback_count']=0;return $page;}
    $items=array_merge($page['items'],$walletItems);
    usort($items,function(array $a,array $b): int{$at=strtotime((string)($a['updated_at']??$a['sent_at']??$a['first_received_at']??''))?:0;$bt=strtotime((string)($b['updated_at']??$b['sent_at']??$b['first_received_at']??''))?:0;return $bt<=>$at;});
    $page['items']=array_slice($items,0,mg_action_center_limit($limit));$page['page']['wallet_fallback_count']=count($walletItems);return $page;
}
