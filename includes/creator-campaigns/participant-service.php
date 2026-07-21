<?php
declare(strict_types=1);

function mg_creator_campaign_participant_capacity(PDO $pdo, array $campaign): array
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM creator_campaign_participants
         WHERE campaign_id=? AND status IN ('approved','agreement_pending','active','completed','suspended')"
    );
    $stmt->execute([(int) $campaign['id']]);
    $count = (int) $stmt->fetchColumn();
    $maximum = isset($campaign['maximum_approved_creators']) && $campaign['maximum_approved_creators'] !== null
        ? (int) $campaign['maximum_approved_creators']
        : null;
    return [
        'approved_count' => $count,
        'maximum_approved_creators' => $maximum,
        'available' => $maximum === null || $count < $maximum,
        'remaining' => $maximum === null ? null : max(0, $maximum - $count),
    ];
}

function mg_creator_campaign_participant_require_capacity(PDO $pdo, array $campaign, int $creatorUserId): void
{
    $existing = $pdo->prepare(
        "SELECT status FROM creator_campaign_participants WHERE campaign_id=? AND creator_user_id=? LIMIT 1"
    );
    $existing->execute([(int) $campaign['id'], $creatorUserId]);
    $status = (string) ($existing->fetchColumn() ?: '');
    if (in_array($status, ['approved','agreement_pending','active','completed','suspended'], true)) return;

    $capacity = mg_creator_campaign_participant_capacity($pdo, $campaign);
    if (!$capacity['available']) {
        throw new DomainException('This campaign has reached its approved creator limit.');
    }
}

function mg_creator_campaign_participant_upsert_pending(
    PDO $pdo,
    array $campaign,
    array $creatorEligibility,
    string $sourceType,
    ?int $sourceApplicationId,
    ?int $sourceInvitationId,
    int $actorUserId,
    ?string $reason = null
): array {
    if (!in_array($sourceType, ['application','invitation','manual'], true)) {
        throw new InvalidArgumentException('Participant source type is invalid.');
    }
    mg_creator_campaign_participation_require_campaign_open($campaign, 'approve new participants');
    $creatorUserId = (int) $creatorEligibility['user_id'];
    $creatorProfileId = (int) $creatorEligibility['creator_profile_id'];
    mg_creator_campaign_participant_require_capacity($pdo, $campaign, $creatorUserId);

    $stmt = $pdo->prepare(
        'SELECT * FROM creator_campaign_participants WHERE campaign_id=? AND creator_user_id=? LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([(int) $campaign['id'], $creatorUserId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $status = (string) $existing['status'];
        if (in_array($status, ['agreement_pending','active','completed'], true)) return $existing;
        if (!in_array($status, ['approved','suspended'], true)) {
            throw new DomainException('This creator already has a terminal participation record for the campaign.');
        }
        $from = $status;
        $update = $pdo->prepare(
            "UPDATE creator_campaign_participants
             SET status='agreement_pending',source_type=?,source_application_id=?,source_invitation_id=?,
                 approved_at=COALESCE(approved_at,NOW()),agreement_pending_at=NOW(),status_reason=?,
                 updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW()
             WHERE id=?"
        );
        $update->execute([
            $sourceType, $sourceApplicationId, $sourceInvitationId,
            $reason, $actorUserId, (int) $existing['id'],
        ]);
        $participantId = (int) $existing['id'];
        mg_creator_campaign_participation_event($pdo, [
            'campaign_id' => (int) $campaign['id'],
            'application_id' => $sourceApplicationId,
            'invitation_id' => $sourceInvitationId,
            'participant_id' => $participantId,
            'actor_user_id' => $actorUserId,
            'event_type' => 'participant.agreement_pending',
            'from_status' => $from,
            'to_status' => 'agreement_pending',
            'reason' => $reason,
            'context' => ['source_type' => $sourceType, 'phase' => 3],
        ]);
    } else {
        $insert = $pdo->prepare(
            "INSERT INTO creator_campaign_participants
             (public_id,campaign_id,creator_user_id,creator_profile_id,source_type,source_application_id,
              source_invitation_id,status,approved_at,agreement_pending_at,status_reason,lock_version,
              created_by_user_id,updated_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,'agreement_pending',NOW(),NOW(),?,1,?,?,NOW(),NOW())"
        );
        $insert->execute([
            mg_creator_campaign_public_id('ccpt'),
            (int) $campaign['id'],
            $creatorUserId,
            $creatorProfileId,
            $sourceType,
            $sourceApplicationId,
            $sourceInvitationId,
            $reason,
            $actorUserId,
            $actorUserId,
        ]);
        $participantId = (int) $pdo->lastInsertId();
        mg_creator_campaign_participation_event($pdo, [
            'campaign_id' => (int) $campaign['id'],
            'application_id' => $sourceApplicationId,
            'invitation_id' => $sourceInvitationId,
            'participant_id' => $participantId,
            'actor_user_id' => $actorUserId,
            'event_type' => 'participant.created',
            'from_status' => null,
            'to_status' => 'agreement_pending',
            'reason' => $reason,
            'context' => ['source_type' => $sourceType, 'phase' => 3, 'agreement_phase_required' => true],
        ]);
    }

    $result = $pdo->prepare(
        'SELECT * FROM creator_campaign_participants WHERE id=? LIMIT 1'
    );
    $result->execute([$participantId]);
    return $result->fetch(PDO::FETCH_ASSOC) ?: [];
}

function mg_creator_campaign_participant_list_merchant(
    PDO $pdo,
    array $user,
    array $filters = []
): array {
    $context = mg_creator_campaign_participation_merchant_context(
        $pdo, $user, 'merchant.creator_participants.view'
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
        if (!in_array($status, mg_creator_campaign_participant_statuses(), true)) {
            throw new InvalidArgumentException('Participant status filter is invalid.');
        }
        $where[] = 'p.status=?';
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

    $base = ' FROM creator_campaign_participants p
              INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id
              INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id
              INNER JOIN users u ON u.id=p.creator_user_id
              WHERE ' . implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(*)' . $base);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $list = $pdo->prepare(
        'SELECT p.public_id,p.status,p.source_type,p.approved_at,p.agreement_pending_at,p.activated_at,
                p.completed_at,p.removed_at,p.suspended_at,p.status_reason,p.lock_version,p.updated_at,
                cc.public_id campaign_public_id,cc.title campaign_title,
                cp.public_id creator_profile_public_id,COALESCE(cp.display_name,u.display_name,u.full_name,u.email) creator_name,
                cp.slug creator_slug,u.email creator_email'
        . $base .
        ' ORDER BY p.updated_at DESC,p.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
    );
    $list->execute($params);

    return [
        'items' => $list->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => (int) ceil($total / $perPage)],
    ];
}

function mg_creator_campaign_participant_transition_merchant(
    PDO $pdo,
    array $user,
    string $participantPublicId,
    string $toStatus,
    array $input
): array {
    $context = mg_creator_campaign_participation_merchant_context(
        $pdo, $user, 'merchant.creator_participants.manage'
    );
    $workspaceId = (int) $context['workspace_id'];
    $actorId = (int) $context['actor_user_id'];
    $toStatus = strtolower(trim($toStatus));
    if (!in_array($toStatus, ['removed','suspended','agreement_pending'], true)) {
        throw new DomainException('Phase 3 may only remove, suspend, or restore a participant to agreement pending.');
    }
    $reason = mg_creator_campaign_string($input['reason'] ?? null, 'reason', 1000, true);
    $expectedLock = (int) ($input['expected_lock_version'] ?? 0);

    mg_creator_campaign_assert_transaction_boundary($pdo);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT p.*,cc.workspace_id,cc.status campaign_status
             FROM creator_campaign_participants p
             INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id
             WHERE p.public_id=? AND cc.workspace_id=? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$participantPublicId, $workspaceId]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$participant) throw new RuntimeException('Creator campaign participant not found.');
        mg_creator_campaign_participation_require_expected_lock($participant, $expectedLock);
        $fromStatus = (string) $participant['status'];
        mg_creator_campaign_assert_transition('participant', $fromStatus, $toStatus);

        $timestamps = [
            'removed' => 'removed_at=NOW()',
            'suspended' => 'suspended_at=NOW()',
            'agreement_pending' => 'agreement_pending_at=NOW(),suspended_at=NULL',
        ];
        $update = $pdo->prepare(
            "UPDATE creator_campaign_participants
             SET status=?,{$timestamps[$toStatus]},status_reason=?,updated_by_user_id=?,
                 lock_version=lock_version+1,updated_at=NOW()
             WHERE id=? AND lock_version=?"
        );
        $update->execute([$toStatus, $reason, $actorId, (int) $participant['id'], $expectedLock]);
        if ($update->rowCount() !== 1) throw new DomainException('Participant update lost its optimistic lock.');

        mg_creator_campaign_participation_event($pdo, [
            'campaign_id' => (int) $participant['campaign_id'],
            'participant_id' => (int) $participant['id'],
            'actor_user_id' => $actorId,
            'event_type' => 'participant.' . $toStatus,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'idempotency_key' => $input['idempotency_key'] ?? null,
            'context' => ['phase' => 3],
        ]);
        $pdo->commit();
        return mg_creator_campaign_participation_participant_by_public_id(
            $pdo, $participantPublicId, null, null, false
        );
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_creator_campaign_participant_list_creator(PDO $pdo, array $user): array
{
    $context = mg_creator_campaign_creator_context(
        $pdo, $user, 'creator.campaign_participants.view_own'
    );
    $stmt = $pdo->prepare(
        "SELECT p.public_id,p.status,p.source_type,p.approved_at,p.agreement_pending_at,p.activated_at,
                p.completed_at,p.status_reason,p.lock_version,p.updated_at,
                cc.public_id campaign_public_id,cc.title campaign_title,cc.description,cc.objective,
                cc.category,cc.starts_at,cc.ends_at,mw.display_name merchant_name
         FROM creator_campaign_participants p
         INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id
         INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
         WHERE p.creator_user_id=?
         ORDER BY p.updated_at DESC,p.id DESC"
    );
    $stmt->execute([(int) $context['creator_user_id']]);
    return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
}
