<?php
declare(strict_types=1);

function mg_task_agent_lifecycle_intent(string $message): string
{
    $text=mb_strtolower(trim($message));
    if(preg_match('/\b(send|regift|claim|redeem|redemption|follow up|follow-up|message|lifecycle|inbox|sent|claimed)\b/u',$text)
        && preg_match('/\b(show|view|status|can|available|where|track|open|what)\b/u',$text))return 'show_lifecycle';
    return '';
}

function mg_task_agent_lifecycle_match(string $message,array $items): array
{
    $text=mb_strtolower(trim($message));
    $matches=[];
    foreach($items as $item){
        foreach([(string)($item['gift']['title']??''),(string)($item['gift']['id']??''),(string)($item['participants']['recipient_name']??''),(string)($item['participants']['sender_name']??'')] as $needle){
            $needle=mb_strtolower(trim($needle));
            if($needle!==''&&str_contains($text,$needle)){$matches[]=$item;break;}
        }
    }
    return $matches?:$items;
}

function mg_task_agent_lifecycle_route(string $message,array $context,array $template): ?array
{
    if(($template['key']??'')!=='birthday_occasion')return null;
    if(mg_task_agent_lifecycle_intent($message)==='')return null;
    $items=is_array($context['lifecycle_tracking']??null)?$context['lifecycle_tracking']:[];
    $matches=array_slice(mg_task_agent_lifecycle_match($message,$items),0,8);
    if(!$matches){
        return [
            'result'=>[
                'reply'=>'I could not find an Action Center gift linked to a buyer-owned order and an exact product version selected by this agent.',
                'cards'=>[[
                    'type'=>'question','title'=>'Open Action Center','body'=>'Review your complete Inbox, Sent, and Claimed lifecycle records in the canonical Action Center.',
                    'action'=>'open_link','action_label'=>'Open Action Center','url'=>'/inbox.php','review_payload'=>[],
                ]],
                'system_intent'=>'lifecycle_tracking_not_found',
            ],
            'response_source'=>'system_query','ai_reason'=>'',
        ];
    }
    return [
        'result'=>[
            'reply'=>'Here are the lifecycle states and currently available Action Center capabilities for gifts linked to this agent. Open the Action Center to perform any send, regift, claim, redemption, follow-up, or message action.',
            'cards'=>array_map(static fn(array $item):array=>mg_task_agent_lifecycle_card($item),$matches),
            'system_intent'=>'show_lifecycle_tracking',
        ],
        'response_source'=>'system_query','ai_reason'=>'',
    ];
}

function mg_task_agent_lifecycle_model_context(array $context): array
{
    return ['gift_lifecycle'=>mg_task_agent_lifecycle_for_model(
        is_array($context['lifecycle_tracking']??null)?$context['lifecycle_tracking']:[]
    )];
}
