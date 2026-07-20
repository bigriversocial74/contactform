<?php
declare(strict_types=1);

function mg_task_agent_group_schema_ready(PDO $pdo): bool
{
    foreach(['user_group_gifts','user_group_gift_participants','multi_agent_group_gift_links'] as $table){
        if(!mg_personal_agent_table_exists($pdo,$table))return false;
    }
    return true;
}

function mg_task_agent_group_require_schema(PDO $pdo): void
{
    if(!mg_task_agent_group_schema_ready($pdo))throw new RuntimeException('Task Agent Phase 4 database migration is required.');
}

function mg_task_agent_group_actions(string $status): array
{
    return match($status){
        'draft'=>['open','cancel'],
        'open'=>['lock','fulfill','close','cancel'],
        'locked'=>['fulfill','close','cancel'],
        'fulfilled'=>['close'],
        default=>[],
    };
}

function mg_task_agent_group_owned_map(PDO $pdo,int $userId): array
{
    $groups=mg_personal_workflows_group_gifts($pdo,$userId)['owned']??[];
    $map=[];
    foreach($groups as $group)$map[(string)($group['id']??'')]=$group;
    return $map;
}

function mg_task_agent_group_gifts(PDO $pdo,int $userId,int $agentId,int $limit=40): array
{
    if(!mg_task_agent_group_schema_ready($pdo))return [];
    $stmt=$pdo->prepare('SELECT g.public_id FROM multi_agent_group_gift_links link INNER JOIN user_group_gifts g ON g.id=link.group_gift_id AND g.organizer_user_id=link.owner_user_id WHERE link.owner_user_id=? AND link.agent_id=? ORDER BY FIELD(g.status,\'open\',\'draft\',\'locked\',\'fulfilled\',\'closed\',\'cancelled\'),g.deadline_at,g.id LIMIT '.max(1,min(80,$limit)));
    $stmt->execute([$userId,$agentId]);
    $ids=array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    if(!$ids)return [];
    $owned=mg_task_agent_group_owned_map($pdo,$userId);
    $out=[];
    foreach($ids as $id){
        if(!isset($owned[$id]))continue;
        $group=$owned[$id];
        $goal=(float)($group['goal']??0);
        $pledged=(float)($group['pledged']??0);
        $group['progress_percent']=$goal>0?min(100,round(($pledged/$goal)*100,1)):0.0;
        $group['actions']=mg_task_agent_group_actions((string)($group['status']??''));
        $group['pledge_only']=((string)($group['contribution_mode']??''))==='pledge_only';
        $group['money_collected']=false;
        $group['links']=['manage'=>'/agent.php?view=group&group='.rawurlencode($id)];
        $out[]=$group;
    }
    return $out;
}

function mg_task_agent_group_available(PDO $pdo,int $userId,int $limit=40): array
{
    if(!mg_task_agent_group_schema_ready($pdo))return [];
    $stmt=$pdo->prepare('SELECT g.public_id FROM user_group_gifts g LEFT JOIN multi_agent_group_gift_links link ON link.group_gift_id=g.id AND link.owner_user_id=g.organizer_user_id WHERE g.organizer_user_id=? AND link.id IS NULL ORDER BY FIELD(g.status,\'open\',\'draft\',\'locked\',\'fulfilled\',\'closed\',\'cancelled\'),g.deadline_at,g.id LIMIT '.max(1,min(80,$limit)));
    $stmt->execute([$userId]);
    $ids=array_fill_keys(array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN)),true);
    if(!$ids)return [];
    return array_values(array_filter(mg_personal_workflows_group_gifts($pdo,$userId)['owned']??[],static fn(array $group):bool=>isset($ids[(string)($group['id']??'')])));
}

function mg_task_agent_group_gift(PDO $pdo,int $userId,int $agentId,string $publicId): array
{
    foreach(mg_task_agent_group_gifts($pdo,$userId,$agentId,80) as $group){
        if(hash_equals((string)$group['id'],$publicId))return $group;
    }
    throw new RuntimeException('Group gift not found for this agent.');
}

function mg_task_agent_group_link(PDO $pdo,int $userId,int $agentId,string $publicId): array
{
    mg_task_agent_group_require_schema($pdo);
    $stmt=$pdo->prepare('SELECT id FROM user_group_gifts WHERE organizer_user_id=? AND public_id=? LIMIT 1');
    $stmt->execute([$userId,$publicId]);
    $groupId=(int)($stmt->fetchColumn()?:0);
    if($groupId<1)throw new RuntimeException('Group gift not found.');
    try{
        $pdo->prepare('INSERT INTO multi_agent_group_gift_links(public_id,agent_id,owner_user_id,group_gift_id,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())')
            ->execute([mg_public_uuid(),$agentId,$userId,$groupId]);
    }catch(PDOException $error){
        if((string)$error->getCode()!=='23000')throw $error;
        $existing=$pdo->prepare('SELECT agent_id FROM multi_agent_group_gift_links WHERE owner_user_id=? AND group_gift_id=? LIMIT 1');
        $existing->execute([$userId,$groupId]);
        if((int)$existing->fetchColumn()!==$agentId)throw new RuntimeException('This group gift is already connected to another agent.');
    }
    mg_audit('multi_agent.group_gift_linked','agent',['agent_id'=>$agentId,'group_gift_id'=>$publicId,'used_ai'=>false,'money_collected'=>false],$userId);
    return mg_task_agent_group_gift($pdo,$userId,$agentId,$publicId);
}

function mg_task_agent_group_create(PDO $pdo,int $userId,int $agentId,array $input): array
{
    mg_task_agent_group_require_schema($pdo);
    $title=mg_personal_agent_text($input['title']??'',190);
    $description=mg_personal_agent_nullable_text($input['description']??null,5000);
    if($title===''||mg_personal_workflows_sensitive_text($title.' '.($description??'')))throw new InvalidArgumentException('Group gift content cannot contain credentials, payment data, or claim codes.');
    $input['title']=$title;
    $input['description']=$description;
    $group=mg_personal_workflows_create_group_gift($pdo,$userId,$input);
    return mg_task_agent_group_link($pdo,$userId,$agentId,(string)$group['id']);
}

function mg_task_agent_group_update(PDO $pdo,int $userId,int $agentId,string $publicId,string $action,string $expectedStatus): array
{
    $current=mg_task_agent_group_gift($pdo,$userId,$agentId,$publicId);
    if($expectedStatus===''||!hash_equals((string)$current['status'],$expectedStatus))throw new RuntimeException('The group gift changed. Refresh it before updating its status.');
    $group=mg_personal_workflows_update_group_gift($pdo,$userId,$publicId,$action);
    mg_audit('multi_agent.group_gift_updated','agent',['agent_id'=>$agentId,'group_gift_id'=>$publicId,'action'=>$action,'used_ai'=>false,'money_collected'=>false],$userId);
    $group['actions']=mg_task_agent_group_actions((string)$group['status']);
    $group['pledge_only']=true;
    $group['money_collected']=false;
    $group['links']=['manage'=>'/agent.php?view=group&group='.rawurlencode($publicId)];
    return $group;
}

function mg_task_agent_group_card(array $group): array
{
    $recipient=is_array($group['recipient']??null)?$group['recipient']:[];
    $list=is_array($group['list']??null)?$group['list']:[];
    return [
        'type'=>'group_gift',
        'title'=>(string)($group['title']??'Group gift'),
        'body'=>'Pledged '.number_format((float)($group['pledged']??0),2).' of '.number_format((float)($group['goal']??0),2).' '.(string)($group['currency']??'USD').'. Pledges are commitments only; no money is collected by this card.',
        'group'=>[
            'id'=>(string)($group['id']??''),'status'=>(string)($group['status']??''),
            'goal'=>$group['goal']??0,'pledged'=>$group['pledged']??0,'progress_percent'=>$group['progress_percent']??0,
            'currency'=>(string)($group['currency']??'USD'),'deadline_at'=>(string)($group['deadline_at']??''),
            'participant_count'=>(int)($group['participant_count']??0),'joined_count'=>(int)($group['joined_count']??0),
            'recipient_name'=>(string)($recipient['name']??''),'list_name'=>(string)($list['name']??''),
            'plan'=>$group['plan']??null,'actions'=>is_array($group['actions']??null)?$group['actions']:[],
            'participants'=>array_slice(is_array($group['participants']??null)?$group['participants']:[],0,12),
        ],
        'action'=>'manage_group_gift','action_label'=>'Manage group gift',
        'url'=>(string)($group['links']['manage']??'/agent.php?view=group'),
        'review_payload'=>['group_id'=>(string)($group['id']??''),'expected_status'=>(string)($group['status']??'')],
        'contribution_mode'=>'pledge_only','money_collected'=>false,'used_ai'=>false,
    ];
}

function mg_task_agent_group_link_card(array $group): array
{
    return [
        'type'=>'group_gift_link','title'=>(string)($group['title']??'Existing group gift'),
        'body'=>'Reuse this existing pledge-only Personal Agent group gift without copying participants, pledges, or plan data.',
        'group'=>['id'=>(string)($group['id']??''),'status'=>(string)($group['status']??''),'goal'=>$group['goal']??0,'pledged'=>$group['pledged']??0,'currency'=>(string)($group['currency']??'USD'),'deadline_at'=>(string)($group['deadline_at']??'')],
        'action'=>'link_group_gift','action_label'=>'Use with this agent','review_payload'=>['group_id'=>(string)($group['id']??'')],
        'canonical_reuse'=>true,'money_collected'=>false,'used_ai'=>false,
    ];
}

function mg_task_agent_group_for_model(array $groups): array
{
    return array_map(static function(array $group):array{
        $recipient=is_array($group['recipient']??null)?$group['recipient']:[];
        return [
            'title'=>(string)($group['title']??''),'status'=>(string)($group['status']??''),
            'goal'=>$group['goal']??0,'pledged'=>$group['pledged']??0,'currency'=>(string)($group['currency']??'USD'),
            'deadline_at'=>(string)($group['deadline_at']??''),'participant_count'=>(int)($group['participant_count']??0),
            'joined_count'=>(int)($group['joined_count']??0),'recipient_name'=>(string)($recipient['name']??''),
            'contribution_mode'=>'pledge_only','money_collected'=>false,
        ];
    },array_slice($groups,0,8));
}
