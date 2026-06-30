<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_action_center_folder(string $folder): string
{
    return in_array($folder,['inbox','sent','claimed'],true) ? $folder : 'inbox';
}

function mg_action_center_limit(mixed $value,int $default=50): int
{
    $limit=is_numeric($value)?(int)$value:$default;
    return max(1,min($limit,100));
}

function mg_action_center_search(mixed $value): string
{
    return mb_substr(trim((string)$value),0,120);
}

function mg_action_center_source_label(string $sourceSystem, string $fallback = ''): string
{
    $sourceSystem = strtolower(trim($sourceSystem));
    return match ($sourceSystem) {
        'campaigns', 'campaign', 'campaign_reward' => 'Campaign Rewards',
        'wallet_item', 'campaign_wallet' => 'Campaign Wallet',
        'store_canvas' => 'Store Canvas',
        'agent', 'agent_orchestration' => 'Agent Orchestration',
        'action_center', 'in_out_box' => 'IN/OUT Box',
        'distribution_api' => 'Distribution API',
        'merchant_canvas' => 'Merchant Canvas',
        default => $fallback !== '' ? $fallback : ($sourceSystem !== '' ? ucwords(str_replace(['_', '-'], ' ', $sourceSystem)) : 'Microgifter'),
    };
}

function mg_action_center_source_fields(array $metadata): array
{
    $sourceSystem = trim((string)($metadata['source_system'] ?? $metadata['sourceSystem'] ?? $metadata['system'] ?? ''));
    $sourceType = trim((string)($metadata['source_type'] ?? $metadata['sourceType'] ?? ''));
    $sourceReference = trim((string)($metadata['source_reference'] ?? $metadata['sourceReference'] ?? $metadata['wallet_item_id'] ?? ''));
    $sourceLabel = trim((string)($metadata['source_label'] ?? $metadata['sourceLabel'] ?? ''));
    $sourceDetail = trim((string)($metadata['source_detail'] ?? $metadata['sourceDetail'] ?? $metadata['campaign_title'] ?? $metadata['campaign_type'] ?? ''));

    if ($sourceSystem === '') {
        if (in_array($sourceType, ['campaign_reward','wallet_item','newsletter_signup','contest_giveaway','qr_reward_drop','referral_reward','birthday_vip','agent_offer'], true)) {
            $sourceSystem = 'campaigns';
        } elseif ($sourceType !== '') {
            $sourceSystem = $sourceType;
        } else {
            $sourceSystem = 'in_out_box';
        }
    }

    return [
        'source_system' => $sourceSystem,
        'source_type' => $sourceType !== '' ? $sourceType : $sourceSystem,
        'source_label' => $sourceLabel !== '' ? $sourceLabel : mg_action_center_source_label($sourceSystem),
        'source_detail' => $sourceDetail,
        'source_reference' => $sourceReference,
    ];
}

function mg_action_center_encode_cursor(string $updatedAt,int $id): string
{
    return rtrim(strtr(base64_encode(json_encode(['updated_at'=>$updatedAt,'id'=>$id],JSON_THROW_ON_ERROR)),'+/','-_'),'=');
}

function mg_action_center_decode_cursor(?string $cursor): ?array
{
    $cursor=trim((string)$cursor);
    if($cursor==='')return null;
    $padding=str_repeat('=',(4-strlen($cursor)%4)%4);
    $decoded=base64_decode(strtr($cursor,'-_','+/').$padding,true);
    if(!is_string($decoded))throw new InvalidArgumentException('Invalid Action Center cursor.');
    try{$data=json_decode($decoded,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){throw new InvalidArgumentException('Invalid Action Center cursor.');}
    if(!is_array($data)||!isset($data['updated_at'],$data['id'])||!is_string($data['updated_at'])||!is_numeric($data['id'])){
        throw new InvalidArgumentException('Invalid Action Center cursor.');
    }
    return ['updated_at'=>$data['updated_at'],'id'=>(int)$data['id']];
}

function mg_action_center_counts(PDO $pdo,int $userId): array
{
    $stmt=$pdo->prepare("SELECT folder,COUNT(*) total,SUM(read_at IS NULL) unread FROM microgift_inbox_items WHERE user_id=? AND archived_at IS NULL GROUP BY folder");
    $stmt->execute([$userId]);
    $counts=['inbox'=>['total'=>0,'unread'=>0],'sent'=>['total'=>0,'unread'=>0],'claimed'=>['total'=>0,'unread'=>0]];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $folder=(string)$row['folder'];
        if(isset($counts[$folder]))$counts[$folder]=['total'=>(int)$row['total'],'unread'=>(int)$row['unread']];
    }
    return $counts;
}

function mg_action_center_select_sql(): string
{
    return "SELECT ac.id action_item_internal_id,ac.public_id action_item_id,ac.folder,ac.state,ac.can_tip,ac.read_at,
                   ac.first_received_at,ac.sent_at,ac.claimed_at,ac.redeemed_at,ac.updated_at,
                   delivery.first_sent_at delivery_first_sent_at,delivery.last_resent_at,
                   COALESCE(delivery.resend_count,0) resend_count,delivery.last_delivery_event_at,
                   follow_up.last_follow_up_at,COALESCE(follow_up.follow_up_count,0) follow_up_count,
                   i.public_id instance_id,i.status instance_status,i.face_value_cents,i.currency,i.expires_at,i.metadata_json instance_metadata_json,
                   CAST(i.owner_user_id AS CHAR) owner_user_id,CAST(i.issuer_user_id AS CHAR) merchant_user_id,
                   t.public_id template_id,t.name template_name,
                   CAST(sender.id AS CHAR) sender_id,COALESCE(sender.display_name,sender.full_name) sender_name,
                   CAST(recipient.id AS CHAR) recipient_id,COALESCE(recipient.display_name,recipient.full_name) recipient_name,
                   COALESCE(ms.display_name,merchant.display_name,merchant.full_name) merchant_name,
                   logo.public_id merchant_logo_asset_id,
                   r.public_id redemption_id,r.status redemption_status,r.redeemed_at merchant_redeemed_at,
                   l.public_id location_id,l.name location_name
            FROM microgift_inbox_items ac
            INNER JOIN microgift_instances i ON i.id=ac.instance_id
            INNER JOIN microgift_templates t ON t.id=i.template_id
            LEFT JOIN users sender ON sender.id=ac.sender_user_id
            LEFT JOIN users recipient ON recipient.id=ac.recipient_user_id
            LEFT JOIN users merchant ON merchant.id=i.issuer_user_id
            LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=i.issuer_user_id AND ms.status IN ('published','draft')
            LEFT JOIN catalog_assets logo ON logo.id=ms.logo_asset_id AND logo.status='ready'
            LEFT JOIN microgift_redemptions r ON r.id=ac.redemption_id
            LEFT JOIN merchant_locations l ON l.id=ac.location_id
            LEFT JOIN (
                SELECT instance_id,
                       MIN(CASE WHEN event_type='sent' THEN occurred_at END) first_sent_at,
                       MAX(CASE WHEN event_type='resent' THEN occurred_at END) last_resent_at,
                       SUM(CASE WHEN event_type='resent' THEN 1 ELSE 0 END) resend_count,
                       MAX(occurred_at) last_delivery_event_at
                FROM microgift_delivery_events
                GROUP BY instance_id
            ) delivery ON delivery.instance_id=i.id
            LEFT JOIN (
                SELECT mt.microgift_instance_id,m.sender_user_id,
                       MAX(m.created_at) last_follow_up_at,
                       COUNT(*) follow_up_count
                FROM messages m
                INNER JOIN message_threads mt ON mt.id=m.thread_id
                WHERE m.source_type='action_center_follow_up'
                GROUP BY mt.microgift_instance_id,m.sender_user_id
            ) follow_up ON follow_up.microgift_instance_id=i.id AND follow_up.sender_user_id=ac.user_id";
}

function mg_action_center_public_item(array $row): array
{
    $metadata=[];
    $rawMetadata=(string)($row['instance_metadata_json']??'');
    if($rawMetadata!==''){
        try{$decoded=json_decode($rawMetadata,true,512,JSON_THROW_ON_ERROR);if(is_array($decoded))$metadata=$decoded;}catch(Throwable){}
    }
    $source = mg_action_center_source_fields($metadata);
    if(!empty($row['delivery_first_sent_at']))$row['sent_at']=$row['delivery_first_sent_at'];
    $row['resend_count']=(int)($row['resend_count']??0);
    $row['follow_up_count']=(int)($row['follow_up_count']??0);
    $logoId=(string)($row['merchant_logo_asset_id']??'');
    $row['merchant_avatar_url']=$logoId!==''?('/api/public/media.php?asset='.rawurlencode($logoId)):'';
    $row['merchant_name']=(string)($row['merchant_name']??'');
    $row['can_follow_up']=(string)($row['folder']??'')==='sent'
        && (string)($row['recipient_id']??'')!==''
        && (string)($row['recipient_id']??'')===(string)($row['owner_user_id']??'')
        && in_array((string)($row['instance_status']??''),['issued','delivered'],true)
        && empty($row['claimed_at'])
        && empty($row['redeemed_at']);
    unset($row['action_item_internal_id'],$row['instance_metadata_json'],$row['delivery_first_sent_at'],$row['merchant_logo_asset_id']);
    $row['sandbox_mode']=(string)($metadata['sandbox_mode']??'');
    $row['demo_scenario']=(string)($metadata['demo_scenario']??'');
    $row['is_demo_preview']=false;
    $row['is_system_demo']=($row['sandbox_mode']==='admin_demo');
    return $row + $source;
}

function mg_action_center_page(PDO $pdo,int $userId,string $folder,int $limit=50,string $search='',?array $cursor=null): array
{
    $folder=mg_action_center_folder($folder);
    $limit=mg_action_center_limit($limit);
    $search=mg_action_center_search($search);
    $where=['ac.user_id=?','ac.folder=?','ac.archived_at IS NULL'];
    $params=[$userId,$folder];
    if($search!==''){
        $where[]="(t.name LIKE ? OR i.public_id LIKE ? OR COALESCE(sender.display_name,sender.full_name,'') LIKE ? OR COALESCE(recipient.display_name,recipient.full_name,'') LIKE ? OR COALESCE(l.name,'') LIKE ? OR ac.state LIKE ? OR COALESCE(ms.display_name,merchant.display_name,merchant.full_name,'') LIKE ?)";
        $needle='%'.$search.'%';
        array_push($params,$needle,$needle,$needle,$needle,$needle,$needle,$needle);
    }
    if($cursor!==null){
        $where[]='(ac.updated_at < ? OR (ac.updated_at = ? AND ac.id < ?))';
        array_push($params,$cursor['updated_at'],$cursor['updated_at'],$cursor['id']);
    }
    $fetchLimit=$limit+1;
    $sql=mg_action_center_select_sql().' WHERE '.implode(' AND ',$where)." ORDER BY ac.updated_at DESC,ac.id DESC LIMIT {$fetchLimit}";
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore=count($rows)>$limit;
    if($hasMore)array_pop($rows);
    $nextCursor=null;
    if($hasMore&&$rows!==[]){
        $last=$rows[array_key_last($rows)];
        $nextCursor=mg_action_center_encode_cursor((string)$last['updated_at'],(int)$last['action_item_internal_id']);
    }
    return [
        'items'=>array_map('mg_action_center_public_item',$rows),
        'page'=>['limit'=>$limit,'has_more'=>$hasMore,'next_cursor'=>$nextCursor],
    ];
}

function mg_action_center_items(PDO $pdo,int $userId,string $folder,int $limit=50): array
{
    return mg_action_center_page($pdo,$userId,$folder,$limit)['items'];
}

function mg_action_center_detail(PDO $pdo,int $userId,string $publicId): ?array
{
    $publicId=trim($publicId);
    if($publicId==='')return null;
    $sql=mg_action_center_select_sql().' WHERE ac.user_id=? AND ac.public_id=? AND ac.archived_at IS NULL LIMIT 1';
    $stmt=$pdo->prepare($sql);
    $stmt->execute([$userId,$publicId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?mg_action_center_public_item($row):null;
}

function mg_action_center_mark_read(PDO $pdo,int $userId,string $publicId): void
{
    $stmt=$pdo->prepare('UPDATE microgift_inbox_items SET read_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE user_id=? AND public_id=? AND read_at IS NULL');
    $stmt->execute([$userId,trim($publicId)]);
}

function mg_action_center_mark_unread(PDO $pdo,int $userId,string $publicId): void
{
    $stmt=$pdo->prepare('UPDATE microgift_inbox_items SET read_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE user_id=? AND public_id=? AND read_at IS NOT NULL');
    $stmt->execute([$userId,trim($publicId)]);
}

function mg_action_center_archive(PDO $pdo,int $userId,string $publicId): void
{
    $stmt=$pdo->prepare('UPDATE microgift_inbox_items SET archived_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE user_id=? AND public_id=? AND archived_at IS NULL');
    $stmt->execute([$userId,trim($publicId)]);
}

function mg_action_center_restore(PDO $pdo,int $userId,string $publicId): void
{
    $stmt=$pdo->prepare('UPDATE microgift_inbox_items SET archived_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE user_id=? AND public_id=? AND archived_at IS NOT NULL');
    $stmt->execute([$userId,trim($publicId)]);
}
