<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';

function mg_action_center_wallet_expired(array $row): bool
{
    $expiresAt=trim((string)($row['expires_at']??''));
    $status=(string)($row['status']??'issued');
    return $expiresAt!==''&&strtotime($expiresAt)!==false&&strtotime($expiresAt)<time()&&!in_array($status,['redeemed','cancelled'],true);
}

function mg_action_center_wallet_state(array $row): string
{
    if(mg_action_center_wallet_expired($row))return 'expired';
    $status=(string)($row['status']??'issued');
    if(in_array($status,['issued','viewed'],true))return 'claimable';
    if($status==='claimed')return 'redeemable';
    if($status==='redeemed')return 'redeemed';
    return $status!==''?$status:'received';
}

function mg_action_center_wallet_folder(array $row): string
{
    $status=(string)($row['status']??'issued');
    if(mg_action_center_wallet_expired($row)||in_array($status,['claimed','redeemed'],true))return 'claimed';
    return 'inbox';
}

function mg_action_center_wallet_identity_where(string $email,array &$params): string
{
    $where='(wi.user_id=?';
    if($email!==''){
        $where.=' OR LOWER(cc.email)=? OR LOWER(wi.source_id)=?';
        $params[]=$email;
        $params[]=$email;
    }
    return $where.')';
}

function mg_action_center_wallet_type_label(string $type): string
{
    return match($type){
        'newsletter_signup'=>'Newsletter reward',
        'contest_giveaway'=>'Contest reward',
        'qr_reward_drop'=>'QR reward',
        'referral_reward'=>'Referral reward',
        'birthday_vip'=>'Birthday reward',
        'agent_offer'=>'Agent offer',
        default=>'Campaign reward',
    };
}

function mg_action_center_wallet_public_item(array $row): array
{
    $folder=mg_action_center_wallet_folder($row);
    $state=mg_action_center_wallet_state($row);
    $merchant=trim((string)($row['merchant_label']??'')) ?: trim((string)($row['merchant_full_name']??'')) ?: 'Participating merchant';
    $title=trim((string)($row['title_snapshot']??'')) ?: trim((string)($row['reward_template_title']??'')) ?: 'Microgifter reward';
    $message=trim((string)($row['reward_template_description']??'')) ?: trim((string)($row['campaign_title']??'')) ?: 'Campaign reward ready to open.';
    $campaignType=trim((string)($row['campaign_type']??''));
    $metadata=[
        'wallet_item_id'=>(string)$row['public_id'],
        'source_type'=>(string)($row['source_type']??'wallet_item'),
        'campaign_type'=>$campaignType,
        'reward_template_title'=>(string)($row['reward_template_title']??''),
        'redemption_instructions'=>$row['redemption_instructions']??null,
        'posts'=>[
            ['type'=>'message','title'=>$title,'body'=>$message,'meta'=>$merchant],
            ['type'=>'offer','title'=>$title,'body'=>trim((string)($row['redemption_instructions']??'')) ?: 'Present this reward to the merchant when you are ready to redeem.','meta'=>'Wallet reward'],
        ],
    ];
    return [
        'action_item_id'=>'wallet-'.(string)$row['public_id'],
        'folder'=>$folder,
        'state'=>$state,
        'can_tip'=>$state==='redeemed'?1:0,
        'read_at'=>null,
        'first_received_at'=>$row['issued_at']??$row['created_at']??null,
        'sent_at'=>$row['issued_at']??$row['created_at']??null,
        'claimed_at'=>$row['claimed_at']??null,
        'redeemed_at'=>$row['redeemed_at']??null,
        'updated_at'=>$row['updated_at']??$row['issued_at']??$row['created_at']??null,
        'instance_id'=>(string)$row['public_id'],
        'instance_status'=>$state,
        'face_value_cents'=>(int)($row['value_cents_snapshot']??0),
        'currency'=>(string)($row['currency_snapshot']??'USD'),
        'expires_at'=>$row['expires_at']??null,
        'metadata_json'=>json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
        'template_id'=>$row['reward_template_public_id']??null,
        'template_name'=>$title,
        'sender_id'=>(string)($row['merchant_user_id']??''),
        'sender_name'=>$merchant,
        'recipient_id'=>(string)($row['user_id']??''),
        'recipient_name'=>trim((string)($row['contact_name']??'')) ?: trim((string)($row['contact_email']??'')),
        'redemption_id'=>null,
        'redemption_status'=>(string)($row['status']??''),
        'merchant_redeemed_at'=>$row['redeemed_at']??null,
        'location_id'=>null,
        'location_name'=>'Participating merchant',
        'merchant_name'=>$merchant,
        'product_type'=>$campaignType!==''?mg_action_center_wallet_type_label($campaignType):'Campaign reward',
        'message'=>$message,
        'received_at'=>$row['issued_at']??$row['created_at']??null,
        'created_at'=>$row['created_at']??null,
        'wallet_item_id'=>(string)$row['public_id'],
        'is_wallet_reward'=>true,
        'can_follow_up'=>false,
        'follow_up_count'=>0,
        'sandbox_mode'=>'',
        'demo_scenario'=>'',
        'is_demo_preview'=>false,
        'is_system_demo'=>false,
    ];
}

function mg_action_center_wallet_items(PDO $pdo,int $userId,string $email,string $folder,int $limit=50,string $search=''): array
{
    $folder=mg_action_center_folder($folder);
    if($folder==='sent')return [];
    $limit=mg_action_center_limit($limit);
    $search=mg_action_center_search($search);
    $email=strtolower(trim($email));
    $params=[$userId];
    $where=['wi.pppm_item_id IS NULL','wi.status<>'cancelled'',mg_action_center_wallet_identity_where($email,$params)];
    if($folder==='inbox'){
        $where[]="(wi.status IN ('issued','viewed') AND (wi.expires_at IS NULL OR wi.expires_at>=NOW()))";
    }else{
        $where[]="(wi.status IN ('claimed','redeemed') OR (wi.expires_at IS NOT NULL AND wi.expires_at<NOW()))";
    }
    if($search!==''){
        $where[]="(wi.title_snapshot LIKE ? OR COALESCE(rt.title,'') LIKE ? OR COALESCE(c.title,'') LIKE ? OR COALESCE(u.display_name,u.full_name,'') LIKE ? OR COALESCE(cc.name,'') LIKE ? OR COALESCE(cc.email,'') LIKE ?)";
        $needle='%'.$search.'%';
        array_push($params,$needle,$needle,$needle,$needle,$needle,$needle);
    }
    $sql="SELECT wi.*,rt.public_id reward_template_public_id,rt.title reward_template_title,rt.description reward_template_description,rt.redemption_instructions,c.title campaign_title,c.campaign_type,u.display_name merchant_label,u.full_name merchant_full_name,cc.email contact_email,cc.name contact_name
          FROM wallet_items wi
          LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id
          LEFT JOIN campaigns c ON c.id=wi.campaign_id
          LEFT JOIN users u ON u.id=wi.merchant_user_id
          LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id
          WHERE ".implode(' AND ',$where)." ORDER BY wi.updated_at DESC,wi.id DESC LIMIT {$limit}";
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('mg_action_center_wallet_public_item',$stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_action_center_wallet_counts(PDO $pdo,int $userId,string $email): array
{
    $email=strtolower(trim($email));
    $params=[$userId];
    $where=['wi.pppm_item_id IS NULL','wi.status<>'cancelled'',mg_action_center_wallet_identity_where($email,$params)];
    $sql="SELECT wi.status,wi.expires_at
          FROM wallet_items wi
          LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id
          WHERE ".implode(' AND ',$where);
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    $counts=['inbox'=>['total'=>0,'unread'=>0],'sent'=>['total'=>0,'unread'=>0],'claimed'=>['total'=>0,'unread'=>0]];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $folder=mg_action_center_wallet_folder($row);
        if(!isset($counts[$folder]))continue;
        $counts[$folder]['total']++;
        if($folder==='inbox')$counts[$folder]['unread']++;
    }
    return $counts;
}

function mg_action_center_counts_plus_wallet(PDO $pdo,int $userId,string $email): array
{
    $counts=mg_action_center_counts($pdo,$userId);
    $wallet=mg_action_center_wallet_counts($pdo,$userId,$email);
    foreach($wallet as $folder=>$values){
        if(!isset($counts[$folder]))$counts[$folder]=['total'=>0,'unread'=>0];
        $counts[$folder]['total']+=(int)($values['total']??0);
        $counts[$folder]['unread']+=(int)($values['unread']??0);
    }
    return $counts;
}

function mg_action_center_page_plus_wallet(PDO $pdo,int $userId,string $email,string $folder,int $limit=50,string $search='',?array $cursor=null): array
{
    $page=mg_action_center_page($pdo,$userId,$folder,$limit,$search,$cursor);
    if($cursor!==null)return $page;
    $walletItems=mg_action_center_wallet_items($pdo,$userId,$email,$folder,$limit,$search);
    if($walletItems===[]){
        $page['page']['wallet_fallback_count']=0;
        return $page;
    }
    $items=array_merge($page['items'],$walletItems);
    usort($items,function(array $a,array $b): int{
        $at=strtotime((string)($a['updated_at']??$a['sent_at']??$a['first_received_at']??''))?:0;
        $bt=strtotime((string)($b['updated_at']??$b['sent_at']??$b['first_received_at']??''))?:0;
        return $bt<=>$at;
    });
    $page['items']=array_slice($items,0,mg_action_center_limit($limit));
    $page['page']['wallet_fallback_count']=count($walletItems);
    return $page;
}

mg_require_method('GET');
$user=mg_require_api_user();
$userId=(int)$user['id'];
$userEmail=strtolower(trim((string)($user['email']??'')));
$folder=mg_action_center_folder(trim((string)($_GET['folder']??'inbox')));
$limit=mg_action_center_limit($_GET['limit']??50);
$search=mg_action_center_search($_GET['q']??'');
try{
    $cursor=mg_action_center_decode_cursor(isset($_GET['cursor'])?(string)$_GET['cursor']:null);
}catch(InvalidArgumentException $e){
    mg_fail($e->getMessage(),422);
}
$pdo=mg_db();
$page=mg_action_center_page_plus_wallet($pdo,$userId,$userEmail,$folder,$limit,$search,$cursor);

mg_ok([
    'folder'=>$folder,
    'query'=>$search,
    'counts'=>mg_action_center_counts_plus_wallet($pdo,$userId,$userEmail),
    'items'=>$page['items'],
    'page'=>$page['page'],
]);
