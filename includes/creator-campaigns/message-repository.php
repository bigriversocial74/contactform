<?php
declare(strict_types=1);

function mg_creator_campaign_message_participant_sql(): string
{
    return "SELECT p.id,p.public_id,p.campaign_id,p.creator_user_id,p.status participant_status,
                   c.public_id campaign_public_id,c.title campaign_name,c.workspace_id merchant_workspace_id,
                   mw.merchant_user_id workspace_owner_user_id,
                   COALESCE(NULLIF(u.display_name,''),NULLIF(u.full_name,''),u.email) creator_name
            FROM creator_campaign_participants p
            INNER JOIN creator_campaigns c ON c.id=p.campaign_id
            INNER JOIN merchant_workspaces mw ON mw.id=c.workspace_id
            INNER JOIN users u ON u.id=p.creator_user_id";
}

function mg_creator_campaign_message_participant_for_merchant(PDO $pdo, int $workspaceId, string $participantPublicId, bool $forUpdate = false): array
{
    $stmt = $pdo->prepare(mg_creator_campaign_message_participant_sql() .
        " WHERE p.public_id=? AND c.workspace_id=? AND p.status NOT IN ('declined','removed') LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$participantPublicId, $workspaceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new DomainException('Creator Campaign participant not found in this workspace.');
    return $row;
}

function mg_creator_campaign_message_participant_for_creator(PDO $pdo, int $creatorUserId, string $participantPublicId, bool $forUpdate = false): array
{
    $stmt = $pdo->prepare(mg_creator_campaign_message_participant_sql() .
        " WHERE p.public_id=? AND p.creator_user_id=? AND p.status NOT IN ('declined','removed') LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$participantPublicId, $creatorUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new DomainException('Creator Campaign participant is not available for this Creator.');
    return $row;
}

function mg_creator_campaign_message_ensure_participant(PDO $pdo, int $threadId, int $userId): void
{
    if ($userId < 1) return;
    $pdo->prepare('INSERT IGNORE INTO message_thread_participants (thread_id,user_id,last_read_at,joined_at) VALUES (?,?,NULL,NOW())')
        ->execute([$threadId, $userId]);
}

function mg_creator_campaign_message_get_or_create_thread(PDO $pdo, array $participant, int $actorUserId): array
{
    $lookup = $pdo->prepare(
        'SELECT mc.*,mt.public_id thread_public_id,mt.subject,mt.updated_at thread_updated_at
         FROM creator_campaign_message_contexts mc
         INNER JOIN message_threads mt ON mt.id=mc.thread_id
         WHERE mc.campaign_id=? AND mc.participant_id=? LIMIT 1 FOR UPDATE'
    );
    $lookup->execute([(int)$participant['campaign_id'], (int)$participant['id']]);
    $context = $lookup->fetch(PDO::FETCH_ASSOC);
    if ($context) {
        mg_creator_campaign_message_ensure_participant($pdo, (int)$context['thread_id'], $actorUserId);
        return $context;
    }

    $threadPublicId = mg_public_uuid();
    $contextPublicId = mg_public_uuid();
    $subject = mb_substr((string)$participant['campaign_name'] . ' · ' . (string)$participant['creator_name'], 0, 160);
    $conversationKey = 'creator_campaign:' . strtolower((string)$participant['public_id']);
    $pdo->prepare(
        'INSERT INTO message_threads (public_id,gift_id,pppm_item_id,created_by_user_id,subject,conversation_key,created_at,updated_at)
         VALUES (?,NULL,NULL,?,?,?,NOW(),NOW())'
    )->execute([$threadPublicId, $actorUserId, $subject, $conversationKey]);
    $threadId = (int)$pdo->lastInsertId();

    foreach (array_unique([(int)$participant['workspace_owner_user_id'], (int)$participant['creator_user_id'], $actorUserId]) as $userId) {
        mg_creator_campaign_message_ensure_participant($pdo, $threadId, $userId);
    }

    $pdo->prepare(
        'INSERT INTO creator_campaign_message_contexts
         (public_id,thread_id,campaign_id,participant_id,merchant_workspace_id,creator_user_id,status,lock_version,created_by_user_id,updated_by_user_id,created_at,updated_at)
         VALUES (?,?,?,?,?,? ,\'open\',1,?,?,NOW(),NOW())'
    )->execute([
        $contextPublicId,$threadId,(int)$participant['campaign_id'],(int)$participant['id'],
        (int)$participant['merchant_workspace_id'],(int)$participant['creator_user_id'],$actorUserId,$actorUserId,
    ]);

    return [
        'id'=>(int)$pdo->lastInsertId(),'public_id'=>$contextPublicId,'thread_id'=>$threadId,
        'campaign_id'=>(int)$participant['campaign_id'],'participant_id'=>(int)$participant['id'],
        'merchant_workspace_id'=>(int)$participant['merchant_workspace_id'],'creator_user_id'=>(int)$participant['creator_user_id'],
        'status'=>'open','lock_version'=>1,'thread_public_id'=>$threadPublicId,'subject'=>$subject,'thread_updated_at'=>gmdate('Y-m-d H:i:s'),
    ];
}

function mg_creator_campaign_message_find_idempotent(PDO $pdo, int $contextId, string $hash): ?array
{
    $stmt = $pdo->prepare(
        'SELECT m.public_id message_public_id,ml.public_id link_public_id,mc.thread_id,mt.public_id thread_public_id
         FROM creator_campaign_message_links ml
         INNER JOIN messages m ON m.id=ml.message_id
         INNER JOIN creator_campaign_message_contexts mc ON mc.id=ml.message_context_id
         INNER JOIN message_threads mt ON mt.id=mc.thread_id
         WHERE ml.message_context_id=? AND ml.idempotency_hash=? LIMIT 1'
    );
    $stmt->execute([$contextId, $hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_creator_campaign_message_insert(PDO $pdo, array $context, int $actorUserId, string $body, string $contextType, ?string $contextPublicId, string $kind, ?string $systemEventType, array $assets, array $metadata, string $idempotencyHash): array
{
    $existing = mg_creator_campaign_message_find_idempotent($pdo, (int)$context['id'], $idempotencyHash);
    if ($existing) return $existing + ['duplicate'=>true];

    $recipientStmt = $pdo->prepare('SELECT user_id FROM message_thread_participants WHERE thread_id=? AND user_id<>? ORDER BY user_id');
    $recipientStmt->execute([(int)$context['thread_id'], $actorUserId]);
    $recipientIds = array_map('intval', $recipientStmt->fetchAll(PDO::FETCH_COLUMN));
    $recipientUserId = count($recipientIds) === 1 ? $recipientIds[0] : null;
    $sourceType = $kind === 'system' ? 'creator_campaign_system' : 'creator_campaign_message';
    $messagePublicId = mg_public_uuid();
    $pdo->prepare(
        'INSERT INTO messages (public_id,thread_id,sender_user_id,recipient_user_id,body,source_type,source_reference,created_at)
         VALUES (?,?,?,?,?,?,?,NOW())'
    )->execute([$messagePublicId,(int)$context['thread_id'],$actorUserId,$recipientUserId,$body,$sourceType,$contextPublicId ?? (string)$context['public_id']]);
    $messageId = (int)$pdo->lastInsertId();
    $linkPublicId = mg_public_uuid();
    $pdo->prepare(
        'INSERT INTO creator_campaign_message_links
         (public_id,message_context_id,message_id,context_type,context_public_id,message_kind,system_event_type,asset_public_ids_json,metadata_json,idempotency_hash,created_by_user_id,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())'
    )->execute([
        $linkPublicId,(int)$context['id'],$messageId,$contextType,$contextPublicId,$kind,$systemEventType,
        $assets !== [] ? json_encode($assets, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) : null,
        $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR) : null,
        $idempotencyHash,$actorUserId,
    ]);
    $pdo->prepare('UPDATE message_threads SET updated_at=NOW() WHERE id=?')->execute([(int)$context['thread_id']]);
    $pdo->prepare('UPDATE message_thread_participants SET last_read_at=NOW() WHERE thread_id=? AND user_id=?')
        ->execute([(int)$context['thread_id'],$actorUserId]);
    return ['message_public_id'=>$messagePublicId,'link_public_id'=>$linkPublicId,'thread_public_id'=>(string)$context['thread_public_id'],'duplicate'=>false];
}

function mg_creator_campaign_message_recipients(PDO $pdo, int $threadId, int $actorUserId): array
{
    $stmt = $pdo->prepare(
        'SELECT mtp.user_id,COALESCE(mts.notifications_enabled,1) notifications_enabled,mts.muted_until
         FROM message_thread_participants mtp
         LEFT JOIN message_thread_settings mts ON mts.thread_id=mtp.thread_id AND mts.user_id=mtp.user_id
         WHERE mtp.thread_id=? AND mtp.user_id<>?'
    );
    $stmt->execute([$threadId,$actorUserId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mg_creator_campaign_message_add_note(PDO $pdo, array $participant, int $workspaceId, int $actorUserId, string $contextType, ?string $contextPublicId, string $body, string $idempotencyHash): array
{
    $stmt = $pdo->prepare('SELECT public_id FROM creator_campaign_internal_notes WHERE merchant_workspace_id=? AND idempotency_hash=? LIMIT 1');
    $stmt->execute([$workspaceId,$idempotencyHash]);
    $existing = $stmt->fetchColumn();
    if ($existing) return ['note_public_id'=>(string)$existing,'duplicate'=>true];
    $publicId = mg_public_uuid();
    $pdo->prepare(
        'INSERT INTO creator_campaign_internal_notes
         (public_id,campaign_id,participant_id,merchant_workspace_id,context_type,context_public_id,body,moderation_status,idempotency_hash,author_user_id,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,\'clear\',?,?,NOW(),NOW())'
    )->execute([$publicId,(int)$participant['campaign_id'],(int)$participant['id'],$workspaceId,$contextType,$contextPublicId,$body,$idempotencyHash,$actorUserId]);
    return ['note_public_id'=>$publicId,'duplicate'=>false];
}

function mg_creator_campaign_message_set_status(PDO $pdo, array $context, string $status, int $actorUserId, int $lockVersion): void
{
    if (!in_array($status, ['open','closed'], true)) throw new InvalidArgumentException('Invalid thread status.');
    $stmt = $pdo->prepare('UPDATE creator_campaign_message_contexts SET status=?,lock_version=lock_version+1,updated_by_user_id=?,updated_at=NOW() WHERE id=? AND lock_version=?');
    $stmt->execute([$status,$actorUserId,(int)$context['id'],$lockVersion]);
    if ($stmt->rowCount() !== 1) throw new DomainException('Thread changed before this request. Refresh and try again.');
}
