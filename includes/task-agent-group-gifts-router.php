<?php
declare(strict_types=1);

function mg_task_agent_group_intent(string $message): string
{
    $text=mg_task_agent_intent_lower($message);
    if(!preg_match('/\b(group gift(?:ing)?|group present|contributors?|pledges?|contribution target|shared gift)\b/u',$text))return '';
    return preg_match('/\b(create|set up|setup|start|build|add|organize)\b/u',$text)?'create':'show';
}

function mg_task_agent_group_lists(PDO $pdo,int $userId,int $limit=40): array
{
    if(!mg_personal_agent_table_exists($pdo,'user_contact_lists'))return [];
    $stmt=$pdo->prepare("SELECT l.public_id,l.name,l.description,l.list_type,
      (SELECT COUNT(*) FROM user_contact_list_members m WHERE m.list_id=l.id AND m.owner_user_id=l.owner_user_id) member_count
      FROM user_contact_lists l WHERE l.owner_user_id=? AND l.is_archived=0
      ORDER BY l.sort_order,l.name,l.id LIMIT ".max(1,min(80,$limit)));
    $stmt->execute([$userId]);
    return array_map(static fn(array $row):array=>['id'=>(string)$row['public_id'],'name'=>(string)$row['name'],'description'=>(string)($row['description']??''),'type'=>(string)$row['list_type'],'member_count'=>(int)$row['member_count']],$stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_task_agent_group_match_named(string $message,array $items,string $field='name'): ?array
{
    $text=mg_task_agent_intent_lower($message);$best=null;$length=0;
    foreach($items as $item){$value=mb_strtolower(trim((string)($item[$field]??'')));if($value!==''&&mb_strlen($value)>$length&&str_contains($text,$value)){$best=$item;$length=mb_strlen($value);}}
    return $best;
}

function mg_task_agent_group_builder(string $message,array $context): array
{
    $snapshot=is_array($context['system_snapshot']??null)?$context['system_snapshot']:[];
    $contact=mg_task_agent_match_contact($message,$snapshot);
    $lists=is_array($context['group_contributor_lists']??null)?$context['group_contributor_lists']:[];
    $plans=is_array($snapshot['plans']??null)?$snapshot['plans']:[];
    $list=mg_task_agent_group_match_named($message,$lists);
    $plan=mg_task_agent_group_match_named($message,$plans,'title');
    $recipientName=trim((string)($contact['display_name']??'Group gift recipient'));
    $contextType=$contact?(string)($contact['type']??'contact'):'none';
    if(!in_array($contextType,['contact','linked_user'],true))$contextType='contact';
    return [
        'type'=>'group_gift_builder','title'=>'Group gift for '.$recipientName,
        'body'=>'Review the recipient, contributor list, optional gift plan, pledge target, limits, and deadline. Opening the group uses existing in-app invitations; it never collects payment.',
        'action'=>'create_group_gift','action_label'=>'Create group gift draft',
        'review_payload'=>[
            'context_type'=>$contact?$contextType:'none','context_id'=>$contact?(string)($contact['id']??''):'',
            'title'=>'Group gift for '.$recipientName,'description'=>'Pledge-only group gift prepared through the Birthday & Occasion task agent.',
            'goal'=>100,'min_contribution'=>5,'max_contribution'=>100,'currency'=>'USD',
            'deadline_at'=>(new DateTimeImmutable('+21 days 17:00:00',new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            'contributor_list_id'=>$list?(string)($list['id']??''):'','plan_id'=>$plan?(string)($plan['id']??''):'',
            'contributor_names_visible'=>true,'anonymous_allowed'=>true,
            'available_lists'=>array_slice($lists,0,30),
            'available_plans'=>array_slice(array_map(static fn(array $row):array=>['id'=>(string)($row['id']??''),'title'=>(string)($row['title']??'Gift plan'),'status'=>(string)($row['status']??'')],$plans),0,30),
        ],
        'contribution_mode'=>'pledge_only','money_collected'=>false,'used_ai'=>false,
    ];
}

function mg_task_agent_group_matches(string $message,array $groups): array
{
    $text=mg_task_agent_intent_lower($message);$matches=[];
    foreach($groups as $group){foreach([(string)($group['title']??''),(string)($group['recipient']['name']??''),(string)($group['list']['name']??'')] as $needle){$needle=mb_strtolower(trim($needle));if($needle!==''&&str_contains($text,$needle)){$matches[]=$group;break;}}}
    return $matches?:$groups;
}

function mg_task_agent_group_route(string $message,array $context,array $template): ?array
{
    if(($template['key']??'')!=='birthday_occasion')return null;
    $intent=mg_task_agent_group_intent($message);if($intent==='')return null;
    if(empty($context['group_schema_ready']))return ['result'=>['reply'=>'Group-gifting integration is built, but the consolidated Phase 4 migration has not been imported yet.','cards'=>[['type'=>'warning','title'=>'Phase 4 migration pending','body'=>'Existing Phase 3 and Personal Agent group-gifting workflows remain available. Import the single Phase 4 SQL file only after Phase 4 is complete.','action'=>'none','review_payload'=>[]]],'system_intent'=>'group_schema_pending'],'response_source'=>'system_query','ai_reason'=>''];
    if($intent==='create')return ['result'=>['reply'=>'I prepared a pledge-only group-gift draft from saved Microgifter contacts, lists, and plans. Review every field; no money will be collected.','cards'=>[mg_task_agent_group_builder($message,$context)],'system_intent'=>'create_group_gift_review'],'response_source'=>'system_query','ai_reason'=>''];
    $groups=is_array($context['group_gifts']??null)?$context['group_gifts']:[];
    $matches=array_slice(mg_task_agent_group_matches($message,$groups),0,8);
    if(!$matches){
        $available=is_array($context['available_group_gifts']??null)?$context['available_group_gifts']:[];
        $cards=array_map(static fn(array $group):array=>mg_task_agent_group_link_card($group),array_slice($available,0,6));$cards[]=mg_task_agent_group_builder($message,$context);
        return ['result'=>['reply'=>$available?'Reuse an existing Personal Agent group gift below, or create a new pledge-only draft.':'This agent has no group gift yet. Create a pledge-only draft using a saved contact or contributor list.','cards'=>array_slice($cards,0,8),'system_intent'=>$available?'link_existing_group_gift':'group_gifts_empty'],'response_source'=>'system_query','ai_reason'=>''];
    }
    return ['result'=>['reply'=>'Here are this agent’s pledge-only group gifts. Status actions reuse the canonical Personal Agent workflow; no payment is collected here.','cards'=>array_map(static fn(array $group):array=>mg_task_agent_group_card($group),$matches),'system_intent'=>'show_group_gifts'],'response_source'=>'system_query','ai_reason'=>''];
}

function mg_task_agent_group_append_context(PDO $pdo,int $userId,array $agent,array $context): array
{
    $groups=mg_task_agent_group_gifts($pdo,$userId,(int)$agent['id'],40);$available=mg_task_agent_group_available($pdo,$userId,40);$lists=mg_task_agent_group_lists($pdo,$userId,40);
    $context['group_gifts']=$groups;$context['group_gifts_for_model']=mg_task_agent_group_for_model($groups);$context['available_group_gifts']=$available;$context['group_contributor_lists']=$lists;$context['group_schema_ready']=mg_task_agent_group_schema_ready($pdo);
    if(is_array($context['system_snapshot']['summary']??null)){$context['system_snapshot']['summary']['group_gifts']=count($groups);$context['system_snapshot']['summary']['group_gifts_open']=count(array_filter($groups,static fn(array $group):bool=>($group['status']??'')==='open'));$context['system_snapshot']['summary']['available_group_gifts']=count($available);}
    return $context;
}

function mg_task_agent_group_chat(PDO $pdo,int $userId,array $agent,array $input): ?array
{
    $message=mg_personal_agent_text($input['message']??'',3000);if($message===''||mg_task_agent_group_intent($message)==='')return null;
    $template=mg_multi_agent_runtime_template($agent);if(($template['key']??'')!=='birthday_occasion')return null;
    mg_multi_agent_runtime_require_schema($pdo);if(($agent['lifecycle_status']??'')!=='active')throw new RuntimeException('Agent is not active.');if(($agent['runtime_status']??'')==='paused')throw new RuntimeException('Agent is paused. Resume it before chatting.');
    $thread=mg_multi_agent_runtime_thread($pdo,$agent,$userId,mg_personal_agent_text($input['thread_id']??'',80));
    $context=mg_task_agent_group_append_context($pdo,$userId,$agent,mg_task_agent_recurring_append_context($pdo,$userId,$agent,mg_multi_agent_runtime_context($pdo,$userId,$agent,$template)));
    $userMessage=mg_multi_agent_runtime_store($pdo,$userId,(int)$agent['id'],(int)$thread['id'],'user',$message,[],$context);$route=mg_task_agent_group_route($message,$context,$template);if(!$route||!is_array($route['result']??null))return null;$result=$route['result'];
    $assistant=mg_multi_agent_runtime_store($pdo,$userId,(int)$agent['id'],(int)$thread['id'],'assistant',(string)$result['reply'],is_array($result['cards']??null)?$result['cards']:[],$context);
    mg_audit('multi_agent.chat_completed','agent',['agent_id'=>(string)$agent['public_id'],'thread_id'=>(string)$thread['public_id'],'response_source'=>'system_query','model_key'=>'system_query','ai_reason'=>'','tool'=>'group_gifts','used_ai'=>false,'ai_tokens_total'=>0],$userId);
    return ['thread'=>['id'=>(string)$thread['public_id'],'title'=>(string)$thread['title']],'user_message'=>$userMessage,'assistant_message'=>$assistant,'used_ai'=>false,'response_source'=>'system_query','ai_reason'=>'','model_key'=>'','ai_tokens_used'=>['input'=>0,'output'=>0,'total'=>0],'ai_credits'=>mg_ai_credit_snapshot($pdo,$userId,'anthropic')];
}
