<?php
declare(strict_types=1);

function mg_task_agent_order_tracking_intent(string $message): string
{
    $text=mb_strtolower(trim($message));
    if(preg_match('/\b(order|purchase|receipt|payment|pppm|microgift|issuance|inbox delivery)\b/u',$text)
        && preg_match('/\b(show|view|track|status|confirm|confirmation|where|did|has|is)\b/u',$text))return 'show_order_tracking';
    return '';
}

function mg_task_agent_order_tracking_match(string $message,array $items): array
{
    $text=mb_strtolower(trim($message));
    $matches=[];
    foreach($items as $item){
        foreach([(string)($item['order']['id']??''),(string)($item['line']['title']??''),(string)($item['plan']['title']??''),(string)($item['receipt']['number']??'')] as $needle){
            $needle=mb_strtolower(trim($needle));
            if($needle!==''&&str_contains($text,$needle)){$matches[]=$item;break;}
        }
    }
    return $matches?:$items;
}

function mg_task_agent_order_tracking_route(string $message,array $context,array $template): ?array
{
    if(($template['key']??'')!=='birthday_occasion')return null;
    if(mg_task_agent_order_tracking_intent($message)==='')return null;
    $items=is_array($context['order_tracking']??null)?$context['order_tracking']:[];
    $matches=array_slice(mg_task_agent_order_tracking_match($message,$items),0,8);
    if(!$matches){
        return [
            'result'=>[
                'reply'=>'I could not find a buyer-owned order with an exact product-version match to a product currently selected for this agent’s gift plans.',
                'cards'=>[[
                    'type'=>'question',
                    'title'=>'Review canonical purchases',
                    'body'=>'Open your commerce center to review all orders. The agent will not infer a plan match from a similar title or price.',
                    'action'=>'open_link',
                    'action_label'=>'Open commerce center',
                    'url'=>'/account-commerce.php',
                    'review_payload'=>[],
                ]],
                'system_intent'=>'order_tracking_not_found',
            ],
            'response_source'=>'system_query','ai_reason'=>'',
        ];
    }
    return [
        'result'=>[
            'reply'=>'Here are the buyer-owned purchases that exactly match a product version selected for this agent’s gift plans. These records are read-only.',
            'cards'=>array_map(static fn(array $item):array=>mg_task_agent_order_tracking_card($item),$matches),
            'system_intent'=>'show_order_tracking',
        ],
        'response_source'=>'system_query','ai_reason'=>'',
    ];
}

function mg_task_agent_order_tracking_model_context(array $context): array
{
    return ['purchase_tracking'=>mg_task_agent_order_tracking_for_model(
        is_array($context['order_tracking']??null)?$context['order_tracking']:[]
    )];
}
