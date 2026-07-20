<?php
declare(strict_types=1);

function mg_task_agent_policy_schema_ready(PDO $pdo): bool
{
    foreach(['distribution_programs','agent_strategies','agent_workflow_runs','agent_workflow_actions','agent_approval_requests'] as $table){
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        if(!(bool)$stmt->fetchColumn())return false;
    }
    return true;
}

function mg_task_agent_policy_json_keys(mixed $value,int $limit=20): array
{
    if(!is_string($value)||trim($value)==='')return [];
    $decoded=json_decode($value,true);
    if(!is_array($decoded))return [];
    $keys=[];
    foreach(array_keys($decoded) as $key){
        $key=trim((string)$key);
        if($key!==''&&preg_match('/^[a-zA-Z0-9_.-]{1,80}$/',$key)===1)$keys[]=$key;
        if(count($keys)>=max(1,min(50,$limit)))break;
    }
    return $keys;
}

function mg_task_agent_program_guardrails(PDO $pdo,int $ownerUserId,int $agentId,array $agent,int $limit=40): array
{
    $types=mg_task_agent_program_allowed_types($agent);
    if(!mg_task_agent_policy_schema_ready($pdo)||$types===[])return [];
    $placeholders=implode(',',array_fill(0,count($types),'?'));
    $stmt=$pdo->prepare("SELECT dp.public_id,dp.name,dp.program_type,dp.status,dp.budget_cents,dp.reserved_cents,dp.issued_cents,dp.max_items,dp.issued_items,dp.per_recipient_limit,dp.rules_json,dp.updated_at
        FROM multi_agent_distribution_program_links link
        INNER JOIN distribution_programs dp ON dp.id=link.distribution_program_id AND dp.merchant_user_id=link.owner_user_id
        WHERE link.owner_user_id=? AND link.agent_id=? AND dp.program_type IN ($placeholders)
        ORDER BY dp.updated_at DESC,dp.id DESC LIMIT ".max(1,min(100,$limit)));
    $stmt->execute(array_merge([$ownerUserId,$agentId],$types));
    return array_map(static function(array $row):array{
        $budget=$row['budget_cents']!==null?(int)$row['budget_cents']:null;
        $reserved=(int)$row['reserved_cents'];$issued=(int)$row['issued_cents'];
        return [
            'id'=>(string)$row['public_id'],'name'=>(string)$row['name'],'program_type'=>(string)$row['program_type'],'status'=>(string)$row['status'],
            'budget'=>$budget===null?null:$budget/100,'reserved'=>$reserved/100,'issued'=>$issued/100,'remaining_budget'=>$budget===null?null:max(0,$budget-$reserved-$issued)/100,'currency'=>'USD',
            'max_items'=>$row['max_items']!==null?(int)$row['max_items']:null,'issued_items'=>(int)$row['issued_items'],'remaining_items'=>$row['max_items']!==null?max(0,(int)$row['max_items']-(int)$row['issued_items']):null,
            'per_recipient_limit'=>$row['per_recipient_limit']!==null?(int)$row['per_recipient_limit']:null,
            'rule_keys'=>mg_task_agent_policy_json_keys($row['rules_json']??null),'rule_count'=>count(mg_task_agent_policy_json_keys($row['rules_json']??null)),
            'updated_at'=>$row['updated_at']??null,'canonical_url'=>'/merchant-distribution-program.php?program_id='.rawurlencode((string)$row['public_id']),'authority'=>'distribution_programs',
        ];
    },$stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_task_agent_strategy_policies(PDO $pdo,int $ownerUserId,int $agentId,int $limit=40): array
{
    if(!mg_task_agent_policy_schema_ready($pdo))return [];
    $stmt=$pdo->prepare('SELECT public_id,name,objective,status,trigger_type,trigger_config_json,policy_json,action_catalog_json,max_actions_per_run,requires_approval,version_no,updated_at FROM agent_strategies WHERE owner_user_id=? AND agent_id=? ORDER BY FIELD(status,\'active\',\'draft\',\'paused\',\'retired\'),updated_at DESC,id DESC LIMIT '.max(1,min(100,$limit)));
    $stmt->execute([$ownerUserId,$agentId]);
    return array_map(static function(array $row):array{
        $actions=json_decode((string)($row['action_catalog_json']??'[]'),true);if(!is_array($actions))$actions=[];
        $actions=array_values(array_slice(array_filter(array_map(static fn($v):string=>trim((string)$v),$actions),static fn(string $v):bool=>$v!==''),0,20));
        return [
            'id'=>(string)$row['public_id'],'name'=>(string)$row['name'],'objective'=>(string)$row['objective'],'status'=>(string)$row['status'],'trigger_type'=>(string)$row['trigger_type'],
            'trigger_keys'=>mg_task_agent_policy_json_keys($row['trigger_config_json']??null),'policy_keys'=>mg_task_agent_policy_json_keys($row['policy_json']??null),
            'action_catalog'=>$actions,'max_actions_per_run'=>(int)$row['max_actions_per_run'],'requires_approval'=>(bool)$row['requires_approval'],'version'=>(int)$row['version_no'],'updated_at'=>$row['updated_at']??null,
            'canonical_url'=>'/merchant-automation.php','authority'=>'agent_strategies',
        ];
    },$stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_task_agent_pending_approvals(PDO $pdo,int $ownerUserId,int $agentId,int $limit=40): array
{
    if(!mg_task_agent_policy_schema_ready($pdo))return [];
    $stmt=$pdo->prepare("SELECT ar.public_id,ar.status,ar.requested_at,ar.expires_at,wa.action_type,wa.target_type,wa.risk_level,wa.requires_approval,wr.status run_status,s.name strategy_name,s.version_no strategy_version
        FROM agent_approval_requests ar
        INNER JOIN agent_workflow_actions wa ON wa.id=ar.action_id
        INNER JOIN agent_workflow_runs wr ON wr.id=ar.run_id AND wr.owner_user_id=ar.owner_user_id
        INNER JOIN agent_strategies s ON s.id=wr.strategy_id AND s.owner_user_id=wr.owner_user_id
        WHERE ar.owner_user_id=? AND wr.agent_id=? AND ar.status='pending'
        ORDER BY ar.requested_at DESC,ar.id DESC LIMIT ".max(1,min(100,$limit)));
    $stmt->execute([$ownerUserId,$agentId]);
    return array_map(static fn(array $row):array=>[
        'id'=>(string)$row['public_id'],'status'=>(string)$row['status'],'action_type'=>(string)$row['action_type'],'target_type'=>(string)$row['target_type'],'risk'=>(string)$row['risk_level'],'requires_approval'=>(bool)$row['requires_approval'],
        'strategy_name'=>(string)$row['strategy_name'],'strategy_version'=>(int)$row['strategy_version'],'run_status'=>(string)$row['run_status'],'requested_at'=>(string)$row['requested_at'],'expires_at'=>$row['expires_at']?:null,
        'reason_required'=>in_array((string)$row['risk_level'],['high','critical'],true),'canonical_url'=>'/merchant-agent-approvals.php?approval='.rawurlencode((string)$row['public_id']),'authority'=>'agent_approval_requests',
    ],$stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_task_agent_policy_for_model(array $guardrails,array $strategies,array $approvals): array
{
    return [
        'program_guardrails'=>array_map(static fn(array $row):array=>[
            'name'=>$row['name'],'program_type'=>$row['program_type'],'status'=>$row['status'],'budget'=>$row['budget'],'reserved'=>$row['reserved'],'issued'=>$row['issued'],'remaining_budget'=>$row['remaining_budget'],'max_items'=>$row['max_items'],'issued_items'=>$row['issued_items'],'remaining_items'=>$row['remaining_items'],'per_recipient_limit'=>$row['per_recipient_limit'],'rule_keys'=>$row['rule_keys'],'rule_count'=>$row['rule_count'],'authority'=>'distribution_programs',
        ],array_slice($guardrails,0,12)),
        'strategies'=>array_map(static fn(array $row):array=>[
            'name'=>$row['name'],'status'=>$row['status'],'trigger_type'=>$row['trigger_type'],'policy_keys'=>$row['policy_keys'],'action_catalog'=>$row['action_catalog'],'max_actions_per_run'=>$row['max_actions_per_run'],'requires_approval'=>$row['requires_approval'],'version'=>$row['version'],'authority'=>'agent_strategies',
        ],array_slice($strategies,0,12)),
        'approval_summary'=>[
            'pending'=>count($approvals),
            'high_risk'=>count(array_filter($approvals,static fn(array $row):bool=>in_array($row['risk'],['high','critical'],true))),
            'expiring_soon'=>count(array_filter($approvals,static fn(array $row):bool=>!empty($row['expires_at'])&&strtotime((string)$row['expires_at'])<=time()+86400)),
            'action_types'=>array_values(array_unique(array_map(static fn(array $row):string=>$row['action_type'],$approvals))),
            'authority'=>'agent_approval_requests',
        ],
    ];
}

function mg_task_agent_policy_append_context(PDO $pdo,int $ownerUserId,array $agent,array $context): array
{
    if(!mg_task_agent_program_template_ready($agent))return $context;
    $ready=mg_task_agent_policy_schema_ready($pdo);
    $guardrails=$ready?mg_task_agent_program_guardrails($pdo,$ownerUserId,(int)$agent['id'],$agent,40):[];
    $strategies=$ready?mg_task_agent_strategy_policies($pdo,$ownerUserId,(int)$agent['id'],40):[];
    $approvals=$ready?mg_task_agent_pending_approvals($pdo,$ownerUserId,(int)$agent['id'],40):[];
    $context['program_guardrails']=$guardrails;$context['agent_strategies']=$strategies;$context['pending_agent_approvals']=$approvals;
    $context['policy_approval_for_model']=mg_task_agent_policy_for_model($guardrails,$strategies,$approvals);
    $context['policy_approval_schema_ready']=$ready;
    return $context;
}

function mg_task_agent_guardrail_card(array $row): array
{
    return ['type'=>'program_guardrail','title'=>$row['name'].' guardrails','body'=>'Budget, item, recipient, and rule boundaries from the canonical distribution program.','guardrail'=>$row,'action'=>'open_program_guardrails','action_label'=>'Open distribution program','url'=>$row['canonical_url'],'review_payload'=>[]];
}

function mg_task_agent_strategy_card(array $row): array
{
    return ['type'=>'agent_strategy_policy','title'=>$row['name'],'body'=>$row['objective'],'strategy'=>$row,'action'=>'open_strategy_control','action_label'=>'Open Automation Control','url'=>$row['canonical_url'],'review_payload'=>[]];
}

function mg_task_agent_approval_card(array $row): array
{
    return ['type'=>'agent_approval_handoff','title'=>ucwords(str_replace('_',' ',$row['action_type'])),'body'=>$row['strategy_name'].' · '.ucwords($row['risk']).' risk · explicit decision required.','approval'=>$row,'action'=>'open_agent_approval','action_label'=>'Review in Agent Approvals','url'=>$row['canonical_url'],'review_payload'=>[]];
}
