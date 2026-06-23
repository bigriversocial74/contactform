<?php
declare(strict_types=1);

function lqr_quest_controls(array $quest): array
{
    $controls = is_array($quest['controls'] ?? null) ? $quest['controls'] : [];
    return array_replace([
        'is_active' => true,
        'sponsor' => (string)($quest['merchant'] ?? ''),
        'starts_at' => '',
        'ends_at' => '',
        'max_total_completions' => 0,
        'max_total_rewards' => 0,
        'featured' => false,
        'visibility' => 'public',
        'requires_signed_code' => false,
        'signed_code_type' => 'quest_checkin',
    ], $controls);
}

function lqr_parse_time(?string $value): ?int
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $time = strtotime($value);
    return $time === false ? null : $time;
}

function lqr_quest_completion_count(array $state, string $questId): int
{
    $count = 0;
    foreach ((array)($state['users'] ?? []) as $user) {
        if (!is_array($user)) continue;
        if (!empty($user['completed_quests'][$questId])) $count++;
    }
    return $count;
}

function lqr_quest_reward_count(array $state, string $questId): int
{
    $count = 0;
    foreach ((array)($state['users'] ?? []) as $user) {
        if (!is_array($user)) continue;
        if (!empty($user['rewards'][$questId])) $count++;
    }
    return $count;
}

function lqr_quest_claim_count(array $state, string $questId): int
{
    $count = 0;
    foreach ((array)($state['users'] ?? []) as $user) {
        if (!is_array($user)) continue;
        $reward = is_array($user['rewards'][$questId] ?? null) ? $user['rewards'][$questId] : [];
        if (($reward['claim_status'] ?? '') === 'claimed_in_quest_app') $count++;
    }
    return $count;
}

function lqr_quest_metrics(array $state, string $questId): array
{
    return [
        'completions' => lqr_quest_completion_count($state, $questId),
        'rewards' => lqr_quest_reward_count($state, $questId),
        'claims' => lqr_quest_claim_count($state, $questId),
    ];
}

function lqr_quest_availability(array $quest, array $state, string $questId, ?int $now = null): array
{
    $now = $now ?? time();
    $controls = lqr_quest_controls($quest);
    if (empty($controls['is_active'])) return [false, 'Inactive'];
    $startsAt = lqr_parse_time((string)$controls['starts_at']);
    $endsAt = lqr_parse_time((string)$controls['ends_at']);
    if ($startsAt !== null && $now < $startsAt) return [false, 'Scheduled'];
    if ($endsAt !== null && $now > $endsAt) return [false, 'Ended'];
    $maxCompletions = (int)$controls['max_total_completions'];
    if ($maxCompletions > 0 && lqr_quest_completion_count($state, $questId) >= $maxCompletions) return [false, 'Completion cap reached'];
    $maxRewards = (int)$controls['max_total_rewards'];
    if ($maxRewards > 0 && lqr_quest_reward_count($state, $questId) >= $maxRewards) return [false, 'Reward cap reached'];
    return [true, 'Live'];
}

function lqr_visible_quests(array $quests, array $state): array
{
    $visible = [];
    foreach ($quests as $questId => $quest) {
        if (!is_array($quest)) continue;
        [$ok] = lqr_quest_availability($quest, $state, (string)$questId);
        if ($ok) $visible[$questId] = $quest;
    }
    return $visible;
}

function lqr_user_quest_history(array $user, array $quests): array
{
    $history = [];
    foreach ($quests as $questId => $quest) {
        if (!is_array($quest)) continue;
        $reward = is_array($user['rewards'][$questId] ?? null) ? $user['rewards'][$questId] : [];
        $history[] = [
            'quest_id' => (string)$questId,
            'quest_title' => (string)($quest['title'] ?? $questId),
            'completed_at' => (string)($user['completed_quests'][$questId] ?? ''),
            'reward_id' => (string)($reward['reward_id'] ?? ''),
            'reward_status' => (string)($reward['status'] ?? ''),
            'claim_status' => (string)($reward['claim_status'] ?? ''),
            'claim_report_status' => (string)($reward['claim_report_status'] ?? ''),
        ];
    }
    return array_values(array_filter($history, static function(array $row): bool {
        return $row['completed_at'] !== '' || $row['reward_id'] !== '' || $row['claim_status'] !== '';
    }));
}

function lqr_update_quest_controls_file(string $questId, array $controls): void
{
    $quests = lqr_quests();
    if (!isset($quests[$questId]) || !is_array($quests[$questId])) throw new RuntimeException('Quest not found.');
    $quests[$questId]['controls'] = array_replace(lqr_quest_controls($quests[$questId]), $controls);
    ksort($quests);
    $content = "<?php\nreturn " . var_export($quests, true) . ";\n";
    file_put_contents(__DIR__ . '/quests.php', $content, LOCK_EX);
}
