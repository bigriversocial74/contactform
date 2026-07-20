<?php
declare(strict_types=1);

function mg_task_agent_recurring_intent(string $message): string
{
    $text=mg_task_agent_intent_lower($message);
    $recurring=preg_match('/\b(recurring|repeat(?:ing)?|program|every (?:week|month|quarter|year)|weekly|monthly|quarterly|yearly)\b/u',$text)===1;
    if(!$recurring)return '';
    if(preg_match('/\b(create|set up|setup|start|build|add)\b/u',$text))return 'create';
    if(preg_match('/\b(show|list|view|status|upcoming|next|manage|pause|resume|skip|cancel|generate|draft)\b/u',$text))return 'show';
    return 'show';
}

function mg_task_agent_recurring_default_start(?array $event): string
{
    try {
        if($event && !empty($event['event_date'])) {
            $date=new DateTimeImmutable((string)$event['event_date'].' 09:00:00',new DateTimeZone('UTC'));
            while($date<=new DateTimeImmutable('now',new DateTimeZone('UTC'))) $date=$date->modify('+1 year');
            return $date->format('Y-m-d H:i:s');
        }
    } catch(Throwable) {}
    return (new DateTimeImmutable('+7 days 09:00:00',new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

function mg_task_agent_recurring_builder(string $message,array $context): array
{
    $snapshot=is_array($context['system_snapshot']??null)?$context['system_snapshot']:[];
    $contact=mg_task_agent_match_contact($message,$snapshot);
    $event=$contact?mg_task_agent_match_event($message,$snapshot,$contact):null;
    $name=trim((string)($contact['display_name']??'Recurring gifting'));
    $label=trim((string)($event['label']??'Gift program'));
    $type=(string)($event['type']??'general');
    $cadence=in_array($type,['birthday','anniversary'],true)?'yearly':'monthly';
    $contextType=$contact?(string)($contact['type']??'contact'):'none';
    if(!in_array($contextType,['contact','linked_user'],true))$contextType=$contact?'contact':'none';
    return [
        'type'=>'recurring_program_builder',
        'title'=>$name.' — '.$label,
        'body'=>'Review the cadence, first review date, end date, and budget. Every occurrence creates one approval-first draft plan only.',
        'action'=>'create_recurring_program',
        'action_label'=>'Create recurring program',
        'review_payload'=>[
            'context_type'=>$contextType,
            'context_id'=>$contact?(string)($contact['id']??''):'',
            'title'=>$name.' — '.$label.' recurring gifts',
            'occasion_type'=>$type,
            'occasion_label'=>$label,
            'cadence'=>$cadence,
            'interval_count'=>1,
            'next_run_at'=>mg_task_agent_recurring_default_start($event),
            'end_at'=>'',
            'budget_min'=>$contact['budget_min']??null,
            'budget_max'=>$contact['budget_max']??null,
            'currency'=>'USD',
            'notes'=>'Recurring draft-plan program created through the Birthday & Occasion task agent. Review every generated plan before cart or checkout.',
        ],
        'approval_required'=>true,
        'generation_mode'=>'draft_plan_only',
        'commerce_executed'=>false,
    ];
}

function mg_task_agent_recurring_matches(string $message,array $programs): array
{
    $text=mg_task_agent_intent_lower($message);
    $matches=[];
    foreach($programs as $program){
        $needles=[
            (string)($program['title']??''),
            (string)($program['context']['name']??''),
            (string)($program['occasion_label']??''),
        ];
        foreach($needles as $needle){
            $needle=mb_strtolower(trim($needle));
            if($needle!==''&&mb_strlen($needle)>=2&&str_contains($text,$needle)){$matches[]=$program;break;}
        }
    }
    return $matches?:$programs;
}

function mg_task_agent_recurring_route(string $message,array $context,array $template): ?array
{
    if(($template['key']??'')!=='birthday_occasion')return null;
    $intent=mg_task_agent_recurring_intent($message);
    if($intent==='')return null;
    if(empty($context['recurring_schema_ready'])){
        return [
            'result'=>[
                'reply'=>'Recurring program integration is built, but the consolidated Phase 4 migration has not been imported yet.',
                'cards'=>[ [
                    'type'=>'warning','title'=>'Phase 4 migration pending','body'=>'Existing Phase 3 gifting remains available. Import the single Phase 4 SQL file after all Phase 4 sections are complete.','action'=>'none','review_payload'=>[],
                ] ],
                'system_intent'=>'recurring_schema_pending',
            ],
            'response_source'=>'system_query','ai_reason'=>'',
        ];
    }
    if($intent==='create'){
        return [
            'result'=>[
                'reply'=>'I prepared a recurring draft-program form from saved Microgifter context. Review every field before creating it. No product, cart, charge, message, or delivery will be created.',
                'cards'=>[mg_task_agent_recurring_builder($message,$context)],
                'system_intent'=>'create_recurring_program_review',
            ],
            'response_source'=>'system_query','ai_reason'=>'',
        ];
    }
    $programs=is_array($context['recurring_programs']??null)?$context['recurring_programs']:[];
    $matches=array_slice(mg_task_agent_recurring_matches($message,$programs),0,8);
    if(!$matches){
        return [
            'result'=>[
                'reply'=>'This agent does not have a recurring gift program yet. Create one to generate approval-first draft plans on a schedule.',
                'cards'=>[mg_task_agent_recurring_builder($message,$context)],
                'system_intent'=>'recurring_programs_empty',
            ],
            'response_source'=>'system_query','ai_reason'=>'',
        ];
    }
    return [
        'result'=>[
            'reply'=>'Here are this agent’s recurring gift programs. Generate and skip actions affect draft-plan cycles only; purchasing always remains a separate explicit checkout step.',
            'cards'=>array_map(static fn(array $program):array=>mg_task_agent_recurring_card($program),$matches),
            'system_intent'=>'show_recurring_programs',
        ],
        'response_source'=>'system_query','ai_reason'=>'',
    ];
}

function mg_task_agent_recurring_model_context(array $context): array
{
    return ['recurring_programs'=>mg_task_agent_recurring_for_model(
        is_array($context['recurring_programs']??null)?$context['recurring_programs']:[]
    )];
}

function mg_task_agent_recurring_append_context(PDO $pdo,int $userId,array $agent,array $context): array
{
    $programs=mg_task_agent_recurring_programs($pdo,$userId,(int)$agent['id'],40);
    $context['recurring_programs']=$programs;
    $context['recurring_programs_for_model']=mg_task_agent_recurring_for_model($programs);
    $context['recurring_schema_ready']=mg_task_agent_recurring_schema_ready($pdo);
    if(is_array($context['system_snapshot']['summary']??null)){
        $context['system_snapshot']['summary']['recurring_programs']=count($programs);
        $context['system_snapshot']['summary']['recurring_programs_due']=count(array_filter($programs,static fn(array $program):bool=>!empty($program['due'])));
    }
    return $context;
}

function mg_task_agent_recurring_chat(PDO $pdo,int $userId,array $agent,array $input): ?array
{
    $message=mg_personal_agent_text($input['message']??'',3000);
    if($message===''||mg_task_agent_recurring_intent($message)==='')return null;
    $template=mg_multi_agent_runtime_template($agent);
    if(($template['key']??'')!=='birthday_occasion')return null;
    mg_multi_agent_runtime_require_schema($pdo);
    if(($agent['lifecycle_status']??'')!=='active')throw new RuntimeException('Agent is not active.');
    if(($agent['runtime_status']??'')==='paused')throw new RuntimeException('Agent is paused. Resume it before chatting.');

    $thread=mg_multi_agent_runtime_thread($pdo,$agent,$userId,mg_personal_agent_text($input['thread_id']??'',80));
    $context=mg_task_agent_recurring_append_context($pdo,$userId,$agent,mg_multi_agent_runtime_context($pdo,$userId,$agent,$template));
    $userMessage=mg_multi_agent_runtime_store($pdo,$userId,(int)$agent['id'],(int)$thread['id'],'user',$message,[],$context);
    $route=mg_task_agent_recurring_route($message,$context,$template);
    if(!$route||!is_array($route['result']??null))return null;
    $result=$route['result'];
    $assistant=mg_multi_agent_runtime_store($pdo,$userId,(int)$agent['id'],(int)$thread['id'],'assistant',(string)$result['reply'],is_array($result['cards']??null)?$result['cards']:[],$context);
    mg_audit('multi_agent.chat_completed','agent',[
        'agent_id'=>(string)$agent['public_id'],
        'thread_id'=>(string)$thread['public_id'],
        'response_source'=>'system_query',
        'model_key'=>'system_query',
        'ai_reason'=>'',
        'tool'=>'recurring_programs',
        'used_ai'=>false,
        'ai_tokens_total'=>0,
    ],$userId);
    return [
        'thread'=>['id'=>(string)$thread['public_id'],'title'=>(string)$thread['title']],
        'user_message'=>$userMessage,
        'assistant_message'=>$assistant,
        'used_ai'=>false,
        'response_source'=>'system_query',
        'ai_reason'=>'',
        'model_key'=>'',
        'ai_tokens_used'=>['input'=>0,'output'=>0,'total'=>0],
        'ai_credits'=>mg_ai_credit_snapshot($pdo,$userId,'anthropic'),
    ];
}
