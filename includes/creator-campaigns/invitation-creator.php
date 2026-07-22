<?php
declare(strict_types=1);

function mg_creator_campaign_invitation_list_creator(PDO $pdo, array $user): array
{
    $context = mg_creator_campaign_creator_context(
        $pdo, $user, 'creator.campaign_invitations.respond_own'
    );
    mg_creator_campaign_invitation_expire_due($pdo, (int) $context['creator_user_id']);
    $stmt = $pdo->prepare(
        'SELECT i.public_id,i.status,i.invitation_message,i.response_deadline_at,i.sent_at,
                i.responded_at,i.lock_version,i.updated_at,
                cc.public_id campaign_public_id,cc.title campaign_title,cc.description,cc.objective,
                cc.category,cc.starts_at,cc.ends_at,mw.display_name merchant_name
         FROM creator_campaign_invitations i
         INNER JOIN creator_campaigns cc ON cc.id=i.campaign_id
         INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
         WHERE i.creator_user_id=?
         ORDER BY FIELD(i.status,\'pending\',\'accepted\',\'declined\',\'expired\',\'cancelled\'),
                  i.updated_at DESC,i.id DESC'
    );
    $stmt->execute([(int) $context['creator_user_id']]);
    return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
}

function mg_creator_campaign_invitation_respond_creator(
    PDO $pdo,
    array $user,
    string $invitationPublicId,
    string $response,
    array $input
): array {
    $context = mg_creator_campaign_creator_context(
        $pdo, $user, 'creator.campaign_invitations.respond_own'
    );
    $creatorUserId = (int) $context['creator_user_id'];
    $actorId = (int) $context['actor_user_id'];
    $expectedLock = (int) ($input['expected_lock_version'] ?? 0);
    $response = strtolower(trim($response));
    if (!in_array($response, ['accepted','declined'], true)) {
        throw new InvalidArgumentException('Invitation response must be accepted or declined.');
    }
    $reason = mg_creator_campaign_string($input['reason'] ?? null, 'reason', 1000);

    mg_creator_campaign_assert_transaction_boundary($pdo);
    $pdo->beginTransaction();
    try {
        $invitation = mg_creator_campaign_participation_invitation_by_public_id(
            $pdo, $invitationPublicId, null, $creatorUserId, true
        );
        mg_creator_campaign_participation_require_expected_lock($invitation, $expectedLock);
        if (
            !empty($invitation['response_deadline_at'])
            && (string) $invitation['response_deadline_at'] < gmdate('Y-m-d H:i:s')
        ) {
            throw new DomainException('This invitation has expired.');
        }
        $from = (string) $invitation['status'];
        mg_creator_campaign_assert_transition('invitation', $from, $response);

        $campaignStmt = $pdo->prepare('SELECT public_id FROM creator_campaigns WHERE id=? LIMIT 1');
        $campaignStmt->execute([(int) $invitation['campaign_id']]);
        $campaign = mg_creator_campaign_participation_campaign_by_public_id(
            $pdo, (string) $campaignStmt->fetchColumn(), null, true
        );
        mg_creator_campaign_participation_require_campaign_open($campaign, 'accept invitations');

        $participant = null;
        $agreement = null;
        if ($response === 'accepted') {
            $eligibility = mg_creator_campaign_require_creator_eligibility($pdo, $creatorUserId);
            $participant = mg_creator_campaign_participant_upsert_pending(
                $pdo, $campaign, $eligibility, 'invitation', null, (int) $invitation['id'], $actorId, $reason
            );
            $agreement = mg_creator_campaign_agreement_ensure_offered(
                $pdo,
                $campaign,
                $participant,
                (int) ($campaign['campaign_manager_user_id'] ?? $campaign['created_by_user_id'] ?? $actorId)
            );
        }

        $update = $pdo->prepare(
            'UPDATE creator_campaign_invitations
             SET status=?,responded_at=NOW(),updated_by_user_id=?,lock_version=lock_version+1,updated_at=NOW()
             WHERE id=? AND lock_version=?'
        );
        $update->execute([$response, $actorId, (int) $invitation['id'], $expectedLock]);
        if ($update->rowCount() !== 1) throw new DomainException('Invitation response lost its optimistic lock.');

        mg_creator_campaign_participation_event($pdo, [
            'campaign_id' => (int) $invitation['campaign_id'],
            'invitation_id' => (int) $invitation['id'],
            'participant_id' => $participant ? (int) $participant['id'] : null,
            'actor_user_id' => $actorId,
            'event_type' => 'invitation.' . $response,
            'from_status' => $from,
            'to_status' => $response,
            'reason' => $reason,
            'idempotency_key' => $input['idempotency_key'] ?? null,
        ]);
        $pdo->commit();

        $result = mg_creator_campaign_participation_invitation_by_public_id(
            $pdo, $invitationPublicId, null, $creatorUserId
        );
        $result['participant'] = $participant;
        $result['agreement'] = $agreement;
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
