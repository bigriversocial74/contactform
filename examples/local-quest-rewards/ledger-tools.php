<?php
declare(strict_types=1);

function lqr_ledger_event_label(string $type): string
{
    return match ($type) {
        'api.programs.list' => 'programs.list',
        'microgifter.account_link_started' => 'account.link.start',
        'microgifter.sandbox_linked_account' => 'account.link.sandbox',
        'microgifter.account_linked' => 'account.link.complete',
        'microgifter.reward.issue' => 'reward.issue',
        'microgifter.reward.status' => 'reward.status',
        'quest.reward.claimed' => 'reward.claim.report',
        'quest.reward.claim_failed' => 'reward.claim.failed',
        'webhook.verified' => 'webhook.verified',
        'webhook.reconciled' => 'webhook.reconciled',
        default => $type,
    };
}

function lqr_ledger_result(string $type, array $context): string
{
    if (str_contains($type, 'failed') || str_contains($type, 'rejected') || (($context['status'] ?? 0) >= 400)) return 'failed';
    if (isset($context['verified']) && empty($context['verified'])) return 'failed';
    return 'success';
}

function lqr_ledger_rows(array $state): array
{
    $rows = [];
    $app = is_array($state['partner_app'] ?? null) ? $state['partner_app'] : [];
    $appId = (string)($app['app_id'] ?? 'local_quest_rewards');
    foreach ((is_array($state['events'] ?? null) ? $state['events'] : []) as $event) {
        if (!is_array($event)) continue;
        $type = (string)($event['type'] ?? 'event');
        $context = is_array($event['context'] ?? null) ? $event['context'] : [];
        $rows[] = [
            'at' => (string)($event['at'] ?? ''),
            'event' => lqr_ledger_event_label($type),
            'raw_type' => $type,
            'result' => lqr_ledger_result($type, $context),
            'actor' => (string)($context['user_id'] ?? $context['external_user_id'] ?? $context['linked_account_id'] ?? 'system'),
            'app_id' => $appId,
            'program_id' => (string)($context['program_id'] ?? ''),
            'template_id' => (string)($context['template_id'] ?? ''),
            'quest_id' => (string)($context['quest_id'] ?? ''),
            'reward_id' => (string)($context['reward_id'] ?? ''),
            'item_id' => (string)($context['item_id'] ?? ''),
            'status' => (string)($context['status'] ?? ''),
            'message' => (string)($event['message'] ?? ''),
            'context' => $context,
        ];
    }
    foreach ((is_array($state['users'] ?? null) ? $state['users'] : []) as $user) {
        if (!is_array($user)) continue;
        foreach ((is_array($user['rewards'] ?? null) ? $user['rewards'] : []) as $questId => $reward) {
            if (!is_array($reward)) continue;
            $rows[] = [
                'at' => (string)($reward['issued_at'] ?? ''),
                'event' => 'reward.wallet.record',
                'raw_type' => 'reward.wallet.record',
                'result' => 'success',
                'actor' => (string)($user['email'] ?? $user['id'] ?? 'user'),
                'app_id' => (string)($reward['partner_app_id'] ?? $appId),
                'program_id' => (string)($reward['program_id'] ?? ''),
                'template_id' => (string)($reward['template_id'] ?? ''),
                'quest_id' => (string)$questId,
                'reward_id' => (string)($reward['reward_id'] ?? ''),
                'item_id' => function_exists('lqr_reward_item_id') ? lqr_reward_item_id($reward) : (string)($reward['item_id'] ?? ''),
                'status' => (string)($reward['status'] ?? ''),
                'message' => 'Wallet reward record.',
                'context' => $reward,
            ];
        }
    }
    usort($rows, static fn(array $a, array $b): int => strcmp((string)($b['at'] ?? ''), (string)($a['at'] ?? '')));
    return $rows;
}

function lqr_ledger_filtered_rows(array $rows, array $filter): array
{
    return array_values(array_filter($rows, static function (array $row) use ($filter): bool {
        foreach (['event','result','reward_id','actor'] as $key) {
            $needle = trim((string)($filter[$key] ?? ''));
            if ($needle !== '' && !str_contains(strtolower((string)($row[$key] ?? '')), strtolower($needle))) return false;
        }
        return true;
    }));
}

function lqr_ledger_metrics(array $rows): array
{
    $metrics = ['total'=>count($rows),'api_calls'=>0,'reward_issues'=>0,'failed'=>0,'claims_reported'=>0,'webhooks_verified'=>0,'last_error'=>''];
    foreach ($rows as $row) {
        $event = (string)($row['event'] ?? '');
        if (str_starts_with($event, 'programs.') || str_starts_with($event, 'account.') || str_starts_with($event, 'reward.')) $metrics['api_calls']++;
        if ($event === 'reward.issue') $metrics['reward_issues']++;
        if (($row['result'] ?? '') === 'failed') { $metrics['failed']++; if ($metrics['last_error'] === '') $metrics['last_error'] = (string)($row['message'] ?? 'failed'); }
        if (in_array($event, ['reward.claim.report','reward.claim.failed'], true)) $metrics['claims_reported']++;
        if ($event === 'webhook.verified') $metrics['webhooks_verified']++;
    }
    return $metrics;
}

function lqr_ledger_chain(array $rows, string $rewardId): array
{
    if ($rewardId === '') return [];
    return array_values(array_filter($rows, static fn(array $row): bool => (string)($row['reward_id'] ?? '') === $rewardId));
}
