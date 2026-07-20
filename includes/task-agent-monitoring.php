<?php
declare(strict_types=1);

function mg_task_agent_monitor_template_supported(array $agent): bool
{
    $key=(string)(mg_multi_agent_runtime_template($agent)['key']??'');
    return in_array($key,['birthday_occasion','workplace_rewards','community_fundraising'],true);
}

function mg_task_agent_monitor_intent(string $message,array $agent): string
{
    if(!mg_task_agent_monitor_template_supported($agent))return '';
    $text=mg_task_agent_intent_lower($message);
    if(preg_match('/\b(monitor|monitoring|attention|needs attention|status review|health|readiness|due now|what is due|upcoming work|review queue|issues|risks|blocked|prepare next|next actions?)\b/u',$text))return 'monitor';
    if(preg_match('/\b(summary|overview|brief)\b/u',$text)&&preg_match('/\b(program|programs|gift|gifts|agent|workplace|community|recurring|group)\b/u',$text))return 'monitor';
    return '';
}

function mg_task_agent_monitor_days_until(?string $value): ?int
{
    if(!$value)return null;
    $time=strtotime($value);if($time===false)return null;
    return (int)floor(($time-time())/86400);
}

function mg_task_agent_monitor_item(string $source,string $severity,string $title,string $body,?string $dueAt,string $status,string $url,array $facts=[]): array
{
    return ['source'=>$source,'severity'=>$severity,'title'=>$title,'body'=>$body,'due_at'=>$dueAt,'status'=>$status,'url'=>$url,'facts'=>$facts,'used_ai'=>false,'stored_alert'=>false];
}

function mg_task_agent_monitor_birthday(PDO $pdo,int $userId,int $agentId): array
{
    $items=[];$recurring=mg_task_agent_recurring_schema_ready($pdo)?mg_task_agent_recurring_programs($pdo,$userId,$agentId,80):[];
    foreach($recurring as $program){
        $days=mg_task_agent_monitor_days_until((string)($program['next_run_at']??''));
        if(!empty($program['due']))$items[]=mg_task_agent_monitor_item('recurring','high',(string)$program['title'].' is due','Generate or skip the next approval-first draft cycle. No purchase will occur.',(string)$program['next_run_at'],(string)$program['status'],'/agent.php?view=recurring',['run_sequence'=>(int)$program['run_sequence'],'last_run_status'=>(string)($program['last_run']['status']??'none')]);
        elseif((string)$program['status']==='active'&&$days!==null&&$days<=14)$items[]=mg_task_agent_monitor_item('recurring','medium',(string)$program['title'].' is upcoming','The next recurring review is '.max(0,$days).' days away.',(string)$program['next_run_at'],'active','/agent.php?view=recurring',['run_sequence'=>(int)$program['run_sequence']]);
        if(in_array((string)($program['last_run']['status']??''),['skipped','completed','draft_created','generated'],true))$items[]=mg_task_agent_monitor_item('recurring_history','info',(string)$program['title'].' cycle recorded','Latest canonical cycle status: '.(string)$program['last_run']['status'].'.',(string)($program['last_run']['scheduled_for']??''),(string)$program['last_run']['status'],'/agent.php?view=recurring',['run_sequence'=>(int)$program['run_sequence']]);
        if(in_array((string)$program['status'],['draft','paused'],true))$items[]=mg_task_agent_monitor_item('recurring','low',(string)$program['title'].' is '.(string)$program['status'],'Review the program before the next cycle can be prepared.',(string)$program['next_run_at'],(string)$program['status'],'/agent.php?view=recurring');
    }
    $groups=mg_task_agent_group_schema_ready($pdo)?mg_task_agent_group_gifts($pdo,$userId,$agentId,80):[];
    foreach($groups as $group){
        $days=mg_task_agent_monitor_days_until((string)($group['deadline_at']??''));$status=(string)($group['status']??'');$progress=(float)($group['progress_percent']??0);
        if(in_array($status,['open','locked'],true)&&$days!==null&&$days<=7)$items[]=mg_task_agent_monitor_item('group_gift',$days<0?'high':'medium',(string)$group['title'].' deadline '.($days<0?'passed':'is near'),'Pledge progress is '.number_format($progress,1).'%. Pledges remain commitments only.',(string)$group['deadline_at'],$status,(string)($group['links']['manage']??'/agent.php?view=group'),['progress_percent'=>$progress,'participant_count'=>(int)($group['participant_count']??0),'joined_count'=>(int)($group['joined_count']??0)]);
        elseif($status==='open'&&$progress<100)$items[]=mg_task_agent_monitor_item('group_gift','low',(string)$group['title'].' is collecting pledges','Current pledge progress is '.number_format($progress,1).'%.',(string)$group['deadline_at'],$status,(string)($group['links']['manage']??'/agent.php?view=group'),['progress_percent'=>$progress]);
    }
    $delivery=mg_task_agent_delivery_schema_ready($pdo)?mg_task_agent_delivery_preparations($pdo,$userId,$agentId,50):[];
    foreach($delivery as $prep){
        $readiness=is_array($prep['readiness']??null)?$prep['readiness']:[];$plan=is_array($prep['plan']??null)?$prep['plan']:[];$recipient=is_array($prep['recipient']??null)?$prep['recipient']:[];$missing=[];
        if(empty($readiness['recipient_identified']))$missing[]='recipient';
        if(empty($readiness['delivery_address_available']))$missing[]='delivery address';
        if(empty($readiness['gift_preferences_available']))$missing[]='gift preferences';
        if($missing)$items[]=mg_task_agent_monitor_item('delivery_readiness','medium',(string)($plan['title']??'Gift plan').' needs recipient readiness','Missing: '.implode(', ',$missing).'. Only readiness booleans are shown; no address value is exposed.',(string)($plan['target_date']??''),(string)($plan['status']??'draft'),'/agent.php?view=plans',['recipient_type'=>(string)($recipient['type']??'none'),'address_value_exposed'=>false]);
        elseif(empty($prep['schedule']))$items[]=mg_task_agent_monitor_item('delivery_readiness','low',(string)($plan['title']??'Gift plan').' is ready to schedule','A product and recipient are prepared, but no send-later preparation exists.',(string)($plan['target_date']??''),(string)($plan['status']??'ready'),'/agent.php?view=plans',['address_value_exposed'=>false]);
    }
    return ['items'=>$items,'recurring_count'=>count($recurring),'group_count'=>count($groups),'delivery_count'=>count($delivery)];
}

function mg_task_agent_monitor_merchant(PDO $pdo,int $userId,int $agentId,array $agent): array
{
    $items=[];$types=mg_task_agent_program_allowed_types($agent);
    $programs=mg_task_agent_program_schema_ready($pdo)?mg_task_agent_program_rows($pdo,$userId,$agentId,$types,80):[];
    foreach($programs as $program){
        $days=mg_task_agent_monitor_days_until($program['ends_at']??null);$remaining=$program['remaining_budget'];$budget=$program['budget'];$status=(string)$program['status'];
        if($budget!==null&&$budget>0&&$remaining!==null&&$remaining/$budget<=0.10)$items[]=mg_task_agent_monitor_item('distribution_capacity','high',(string)$program['name'].' budget is nearly used','Remaining budget: '.number_format((float)$remaining,2).' USD.',null,$status,(string)$program['canonical_url'],['remaining_budget'=>$remaining,'budget'=>$budget]);
        if($program['max_items']!==null&&$program['issued_items']>=(int)$program['max_items'])$items[]=mg_task_agent_monitor_item('distribution_capacity','high',(string)$program['name'].' reached its item limit','Issued items have reached the canonical program maximum.',null,$status,(string)$program['canonical_url'],['issued_items'=>$program['issued_items'],'max_items'=>$program['max_items']]);
        if(in_array($status,['active','scheduled'],true)&&$days!==null&&$days<=7)$items[]=mg_task_agent_monitor_item('distribution_deadline',$days<0?'high':'medium',(string)$program['name'].' end date '.($days<0?'passed':'is near'),'Review canonical program status, remaining capacity, recipients, and issuance.',(string)$program['ends_at'],$status,(string)$program['canonical_url'],['remaining_budget'=>$remaining,'issued_items'=>$program['issued_items']]);
        if((int)$program['product_count']===0)$items[]=mg_task_agent_monitor_item('distribution_readiness','medium',(string)$program['name'].' has no active products','Add eligible products in the canonical Distribution Program workspace.',null,$status,(string)$program['canonical_url']);
        if((int)$program['recipient_count']===0)$items[]=mg_task_agent_monitor_item('distribution_readiness','medium',(string)$program['name'].' has no recipients','Add recipients and run canonical eligibility checks before allocation.',null,$status,(string)$program['canonical_url']);
        if(in_array($status,['draft','paused'],true))$items[]=mg_task_agent_monitor_item('distribution_status','low',(string)$program['name'].' is '.$status,'Review the program in the canonical workspace before activity continues.',null,$status,(string)$program['canonical_url']);
    }
    $approvals=mg_task_agent_policy_schema_ready($pdo)?mg_task_agent_pending_approvals($pdo,$userId,$agentId,80):[];
    foreach($approvals as $approval)$items[]=mg_task_agent_monitor_item('approval',in_array($approval['risk'],['high','critical'],true)?'high':'medium',ucwords(str_replace('_',' ',$approval['action_type'])).' needs approval',$approval['strategy_name'].' · '.ucwords($approval['risk']).' risk. Decide only in Agent Approvals.',$approval['expires_at'],$approval['status'],$approval['canonical_url'],['risk'=>$approval['risk'],'reason_required'=>$approval['reason_required']]);
    return ['items'=>$items,'program_count'=>count($programs),'approval_count'=>count($approvals)];
}

function mg_task_agent_monitor_snapshot(PDO $pdo,int $userId,array $agent): array
{
    $template=mg_multi_agent_runtime_template($agent);$key=(string)($template['key']??'');
    $data=$key==='birthday_occasion'?mg_task_agent_monitor_birthday($pdo,$userId,(int)$agent['id']):mg_task_agent_monitor_merchant($pdo,$userId,(int)$agent['id'],$agent);
    $rank=['high'=>0,'medium'=>1,'low'=>2,'info'=>3];
    usort($data['items'],static function(array $a,array $b)use($rank):int{$cmp=($rank[$a['severity']]??9)<=>($rank[$b['severity']]??9);if($cmp!==0)return $cmp;return strcmp((string)($a['due_at']??'9999'),(string)($b['due_at']??'9999'));});
    $counts=array_fill_keys(['high','medium','low','info'],0);foreach($data['items'] as $item)$counts[$item['severity']]++;
    return $data+['counts'=>$counts,'generated_at'=>gmdate('Y-m-d H:i:s'),'source'=>'system_query','used_ai'=>false,'stored_alerts'=>false];
}

function mg_task_agent_monitor_for_model(array $snapshot): array
{
    return ['counts'=>$snapshot['counts']??[],'items'=>array_map(static fn(array $item):array=>['source'=>$item['source'],'severity'=>$item['severity'],'due_at'=>$item['due_at'],'status'=>$item['status'],'facts'=>$item['facts'],'stored_alert'=>false],array_slice($snapshot['items']??[],0,16)),'source'=>'system_query','used_ai'=>false];
}

function mg_task_agent_monitor_card(array $item): array
{
    return ['type'=>'task_agent_monitor','title'=>$item['title'],'body'=>$item['body'],'monitor'=>['source'=>$item['source'],'severity'=>$item['severity'],'due_at'=>$item['due_at'],'status'=>$item['status'],'facts'=>$item['facts']],'action'=>'open_monitor_source','action_label'=>'Review canonical source','url'=>$item['url'],'review_payload'=>[],'used_ai'=>false,'stored_alert'=>false];
}
