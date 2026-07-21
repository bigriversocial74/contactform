<?php
declare(strict_types=1);

function mg_creator_campaign_invitation_create_merchant(
    PDO $pdo,
    array $user,
    string $campaignPublicId,
    array $input
): array {
    $context = mg_creator_campaign_participation_merchant_context(
        $pdo, $user, 'merchant.creator_invitations.manage'
    );
    $workspaceId = (int) $context['workspace_id'];
    $actorId = (int) $context['actor_user_id'];
    $creatorPublicId = mg_creator_campaign_string(
        $input['creator_profile_id'] ?? null, 'creator_profile_id', 40, true
    );
    $message = mg_creator_campaign_string($input['invitation_message'] ?? null, 'invitation_message', 8000);
    $internalNote = mg_creator_campaign_string($input['internal_note'] ?? null, 'internal_note', 16000);
    $deadline = mg_creator_campaign_datetime(
        $input['response_deadline_at'] ?? null,
        'response_deadline_at',
        'UTC'
    );
    if ($deadline !== null && $deadline <= gmdate('Y-m-d H:i:s')) {
        throw new InvalidArgumentException('Invitation response deadline must be in the future.');
    }
    $idempotencyKey = mg_creator_campaign_validate_idempotency_key($input['idempotency_key'] ?? null);
    $hash = mg_creator_campaign_idempotency_hash($idempotencyKey);

    mg_creator_campaign_assert_transaction_boundary($pdo);
    $pdo->beginTransaction();
    try {
        $campaign = mg_creator_campaign_participation_campaign_by_public_id(
            $pdo, $campaignPublicId, $workspaceId, true
        );
        if (!mg_creator_campaign_participation_campaign_accepts_invitations($campaign)) {
            throw new DomainException('This campaign participation method does not allow invitations.');
        }
        mg_creator_campaign_participation_require_campaign_open($campaign, 'send invitations');

        $creator = mg_creator_campaign_participation_creator_by_public_id($pdo, $creatorPublicId);
        mg_creator_campaign_require_creator_eligibility($pdo, (int) $creator['user_id']);
        mg_creator_campaign_participant_require_capacity($pdo, $campaign, (int) $creator['user_id']);

        $replay = $pdo->prepare(
            'SELECT public_id FROM creator_campaign_invitations WHERE campaign_id=? AND idempotency_hash=? LIMIT 1'
        );
        $replay->execute([(int) $campaign['id'], $hash]);
        $replayPublicId = (string) ($replay->fetchColumn() ?: '');
        if ($replayPublicId !== '') {
            $pdo->commit();
            $row = mg_creator_campaign_participation_invitation_by_public_id($pdo, $replayPublicId);
            $row['idempotent_replay'] = true;
            return $row;
        }

        $existing = $pdo->prepare(
            'SELECT * FROM creator_campaign_invitations WHERE campaign_id=? AND creator_user_id=? LIMIT 1 FOR UPDATE'
        );
        $existing->execute([(int) $campaign['id'], (int) $creator['user_id']]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if ($row && in_array((string) $row['status'], ['pending','accepted'], true)) {
            throw new DomainException('This creator already has an active invitation or participation path for the campaign.');
        }

        if ($row) {
            $from = (string) $row['status'];
            mg_creator_campaign_assert_transition('invitation', $from, 'pending');
            $update = $pdo->prepare(
                "UPDATE creator_campaign_invitations
                 SET status='pending',invitation_message=?,internal_note=?,response_deadline_at=?,
                     sent_at=NOW(),responded_at=NULL,cancelled_at=NULL,idempotency_hash=?,
                     updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW()
                 WHERE id=?"
            );
            $update->execute([
                $message, $internalNote, $deadline, $hash, $actorId, (int) $row['id'],
            ]);
            $invitationId = (int) $row['id'];
            $publicId = (string) $row['public_id'];
        } else {
            $from = null;
            $insert = $pdo->prepare(
                "INSERT INTO creator_campaign_invitations
                 (public_id,campaign_id,creator_user_id,creator_profile_id,status,invitation_message,
                  internal_note,response_deadline_at,sent_at,idempotency_hash,lock_version,
                  created_by_user_id,updated_by_user_id,created_at,updated_at)
                 VALUES (?,?,?,?,'pending',?,?,?,NOW(),?,1,?,?,NOW(),NOW())"
            );
            $publicId = mg_creator_campaign_public_id('ccin');
            $insert->execute([
                $publicId,
                (int) $campaign['id'],
                (int) $creator['user_id'],
                (int) $creator['creator_profile_id'],
                $message,
                $internalNote,
                $deadline,
                $hash,
                $actorId,
                $actorId,
            ]);
            $invitationId = (int) $pdo->lastInsertId();
        }

        mg_creator_campaign_participation_event($pdo, [
            'campaign_id' => (int) $campaign['id'],
            'invitation_id' => $invitationId,
            'actor_user_id' => $actorId,
            'event_type' => $from === null ? 'invitation.sent' : 'invitation.resent',
            'from_status' => $from,
            'to_status' => 'pending',
            'context' => ['response_deadline_at' => $deadline],
        ]);
        $pdo->commit();

        $result = mg_creator_campaign_participation_invitation_by_public_id($pdo, $publicId);
        $result['idempotent_replay'] = false;
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_creator_campaign_invitation_list_merchant(PDO $pdo, array $user, array $filters = []): array
{
    $context = mg_creator_campaign_participation_merchant_context(
        $pdo, $user, 'merchant.creator_invitations.manage'
    );
    mg_creator_campaign_invitation_expire_due($pdo);
    $workspaceId = (int) $context['workspace_id'];
    $status = strtolower(trim((string) ($filters['status'] ?? '')));
    $campaignPublicId = trim((string) ($filters['campaign_id'] ?? ''));
    $search = trim((string) ($filters['search'] ?? ''));
    $page = max(1, (int) ($filters['page'] ?? 1));
    $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));

    $where = ['cc.workspace_id=?'];
    $params = [$workspaceId];
    if ($status !== '') {
        if (!in_array($status, mg_creator_campaign_invitation_statuses(), true)) {
            throw new InvalidArgumentException('Invitation status filter is invalid.');
        }
        $where[] = 'i.status=?';
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
    $base = ' FROM creator_campaign_invitations i
              INNER JOIN creator_campaigns cc ON cc.id=i.campaign_id
              INNER JOIN creator_profiles cp ON cp.id=i.creator_profile_id
              INNER JOIN users u ON u.id=i.creator_user_id
              WHERE ' . implode(' AND ', $where);

    $count = $pdo->prepare('SELECT COUNT(*)' . $base);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT i.public_id,i.status,i.invitation_message,i.internal_note,i.response_deadline_at,
                i.sent_at,i.responded_at,i.cancelled_at,i.lock_version,i.updated_at,
                cc.public_id campaign_public_id,cc.title campaign_title,
                cp.public_id creator_profile_public_id,
                COALESCE(cp.display_name,u.display_name,u.full_name,u.email) creator_name,
                cp.slug creator_slug,u.email creator_email'
        . $base .
        ' ORDER BY FIELD(i.status,\'pending\',\'accepted\',\'declined\',\'expired\',\'cancelled\'),
                   i.updated_at DESC,i.id DESC
          LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
    );
    $stmt->execute($params);

    return [
        'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => (int) ceil($total / $perPage)],
    ];
}

function mg_creator_campaign_invitation_cancel_merchant(
    PDO $pdo,
    array $user,
    string $invitationPublicId,
    array $input
): array {
    $context = mg_creator_campaign_participation_merchant_context(
        $pdo, $user, 'merchant.creator_invitations.manage'
    );
    $workspaceId = (int) $context['workspace_id'];
    $actorId = (int) $context['actor_user_id'];
    $expectedLock = (int) ($input['expected_lock_version'] ?? 0);
    $reason = mg_creator_campaign_string($input['reason'] ?? null, 'reason', 1000, true);

    mg_creator_campaign_assert_transaction_boundary($pdo);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT i.* FROM creator_campaign_invitations i
             INNER JOIN creator_campaigns cc ON cc.id=i.campaign_id
             WHERE i.public_id=? AND cc.workspace_id=? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$invitationPublicId, $workspaceId]);
        $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$invitation) throw new RuntimeException('Creator campaign invitation not found.');
        mg_creator_campaign_participation_require_expected_lock($invitation, $expectedLock);
        $from = (string) $invitation['status'];
        mg_creator_campaign_assert_transition('invitation', $from, 'cancelled');

        $update = $pdo->prepare(
            "UPDATE creator_campaign_invitations
             SET status='cancelled',cancelled_at=NOW(),internal_note=CONCAT_WS('\n',internal_note,?),
                 updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW()
             WHERE id=? AND lock_version=?"
        );
        $update->execute([$reason, $actorId, (int) $invitation['id'], $expectedLock]);
        if ($update->rowCount() !== 1) throw new DomainException('Invitation cancellation lost its optimistic lock.');

        mg_creator_campaign_participation_event($pdo, [
            'campaign_id' => (int) $invitation['campaign_id'],
            'invitation_id' => (int) $invitation['id'],
            'actor_user_id' => $actorId,
            'event_type' => 'invitation.cancelled',
            'from_status' => $from,
            'to_status' => 'cancelled',
            'reason' => $reason,
            'idempotency_key' => $input['idempotency_key'] ?? null,
        ]);
        $pdo->commit();
        return mg_creator_campaign_participation_invitation_by_public_id($pdo, $invitationPublicId);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

