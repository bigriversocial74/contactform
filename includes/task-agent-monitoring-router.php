<?php
declare(strict_types=1);

function mg_task_agent_monitor_route(array $snapshot): array
{
    $items=is_array($snapshot['items']??null)?$snapshot['items']:[];
    $counts=is_array($snapshot['counts']??null)?$snapshot['counts']:[];
    $summary=sprintf('%d high, %d medium, %d low, and %d informational review items.',(int)($counts['high']??0),(int)($counts['medium']??0),(int)($counts['low']??0),(int)($counts['info']??0));
    if(!$items){
        return ['result'=>[
            'reply'=>'No current monitoring issues were found in this agent’s connected canonical records.',
            'cards'=>[['type'=>'task_agent_monitor_summary','title'=>'Monitoring clear','body'=>$summary,'monitor'=>['counts'=>$counts,'generated_at'=>$snapshot['generated_at']??null],'action'=>'none','review_payload'=>[],'used_ai'=>false,'stored_alert'=>false]],
            'system_intent'=>'monitoring_clear',
        ],'response_source'=>'system_query','ai_reason'=>'','tool'=>'task_agent_monitor'];
    }
    $cards=array_map('mg_task_agent_monitor_card',array_slice($items,0,12));
    array_unshift($cards,['type'=>'task_agent_monitor_summary','title'=>'Agent monitoring review','body'=>$summary,'monitor'=>['counts'=>$counts,'generated_at'=>$snapshot['generated_at']??null],'action'=>'none','review_payload'=>[],'used_ai'=>false,'stored_alert'=>false]);
    return ['result'=>[
        'reply'=>'Here is the current system-query monitoring review. Each card links to the canonical source; no alert row, purchase, message, allocation, issuance, or approval decision was created.',
        'cards'=>$cards,
        'system_intent'=>'task_agent_monitoring_review',
    ],'response_source'=>'system_query','ai_reason'=>'','tool'=>'task_agent_monitor'];
}
