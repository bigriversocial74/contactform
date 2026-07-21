<?php
declare(strict_types=1);

function mg_creator_campaign_application_save_creator(
    PDO $pdo,
    array $user,
    string $campaignPublicId,
    array $input,
    bool $submit
): array {
    $context = mg_creator_campaign_creator_context(
        $pdo, $user, 'creator.campaign_applications.manage_own'
    );
    $creatorUserId = (int) $context['creator_user_id'];
    $actorId = (int) $context['actor_user_id'];
    $campaign = mg_creator_campaign_participation_campaign_by_public_id($pdo, $campaignPublicId);

    if (!mg_creator_campaign_participation_public_campaign($campaign)) {
        throw new DomainException('This campaign is not currently accepting public applications.');
    }

    $coverNote = mg_creator_campaign_string($input['cover_note'] ?? null, 'cover_note', 8000);
    $portfolioUrl = mg_creator_campaign_string($input['portfolio_url'] ?? null, 'portfolio_url', 600);
    if ($portfolioUrl !== null && filter_var($portfolioUrl, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException('portfolio_url must be a valid URL.');
    }
    $answers = is_array($input['answers'] ?? null) ? $input['answers'] : [];
    $expectedLock = (int) ($input['expected_lock_version'] ?? 0);

    mg_creator_campaign_assert_transaction_boundary($pdo);
    $pdo->beginTransaction();
    try {
        $campaign = mg_creator_campaign_participation_campaign_by_public_id($pdo, $campaignPublicId, null, true);
        if (!mg_creator_campaign_participation_public_campaign($campaign)) {
            throw new DomainException('This campaign is not accepting applications.');
        }

        $find = $pdo->prepare(
            'SELECT * FROM creator_campaign_applications WHERE campaign_id=? AND creator_user_id=? LIMIT 1 FOR UPDATE'
        );
        $find->execute([(int) $campaign['id'], $creatorUserId]);
        $application = $find->fetch(PDO::FETCH_ASSOC);

        if (!$application) {
            $capacity = mg_creator_campaign_application_count_capacity($pdo, $campaign);
            if (!$capacity['available']) throw new DomainException('This campaign has reached its application limit.');
            $snapshot = mg_creator_campaign_participation_creator_snapshot($pdo, $creatorUserId);
            $status = $submit ? 'submitted' : 'draft';
            $insert = $pdo->prepare(
                'INSERT INTO creator_campaign_applications
                 (public_id,campaign_id,creator_user_id,creator_profile_id,status,cover_note,portfolio_url,
                  creator_snapshot_json,submitted_at,lock_version,created_by_user_id,updated_by_user_id,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,1,?,?,NOW(),NOW())'
            );
            $insert->execute([
                mg_creator_campaign_public_id('ccap'),
                (int) $campaign['id'],
                $creatorUserId,
                (int) $context['creator_profile_id'],
                $status,
                $coverNote,
                $portfolioUrl,
                mg_creator_campaign_json_encode($snapshot),
                $submit ? gmdate('Y-m-d H:i:s') : null,
                $actorId,
                $actorId,
            ]);
            $applicationId = (int) $pdo->lastInsertId();
            $publicId = (string) $pdo->query('SELECT public_id FROM creator_campaign_applications WHERE id=' . $applicationId)->fetchColumn();
            $fromStatus = null;
        } else {
            $applicationId = (int) $application['id'];
            $publicId = (string) $application['public_id'];
            mg_creator_campaign_participation_require_expected_lock($application, $expectedLock);
            $fromStatus = (string) $application['status'];
            $allowed = $submit
                ? ['draft','information_requested','withdrawn']
                : ['draft','information_requested'];
            if (!in_array($fromStatus, $allowed, true)) {
                throw new DomainException('This application cannot be edited in its current status.');
            }
            $toStatus = $submit ? 'submitted' : $fromStatus;
            if ($submit && $fromStatus !== 'submitted') {
                mg_creator_campaign_assert_transition('application', $fromStatus, 'submitted');
            }
            $update = $pdo->prepare(
                'UPDATE creator_campaign_applications
                 SET status=?,cover_note=?,portfolio_url=?,submitted_at=IF(?=1,NOW(),submitted_at),
                     withdrawn_at=NULL,updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW()
                 WHERE id=? AND lock_version=?'
            );
            $update->execute([
                $toStatus, $coverNote, $portfolioUrl, $submit ? 1 : 0,
                $actorId, $applicationId, $expectedLock,
            ]);
            if ($update->rowCount() !== 1) throw new DomainException('Application save lost its optimistic lock.');
        }

        mg_creator_campaign_application_write_answers(
            $pdo, $applicationId, (int) $campaign['id'], $answers, $submit
        );

        // Phase 3 requires a human merchant decision for every application.
        // The legacy builder flag remains stored for forward compatibility, but
        // it cannot approve an application or create a participant here.
        $finalStatus = $submit ? 'submitted' : ($fromStatus ?? 'draft');

        mg_creator_campaign_participation_event($pdo, [
            'campaign_id' => (int) $campaign['id'],
            'application_id' => $applicationId,
            'participant_id' => null,
            'actor_user_id' => $actorId,
            'event_type' => $submit ? 'application.submitted' : 'application.saved',
            'from_status' => $fromStatus,
            'to_status' => $finalStatus,
            'reason' => null,
            'context' => [
                'human_approval_required' => true,
                'automatic_acceptance_ignored' => !empty($campaign['automatic_acceptance']),
            ],
        ]);

        $pdo->commit();
        $result = mg_creator_campaign_participation_application_by_public_id(
            $pdo, $publicId, (int) $campaign['id'], $creatorUserId
        );
        $result['answers'] = mg_creator_campaign_participation_answer_rows($pdo, (int) $result['id']);
        $result['participant'] = null;
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_creator_campaign_application_withdraw_creator(
    PDO $pdo,
    array $user,
    string $applicationPublicId,
    array $input
): array {
    $context = mg_creator_campaign_creator_context(
        $pdo, $user, 'creator.campaign_applications.manage_own'
    );
    $creatorUserId = (int) $context['creator_user_id'];
    $reason = mg_creator_campaign_string($input['reason'] ?? null, 'reason', 1000);
    $expectedLock = (int) ($input['expected_lock_version'] ?? 0);

    mg_creator_campaign_assert_transaction_boundary($pdo);
    $pdo->beginTransaction();
    try {
        $application = mg_creator_campaign_participation_application_by_public_id(
            $pdo, $applicationPublicId, null, $creatorUserId, true
        );
        mg_creator_campaign_participation_require_expected_lock($application, $expectedLock);
        $from = (string) $application['status'];
        mg_creator_campaign_assert_transition('application', $from, 'withdrawn');

        $update = $pdo->prepare(
            "UPDATE creator_campaign_applications
             SET status='withdrawn',withdrawn_at=NOW(),updated_by_user_id=?,
                 lock_version=lock_version+1,updated_at=NOW()
             WHERE id=? AND lock_version=?"
        );
        $update->execute([(int) $context['actor_user_id'], (int) $application['id'], $expectedLock]);
        if ($update->rowCount() !== 1) throw new DomainException('Application withdrawal lost its optimistic lock.');

        mg_creator_campaign_participation_event($pdo, [
            'campaign_id' => (int) $application['campaign_id'],
            'application_id' => (int) $application['id'],
            'actor_user_id' => (int) $context['actor_user_id'],
            'event_type' => 'application.withdrawn',
            'from_status' => $from,
            'to_status' => 'withdrawn',
            'reason' => $reason,
            'idempotency_key' => $input['idempotency_key'] ?? null,
        ]);
        $pdo->commit();
        return mg_creator_campaign_participation_application_by_public_id(
            $pdo, $applicationPublicId, null, $creatorUserId
        );
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_creator_campaign_application_list_creator(PDO $pdo, array $user): array
{
    $context = mg_creator_campaign_creator_context(
        $pdo, $user, 'creator.campaign_applications.manage_own'
    );
    $stmt = $pdo->prepare(
        'SELECT a.public_id,a.status,a.cover_note,a.portfolio_url,a.decision_note,a.submitted_at,
                a.reviewed_at,a.decided_at,a.withdrawn_at,a.lock_version,a.updated_at,
                cc.public_id campaign_public_id,cc.title campaign_title,cc.objective,cc.category,
                cc.starts_at,cc.ends_at,mw.display_name merchant_name
         FROM creator_campaign_applications a
         INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id
         INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
         WHERE a.creator_user_id=?
         ORDER BY a.updated_at DESC,a.id DESC'
    );
    $stmt->execute([(int) $context['creator_user_id']]);
    return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
}

