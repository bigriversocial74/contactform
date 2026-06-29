<?php
 declare(strict_types=1);

require_once __DIR__ . '/training-storage.php';

function tcl_create_receipts_for_submission(string $submissionId): array
{
    $state = tcl_state_load();
    $submission = $state['submissions'][$submissionId] ?? null;
    if (!is_array($submission) || ($submission['status'] ?? '') !== 'approved') return [];

    $created = [];
    $participantId = (string)$submission['participant_id'];
    $campaignSlug = (string)$submission['campaign_slug'];
    $taskSlug = (string)$submission['task_slug'];
    $sequenceSlug = (string)$submission['sequence_slug'];

    $taskKey = 'task|' . $participantId . '|' . $campaignSlug . '|' . $taskSlug . '|' . $submissionId;
    if (empty($state['receipts'][$taskKey])) {
        $state['receipts'][$taskKey] = [
            'id' => tcl_public_id(),
            'key' => $taskKey,
            'receipt_type' => 'task_completion',
            'participant_id' => $participantId,
            'campaign_slug' => $campaignSlug,
            'sequence_slug' => $sequenceSlug,
            'task_slug' => $taskSlug,
            'submission_id' => $submissionId,
            'status' => 'verified',
            'points_awarded' => 0,
            'created_at' => tcl_now(),
        ];
        $created[] = $state['receipts'][$taskKey];
        tcl_event($state, 'training.receipt.created', 'Task completion Action Receipt created.', ['receipt_key' => $taskKey]);
    }

    $campaign = tcl_storage_campaign_by_slug($campaignSlug);
    $steps = (array)($campaign['sequence']['steps'] ?? []);
    $complete = true;
    foreach ($steps as $step) {
        if (!($step['is_required'] ?? true)) continue;
        $latest = tcl_latest_submission_for_task($participantId, $campaignSlug, (string)$step['slug']);
        if (($latest['status'] ?? '') !== 'approved') { $complete = false; break; }
    }

    if ($complete && $steps) {
        $sequenceKey = 'sequence|' . $participantId . '|' . $campaignSlug . '|' . $sequenceSlug;
        if (empty($state['receipts'][$sequenceKey])) {
            $state['receipts'][$sequenceKey] = [
                'id' => tcl_public_id(),
                'key' => $sequenceKey,
                'receipt_type' => 'sequence_completion',
                'participant_id' => $participantId,
                'campaign_slug' => $campaignSlug,
                'sequence_slug' => $sequenceSlug,
                'task_slug' => '',
                'submission_id' => $submissionId,
                'status' => 'verified',
                'points_awarded' => array_sum(array_map(static fn($s): int => (int)($s['points'] ?? 0), $steps)),
                'created_at' => tcl_now(),
            ];
            $created[] = $state['receipts'][$sequenceKey];
            if (!empty($state['participants'][$campaignSlug . '|' . ($submission['user_id'] ?? '')])) {
                $state['participants'][$campaignSlug . '|' . ($submission['user_id'] ?? '')]['status'] = 'completed';
                $state['participants'][$campaignSlug . '|' . ($submission['user_id'] ?? '')]['completed_at'] = tcl_now();
            }
            tcl_event($state, 'training.sequence.verified_complete', 'Sequence completion Action Receipt created.', ['receipt_key' => $sequenceKey]);
        }
    }

    tcl_state_save($state);
    return $created;
}
