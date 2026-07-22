<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/api/communications/_communications.php';

function mg_creator_campaign_deliverable_notify(PDO $pdo, int $recipientUserId, int $actorUserId, string $eventKey, string $title, string $body, string $actionUrl, array $context = []): void
{
    if ($recipientUserId < 1 || $recipientUserId === $actorUserId) return;
    try {
        mg_create_notification($pdo, $recipientUserId, 'creator_campaign', $title, $body, $actionUrl, $context + [
            'actor_user_id'=>$actorUserId,
            'event_key'=>$eventKey,
        ]);
    } catch (Throwable $ignored) {
        // Notifications must never invalidate the canonical content transaction.
    }
}

function mg_creator_campaign_notify_creator_assignment(PDO $pdo, array $assignment, int $actorUserId): void
{
    mg_creator_campaign_deliverable_notify(
        $pdo,
        (int) ($assignment['creator_user_id'] ?? 0),
        $actorUserId,
        'creator_campaign.assignment.' . (string) ($assignment['public_id'] ?? ''),
        'New campaign deliverable',
        (string) ($assignment['deliverable_title'] ?? 'A campaign deliverable') . ' has been assigned.',
        '/creator-campaign-deliverables.php',
        ['campaign_id'=>$assignment['campaign_public_id'] ?? null,'assignment_id'=>$assignment['public_id'] ?? null]
    );
}

function mg_creator_campaign_notify_merchant_submission(PDO $pdo, array $submission, int $actorUserId, string $event): void
{
    $merchantUserId = (int) ($submission['merchant_user_id'] ?? 0);
    $title = $event === 'publication_proof' ? 'Publication proof submitted' : 'Creator content submitted';
    $body = (string) ($submission['creator_name'] ?? 'A creator') . ' updated ' . (string) ($submission['deliverable_title'] ?? 'a campaign deliverable') . '.';
    mg_creator_campaign_deliverable_notify(
        $pdo,
        $merchantUserId,
        $actorUserId,
        'creator_campaign.submission.' . $event . '.' . (string) ($submission['public_id'] ?? ''),
        $title,
        $body,
        '/merchant-creator-deliverables.php',
        ['campaign_id'=>$submission['campaign_public_id'] ?? null,'submission_id'=>$submission['public_id'] ?? null]
    );
}

function mg_creator_campaign_notify_creator_review(PDO $pdo, array $submission, int $actorUserId, string $decision): void
{
    $labels = [
        'revision_requested'=>['Revision requested','The merchant requested changes to your campaign submission.'],
        'approved'=>['Submission approved','Your campaign submission was approved.'],
        'rejected'=>['Submission rejected','The merchant rejected your campaign submission.'],
        'verified'=>['Publication verified','Your publication proof was verified.'],
    ];
    [$title,$body] = $labels[$decision] ?? ['Campaign submission updated','Your campaign submission status changed.'];
    mg_creator_campaign_deliverable_notify(
        $pdo,
        (int) ($submission['creator_user_id'] ?? 0),
        $actorUserId,
        'creator_campaign.review.' . $decision . '.' . (string) ($submission['public_id'] ?? ''),
        $title,
        $body,
        '/creator-campaign-deliverables.php',
        ['campaign_id'=>$submission['campaign_public_id'] ?? null,'submission_id'=>$submission['public_id'] ?? null]
    );
}
