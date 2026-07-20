<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/account/_action_center_contract.php';

function mg_task_agent_lifecycle_schema_ready(PDO $pdo): bool
{
    foreach (['microgift_inbox_items','microgift_instances','commerce_order_items','commerce_orders','multi_agent_shortlist_items'] as $table) {
        if (!mg_personal_agent_table_exists($pdo, $table)) return false;
    }
    return true;
}

function mg_task_agent_lifecycle_items(PDO $pdo, int $userId, int $agentId, int $limit = 30): array
{
    if (!mg_task_agent_lifecycle_schema_ready($pdo)) return [];
    $sql = mg_action_center_select_sql() . "
        WHERE ac.user_id=? AND ac.archived_at IS NULL
          AND EXISTS(
            SELECT 1
            FROM commerce_order_items coi
            INNER JOIN commerce_orders co ON co.id=coi.order_id AND co.buyer_user_id=?
            INNER JOIN multi_agent_shortlist_items s ON s.product_version_id=coi.product_version_id
              AND s.owner_user_id=co.buyer_user_id AND s.agent_id=? AND s.status='selected' AND s.plan_id IS NOT NULL
            WHERE coi.id=i.commerce_order_item_id
          )
        ORDER BY ac.updated_at DESC,ac.id DESC
        LIMIT " . max(1,min(60,$limit));
    $stmt=$pdo->prepare($sql);
    $stmt->execute([$userId,$userId,$agentId]);
    $rows=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $rows[]=mg_action_center_public_item($row);
    }
    $contracts=mg_action_center_contract_items($pdo,$userId,$rows);
    return array_map(static function(array $contract):array{
        $gift=is_array($contract['gift']??null)?$contract['gift']:[];
        $snapshot=is_array($gift['snapshot']??null)?$gift['snapshot']:[];
        $activity=is_array($contract['activity']??null)?$contract['activity']:[];
        $participants=is_array($contract['participants']??null)?$contract['participants']:[];
        $merchant=is_array($contract['merchant']??null)?$contract['merchant']:[];
        $redemption=is_array($contract['redemption']??null)?$contract['redemption']:[];
        $capabilities=is_array($contract['capabilities']??null)?$contract['capabilities']:[];
        $reasons=is_array($contract['capability_reasons']??null)?$contract['capability_reasons']:[];
        $folder=(string)($contract['folder']??'inbox');
        $itemId=(string)($contract['action_item_id']??'');
        return [
            'action_item_id'=>$itemId,
            'folder'=>$folder,
            'gift'=>[
                'id'=>(string)($gift['id']??''),
                'status'=>(string)($gift['status']??''),
                'state'=>(string)($gift['state']??''),
                'title'=>(string)($snapshot['title']??'Microgift'),
                'description'=>(string)($snapshot['description']??''),
                'value_cents'=>(int)($snapshot['value_cents']??0),
                'currency'=>(string)($snapshot['currency']??'USD'),
                'expires_at'=>$snapshot['expires_at']??null,
            ],
            'participants'=>[
                'sender_name'=>(string)($participants['sender']['name']??''),
                'recipient_name'=>(string)($participants['recipient']['name']??''),
            ],
            'merchant'=>[
                'name'=>(string)($merchant['name']??'Microgifter'),
                'avatar_url'=>(string)($merchant['avatar_url']??''),
            ],
            'activity'=>[
                'received_at'=>$activity['received_at']??null,
                'sent_at'=>$activity['sent_at']??null,
                'claimed_at'=>$activity['claimed_at']??null,
                'redeemed_at'=>$activity['redeemed_at']??null,
                'last_delivery_at'=>$activity['last_delivery_at']??null,
                'resend_count'=>(int)($activity['resend_count']??0),
                'last_follow_up_at'=>$activity['last_follow_up_at']??null,
                'follow_up_count'=>(int)($activity['follow_up_count']??0),
                'updated_at'=>$activity['updated_at']??null,
            ],
            'redemption'=>[
                'status'=>(string)($redemption['status']??''),
                'redeemed_at'=>$redemption['redeemed_at']??null,
            ],
            'capabilities'=>[
                'send'=>!empty($capabilities['send']),
                'claim'=>!empty($capabilities['claim']),
                'redeem'=>!empty($capabilities['redeem']),
                'follow_up'=>!empty($capabilities['follow_up']),
                'message'=>!empty($capabilities['message']),
            ],
            'capability_reasons'=>array_intersect_key($reasons,array_flip(['send','claim','redeem','follow_up','message'])),
            'links'=>[
                'action_center'=>'/inbox.php?folder='.rawurlencode($folder).($itemId!==''?'&item='.rawurlencode($itemId):''),
                'inbox'=>'/inbox.php',
                'sent'=>'/sent.php',
                'claimed'=>'/claimed.php',
            ],
            'read_only'=>true,
            'handoff_required'=>true,
        ];
    },$contracts);
}

function mg_task_agent_lifecycle_card(array $item): array
{
    $gift=is_array($item['gift']??null)?$item['gift']:[];
    $capabilities=is_array($item['capabilities']??null)?$item['capabilities']:[];
    $available=[];
    foreach(['send'=>'Send or regift','claim'=>'Claim','redeem'=>'Merchant redemption','follow_up'=>'Follow up','message'=>'Message'] as $key=>$label){
        if(!empty($capabilities[$key]))$available[]=$label;
    }
    return [
        'type'=>'gift_lifecycle_tracking',
        'title'=>(string)($gift['title']??'Microgift'),
        'body'=>$available
            ? 'Available in the canonical Action Center: '.implode(', ',$available).'.'
            : 'No lifecycle mutation is currently available. Review the capability reasons and current state.',
        'folder'=>(string)($item['folder']??'inbox'),
        'gift'=>$gift,
        'participants'=>is_array($item['participants']??null)?$item['participants']:[],
        'merchant'=>is_array($item['merchant']??null)?$item['merchant']:[],
        'activity'=>is_array($item['activity']??null)?$item['activity']:[],
        'redemption'=>is_array($item['redemption']??null)?$item['redemption']:[],
        'capabilities'=>$capabilities,
        'capability_reasons'=>is_array($item['capability_reasons']??null)?$item['capability_reasons']:[],
        'action'=>'open_action_center_item',
        'action_label'=>'Open Action Center',
        'url'=>(string)($item['links']['action_center']??'/inbox.php'),
        'review_payload'=>[],
        'read_only'=>true,
        'handoff_required'=>true,
    ];
}

function mg_task_agent_lifecycle_for_model(array $items): array
{
    return array_map(static function(array $item):array{
        return [
            'folder'=>(string)($item['folder']??''),
            'gift_title'=>(string)($item['gift']['title']??''),
            'gift_state'=>(string)($item['gift']['state']??''),
            'gift_status'=>(string)($item['gift']['status']??''),
            'expires_at'=>(string)($item['gift']['expires_at']??''),
            'sent_at'=>(string)($item['activity']['sent_at']??''),
            'claimed_at'=>(string)($item['activity']['claimed_at']??''),
            'redeemed_at'=>(string)($item['activity']['redeemed_at']??''),
            'resend_count'=>(int)($item['activity']['resend_count']??0),
            'follow_up_count'=>(int)($item['activity']['follow_up_count']??0),
            'capabilities'=>is_array($item['capabilities']??null)?$item['capabilities']:[],
            'read_only'=>true,
            'handoff_required'=>true,
        ];
    },array_slice($items,0,8));
}
