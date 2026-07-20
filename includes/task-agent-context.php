<?php
declare(strict_types=1);

/**
 * Database-first context for specialized customer agents.
 * This service never calls an external model and consumes no AI credits.
 */
function mg_task_agent_context_snapshot(PDO $pdo, int $userId, int $horizonDays = 90): array
{
    $contacts = mg_personal_agent_contacts($pdo, $userId, 250);
    $upcoming = mg_personal_agent_upcoming_dates($pdo, $userId, $horizonDays, 100);
    $plans = mg_personal_agent_plans($pdo, $userId, 'all', 100);
    $reminders = mg_personal_agent_reminders($pdo, $userId, 'scheduled', 100);

    $missingBirthdays = array_values(array_filter($contacts, static fn(array $contact): bool =>
        ($contact['type'] ?? '') === 'contact' && empty($contact['birthdate'])
    ));
    $withBudgets = array_values(array_filter($contacts, static fn(array $contact): bool =>
        $contact['budget_min'] !== null || $contact['budget_max'] !== null
    ));

    return [
        'source' => 'system',
        'used_ai' => false,
        'horizon_days' => max(1, min(365, $horizonDays)),
        'summary' => [
            'contacts' => count($contacts),
            'upcoming_dates' => count($upcoming),
            'missing_birthdays' => count($missingBirthdays),
            'active_plans' => count(array_filter($plans, static fn(array $plan): bool => !in_array($plan['status'] ?? '', ['completed','cancelled'], true))),
            'scheduled_reminders' => count($reminders),
            'contacts_with_budgets' => count($withBudgets),
        ],
        'upcoming' => array_slice($upcoming, 0, 12),
        'missing_birthdays' => array_slice(array_map(static fn(array $contact): array => [
            'id' => (string) ($contact['id'] ?? ''),
            'name' => (string) ($contact['display_name'] ?? 'Contact'),
            'relationship' => (string) ($contact['relationship'] ?? ''),
            'list_names' => (string) ($contact['list_names'] ?? ''),
        ], $missingBirthdays), 0, 12),
        'plans' => array_slice($plans, 0, 12),
        'reminders' => array_slice($reminders, 0, 12),
    ];
}

function mg_task_agent_opportunity_cards(array $snapshot): array
{
    $cards = [];
    foreach (array_slice($snapshot['upcoming'] ?? [], 0, 6) as $event) {
        $name = trim((string) ($event['contact_name'] ?? 'Contact'));
        $label = trim((string) ($event['label'] ?? 'Occasion'));
        $days = max(0, (int) ($event['days_until'] ?? 0));
        $cards[] = [
            'type' => 'opportunity',
            'title' => $name . ' — ' . $label,
            'body' => $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : $days . ' days away'),
            'action' => 'seed_prompt',
            'prompt' => 'Help me prepare for ' . $name . "'s " . strtolower($label) . '.',
            'review_payload' => [
                'contact_id' => (string) ($event['contact_id'] ?? ''),
                'event_id' => (string) ($event['id'] ?? ''),
                'event_date' => (string) ($event['event_date'] ?? ''),
            ],
        ];
    }
    return $cards;
}

function mg_task_agent_system_response(string $message, array $snapshot): ?array
{
    $text = mb_strtolower(trim($message));
    if ($text === '') return null;

    $upcoming = $snapshot['upcoming'] ?? [];
    $missing = $snapshot['missing_birthdays'] ?? [];
    $summary = $snapshot['summary'] ?? [];

    if (preg_match('/\b(birthday|birthdays|occasion|occasions|important dates?|upcoming)\b/u', $text)) {
        if (!$upcoming) {
            return ['reply' => 'You do not have any saved birthdays or important dates in the next ' . (int) ($snapshot['horizon_days'] ?? 90) . ' days.', 'cards' => [], 'system_intent' => 'upcoming_dates'];
        }
        $lines = [];
        foreach (array_slice($upcoming, 0, 10) as $event) {
            $lines[] = (string) ($event['contact_name'] ?? 'Contact') . ' — ' . (string) ($event['label'] ?? 'Occasion') . ' on ' . (string) ($event['event_date'] ?? '') . ' (' . (int) ($event['days_until'] ?? 0) . ' days)';
        }
        return ['reply' => "Here are your upcoming saved dates:\n\n" . implode("\n", $lines), 'cards' => mg_task_agent_opportunity_cards($snapshot), 'system_intent' => 'upcoming_dates'];
    }

    if (str_contains($text, 'missing birthday') || str_contains($text, 'without birthday')) {
        if (!$missing) return ['reply' => 'Every private contact currently has a saved birthday.', 'cards' => [], 'system_intent' => 'missing_birthdays'];
        $names = array_map(static fn(array $contact): string => (string) ($contact['name'] ?? 'Contact'), array_slice($missing, 0, 20));
        return ['reply' => count($missing) . " contacts are missing birthdays:\n\n" . implode("\n", $names), 'cards' => [], 'system_intent' => 'missing_birthdays'];
    }

    if (preg_match('/\b(summary|overview|brief|status)\b/u', $text)) {
        $reply = sprintf(
            'You have %d contacts, %d upcoming dates, %d contacts missing birthdays, %d active gift plans, and %d scheduled reminders.',
            (int) ($summary['contacts'] ?? 0),
            (int) ($summary['upcoming_dates'] ?? 0),
            (int) ($summary['missing_birthdays'] ?? 0),
            (int) ($summary['active_plans'] ?? 0),
            (int) ($summary['scheduled_reminders'] ?? 0)
        );
        return ['reply' => $reply, 'cards' => mg_task_agent_opportunity_cards($snapshot), 'system_intent' => 'overview'];
    }

    if (preg_match('/\b(reminder|reminders)\b/u', $text) && (str_contains($text, 'show') || str_contains($text, 'list') || str_contains($text, 'upcoming'))) {
        $reminders = $snapshot['reminders'] ?? [];
        if (!$reminders) return ['reply' => 'You do not have any scheduled gifting reminders.', 'cards' => [], 'system_intent' => 'reminders'];
        $lines = array_map(static fn(array $reminder): string => (string) ($reminder['title'] ?? 'Reminder') . ' — ' . (string) ($reminder['remind_at'] ?? ''), array_slice($reminders, 0, 20));
        return ['reply' => "Here are your scheduled reminders:\n\n" . implode("\n", $lines), 'cards' => [], 'system_intent' => 'reminders'];
    }

    return null;
}
