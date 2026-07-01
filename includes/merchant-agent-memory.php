<?php
declare(strict_types=1);

function mg_agent_memory_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function mg_agent_memory_json(mixed $value): array
{
    if (is_array($value)) return $value;
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_agent_memory_clean(mixed $value, int $max = 500): string
{
    $text = trim((string)$value);
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return mb_substr($text, 0, $max);
}

function mg_agent_memory_insert(PDO $pdo, int $merchantId, string $eventType, array $ctx): string
{
    $id = mg_agent_memory_uuid();
    $json = json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
        ->execute([$id, $merchantId, null, null, $eventType, $json]);
    return $id;
}

function mg_agent_memory_feedback_types(): array
{
    return ['useful','not_useful','too_risky','already_done','save_preference','avoid_action_type'];
}

function mg_agent_memory_record(PDO $pdo, int $merchantId, int $userId, array $input): array
{
    $feedback = strtolower(mg_agent_memory_clean($input['feedback'] ?? $input['action'] ?? 'useful', 40));
    if (!in_array($feedback, mg_agent_memory_feedback_types(), true)) mg_fail('Unsupported agent feedback type.', 422);
    $ctx = [
        'feedback' => $feedback,
        'source_type' => mg_agent_memory_clean($input['source_type'] ?? 'unknown', 80),
        'source_id' => mg_agent_memory_clean($input['source_id'] ?? '', 140),
        'title' => mg_agent_memory_clean($input['title'] ?? '', 180),
        'action_key' => mg_agent_memory_clean($input['action_key'] ?? '', 120),
        'risk_level' => mg_agent_memory_clean($input['risk_level'] ?? '', 40),
        'note' => mg_agent_memory_clean($input['note'] ?? '', 500),
        'saved_by_user_id' => $userId,
    ];
    if ($feedback === 'save_preference') $ctx['preference'] = mg_agent_memory_clean($input['preference'] ?? $ctx['title'] ?: $ctx['action_key'], 220);
    if ($feedback === 'avoid_action_type') $ctx['avoid_action_key'] = mg_agent_memory_clean($input['action_key'] ?? $ctx['title'], 120);
    $eventType = $feedback === 'save_preference' ? 'merchant.agent_preference.saved' : ($feedback === 'avoid_action_type' ? 'merchant.agent_avoid_action.saved' : 'merchant.agent_feedback.saved');
    $eventId = mg_agent_memory_insert($pdo, $merchantId, $eventType, $ctx);
    mg_agent_memory_insert($pdo, $merchantId, 'merchant.agent_memory.updated', ['source_event_id' => $eventId, 'feedback' => $feedback, 'title' => $ctx['title'], 'action_key' => $ctx['action_key'], 'saved_by_user_id' => $userId]);
    return ['event_id' => $eventId, 'feedback' => $feedback, 'memory' => mg_agent_memory_summary($pdo, $merchantId)];
}

function mg_agent_memory_parse_profile_text(string $text): array
{
    $profile = [];
    $map = [
        'brand voice' => 'brand_voice',
        'campaign style' => 'campaign_style',
        'customer tone' => 'customer_tone',
        'preferred customer tone' => 'customer_tone',
        'default offer type' => 'default_offer_type',
        'business goals' => 'business_goals',
        'business goal' => 'business_goals',
        'local market notes' => 'local_market_notes',
        'local market note' => 'local_market_notes',
    ];
    foreach (preg_split('/\R+/', $text) ?: [] as $line) {
        if (!str_contains($line, ':')) continue;
        [$rawKey, $rawValue] = array_map('trim', explode(':', $line, 2));
        $key = strtolower(preg_replace('/\s+/', ' ', $rawKey) ?? $rawKey);
        if (isset($map[$key])) $profile[$map[$key]] = mg_agent_memory_clean($rawValue, 700);
    }
    return $profile;
}

function mg_agent_memory_profile_fields(array $input): array
{
    $fromText = mg_agent_memory_parse_profile_text((string)($input['memory_text'] ?? $input['message'] ?? ''));
    $fields = [
        'brand_voice' => mg_agent_memory_clean($input['brand_voice'] ?? ($fromText['brand_voice'] ?? ''), 700),
        'campaign_style' => mg_agent_memory_clean($input['campaign_style'] ?? ($fromText['campaign_style'] ?? ''), 700),
        'customer_tone' => mg_agent_memory_clean($input['customer_tone'] ?? ($fromText['customer_tone'] ?? ''), 700),
        'default_offer_type' => mg_agent_memory_clean($input['default_offer_type'] ?? ($fromText['default_offer_type'] ?? ''), 700),
        'business_goals' => mg_agent_memory_clean($input['business_goals'] ?? ($fromText['business_goals'] ?? ''), 900),
        'local_market_notes' => mg_agent_memory_clean($input['local_market_notes'] ?? ($fromText['local_market_notes'] ?? ''), 900),
    ];
    return array_filter($fields, static fn(string $value): bool => $value !== '');
}

function mg_agent_memory_profile_save(PDO $pdo, int $merchantId, int $userId, array $input): array
{
    $profile = mg_agent_memory_profile_fields($input);
    if ($profile === []) mg_fail('Enter at least one memory field to save.', 422);
    $ctx = $profile + [
        'source' => mg_agent_memory_clean($input['source'] ?? 'merchant_agent_chat', 80),
        'status' => mg_agent_memory_clean($input['status'] ?? 'saved', 40),
        'saved_by_user_id' => $userId,
    ];
    $eventId = mg_agent_memory_insert($pdo, $merchantId, 'merchant.agent_memory_profile.saved', $ctx);
    mg_agent_memory_insert($pdo, $merchantId, 'merchant.agent_memory.updated', ['source_event_id' => $eventId, 'feedback' => 'memory_profile', 'saved_by_user_id' => $userId]);
    return ['event_id' => $eventId, 'profile' => $profile, 'memory' => mg_agent_memory_summary($pdo, $merchantId)];
}

function mg_agent_memory_rows(PDO $pdo, int $merchantId, int $limit = 200): array
{
    $types = ['merchant.agent_goals.saved','merchant.agent_memory_profile.saved','merchant.agent_feedback.saved','merchant.agent_preference.saved','merchant.agent_avoid_action.saved','merchant.agent_memory.updated','merchant.ai_plan_item.executed','merchant.ai_plan_item.rejected','merchant.ai_plan_item.deferred'];
    $in = implode(',', array_fill(0, count($types), '?'));
    try {
        $stmt = $pdo->prepare("SELECT event_type,event_context_json,created_at FROM campaign_events WHERE merchant_user_id=? AND event_type IN ({$in}) ORDER BY id DESC LIMIT " . max(1, min(300, $limit)));
        $stmt->execute(array_merge([$merchantId], $types));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

function mg_agent_memory_summary(PDO $pdo, int $merchantId): array
{
    $summary = [
        'goals' => ['primary_goal' => '', 'secondary_goal' => '', 'focus' => '', 'tone' => '', 'budget' => ''],
        'merchant_profile' => ['brand_voice' => '', 'campaign_style' => '', 'customer_tone' => '', 'default_offer_type' => '', 'business_goals' => '', 'local_market_notes' => ''],
        'preferences' => [],
        'avoid_actions' => [],
        'approved_patterns' => [],
        'rejected_patterns' => [],
        'recent_feedback' => [],
        'counts' => ['useful' => 0, 'not_useful' => 0, 'too_risky' => 0, 'already_done' => 0, 'saved_preferences' => 0, 'avoid_actions' => 0, 'memory_profiles' => 0],
    ];
    $seenPrefs = [];
    $seenAvoid = [];
    foreach (mg_agent_memory_rows($pdo, $merchantId) as $row) {
        $type = (string)($row['event_type'] ?? '');
        $ctx = mg_agent_memory_json($row['event_context_json'] ?? null);
        if ($type === 'merchant.agent_goals.saved' && $summary['goals']['primary_goal'] === '') $summary['goals'] = array_merge($summary['goals'], array_intersect_key($ctx, $summary['goals']));
        if ($type === 'merchant.agent_memory_profile.saved') {
            if (implode('', $summary['merchant_profile']) === '') $summary['merchant_profile'] = array_merge($summary['merchant_profile'], array_intersect_key($ctx, $summary['merchant_profile']));
            $summary['counts']['memory_profiles']++;
        }
        if ($type === 'merchant.agent_preference.saved') {
            $pref = mg_agent_memory_clean($ctx['preference'] ?? $ctx['title'] ?? $ctx['action_key'] ?? '', 220);
            if ($pref !== '' && empty($seenPrefs[$pref])) { $seenPrefs[$pref] = true; $summary['preferences'][] = ['label' => $pref, 'action_key' => (string)($ctx['action_key'] ?? ''), 'created_at' => $row['created_at'] ?? null]; }
            $summary['counts']['saved_preferences']++;
        }
        if ($type === 'merchant.agent_avoid_action.saved') {
            $avoid = mg_agent_memory_clean($ctx['avoid_action_key'] ?? $ctx['action_key'] ?? $ctx['title'] ?? '', 120);
            if ($avoid !== '' && empty($seenAvoid[$avoid])) { $seenAvoid[$avoid] = true; $summary['avoid_actions'][] = ['action_key' => $avoid, 'title' => (string)($ctx['title'] ?? ''), 'created_at' => $row['created_at'] ?? null]; }
            $summary['counts']['avoid_actions']++;
        }
        if ($type === 'merchant.agent_feedback.saved') {
            $feedback = (string)($ctx['feedback'] ?? '');
            if (isset($summary['counts'][$feedback])) $summary['counts'][$feedback]++;
            $item = ['feedback' => $feedback, 'title' => (string)($ctx['title'] ?? ''), 'action_key' => (string)($ctx['action_key'] ?? ''), 'created_at' => $row['created_at'] ?? null];
            if (count($summary['recent_feedback']) < 10) $summary['recent_feedback'][] = $item;
            if (in_array($feedback, ['useful','already_done'], true) && count($summary['approved_patterns']) < 10) $summary['approved_patterns'][] = $item;
            if (in_array($feedback, ['not_useful','too_risky'], true) && count($summary['rejected_patterns']) < 10) $summary['rejected_patterns'][] = $item;
        }
        if ($type === 'merchant.ai_plan_item.executed' && count($summary['approved_patterns']) < 10) $summary['approved_patterns'][] = ['feedback' => 'executed', 'title' => (string)($ctx['title'] ?? $ctx['playbook_title'] ?? ''), 'action_key' => (string)($ctx['action_key'] ?? ''), 'created_at' => $row['created_at'] ?? null];
        if (in_array($type, ['merchant.ai_plan_item.rejected','merchant.ai_plan_item.deferred'], true) && count($summary['rejected_patterns']) < 10) $summary['rejected_patterns'][] = ['feedback' => str_replace('merchant.ai_plan_item.', '', $type), 'title' => (string)($ctx['title'] ?? $ctx['playbook_title'] ?? ''), 'action_key' => (string)($ctx['action_key'] ?? ''), 'created_at' => $row['created_at'] ?? null];
    }
    return $summary;
}

function mg_agent_memory_prompt_context(PDO $pdo, int $merchantId): array
{
    $m = mg_agent_memory_summary($pdo, $merchantId);
    return [
        'merchant_goals' => $m['goals'],
        'merchant_profile' => $m['merchant_profile'],
        'saved_preferences' => array_slice($m['preferences'], 0, 8),
        'avoid_actions' => array_slice($m['avoid_actions'], 0, 8),
        'approved_patterns' => array_slice($m['approved_patterns'], 0, 8),
        'rejected_patterns' => array_slice($m['rejected_patterns'], 0, 8),
        'guidance' => 'Use saved preferences, merchant profile fields, and approved patterns first. Apply brand voice, campaign style, customer tone, default offer type, business goals, and local market notes when creating recommendations. Avoid action types and ideas similar to rejected or too-risky feedback. Prefer low-risk review-only cards when confidence is uncertain.',
    ];
}
