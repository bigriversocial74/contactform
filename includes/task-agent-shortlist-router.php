<?php
declare(strict_types=1);

function mg_task_agent_shortlist_search_requested(string $message): bool
{
    $text = mg_task_agent_intent_lower($message);
    if (preg_match('/\b(compare|rank|best fit|best choice|choose between|pros and cons)\b/u',$text)) return false;
    return preg_match('/\b(find|search|browse|explore|look for|show me)\b/u',$text) === 1
        && preg_match('/\b(gift|gifts|product|products|voucher|vouchers|local)\b/u',$text) === 1;
}

function mg_task_agent_shortlist_show_requested(string $message): bool
{
    $text = mg_task_agent_intent_lower($message);
    return preg_match('/\b(show|list|open|view|what(?:\'s| is) on)\b/u',$text) === 1
        && preg_match('/\b(shortlist|shortlisted|saved gifts?|saved products?)\b/u',$text) === 1;
}

function mg_task_agent_shortlist_search_payload(string $message, array $context): array
{
    $snapshot = is_array($context['system_snapshot'] ?? null) ? $context['system_snapshot'] : [];
    $contact = mg_task_agent_match_contact($message,$snapshot);
    $event = $contact ? mg_task_agent_match_event($message,$snapshot,$contact) : null;
    $recipient = [];
    $query = '';
    $location = '';
    $budgetMin = null;
    $budgetMax = null;
    if ($contact) {
        $query = mg_task_agent_intent_text((string)($contact['gift_preferences'] ?? ''),100);
        if ($query === '') $query = mg_task_agent_intent_text((string)($contact['interests'] ?? ''),100);
        $location = mg_task_agent_intent_text((string)($contact['location'] ?? ''),100);
        $budgetMin = isset($contact['budget_min']) ? (float)$contact['budget_min'] : null;
        $budgetMax = isset($contact['budget_max']) ? (float)$contact['budget_max'] : null;
        $recipient = [
            'contact_id'=>(string)($contact['id'] ?? ''),
            'name'=>(string)($contact['display_name'] ?? ''),
            'relationship'=>(string)($contact['relationship'] ?? ''),
            'occasion'=>(string)($event['label'] ?? ''),
            'target_date'=>(string)($event['event_date'] ?? ''),
            'location'=>$location,
            'budget_min'=>$budgetMin,
            'budget_max'=>$budgetMax,
        ];
    }
    return [
        'q'=>$query,
        'location'=>$location,
        'category'=>'',
        'budget_min'=>$budgetMin,
        'budget_max'=>$budgetMax,
        'limit'=>12,
        'allow_broad_fallback'=>true,
        'recipient_context'=>$recipient,
    ];
}

function mg_task_agent_shortlist_route(string $message, array $context, array $template): ?array
{
    if (mg_task_agent_shortlist_show_requested($message)) {
        $items = is_array($context['shortlist'] ?? null) ? $context['shortlist'] : [];
        return [
            'result'=>[
                'reply'=>$items ? 'Here are the currently available products on this agent’s shortlist.' : 'This agent does not have any currently available shortlisted products.',
                'cards'=>mg_task_agent_shortlist_cards($items),
                'system_intent'=>'show_shortlist',
            ],
            'response_source'=>'system_query',
            'ai_reason'=>'',
            'tool'=>'',
            'tool_input'=>[],
        ];
    }

    if (!mg_task_agent_shortlist_search_requested($message)) return null;
    $payload = mg_task_agent_shortlist_search_payload($message,$context);
    if (($template['key'] ?? '') === 'birthday_occasion' && empty($payload['recipient_context']['contact_id'])) {
        return [
            'result'=>[
                'reply'=>'Name a saved recipient so I can apply the correct budget, preferences, occasion, and location to local gift discovery.',
                'cards'=>[ [
                    'type'=>'question','title'=>'Recipient needed','body'=>'Use a saved contact name for permission-safe gift discovery.',
                    'action'=>'seed_prompt','prompt'=>'Find local gifts for ','review_payload'=>[],
                ] ],
                'system_intent'=>'discovery_missing_recipient',
            ],
            'response_source'=>'system_query',
            'ai_reason'=>'',
            'tool'=>'',
            'tool_input'=>[],
        ];
    }
    return [
        'result'=>null,
        'response_source'=>'system_query',
        'ai_reason'=>'',
        'tool'=>'discover_products',
        'tool_input'=>$payload,
    ];
}

function mg_task_agent_shortlist_model_context(array $context): array
{
    return [
        'shortlist'=>array_slice(is_array($context['shortlist_for_model'] ?? null)?$context['shortlist_for_model']:[],0,8),
        'shortlist_count'=>count(is_array($context['shortlist'] ?? null)?$context['shortlist']:[]),
    ];
}
