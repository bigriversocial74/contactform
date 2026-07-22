<?php
declare(strict_types=1);

function mg_creator_campaign_crm_projection_by_key(PDO $pdo, int $merchantUserId, string $sourceEventKey, bool $forUpdate = false): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM merchant_crm_creator_campaign_events WHERE merchant_user_id=? AND source_event_key=? LIMIT 1'
        . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$merchantUserId, $sourceEventKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_creator_campaign_crm_reserve_projection(PDO $pdo, array $source): array
{
    $merchantUserId = (int) ($source['merchant_user_id'] ?? 0);
    $campaignId = (int) ($source['creator_campaign_id'] ?? 0);
    $sourceKey = (string) ($source['source_event_key'] ?? '');
    $domain = (string) ($source['source_domain'] ?? 'manual');
    $relationship = (string) ($source['relationship_type'] ?? 'creator_partner');
    if ($merchantUserId < 1 || $campaignId < 1 || $sourceKey === '') {
        throw new InvalidArgumentException('Creator Campaign CRM projection source is incomplete.');
    }
    if (!in_array($domain, mg_creator_campaign_crm_source_domains(), true)
        || !in_array($relationship, mg_creator_campaign_crm_relationship_types(), true)) {
        throw new InvalidArgumentException('Creator Campaign CRM projection source is invalid.');
    }

    $existing = mg_creator_campaign_crm_projection_by_key($pdo, $merchantUserId, $sourceKey, true);
    if ($existing) {
        return ['projection' => $existing, 'idempotent_replay' => in_array((string) $existing['projection_status'], ['completed','skipped'], true)];
    }

    $publicId = mg_merchant_crm_uuid();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO merchant_crm_creator_campaign_events
             (public_id,merchant_user_id,creator_campaign_id,relationship_type,source_domain,source_event_key,
              source_public_id,event_type,projection_status,metadata_json,occurred_at,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,\'pending\',?,?,NOW(),NOW())'
        );
        $stmt->execute([
            $publicId,$merchantUserId,$campaignId,$relationship,$domain,$sourceKey,
            $source['source_public_id'] ?? null,$source['event_type'] ?? 'creator_campaign_event',
            ($source['metadata'] ?? []) === [] ? null : mg_creator_campaign_json_encode((array) $source['metadata']),
            $source['occurred_at'] ?? gmdate('Y-m-d H:i:s'),
        ]);
    } catch (PDOException $error) {
        if ((string) $error->getCode() !== '23000') throw $error;
        $existing = mg_creator_campaign_crm_projection_by_key($pdo, $merchantUserId, $sourceKey, true);
        if (!$existing) throw $error;
        return ['projection' => $existing, 'idempotent_replay' => in_array((string) $existing['projection_status'], ['completed','skipped'], true)];
    }

    $projection = mg_creator_campaign_crm_projection_by_key($pdo, $merchantUserId, $sourceKey, true);
    if (!$projection) throw new RuntimeException('Creator Campaign CRM projection reservation failed.');
    return ['projection' => $projection, 'idempotent_replay' => false];
}

function mg_creator_campaign_crm_complete_projection(
    PDO $pdo,
    int $projectionId,
    string $status,
    ?int $contactId,
    ?int $crmEventId,
    array $metadata = [],
    ?string $errorCode = null,
    ?string $errorMessage = null
): void {
    if (!in_array($status, ['completed','failed','skipped'], true)) {
        throw new InvalidArgumentException('Creator Campaign CRM projection status is invalid.');
    }
    $stmt = $pdo->prepare(
        'UPDATE merchant_crm_creator_campaign_events
         SET crm_contact_id=?,crm_event_id=?,projection_status=?,error_code=?,error_message=?,metadata_json=?,
             projected_at=NOW(),updated_at=NOW() WHERE id=?'
    );
    $stmt->execute([
        $contactId,$crmEventId,$status,$errorCode,
        $errorMessage === null ? null : mb_substr($errorMessage, 0, 1000),
        $metadata === [] ? null : mg_creator_campaign_json_encode($metadata),$projectionId,
    ]);
}

function mg_creator_campaign_crm_participation_source(PDO $pdo, string $eventPublicId): array
{
    $stmt = $pdo->prepare(
        'SELECT pe.*,cc.public_id campaign_public_id,cc.title campaign_title,cc.workspace_id,
                mw.merchant_user_id,mw.display_name merchant_name,
                COALESCE(p.creator_user_id,a.creator_user_id,i.creator_user_id) creator_user_id,
                COALESCE(p.creator_profile_id,a.creator_profile_id,i.creator_profile_id) creator_profile_id,
                COALESCE(p.public_id,a.public_id,i.public_id) relationship_source_public_id,
                cp.display_name creator_name,u.email creator_email,u.full_name creator_full_name
         FROM creator_campaign_participation_events pe
         INNER JOIN creator_campaigns cc ON cc.id=pe.campaign_id
         INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
         LEFT JOIN creator_campaign_participants p ON p.id=pe.participant_id
         LEFT JOIN creator_campaign_applications a ON a.id=pe.application_id
         LEFT JOIN creator_campaign_invitations i ON i.id=pe.invitation_id
         LEFT JOIN creator_profiles cp ON cp.id=COALESCE(p.creator_profile_id,a.creator_profile_id,i.creator_profile_id)
         LEFT JOIN users u ON u.id=COALESCE(p.creator_user_id,a.creator_user_id,i.creator_user_id)
         WHERE pe.public_id=? LIMIT 1'
    );
    $stmt->execute([trim($eventPublicId)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator Campaign participation event not found.');
    $row['context'] = mg_creator_campaign_participation_decode_json($row['context_json'] ?? null) ?: [];
    return $row;
}

function mg_creator_campaign_crm_tracking_source(PDO $pdo, string $eventPublicId): array
{
    $stmt = $pdo->prepare(
        'SELECT e.*,cc.public_id campaign_public_id,cc.title campaign_title,cc.workspace_id,
                mw.merchant_user_id,mw.display_name merchant_name,
                s.public_id tracking_source_public_id,p.public_id participant_public_id,
                cp.display_name creator_name
         FROM creator_campaign_tracking_events e
         INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id
         INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
         LEFT JOIN creator_campaign_tracking_sources s ON s.id=e.source_id
         LEFT JOIN creator_campaign_participants p ON p.id=e.participant_id
         LEFT JOIN creator_profiles cp ON cp.id=p.creator_profile_id
         WHERE e.public_id=? LIMIT 1'
    );
    $stmt->execute([trim($eventPublicId)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator Campaign tracking event not found.');
    $row['metadata'] = mg_creator_campaign_participation_decode_json($row['metadata_json'] ?? null) ?: [];
    return $row;
}

function mg_creator_campaign_crm_resolve_crm_ids(PDO $pdo, int $merchantUserId, ?string $contactPublicId, ?string $eventPublicId): array
{
    $contactId = null;
    $eventId = null;
    if ($contactPublicId !== null && $contactPublicId !== '') {
        $stmt = $pdo->prepare('SELECT id FROM merchant_crm_contacts WHERE merchant_user_id=? AND public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$merchantUserId, $contactPublicId]);
        $value = $stmt->fetchColumn();
        $contactId = $value === false ? null : (int) $value;
    }
    if ($eventPublicId !== null && $eventPublicId !== '') {
        $stmt = $pdo->prepare('SELECT id FROM merchant_crm_contact_events WHERE merchant_user_id=? AND public_id=? LIMIT 1');
        $stmt->execute([$merchantUserId, $eventPublicId]);
        $value = $stmt->fetchColumn();
        $eventId = $value === false ? null : (int) $value;
    }
    return ['contact_id' => $contactId, 'event_id' => $eventId];
}

function mg_creator_campaign_crm_merge_contact_context(
    PDO $pdo,
    int $contactId,
    string $relationshipType,
    array $source
): void {
    $stmt = $pdo->prepare('SELECT lifecycle_stage,tags_json,metadata_json FROM merchant_crm_contacts WHERE id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$contactId]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$contact) return;

    $tags = mg_creator_campaign_participation_decode_json($contact['tags_json'] ?? null);
    $tags = is_array($tags) ? array_values(array_filter(array_map('strval', $tags))) : [];
    foreach (['creator_campaign', $relationshipType] as $tag) {
        if (!in_array($tag, $tags, true)) $tags[] = $tag;
    }
    $tags = array_slice(array_values(array_unique($tags)), 0, 25);

    $metadata = mg_creator_campaign_participation_decode_json($contact['metadata_json'] ?? null);
    $metadata = is_array($metadata) ? $metadata : [];
    $metadata['creator_campaign'] = [
        'campaign_id' => $source['campaign_public_id'] ?? null,
        'campaign_title' => $source['campaign_title'] ?? null,
        'relationship_type' => $relationshipType,
        'last_event_type' => $source['event_type'] ?? null,
        'last_event_at' => $source['occurred_at'] ?? gmdate('Y-m-d H:i:s'),
    ];

    $stage = (string) ($contact['lifecycle_stage'] ?? 'lead');
    if ($relationshipType === 'creator_partner' && $stage === 'lead') $stage = 'custom';
    $pdo->prepare(
        'UPDATE merchant_crm_contacts SET lifecycle_stage=?,last_campaign_type=\'creator_campaign\',
         last_source_type=?,tags_json=?,metadata_json=?,updated_at=NOW() WHERE id=?'
    )->execute([
        $stage,
        'creator_campaign_' . (string) ($source['source_domain'] ?? 'event'),
        mg_creator_campaign_json_encode($tags),
        mg_creator_campaign_json_encode($metadata),
        $contactId,
    ]);
}

function mg_creator_campaign_crm_upsert_relationship(
    PDO $pdo,
    int $merchantUserId,
    int $contactId,
    int $campaignId,
    string $relationshipType,
    string $eventType,
    string $occurredAt,
    bool $closed,
    array $metadata = []
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO merchant_crm_contact_creator_campaigns
         (public_id,merchant_user_id,crm_contact_id,creator_campaign_id,relationship_type,relationship_status,
          first_event_at,last_event_at,event_count,last_event_type,metadata_json,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?, ?,1,?,?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE relationship_status=VALUES(relationship_status),last_event_at=VALUES(last_event_at),
          event_count=event_count+1,last_event_type=VALUES(last_event_type),metadata_json=VALUES(metadata_json),updated_at=NOW()'
    );
    $stmt->execute([
        mg_merchant_crm_uuid(),$merchantUserId,$contactId,$campaignId,$relationshipType,
        $closed ? 'closed' : 'active',$occurredAt,$occurredAt,$eventType,
        $metadata === [] ? null : mg_creator_campaign_json_encode($metadata),
    ]);
}

function mg_creator_campaign_crm_create_run(PDO $pdo, int $merchantUserId, ?int $campaignId, int $actorUserId, string $mode): array
{
    if (!in_array($mode, ['event','campaign','workspace'], true)) throw new InvalidArgumentException('Projection run mode is invalid.');
    $publicId = mg_merchant_crm_uuid();
    $pdo->prepare(
        'INSERT INTO merchant_crm_creator_campaign_projection_runs
         (public_id,merchant_user_id,creator_campaign_id,actor_user_id,run_mode,status,started_at,created_at)
         VALUES (?,?,?,?,?,\'running\',NOW(),NOW())'
    )->execute([$publicId,$merchantUserId,$campaignId,$actorUserId,$mode]);
    return ['id' => (int) $pdo->lastInsertId(), 'public_id' => $publicId];
}

function mg_creator_campaign_crm_complete_run(PDO $pdo, int $runId, array $summary, array $errors = []): void
{
    $status = ((int) ($summary['failed_count'] ?? 0)) > 0 ? 'completed_with_errors' : 'completed';
    $pdo->prepare(
        'UPDATE merchant_crm_creator_campaign_projection_runs SET status=?,participation_scanned=?,tracking_scanned=?,
         projected_count=?,replay_count=?,skipped_count=?,failed_count=?,error_summary_json=?,completed_at=NOW() WHERE id=?'
    )->execute([
        $status,(int)($summary['participation_scanned']??0),(int)($summary['tracking_scanned']??0),
        (int)($summary['projected_count']??0),(int)($summary['replay_count']??0),(int)($summary['skipped_count']??0),
        (int)($summary['failed_count']??0),$errors===[]?null:mg_creator_campaign_json_encode(array_slice($errors,0,25)),$runId,
    ]);
}
