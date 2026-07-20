<?php
declare(strict_types=1);

function mg_task_agent_delivery_lower(string $value): string
{
    return mb_strtolower(trim($value));
}

function mg_task_agent_delivery_intent(string $message): string
{
    $text=mg_task_agent_delivery_lower($message);
    if(preg_match('/\b(show|view|review|status|ready|readiness)\b/u',$text) && preg_match('/\b(delivery|send later|schedule|preparation|recipient information)\b/u',$text))return 'show_delivery';
    if(preg_match('/\b(request|ask|permission)\b/u',$text) && preg_match('/\b(address|preference|preferences|birthday|birthdate|recipient information)\b/u',$text))return 'request_information';
    if(preg_match('/\b(approve|pause|resume|prepare|cancel)\b/u',$text) && preg_match('/\b(delivery|send later|schedule|preparation)\b/u',$text))return 'manage_schedule';
    if(preg_match('/\b(schedule|send later|prepare for|delivery preparation)\b/u',$text))return 'create_schedule';
    return '';
}

function mg_task_agent_delivery_match_preparation(string $message,array $items): ?array
{
    $text=mg_task_agent_delivery_lower($message);
    $matches=[];
    foreach($items as $item){
        foreach([(string)($item['plan']['title']??''),(string)($item['product']['title']??''),(string)($item['recipient']['name']??'')] as $needle){
            $needle=mg_task_agent_delivery_lower($needle);
            if($needle!==''&&str_contains($text,$needle)){$matches[]=$item;break;}
        }
    }
    if(count($matches)===1)return $matches[0];
    if(!$matches&&count($items)===1)return $items[0];
    return null;
}

function mg_task_agent_delivery_question_cards(array $items,string $promptPrefix): array
{
    $cards=[];
    foreach(array_slice($items,0,6) as $item){
        $planTitle=(string)($item['plan']['title']??'Gift plan');
        $cards[]=[
            'type'=>'question',
            'title'=>$planTitle,
            'body'=>'Selected product: '.((string)($item['product']['title']??'gift')).'. Recipient: '.((string)($item['recipient']['name']??'not identified')).'.',
            'action'=>'seed_prompt',
            'action_label'=>'Use this plan',
            'prompt'=>$promptPrefix.$planTitle,
            'review_payload'=>[],
        ];
    }
    return $cards;
}

function mg_task_agent_delivery_route(string $message,array $context,array $template): ?array
{
    if(($template['key']??'')!=='birthday_occasion')return null;
    $intent=mg_task_agent_delivery_intent($message);
    if($intent==='')return null;
    $items=is_array($context['delivery_preparations']??null)?$context['delivery_preparations']:[];

    if($intent==='show_delivery'){
        return [
            'result'=>[
                'reply'=>$items?'Here are your current recipient and send-later preparation states.':'Select a published product for an editable gift plan before preparing delivery.',
                'cards'=>array_map(static fn(array $item):array=>mg_task_agent_delivery_card($item),$items),
                'system_intent'=>'show_delivery_preparation',
            ],
            'response_source'=>'system_query','ai_reason'=>'',
        ];
    }

    $item=mg_task_agent_delivery_match_preparation($message,$items);
    if(!$item){
        return [
            'result'=>[
                'reply'=>'Choose one gift plan with a selected product before preparing recipient information or a send-later checkpoint.',
                'cards'=>mg_task_agent_delivery_question_cards($items,'Show delivery preparation for '),
                'system_intent'=>'delivery_preparation_missing_plan',
            ],
            'response_source'=>'system_query','ai_reason'=>'',
        ];
    }

    if($intent==='request_information'){
        $recipient=is_array($item['recipient']??null)?$item['recipient']:[];
        if(empty($recipient['linked_user'])){
            return [
                'result'=>[
                    'reply'=>'Permission requests can only be sent to a mutually connected Microgifter user. For a private contact, update the saved contact directly outside agent chat.',
                    'cards'=>[mg_task_agent_delivery_card($item)],
                    'system_intent'=>'recipient_request_unavailable',
                ],
                'response_source'=>'system_query','ai_reason'=>'',
            ];
        }
        $text=mg_task_agent_delivery_lower($message);
        $scopes=[];
        if(str_contains($text,'address')||str_contains($text,'delivery'))$scopes[]='profile.address';
        if(str_contains($text,'preference'))$scopes[]='profile.gift_preferences';
        if(str_contains($text,'birthday')||str_contains($text,'birthdate'))$scopes[]='profile.birthdate';
        if(!$scopes)$scopes=['profile.address','profile.gift_preferences'];
        return [
            'result'=>[
                'reply'=>'Review this recipient permission request. The recipient controls every approved scope and can revoke access later.',
                'cards'=>[mg_task_agent_delivery_request_card($item,$scopes)],
                'system_intent'=>'recipient_permission_request',
            ],
            'response_source'=>'system_query','ai_reason'=>'',
        ];
    }

    if($intent==='manage_schedule'){
        $schedule=is_array($item['schedule']??null)?$item['schedule']:null;
        if(!$schedule){
            return [
                'result'=>[
                    'reply'=>'This plan does not have a send-later preparation yet.',
                    'cards'=>[mg_task_agent_delivery_card($item)],
                    'system_intent'=>'manage_delivery_schedule_missing',
                ],
                'response_source'=>'system_query','ai_reason'=>'',
            ];
        }
        $text=mg_task_agent_delivery_lower($message);
        $action='';
        foreach(['approve','pause','resume','prepare','cancel'] as $candidate){if(preg_match('/\b'.preg_quote($candidate,'/').'\b/u',$text)){$action=$candidate;break;}}
        if($action===''){
            return [
                'result'=>[
                    'reply'=>'Choose approve, pause, resume, mark prepared, or cancel. None of these actions sends or purchases the gift.',
                    'cards'=>[mg_task_agent_delivery_card($item)],
                    'system_intent'=>'manage_delivery_schedule_missing_action',
                ],
                'response_source'=>'system_query','ai_reason'=>'',
            ];
        }
        $card=mg_task_agent_delivery_card($item);
        $card['action']='update_delivery_schedule';
        $card['action_label']=ucfirst($action).' preparation';
        $card['review_payload']=['schedule_id'=>(string)$schedule['id'],'plan_id'=>(string)($item['plan']['id']??''),'schedule_action'=>$action];
        return [
            'result'=>[
                'reply'=>'Review this prepare-only schedule change. It does not purchase, send, claim, redeem, or complete an order.',
                'cards'=>[$card],
                'system_intent'=>'manage_delivery_schedule',
            ],
            'response_source'=>'system_query','ai_reason'=>'',
        ];
    }

    $date=mg_task_agent_delivery_parse_datetime($message);
    if(!$date){
        $planTitle=(string)($item['plan']['title']??'this gift plan');
        return [
            'result'=>[
                'reply'=>'Add a future date, optional time, and timezone for the send-later preparation.',
                'cards'=>[ [
                    'type'=>'question','title'=>'Choose send-later time','body'=>'Example: 2026-08-20 at 09:00 America/Phoenix.',
                    'action'=>'seed_prompt','action_label'=>'Add date','prompt'=>'Prepare '.$planTitle.' for YYYY-MM-DD at 09:00 America/Phoenix.','review_payload'=>[],
                ] ],
                'system_intent'=>'create_delivery_schedule_missing_time',
            ],
            'response_source'=>'system_query','ai_reason'=>'',
        ];
    }
    return [
        'result'=>[
            'reply'=>'Review this send-later preparation. It creates a checkpoint only and cannot send or purchase the gift.',
            'cards'=>[mg_task_agent_delivery_card($item,true,$date)],
            'system_intent'=>'create_delivery_schedule',
        ],
        'response_source'=>'system_query','ai_reason'=>'',
    ];
}

function mg_task_agent_delivery_model_context(array $context): array
{
    return ['delivery_preparations'=>mg_task_agent_delivery_for_model(
        is_array($context['delivery_preparations']??null)?$context['delivery_preparations']:[]
    )];
}
