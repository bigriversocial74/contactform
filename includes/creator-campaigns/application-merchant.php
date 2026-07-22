<?php
declare(strict_types=1);

function mg_creator_campaign_application_list_merchant(PDO $pdo, array $user, array $filters = []): array
{
    $context = mg_creator_campaign_participation_merchant_context(
        $pdo, $user, 'merchant.creator_applications.view'
    );
    $workspaceId = (int) $context['workspace_id'];
    $status = strtolower(trim((string) ($filters['status'] ?? '')));
    $campaignPublicId = trim((string) ($filters['campaign_id'] ?? ''));
    $search = trim((string) ($filters['search'] ?? ''));
    $page = max(1, (int) ($filters['page'] ?? 1));
    $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));

    $where = ['cc.workspace_id=?'];
    $params = [$workspaceId];
    if ($status !== '') {
        if (!in_array($status, mg_creator_campaign_application_statuses(), true)) {
            throw new InvalidArgumentException('Application status filter is invalid.');
        }
        $where[] = 'a.status=?';
        $params[] = $status;
    }
    if ($campaignPublicId !== '') {
        $where[] = 'cc.public_id=?';
        $params[] = $campaignPublicId;
    }
    if ($search !== '') {
        $where[] = '(cp.display_name LIKE ? OR cp.slug LIKE ? OR u.email LIKE ? OR cc.title LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $base = ' FROM creator_campaign_applications a
              INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id
              INNER JOIN creator_profiles cp ON cp.id=a.creator_profile_id
              INNER JOIN users u ON u.id=a.creator_user_id
              WHERE ' . implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(*)' . $base);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT a.public_id,a.status,a.cover_note,a.portfolio_url,a.decision_note,a.internal_note,
                a.submitted_at,a.reviewed_at,a.decided_at,a.lock_version,a.updated_at,
                cc.public_id campaign_public_id,cc.title campaign_title,
                cp.public_id creator_profile_public_id,
                COALESCE(cp.display_name,u.display_name,u.full_name,u.email) creator_name,
                cp.slug creator_slug,u.email creator_email'
        . $base .
        ' ORDER BY FIELD(a.status,\'submitted\',\'under_review\',\'information_requested\',\'draft\',\'approved\',\'declined\',\'withdrawn\'),
                   a.updated_at DESC,a.id DESC
          LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
    );
    $stmt->execute($params);

    return [
        'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => (int) ceil($total / $perPage)],
    ];
}

function mg_creator_campaign_application_detail_merchant(
    PDO $pdo,
    array $user,
    string $applicationPublicId
): array {
    $context = mg_creator_campaign_participation_merchant_context(
        $pdo, $user, 'merchant.creator_applications.view'
    );
    $stmt = $pdo->prepare(
        'SELECT a.public_id FROM creator_campaign_applications a
         INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id
         WHERE a.public_id=? AND cc.workspace_id=? LIMIT 1'
    );
    $stmt->execute([$applicationPublicId, (int) $context['workspace_id']]);
    if (!$stmt->fetchColumn()) throw new RuntimeException('Creator campaign application not found.');
    $application = mg_creator_campaign_participation_application_by_public_id($pdo, $applicationPublicId);
    $application['answers'] = mg_creator_campaign_participation_answer_rows($pdo, (int) $application['id']);
    $application['creator_snapshot'] = mg_creator_campaign_participation_decode_json($application['creator_snapshot_json'] ?? null);
    unset($application['creator_snapshot_json']);
    return $application;
}

function mg_creator_campaign_application_review_merchant(
    PDO $pdo,
    array $user,
    string $applicationPublicId,
    string $action,
    array $input
): array {
    $context = mg_creator_campaign_participation_merchant_context(
        $pdo, $user, 'merchant.creator_applications.manage'
    );
    $workspaceId = (int) $context['workspace_id'];
    $actorId = (int) $context['actor_user_id'];
    $expectedLock = (int) ($input['expected_lock_version'] ?? 0);
    $reason = mg_creator_campaign_string($input['reason'] ?? null, 'reason', 1000);
    $internalNote = mg_creator_campaign_string($input['internal_note'] ?? null, 'internal_note', 16000);
    $action = strtolower(trim($action));
    $target = match ($action) {
        'start_review' => 'under_review',
        'request_information' => 'information_requested',
        'approve' => 'approved',
        'decline' => 'declined',
        default => throw new InvalidArgumentException('Application review action is invalid.'),
    };
    if (in_array($target, ['information_requested','declined'], true) && $reason === null) {
        throw new InvalidArgumentException('A decision reason is required.');
    }

    mg_creator_campaign_assert_transaction_boundary($pdo);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT a.*,cc.workspace_id,cc.public_id campaign_public_id,cc.status campaign_status,
                    cc.maximum_approved_creators,cc.access_mode,cc.application_deadline_at
             FROM creator_campaign_applications a
             INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id
             WHERE a.public_id=? AND cc.workspace_id=? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$applicationPublicId, $workspaceId]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$application) throw new RuntimeException('Creator campaign application not found.');
        mg_creator_campaign_participation_require_expected_lock($application, $expectedLock);
        $from = (string) $application['status'];
        mg_creator_campaign_assert_transition('application', $from, $target);

        $campaign = mg_creator_campaign_participation_campaign_by_public_id(
            $pdo, (string) $application['campaign_public_id'], $workspaceId, true
        );
        mg_creator_campaign_participation_require_campaign_open($campaign, 'approve or review applications');

        $participant = null;
        $agreement = null;
        if ($target === 'approved') {
            $eligibility = mg_creator_campaign_require_creator_eligibility($pdo, (int) $application['creator_user_id']);
            $participant = mg_creator_campaign_participant_upsert_pending(
                $pdo, $campaign, $eligibility, 'application', (int) $application['id'], null, $actorId, $reason
            );
            $agreement = mg_creator_campaign_agreement_ensure_offered(
                $pdo,
                $campaign,
                $participant,
                $actorId
            );
        }

        $update = $pdo->prepare(
            'UPDATE creator_campaign_applications
             SET status=?,decision_note=?,internal_note=COALESCE(?,internal_note),
                 reviewed_at=COALESCE(reviewed_at,NOW()),decided_at=IF(? IN (\'approved\',\'declined\'),NOW(),decided_at),
                 updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW()
             WHERE id=? AND lock_version=?'
        );
        $update->execute([
            $target, $reason, $internalNote, $target, $actorId, (int) $application['id'], $expectedLock,
        ]);
        if ($update->rowCount() !== 1) throw new DomainException('Application review lost its optimistic lock.');

        mg_creator_campaign_participation_event($pdo, [
            'campaign_id' => (int) $application['campaign_id'],
            'application_id' => (int) $application['id'],
            'participant_id' => $participant ? (int) $participant['id'] : null,
            'actor_user_id' => $actorId,
            'event_type' => 'application.' . $target,
            'from_status' => $from,
            'to_status' => $target,
            'reason' => $reason,
            'idempotency_key' => $input['idempotency_key'] ?? null,
            'context' => ['internal_note_present' => $internalNote !== null],
        ]);
        $pdo->commit();

        $result = mg_creator_campaign_application_detail_merchant($pdo, $user, $applicationPublicId);
        $result['participant'] = $participant;
        $result['agreement'] = $agreement;
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
