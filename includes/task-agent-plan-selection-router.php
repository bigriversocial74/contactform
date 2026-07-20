<?php
declare(strict_types=1);

function mg_task_agent_plan_selection_lower(string $value): string
{
    return mb_strtolower(trim($value));
}

function mg_task_agent_plan_selection_intent(string $message): string
{
    $text = mg_task_agent_plan_selection_lower($message);
    if (preg_match('/\b(show|view|open|review|continue)\b/u', $text) && preg_match('/\b(selected product|plan product|cart handoff)\b/u', $text)) return 'show_selection';
    if (preg_match('/\b(remove|detach|clear|change)\b/u', $text) && preg_match('/\b(selected product|plan product|from plan)\b/u', $text)) return 'remove_selection';
    if (preg_match('/\b(use|select|choose|attach|add)\b/u', $text) && preg_match('/\b(plan|gift plan|shortlist|shortlisted|product|gift)\b/u', $text)) return 'select_product';
    return '';
}

function mg_task_agent_plan_selection_match_shortlist(string $message, array $items): ?array
{
    $text = mg_task_agent_plan_selection_lower($message);
    $matches = [];
    foreach ($items as $item) {
        $title = mg_task_agent_plan_selection_lower((string)($item['product']['title'] ?? ''));
        if ($title !== '' && str_contains($text, $title)) $matches[] = $item;
    }
    if (count($matches) === 1) return $matches[0];
    if (!$matches && count($items) === 1) return $items[0];
    return null;
}

function mg_task_agent_plan_selection_match_plan(string $message, array $plans): ?array
{
    $text = mg_task_agent_plan_selection_lower($message);
    $editable = array_values(array_filter($plans, static fn(array $plan): bool => in_array((string)($plan['status'] ?? ''), ['draft','planned','ready'], true)));
    $matches = [];
    foreach ($editable as $plan) {
        foreach ([(string)($plan['title'] ?? ''), (string)($plan['context']['name'] ?? '')] as $needle) {
            $needle = mg_task_agent_plan_selection_lower($needle);
            if ($needle !== '' && str_contains($text, $needle)) {
                $matches[] = $plan;
                break;
            }
        }
    }
    if (count($matches) === 1) return $matches[0];
    if (!$matches && count($editable) === 1) return $editable[0];
    return null;
}

function mg_task_agent_plan_selection_questions(array $shortlist, array $plans): array
{
    $cards = [];
    foreach (array_slice($shortlist, 0, 3) as $item) {
        $title = (string)($item['product']['title'] ?? 'Shortlisted product');
        $cards[] = [
            'type'=>'question','title'=>$title,'body'=>'Name the editable gift plan that should use this product.',
            'action'=>'seed_prompt','prompt'=>'Use '.$title.' for ','review_payload'=>[],
        ];
    }
    foreach (array_slice($plans, 0, 3) as $plan) {
        $title = (string)($plan['title'] ?? 'Gift plan');
        $cards[] = [
            'type'=>'question','title'=>$title,'body'=>'Name the shortlisted product to attach to this plan.',
            'action'=>'seed_prompt','prompt'=>'Add a shortlisted product to '.$title.'.','review_payload'=>[],
        ];
    }
    return array_slice($cards, 0, 6);
}

function mg_task_agent_plan_selection_route(string $message, array $context, array $template): ?array
{
    $intent = mg_task_agent_plan_selection_intent($message);
    if ($intent === '') return null;

    $selections = is_array($context['plan_selections'] ?? null) ? $context['plan_selections'] : [];
    if ($intent === 'show_selection') {
        return [
            'result'=>[
                'reply'=>$selections ? 'Here are the products currently selected for your editable gift plans.' : 'No editable gift plan currently has a selected product.',
                'cards'=>array_map(static fn(array $selection): array => mg_task_agent_plan_selection_card($selection), $selections),
                'system_intent'=>'show_plan_selection',
            ],
            'response_source'=>'system_query','ai_reason'=>'',
        ];
    }

    if ($intent === 'remove_selection') {
        $selection = null;
        foreach ($selections as $candidate) {
            $title = mg_task_agent_plan_selection_lower((string)($candidate['product']['title'] ?? ''));
            if ($title !== '' && str_contains(mg_task_agent_plan_selection_lower($message), $title)) {
                $selection = $candidate;
                break;
            }
        }
        if (!$selection && count($selections) === 1) $selection = $selections[0];
        if (!$selection) {
            return [
                'result'=>[
                    'reply'=>'Name the selected product you want to remove from its gift plan.',
                    'cards'=>array_map(static fn(array $item): array => mg_task_agent_plan_selection_card($item), array_slice($selections,0,6)),
                    'system_intent'=>'remove_plan_selection_missing',
                ],
                'response_source'=>'system_query','ai_reason'=>'',
            ];
        }
        return [
            'result'=>[
                'reply'=>'Review this removal. The product stays on the shortlist but will no longer be attached to the gift plan.',
                'cards'=>[ [
                    'type'=>'plan_product_selection',
                    'title'=>'Remove '.(string)($selection['product']['title'] ?? 'selected product'),
                    'body'=>'This does not alter the cart or any completed order.',
                    'action'=>'remove_plan_product',
                    'action_label'=>'Remove from gift plan',
                    'review_payload'=>[
                        'shortlist_id'=>(string)$selection['shortlist_id'],
                        'plan_id'=>(string)($selection['plan']['id'] ?? ''),
                    ],
                    'product'=>mg_task_agent_plan_selection_product_snapshot($selection['product'] ?? []),
                    'plan'=>$selection['plan'] ?? [],
                    'approval_required'=>true,
                ] ],
                'system_intent'=>'remove_plan_selection',
            ],
            'response_source'=>'system_query','ai_reason'=>'',
        ];
    }

    $shortlist = array_values(array_filter(
        is_array($context['shortlist'] ?? null) ? $context['shortlist'] : [],
        static fn(array $item): bool => ($item['status'] ?? 'active') === 'active'
    ));
    $plans = is_array($context['active_plans'] ?? null) ? $context['active_plans'] : [];
    $item = mg_task_agent_plan_selection_match_shortlist($message, $shortlist);
    $plan = mg_task_agent_plan_selection_match_plan($message, $plans);

    if (!$item || !$plan) {
        return [
            'result'=>[
                'reply'=>'Choose one shortlisted product and one editable gift plan before attaching the product.',
                'cards'=>mg_task_agent_plan_selection_questions($shortlist,$plans),
                'system_intent'=>'select_plan_product_missing_context',
            ],
            'response_source'=>'system_query','ai_reason'=>'',
        ];
    }

    $recipientId = (string)($item['recipient_context']['contact_id'] ?? '');
    $planContextId = (string)($plan['context']['id'] ?? '');
    if ($recipientId !== '' && $planContextId !== '' && !hash_equals($recipientId, $planContextId)) {
        return [
            'result'=>[
                'reply'=>'That shortlisted product was prepared for a different recipient. Choose a matching product or search again.',
                'cards'=>[],
                'system_intent'=>'select_plan_product_context_mismatch',
            ],
            'response_source'=>'system_query','ai_reason'=>'',
        ];
    }

    $selection = ['shortlist_id'=>(string)$item['id'],'plan'=>$plan,'product'=>$item['product'] ?? []];
    return [
        'result'=>[
            'reply'=>'Review this product selection before updating the gift plan. Nothing will be added to the cart yet.',
            'cards'=>[mg_task_agent_plan_selection_card($selection,true)],
            'system_intent'=>'select_plan_product',
        ],
        'response_source'=>'system_query','ai_reason'=>'',
    ];
}

function mg_task_agent_plan_selection_model_context(array $context): array
{
    return [
        'selected_plan_products'=>mg_task_agent_plan_selection_for_model(
            is_array($context['plan_selections'] ?? null) ? $context['plan_selections'] : []
        ),
    ];
}
