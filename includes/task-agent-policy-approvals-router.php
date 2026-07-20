<?php
declare(strict_types=1);

function mg_task_agent_policy_intent(string $message,array $agent): string
{
    if(!mg_task_agent_program_template_ready($agent))return '';
    $text=mg_task_agent_intent_lower($message);
    $approval=preg_match('/\b(approval|approvals|approve|reject|decision|decisions|pending action|risk)\b/u',$text)===1;
    $strategy=preg_match('/\b(strategy|strategies|automation|policy|policies|guardrail|guardrails|rules?)\b/u',$text)===1;
    $budget=preg_match('/\b(budget|budgets|spend|spending|limit|limits|capacity|per recipient|remaining)\b/u',$text)===1;
    if(!$approval&&!$strategy&&!$budget)return '';
    if($approval&&preg_match('/\b(approve|reject|decide|change|update)\b/u',$text))return 'approval_handoff';
    if($strategy&&preg_match('/\b(create|edit|update|activate|pause|retire|change|set)\b/u',$text))return 'strategy_handoff';
    if($budget&&preg_match('/\b(change|edit|update|increase|decrease|set)\b/u',$text))return 'budget_handoff';
    if($approval)return 'approvals';
    if($strategy)return 'strategies';
    return 'guardrails';
}

function mg_task_agent_policy_route(string $message,array $context,array $agent): ?array
{
    $intent=mg_task_agent_policy_intent($message,$agent);
    if($intent==='')return null;
    if(empty($context['policy_approval_schema_ready'])){
        return ['result'=>[
            'reply'=>'Rules, budget, and approval coordination requires the existing Stage 16 strategy and approval schema.',
            'cards'=>[['type'=>'warning','title'=>'Canonical approval schema required','body'=>'No duplicate rule or approval system will be created by Phase 4.','action'=>'none','review_payload'=>[]]],
            'system_intent'=>'policy_approval_schema_pending',
        ],'response_source'=>'system_query','ai_reason'=>'','tool'=>'policy_approvals'];
    }
    if($intent==='approval_handoff'){
        return ['result'=>[
            'reply'=>'Approval decisions remain in the canonical Agent Approval Queue, where risk, expiration, required reasons, owner isolation, and duplicate-decision protection are enforced.',
            'cards'=>[['type'=>'policy_handoff','title'=>'Review Agent Approvals','body'=>'Open the canonical approval queue to approve or reject each action individually. Bulk approval is not enabled.','action'=>'open_agent_approvals','action_label'=>'Open Agent Approvals','url'=>'/merchant-agent-approvals.php','review_payload'=>[]]],
            'system_intent'=>'approval_decision_handoff',
        ],'response_source'=>'system_query','ai_reason'=>'','tool'=>'policy_approvals'];
    }
    if($intent==='strategy_handoff'){
        return ['result'=>[
            'reply'=>'Strategy creation and editing remain in Merchant Automation so action catalogs, trigger policies, versions, and approval requirements use the existing authority.',
            'cards'=>[['type'=>'policy_handoff','title'=>'Manage Automation Policies','body'=>'Create, edit, activate, pause, or retire strategies in the canonical Automation Control Center.','action'=>'open_strategy_control','action_label'=>'Open Automation Control','url'=>'/merchant-automation.php','review_payload'=>[]]],
            'system_intent'=>'strategy_mutation_handoff',
        ],'response_source'=>'system_query','ai_reason'=>'','tool'=>'policy_approvals'];
    }
    if($intent==='budget_handoff'){
        return ['result'=>[
            'reply'=>'Budget, item, per-recipient, and rule changes remain in the canonical Distribution Program editor. This agent only reports the current boundaries.',
            'cards'=>[['type'=>'policy_handoff','title'=>'Edit Distribution Guardrails','body'=>'Open Distribution Programs to change budgets, item limits, recipient limits, or program rules.','action'=>'open_program_guardrails','action_label'=>'Open Distribution Programs','url'=>'/merchant-distribution-program.php','review_payload'=>[]]],
            'system_intent'=>'budget_mutation_handoff',
        ],'response_source'=>'system_query','ai_reason'=>'','tool'=>'policy_approvals'];
    }
    if($intent==='approvals'){
        $rows=is_array($context['pending_agent_approvals']??null)?$context['pending_agent_approvals']:[];
        return ['result'=>[
            'reply'=>$rows?'Here are the pending approvals for this specialized agent. Decisions must be made in Agent Approvals.':'This specialized agent has no pending approval requests.',
            'cards'=>$rows?array_map('mg_task_agent_approval_card',array_slice($rows,0,8)):[['type'=>'policy_handoff','title'=>'Agent Approval Queue','body'=>'Open the canonical queue to review historical and pending decisions.','action'=>'open_agent_approvals','action_label'=>'Open Agent Approvals','url'=>'/merchant-agent-approvals.php','review_payload'=>[]]],
            'system_intent'=>'show_pending_approvals',
        ],'response_source'=>'system_query','ai_reason'=>'','tool'=>'policy_approvals'];
    }
    if($intent==='strategies'){
        $rows=is_array($context['agent_strategies']??null)?$context['agent_strategies']:[];
        return ['result'=>[
            'reply'=>$rows?'Here are this agent’s canonical automation strategies and approval boundaries.':'This specialized agent has no canonical automation strategy yet.',
            'cards'=>$rows?array_map('mg_task_agent_strategy_card',array_slice($rows,0,8)):[['type'=>'policy_handoff','title'=>'Create an Automation Strategy','body'=>'Use Merchant Automation to define the strategy, allowed actions, triggers, limits, and approval requirement.','action'=>'open_strategy_control','action_label'=>'Open Automation Control','url'=>'/merchant-automation.php','review_payload'=>[]]],
            'system_intent'=>'show_agent_strategies',
        ],'response_source'=>'system_query','ai_reason'=>'','tool'=>'policy_approvals'];
    }
    $rows=is_array($context['program_guardrails']??null)?$context['program_guardrails']:[];
    return ['result'=>[
        'reply'=>$rows?'Here are the current canonical program guardrails: budgets, remaining capacity, item limits, recipient limits, and named rule keys.':'No connected distribution-program guardrails are available for this agent.',
        'cards'=>$rows?array_map('mg_task_agent_guardrail_card',array_slice($rows,0,8)):[['type'=>'policy_handoff','title'=>'Connect a Distribution Program','body'=>'Connect an existing program before reviewing its rules and budgets.','action'=>'open_distribution_program','action_label'=>'Open Distribution Programs','url'=>'/merchant-distribution-program.php','review_payload'=>[]]],
        'system_intent'=>'show_program_guardrails',
    ],'response_source'=>'system_query','ai_reason'=>'','tool'=>'policy_approvals'];
}

function mg_task_agent_policy_chat(PDO $pdo,int $userId,array $agent,array $input): ?array
{
    $message=mg_personal_agent_text($input['message']??'',3000);
    if($message===''||mg_task_agent_policy_intent($message,$agent)==='')return null;
    mg_multi_agent_runtime_require_schema($pdo);
    if(($agent['lifecycle_status']??'')!=='active')throw new RuntimeException('Agent is not active.');
    if(($agent['runtime_status']??'')==='paused')throw new RuntimeException('Agent is paused. Resume it before chatting.');
    $template=mg_multi_agent_runtime_template($agent);
    $thread=mg_multi_agent_runtime_thread($pdo,$agent,$userId,mg_personal_agent_text($input['thread_id']??'',80));
    $context=mg_task_agent_policy_append_context($pdo,$userId,$agent,mg_task_agent_program_append_context($pdo,$userId,$agent,mg_multi_agent_runtime_context($pdo,$userId,$agent,$template)));
    $userMessage=mg_multi_agent_runtime_store($pdo,$userId,(int)$agent['id'],(int)$thread['id'],'user',$message,[],$context);
    $route=mg_task_agent_policy_route($message,$context,$agent);
    if(!$route||!is_array($route['result']??null))return null;
    $result=$route['result'];
    $assistant=mg_multi_agent_runtime_store($pdo,$userId,(int)$agent['id'],(int)$thread['id'],'assistant',(string)$result['reply'],is_array($result['cards']??null)?$result['cards']:[],$context);
    mg_audit('multi_agent.chat_completed','agent',['agent_id'=>(string)$agent['public_id'],'thread_id'=>(string)$thread['public_id'],'response_source'=>'system_query','model_key'=>'system_query','ai_reason'=>'','tool'=>'policy_approvals','used_ai'=>false,'ai_tokens_total'=>0],$userId);
    return ['thread'=>['id'=>(string)$thread['public_id'],'title'=>(string)$thread['title']],'user_message'=>$userMessage,'assistant_message'=>$assistant,'used_ai'=>false,'response_source'=>'system_query','ai_reason'=>'','model_key'=>'','ai_tokens_used'=>['input'=>0,'output'=>0,'total'=>0],'ai_credits'=>mg_ai_credit_snapshot($pdo,$userId,'anthropic')];
}
