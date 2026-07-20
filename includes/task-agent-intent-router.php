<?php
declare(strict_types=1);

function mg_task_agent_intent_text(string $value, int $limit = 500): string
{
    return trim(mb_substr($value, 0, $limit));
}

function mg_task_agent_intent_lower(string $value): string
{
    return mb_strtolower(mg_task_agent_intent_text($value, 3000));
}

function mg_task_agent_match_contact(string $message, array $snapshot): ?array
{
    $text = mg_task_agent_intent_lower($message);
    $best = null;
    $bestLength = 0;
    foreach (($snapshot['contacts_by_id'] ?? []) as $contact) {
        if (!is_array($contact)) continue;
        foreach ([(string)($contact['display_name'] ?? ''), (string)($contact['nickname'] ?? '')] as $candidate) {
            $needle = mb_strtolower(trim($candidate));
            if ($needle === '' || mb_strlen($needle) < 2 || !str_contains($text, $needle)) continue;
            if (mb_strlen($needle) > $bestLength) {
                $best = $contact;
                $bestLength = mb_strlen($needle);
            }
        }
    }
    return $best;
}

function mg_task_agent_match_event(string $message, array $snapshot, ?array $contact = null): ?array
{
    $text = mg_task_agent_intent_lower($message);
    $contactId = (string)($contact['id'] ?? '');
    $events = is_array($snapshot['upcoming'] ?? null) ? $snapshot['upcoming'] : [];
    foreach ($events as $event) {
        if (!is_array($event)) continue;
        $name = mb_strtolower((string)($event['contact_name'] ?? ''));
        $label = mb_strtolower((string)($event['label'] ?? ''));
        $sameContact = $contactId !== '' && hash_equals($contactId, (string)($event['contact_id'] ?? ''));
        if ($sameContact && ($label === '' || str_contains($text, $label) || count($events) === 1)) return $event;
        if ($name !== '' && str_contains($text, $name) && ($label === '' || str_contains($text, $label) || str_contains($text, 'birthday') || str_contains($text, 'occasion'))) return $event;
    }
    if ($contactId !== '') {
        foreach ($events as $event) if ((string)($event['contact_id'] ?? '') === $contactId) return $event;
    }
    return null;
}

function mg_task_agent_matching_plans(array $context, ?array $contact, int $limit = 4): array
{
    $contactId = (string)($contact['id'] ?? '');
    if ($contactId === '') return [];
    $matches = [];
    foreach (($context['active_plans'] ?? []) as $plan) {
        if (!is_array($plan)) continue;
        $planContext = is_array($plan['context'] ?? null) ? $plan['context'] : [];
        if ((string)($planContext['id'] ?? '') !== $contactId) continue;
        $matches[] = $plan;
        if (count($matches) >= $limit) break;
    }
    return $matches;
}

function mg_task_agent_matching_reminders(array $context, ?array $contact, int $limit = 4): array
{
    $contactId = (string)($contact['id'] ?? '');
    if ($contactId === '') return [];
    $matches = [];
    foreach (($context['scheduled_reminders'] ?? []) as $reminder) {
        if (!is_array($reminder)) continue;
        $reminderContext = is_array($reminder['context'] ?? null) ? $reminder['context'] : [];
        if ((string)($reminderContext['id'] ?? '') !== $contactId) continue;
        $matches[] = $reminder;
        if (count($matches) >= $limit) break;
    }
    return $matches;
}

function mg_task_agent_money_range(?float $min, ?float $max): string
{
    if ($min === null && $max === null) return '';
    if ($min !== null && $max !== null) return '$'.number_format($min, 0).'–$'.number_format($max, 0);
    if ($max !== null) return 'Up to $'.number_format($max, 0);
    return 'From $'.number_format((float)$min, 0);
}

function mg_task_agent_discovery_url(array $contact, ?array $event): string
{
    $queryParts = [];
    $preferences = mg_task_agent_intent_text((string)($contact['gift_preferences'] ?? ''), 120);
    $interests = mg_task_agent_intent_text((string)($contact['interests'] ?? ''), 120);
    if ($preferences !== '') $queryParts[] = $preferences;
    elseif ($interests !== '') $queryParts[] = $interests;
    if ($event && !empty($event['label'])) $queryParts[] = (string)$event['label'];
    $params = ['type' => 'merchant'];
    $query = trim(implode(' ', array_slice($queryParts, 0, 2)));
    if ($query !== '') $params['q'] = mb_substr($query, 0, 100);
    $location = mg_task_agent_intent_text((string)($contact['location'] ?? ''), 100);
    if ($location !== '') $params['location'] = $location;
    return '/discover.php?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function mg_task_agent_missing_context_cards(array $contact, ?array $event): array
{
    $cards = [];
    $name = (string)($contact['display_name'] ?? 'this recipient');
    if (($contact['budget_min'] ?? null) === null && ($contact['budget_max'] ?? null) === null) {
        $cards[] = [
            'type' => 'warning',
            'title' => 'Budget needed',
            'body' => 'Add a budget range before comparing specific gift options.',
            'action' => 'seed_prompt',
            'prompt' => 'Remember that my budget for '.$name.' is ',
            'review_payload' => [],
        ];
    }
    if (trim((string)($contact['interests'] ?? '')) === '' && trim((string)($contact['gift_preferences'] ?? '')) === '') {
        $cards[] = [
            'type' => 'warning',
            'title' => 'Preferences needed',
            'body' => 'Add interests, restrictions, or gift preferences to improve recommendations.',
            'action' => 'seed_prompt',
            'prompt' => 'Remember that '.$name.' likes ',
            'review_payload' => [],
        ];
    }
    if (!$event) {
        $cards[] = [
            'type' => 'warning',
            'title' => 'Occasion or target date needed',
            'body' => 'Choose an occasion or target date before creating a timing-aware plan.',
            'action' => 'seed_prompt',
            'prompt' => 'Create a gift plan for '.$name.' for ',
            'review_payload' => [],
        ];
    }
    return $cards;
}

function mg_task_agent_memory_draft(string $message): ?array
{
    $text = mg_task_agent_intent_text($message, 1500);
    $lower = mb_strtolower($text);
    if (!preg_match('/\b(remember|save|store|keep)\b/u', $lower)) return null;
    if (!preg_match('/\b(preference|budget|likes?|dislikes?|avoid|instruction|style|timing|merchant|category|remember that|save that)\b/u', $lower)) return null;
    $value = preg_replace('/^.*?\b(?:remember|save|store|keep)(?:\s+that)?\s+/iu', '', $text, 1) ?? $text;
    $value = trim($value);
    if ($value === '' || mg_task_agent_memory_sensitive($value)) return null;
    $category = 'preference';
    if (preg_match('/\b(budget|\$|dollars?)\b/u', $lower)) $category = 'budget';
    elseif (preg_match('/\b(timing|before|days? early|lead time)\b/u', $lower)) $category = 'timing';
    elseif (preg_match('/\b(merchant|store|shop)\b/u', $lower)) $category = 'merchant';
    elseif (preg_match('/\b(category|restaurant|fitness|event|creator)\b/u', $lower)) $category = 'category';
    elseif (preg_match('/\b(instruction|always|never)\b/u', $lower)) $category = 'instruction';
    $title = match ($category) {
        'budget' => 'Saved budget guidance',
        'timing' => 'Saved timing preference',
        'merchant' => 'Saved merchant preference',
        'category' => 'Saved category preference',
        'instruction' => 'Saved agent instruction',
        default => 'Saved gifting preference',
    };
    return [
        'memory_key' => 'chat.'.substr(hash('sha256', $category.'|'.mb_strtolower($value)), 0, 24),
        'category' => $category,
        'title' => $title,
        'value' => $value,
    ];
}

function mg_task_agent_ai_reason(string $message): string
{
    $text = mg_task_agent_intent_lower($message);
    if (preg_match('/\b(write|compose|word|draft)\b/u', $text) && preg_match('/\b(message|note|card|greeting)\b/u', $text)) return 'personal_message_synthesis';
    if (preg_match('/\b(compare|rank|best fit|best choice|choose between|pros and cons)\b/u', $text)) return 'gift_comparison';
    if (preg_match('/\b(explain why|thoughtful|personalized|recommend the best|which gift)\b/u', $text)) return 'recommendation_synthesis';
    return '';
}

function mg_task_agent_context_requested(string $message): bool
{
    return preg_match('/\b(help me prepare|prepare for|what do (?:we|you) know|show context|gift ideas?|shop for|find (?:a )?(?:gift|merchant|product)|recommend|compare|best fit|best choice|thoughtful|personalized|write|compose)\b/u', mg_task_agent_intent_lower($message)) === 1;
}

function mg_task_agent_contextual_response(string $message, array $context): ?array
{
    if (!mg_task_agent_context_requested($message)) return null;
    $snapshot = is_array($context['system_snapshot'] ?? null) ? $context['system_snapshot'] : [];
    $contact = mg_task_agent_match_contact($message, $snapshot);
    if (!$contact) {
        return [
            'reply' => 'Choose a saved contact or name the recipient so I can load the correct permission-safe context.',
            'cards' => [[
                'type' => 'question',
                'title' => 'Recipient needed',
                'body' => 'Name a saved contact. I will use only that contact’s approved gifting context.',
                'action' => 'seed_prompt',
                'prompt' => 'Help me prepare a gift for ',
                'review_payload' => [],
            ]],
            'system_intent' => 'context_missing_recipient',
        ];
    }

    $event = mg_task_agent_match_event($message, $snapshot, $contact);
    $plans = mg_task_agent_matching_plans($context, $contact);
    $reminders = mg_task_agent_matching_reminders($context, $contact);
    $name = (string)($contact['display_name'] ?? 'Recipient');
    $relationship = trim((string)($contact['relationship'] ?? ''));
    $budget = mg_task_agent_money_range(
        isset($contact['budget_min']) ? (float)$contact['budget_min'] : null,
        isset($contact['budget_max']) ? (float)$contact['budget_max'] : null
    );
    $details = [];
    if ($relationship !== '') $details[] = 'Relationship: '.$relationship;
    if ($event) $details[] = 'Occasion: '.(string)($event['label'] ?? 'Occasion').' on '.(string)($event['event_date'] ?? '');
    if ($budget !== '') $details[] = 'Budget: '.$budget;
    if (trim((string)($contact['interests'] ?? '')) !== '') $details[] = 'Interests: '.mg_task_agent_intent_text((string)$contact['interests'], 220);
    if (trim((string)($contact['gift_preferences'] ?? '')) !== '') $details[] = 'Preferences: '.mg_task_agent_intent_text((string)$contact['gift_preferences'], 220);
    if (!empty($contact['list_names'])) $details[] = 'Lists: '.mg_task_agent_intent_text((string)$contact['list_names'], 160);
    $details[] = 'Existing plans: '.count($plans);
    $details[] = 'Scheduled reminders: '.count($reminders);

    $cards = [[
        'type' => 'context',
        'title' => $name.' gifting context',
        'body' => implode(' · ', $details),
        'action' => 'none',
        'review_payload' => [],
    ]];
    if ($event) {
        $cards[] = [
            'type' => 'gift_plan',
            'title' => 'Create reviewable gift plan',
            'body' => 'Use the selected recipient, occasion, date, budget, and saved preferences.',
            'action' => 'save_draft',
            'review_payload' => ['canonical_plan' => mg_task_agent_plan_payload($event, $snapshot)],
        ];
        $cards[] = [
            'type' => 'reminder',
            'title' => 'Create planning reminder',
            'body' => 'Create an in-app reminder using the saved occasion lead time.',
            'action' => 'save_reminder',
            'review_payload' => ['canonical_reminder' => mg_task_agent_reminder_payload($event)],
        ];
    }
    $cards[] = [
        'type' => 'marketplace',
        'title' => 'Browse relevant local merchants',
        'body' => 'Open Microgifter discovery using the approved recipient preferences and location available in this context.',
        'action' => 'open_link',
        'action_label' => 'Explore local gifts',
        'url' => mg_task_agent_discovery_url($contact, $event),
        'review_payload' => [],
    ];
    $cards = array_merge($cards, mg_task_agent_missing_context_cards($contact, $event));

    return [
        'reply' => 'I loaded the permission-safe context for '.$name.'. Review the context and choose a deterministic next action below.',
        'cards' => array_slice($cards, 0, 8),
        'system_intent' => 'contextual_action_cards',
    ];
}

function mg_task_agent_memory_preview_response(string $message): ?array
{
    $memory = mg_task_agent_memory_draft($message);
    if (!$memory) return null;
    return [
        'reply' => 'I prepared a safe agent-memory item for review. It will be stored only for this agent after you select Save preference.',
        'cards' => [[
            'type' => 'memory',
            'title' => (string)$memory['title'],
            'body' => (string)$memory['value'],
            'action' => 'save_memory',
            'action_label' => 'Save preference',
            'review_payload' => ['canonical_memory' => $memory],
        ]],
        'system_intent' => 'memory_save_preview',
    ];
}

function mg_task_agent_route(string $message, array $context, array $template): array
{
    $memoryQuery = mg_task_agent_memory_system_response($message, $context['memory'] ?? []);
    if ($memoryQuery) return ['result' => $memoryQuery, 'response_source' => 'system_query', 'ai_reason' => ''];

    $memoryPreview = mg_task_agent_memory_preview_response($message);
    if ($memoryPreview) return ['result' => $memoryPreview, 'response_source' => 'system_query', 'ai_reason' => ''];

    if (($template['key'] ?? '') === 'birthday_occasion') {
        $system = mg_task_agent_system_response($message, $context['system_snapshot'] ?? []);
        if ($system) return ['result' => $system, 'response_source' => 'system_query', 'ai_reason' => ''];
        $contextual = mg_task_agent_contextual_response($message, $context);
        if ($contextual) {
            $aiReason = mg_task_agent_ai_reason($message);
            $missing = array_filter($contextual['cards'] ?? [], static fn(array $card): bool => ($card['type'] ?? '') === 'warning');
            if ($aiReason !== '' && !$missing) return ['result' => null, 'response_source' => 'anthropic', 'ai_reason' => $aiReason];
            return ['result' => $contextual, 'response_source' => 'system_query', 'ai_reason' => ''];
        }
    }

    $aiReason = mg_task_agent_ai_reason($message);
    return ['result' => null, 'response_source' => $aiReason !== '' ? 'anthropic' : 'safe_fallback', 'ai_reason' => $aiReason];
}

function mg_task_agent_model_context(string $message, array $context): array
{
    $snapshot = is_array($context['system_snapshot'] ?? null) ? $context['system_snapshot'] : [];
    $contact = mg_task_agent_match_contact($message, $snapshot);
    $event = $contact ? mg_task_agent_match_event($message, $snapshot, $contact) : null;
    $recipient = [];
    if ($contact) {
        $recipient = [
            'name' => (string)($contact['display_name'] ?? ''),
            'relationship' => (string)($contact['relationship'] ?? ''),
            'interests' => mg_task_agent_intent_text((string)($contact['interests'] ?? ''), 300),
            'gift_preferences' => mg_task_agent_intent_text((string)($contact['gift_preferences'] ?? ''), 300),
            'budget_min' => $contact['budget_min'] ?? null,
            'budget_max' => $contact['budget_max'] ?? null,
            'location' => mg_task_agent_intent_text((string)($contact['location'] ?? ''), 100),
            'list_names' => mg_task_agent_intent_text((string)($contact['list_names'] ?? ''), 160),
        ];
    }
    $occasion = $event ? [
        'label' => (string)($event['label'] ?? ''),
        'type' => (string)($event['type'] ?? ''),
        'event_date' => (string)($event['event_date'] ?? ''),
        'days_until' => (int)($event['days_until'] ?? 0),
    ] : [];
    return [
        'agent' => $context['agent'] ?? [],
        'recipient' => $recipient,
        'occasion' => $occasion,
        'memory' => array_slice($context['memory_for_model'] ?? [], 0, 12),
        'existing_plan_count' => count(mg_task_agent_matching_plans($context, $contact)),
        'scheduled_reminder_count' => count(mg_task_agent_matching_reminders($context, $contact)),
        'safety' => [
            'approval_required' => true,
            'no_purchase_or_send' => true,
            'missing_data_must_be_disclosed' => true,
        ],
    ];
}

function mg_task_agent_sanitize_model_cards(array $cards): array
{
    $safe = [];
    foreach (array_slice($cards, 0, 4) as $card) {
        if (!is_array($card)) continue;
        $type = mg_task_agent_intent_text((string)($card['type'] ?? 'recommendation'), 40);
        if (!in_array($type, ['plan','question','recommendation','draft','warning'], true)) $type = 'recommendation';
        $action = mg_task_agent_intent_text((string)($card['action'] ?? 'none'), 30);
        if (!in_array($action, ['none','seed_prompt'], true)) $action = 'none';
        $safe[] = [
            'type' => $type,
            'title' => mg_task_agent_intent_text((string)($card['title'] ?? 'Recommendation'), 160),
            'body' => mg_task_agent_intent_text((string)($card['body'] ?? ''), 1200),
            'action' => $action,
            'prompt' => $action === 'seed_prompt' ? mg_task_agent_intent_text((string)($card['prompt'] ?? ''), 500) : '',
            'review_payload' => [],
        ];
    }
    return $safe;
}
