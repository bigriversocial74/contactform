<?php
declare(strict_types=1);

require_once __DIR__.'/task-agent-policy-approvals.php';
require_once __DIR__.'/task-agent-policy-approvals-router.php';
require_once __DIR__.'/task-agent-monitoring.php';
require_once __DIR__.'/task-agent-monitoring-router.php';

function mg_task_agent_program_intent(string $message,array $agent): string
{
    if (!mg_task_agent_program_template_ready($agent)) return '';
    $monitorIntent=mg_task_agent_monitor_intent($message,$agent);
    if($monitorIntent!=='')return 'monitor';
    $policyIntent=mg_task_agent_policy_intent($message,$agent);
    if($policyIntent!=='')return 'policy';
    $text=mg_task_agent_intent_lower($message);
    if (preg_match('/\b(connect|link|attach|reuse)\b/u',$text) && preg_match('/\b(program|reward|fundraiser|contest|giveaway)\b/u',$text)) return 'link';
    if (preg_match('/\b(create|new|start|build|set up|setup)\b/u',$text) && preg_match('/\b(program|reward|fundraiser|contest|giveaway)\b/u',$text)) return 'handoff_create';
    if (preg_match('/\b(program|programs|workplace|employee|reward|rewards|community|fundraiser|fundraising|contest|giveaway|sponsor|sponsored|budget|issuance|recipient|recipients|allocation|allocations)\b/u',$text)) return 'show';
    return '';
}

function mg_task_agent_program_matches(string $message,array $programs): array
{
    $text=mg_task_agent_intent_lower($message);
    $matched=[];
    foreach ($programs as $program) {
        foreach ([(string)($program['name']??''),(string)($program['program_type']??''),(string)($program['status']??'')] as $needle) {
            $needle=mb_strtolower(trim(str_replace('_',' ',$needle)));
            if ($needle!=='' && mb_strlen($needle)>=3 && str_contains($text,$needle)) {$matched[]=$program;break;}
        }
    }
    return $matched?:$programs;
}

function mg_task_agent_program_route(string $message,array $context,array $agent): ?array
{
    $intent=mg_task_agent_program_intent($message,$agent);
    if ($intent==='') return null;
    if($intent==='monitor')return mg_task_agent_monitor_route(is_array($context['monitor_snapshot']??null)?$context['monitor_snapshot']:[]);
    if($intent==='policy')return mg_task_agent_policy_route($message,$context,$agent);
    if (empty($context['distribution_program_schema_ready'])) {
        return [
            'result'=>[
                'reply'=>'Workplace and community program coordination is built, but the consolidated Phase 4 migration has not been imported yet.',
                'cards'=>[ ['type'=>'warning','title'=>'Phase 4 migration pending','body'=>'The canonical merchant distribution workspace remains available. Import the one Phase 4 SQL file after every Phase 4 section is complete.','action'=>'none','review_payload'=>[]] ],
                'system_intent'=>'distribution_program_schema_pending',
            ],
            'response_source'=>'system_query','ai_reason'=>'','tool'=>'distribution_programs',
        ];
    }
    $programs=is_array($context['distribution_programs']??null)?$context['distribution_programs']:[];
    $available=is_array($context['available_distribution_programs']??null)?$context['available_distribution_programs']:[];
    if ($intent==='handoff_create') {
        return [
            'result'=>[
                'reply'=>'Program creation stays in the canonical merchant distribution workspace so budgets, recipients, products, eligibility, allocation, and issuance use one authority. Open that workspace to create the program, then connect it to this agent.',
                'cards'=>[ [
                    'type'=>'distribution_program_handoff','title'=>'Create in Distribution Programs','body'=>'Create the workplace reward, contest, giveaway, fundraiser, or sponsored program through the existing merchant authority. No program or issuance was created here.','action'=>'open_distribution_program','action_label'=>'Open distribution workspace','url'=>'/merchant-distribution-program.php','review_payload'=>[],
                ] ],
                'system_intent'=>'distribution_program_create_handoff',
            ],
            'response_source'=>'system_query','ai_reason'=>'','tool'=>'distribution_programs',
        ];
    }
    if ($intent==='link' || !$programs) {
        $cards=array_map(static fn(array $program):array=>mg_task_agent_program_card($program,true),array_slice($available,0,8));
        if (!$cards) $cards[]=[
            'type'=>'distribution_program_handoff','title'=>'No eligible existing programs','body'=>'Create an eligible program in the canonical merchant distribution workspace, then return to connect it.','action'=>'open_distribution_program','action_label'=>'Open distribution workspace','url'=>'/merchant-distribution-program.php','review_payload'=>[],
        ];
        return [
            'result'=>[
                'reply'=>$available?'Choose an existing canonical distribution program to connect. Participants, budgets, products, allocations, and issuance stay in the original program.':'No eligible canonical distribution program is available to connect yet.',
                'cards'=>$cards,
                'system_intent'=>$available?'link_existing_distribution_program':'distribution_programs_empty',
            ],
            'response_source'=>'system_query','ai_reason'=>'','tool'=>'distribution_programs',
        ];
    }
    $matches=array_slice(mg_task_agent_program_matches($message,$programs),0,8);
    return [
        'result'=>[
            'reply'=>'Here are the connected canonical programs. This agent provides system-query monitoring only; recipient eligibility, product assignment, allocation, issuance, and status mutations remain in the merchant distribution workspace.',
            'cards'=>array_map(static fn(array $program):array=>mg_task_agent_program_card($program,false),$matches),
            'system_intent'=>'show_distribution_programs',
        ],
        'response_source'=>'system_query','ai_reason'=>'','tool'=>'distribution_programs',
    ];
}

function mg_task_agent_program_chat(PDO $pdo,int $userId,array $agent,array $input): ?array
{
    $message=mg_personal_agent_text($input['message']??'',3000);
    $intent=$message!==''?mg_task_agent_program_intent($message,$agent):'';
    if ($message==='' || $intent==='') return null;
    mg_multi_agent_runtime_require_schema($pdo);
    if (($agent['lifecycle_status']??'')!=='active') throw new RuntimeException('Agent is not active.');
    if (($agent['runtime_status']??'')==='paused') throw new RuntimeException('Agent is paused. Resume it before chatting.');
    $template=mg_multi_agent_runtime_template($agent);
    $thread=mg_multi_agent_runtime_thread($pdo,$agent,$userId,mg_personal_agent_text($input['thread_id']??'',80));
    $context=mg_task_agent_program_append_context($pdo,$userId,$agent,mg_multi_agent_runtime_context($pdo,$userId,$agent,$template));
    if($intent==='policy')$context=mg_task_agent_policy_append_context($pdo,$userId,$agent,$context);
    if($intent==='monitor'){
        $monitor=mg_task_agent_monitor_snapshot($pdo,$userId,$agent);
        $context['monitor_snapshot']=$monitor;
        $context['monitor_for_model']=mg_task_agent_monitor_for_model($monitor);
    }
    $userMessage=mg_multi_agent_runtime_store($pdo,$userId,(int)$agent['id'],(int)$thread['id'],'user',$message,[],$context);
    $route=$intent==='policy'?mg_task_agent_policy_route($message,$context,$agent):mg_task_agent_program_route($message,$context,$agent);
    if (!$route || !is_array($route['result']??null)) return null;
    $result=$route['result'];
    $assistant=mg_multi_agent_runtime_store($pdo,$userId,(int)$agent['id'],(int)$thread['id'],'assistant',(string)$result['reply'],is_array($result['cards']??null)?$result['cards']:[],$context);
    $tool=$intent==='policy'?'policy_approvals':'distribution_programs';
    if($intent==='monitor')$tool='task_agent_monitor';
    mg_audit('multi_agent.chat_completed','agent',[
        'agent_id'=>(string)$agent['public_id'],'thread_id'=>(string)$thread['public_id'],'response_source'=>'system_query','model_key'=>'system_query','ai_reason'=>'','tool'=>$tool,'used_ai'=>false,'ai_tokens_total'=>0,
    ],$userId);
    return [
        'thread'=>['id'=>(string)$thread['public_id'],'title'=>(string)$thread['title']],
        'user_message'=>$userMessage,'assistant_message'=>$assistant,'used_ai'=>false,'response_source'=>'system_query','ai_reason'=>'','model_key'=>'','ai_tokens_used'=>['input'=>0,'output'=>0,'total'=>0],'ai_credits'=>mg_ai_credit_snapshot($pdo,$userId,'anthropic'),
    ];
}
