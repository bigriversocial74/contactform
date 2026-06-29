<?php
 declare(strict_types=1);

require_once __DIR__ . '/training-storage.php';

function tcl_evaluate_rewards_for_submission(string $submissionId): array
{
    $state = tcl_state_load();
    $submission = $state['submissions'][$submissionId] ?? null;
    if (!is_array($submission) || ($submission['status'] ?? '') !== 'approved') return [];

    $participantId = (string)$submission['participant_id'];
    $campaignSlug = (string)$submission['campaign_slug'];
    $campaign = tcl_storage_campaign_by_slug($campaignSlug);
    if (!$campaign) return [];

    $issues = [];
    foreach ($state['receipts'] as $receipt) {
        if (($receipt['participant_id'] ?? '') !== $participantId) continue;
        if (($receipt['campaign_slug'] ?? '') !== $campaignSlug) continue;
        if (($receipt['receipt_type'] ?? '') !== 'sequence_completion') continue;

        $rules = (array)($campaign['reward_ladder'] ?? []);
        foreach ($rules as $rule) {
            $ruleKey = (string)($rule['public_id'] ?? $rule['label'] ?? 'rule');
            $issueKey = 'reward|' . $participantId . '|' . ($receipt['id'] ?? $receipt['key']) . '|' . $ruleKey;
            if (!empty($state['reward_issues'][$issueKey])) continue;

            $linked = false;
            if (function_exists('lqr_config')) {
                $lqrState = function_exists('lqr_load_state') ? lqr_load_state() : [];
                foreach (($lqrState['users'] ?? []) as $user) {
                    if (($user['id'] ?? '') !== ($submission['user_id'] ?? '')) continue;
                    $linked = !empty($user['linked_account_id']);
                }
            }

            $status = $linked ? 'pending_issue' : 'needs_linked_account';
            $state['reward_issues'][$issueKey] = [
                'id' => tcl_public_id(),
                'key' => $issueKey,
                'participant_id' => $participantId,
                'campaign_slug' => $campaignSlug,
                'receipt_id' => (string)($receipt['id'] ?? ''),
                'reward_rule' => $rule,
                'reward_label' => (string)($rule['reward'] ?? 'Reward'),
                'status' => $status,
                'microgifter_reward_id' => '',
                'failure_reason' => $linked ? 'Demo-safe issue record created. Real Microgifter issuing is not called in this branch.' : 'Participant needs linked Microgifter account before issuing.',
                'created_at' => tcl_now(),
                'updated_at' => tcl_now(),
            ];
            $issues[] = $state['reward_issues'][$issueKey];
            tcl_event($state, 'training.reward.' . $status, 'Reward issue record created.', ['issue_key' => $issueKey, 'status' => $status]);
        }
    }

    tcl_state_save($state);
    return $issues;
}

function tcl_reward_status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}
