<?php
declare(strict_types=1);

function mg_creator_campaign_participation_decode_json(mixed $value): mixed
{
    if ($value === null || $value === '') return null;
    if (is_array($value)) return $value;
    $decoded = json_decode((string) $value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
}

function mg_creator_campaign_participation_campaign_by_public_id(
    PDO $pdo,
    string $publicId,
    ?int $workspaceId = null,
    bool $forUpdate = false
): array {
    $publicId = trim($publicId);
    if ($publicId === '') throw new InvalidArgumentException('campaign_id is required.');
    $sql = 'SELECT cc.*,mw.display_name merchant_name,mw.merchant_user_id
            FROM creator_campaigns cc
            INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
            WHERE cc.public_id=?';
    $params = [$publicId];
    if ($workspaceId !== null) {
        $sql .= ' AND cc.workspace_id=?';
        $params[] = $workspaceId;
    }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) throw new RuntimeException('Creator campaign not found.');
    $campaign['geographic_scope'] = mg_creator_campaign_participation_decode_json($campaign['geographic_scope_json'] ?? null);
    $campaign['builder_validation'] = mg_creator_campaign_participation_decode_json($campaign['builder_validation_json'] ?? null);
    return $campaign;
}

function mg_creator_campaign_participation_application_by_public_id(
    PDO $pdo,
    string $publicId,
    ?int $campaignId = null,
    ?int $creatorUserId = null,
    bool $forUpdate = false
): array {
    $publicId = trim($publicId);
    if ($publicId === '') throw new InvalidArgumentException('application_id is required.');
    $sql = 'SELECT a.*,cp.display_name creator_display_name,cp.slug creator_slug,
                   u.email creator_email,u.full_name creator_full_name
            FROM creator_campaign_applications a
            INNER JOIN creator_profiles cp ON cp.id=a.creator_profile_id
            INNER JOIN users u ON u.id=a.creator_user_id
            WHERE a.public_id=?';
    $params = [$publicId];
    if ($campaignId !== null) {
        $sql .= ' AND a.campaign_id=?';
        $params[] = $campaignId;
    }
    if ($creatorUserId !== null) {
        $sql .= ' AND a.creator_user_id=?';
        $params[] = $creatorUserId;
    }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator campaign application not found.');
    $row['creator_snapshot'] = mg_creator_campaign_participation_decode_json($row['creator_snapshot_json'] ?? null);
    return $row;
}

function mg_creator_campaign_participation_invitation_by_public_id(
    PDO $pdo,
    string $publicId,
    ?int $campaignId = null,
    ?int $creatorUserId = null,
    bool $forUpdate = false
): array {
    $publicId = trim($publicId);
    if ($publicId === '') throw new InvalidArgumentException('invitation_id is required.');
    $sql = 'SELECT i.*,cp.display_name creator_display_name,cp.slug creator_slug,
                   u.email creator_email,u.full_name creator_full_name
            FROM creator_campaign_invitations i
            INNER JOIN creator_profiles cp ON cp.id=i.creator_profile_id
            INNER JOIN users u ON u.id=i.creator_user_id
            WHERE i.public_id=?';
    $params = [$publicId];
    if ($campaignId !== null) {
        $sql .= ' AND i.campaign_id=?';
        $params[] = $campaignId;
    }
    if ($creatorUserId !== null) {
        $sql .= ' AND i.creator_user_id=?';
        $params[] = $creatorUserId;
    }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator campaign invitation not found.');
    return $row;
}

function mg_creator_campaign_participation_participant_by_public_id(
    PDO $pdo,
    string $publicId,
    ?int $campaignId = null,
    ?int $creatorUserId = null,
    bool $forUpdate = false
): array {
    $publicId = trim($publicId);
    if ($publicId === '') throw new InvalidArgumentException('participant_id is required.');
    $sql = 'SELECT p.*,cp.display_name creator_display_name,cp.slug creator_slug,
                   u.email creator_email,u.full_name creator_full_name
            FROM creator_campaign_participants p
            INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id
            INNER JOIN users u ON u.id=p.creator_user_id
            WHERE p.public_id=?';
    $params = [$publicId];
    if ($campaignId !== null) {
        $sql .= ' AND p.campaign_id=?';
        $params[] = $campaignId;
    }
    if ($creatorUserId !== null) {
        $sql .= ' AND p.creator_user_id=?';
        $params[] = $creatorUserId;
    }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator campaign participant not found.');
    return $row;
}

function mg_creator_campaign_participation_answer_rows(PDO $pdo, int $applicationId): array
{
    $stmt = $pdo->prepare(
        'SELECT aa.public_id,aa.answer_json,q.public_id question_public_id,q.prompt,q.helper_text,
                q.question_type,q.options_json,q.is_required,q.sort_order
         FROM creator_campaign_application_answers aa
         INNER JOIN creator_campaign_application_questions q ON q.id=aa.question_id
         WHERE aa.application_id=?
         ORDER BY q.sort_order,q.id'
    );
    $stmt->execute([$applicationId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $row['answer'] = mg_creator_campaign_participation_decode_json($row['answer_json'] ?? null);
        $row['options'] = mg_creator_campaign_participation_decode_json($row['options_json'] ?? null) ?: [];
        unset($row['answer_json'], $row['options_json']);
    }
    unset($row);
    return $rows;
}

function mg_creator_campaign_participation_questions(PDO $pdo, int $campaignId): array
{
    $stmt = $pdo->prepare(
        'SELECT public_id,prompt,helper_text,question_type,options_json,is_required,sort_order
         FROM creator_campaign_application_questions WHERE campaign_id=? ORDER BY sort_order,id'
    );
    $stmt->execute([$campaignId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $row['options'] = mg_creator_campaign_participation_decode_json($row['options_json'] ?? null) ?: [];
        unset($row['options_json']);
    }
    unset($row);
    return $rows;
}

function mg_creator_campaign_participation_products(PDO $pdo, int $campaignId): array
{
    $stmt = $pdo->prepare(
        "SELECT ccp.relationship_type,ccp.sort_order,ccp.value_snapshot_cents,ccp.currency,
                p.public_id product_public_id,p.slug,p.product_type,
                v.public_id version_public_id,v.title,v.description,v.unit_value_cents,v.currency version_currency
         FROM creator_campaign_products ccp
         INNER JOIN catalog_products p ON p.id=ccp.product_id
         LEFT JOIN catalog_product_versions v ON v.id=ccp.selected_product_version_id
         WHERE ccp.campaign_id=? AND ccp.relationship_type<>'excluded'
         ORDER BY FIELD(ccp.relationship_type,'primary','featured','creator_compensation','commissionable'),ccp.sort_order,ccp.id"
    );
    $stmt->execute([$campaignId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_creator_campaign_participation_summary(PDO $pdo, int $campaignId): array
{
    $queries = [
        'applications' => 'SELECT COUNT(*) FROM creator_campaign_applications WHERE campaign_id=?',
        'pending_applications' => "SELECT COUNT(*) FROM creator_campaign_applications WHERE campaign_id=? AND status IN ('submitted','under_review','information_requested')",
        'invitations' => 'SELECT COUNT(*) FROM creator_campaign_invitations WHERE campaign_id=?',
        'pending_invitations' => "SELECT COUNT(*) FROM creator_campaign_invitations WHERE campaign_id=? AND status='pending'",
        'participants' => "SELECT COUNT(*) FROM creator_campaign_participants WHERE campaign_id=? AND status IN ('approved','agreement_pending','active','completed','suspended')",
        'agreement_pending' => "SELECT COUNT(*) FROM creator_campaign_participants WHERE campaign_id=? AND status='agreement_pending'",
        'active' => "SELECT COUNT(*) FROM creator_campaign_participants WHERE campaign_id=? AND status='active'",
    ];
    $summary = [];
    foreach ($queries as $key => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$campaignId]);
        $summary[$key] = (int) $stmt->fetchColumn();
    }
    return $summary;
}

function mg_creator_campaign_participation_event(PDO $pdo, array $event): array
{
    $campaignId = (int) ($event['campaign_id'] ?? 0);
    $actorUserId = (int) ($event['actor_user_id'] ?? 0);
    $eventType = trim((string) ($event['event_type'] ?? ''));
    if ($campaignId < 1 || $actorUserId < 1 || $eventType === '') {
        throw new InvalidArgumentException('Participation event campaign, actor, and type are required.');
    }

    $idempotencyKey = trim((string) ($event['idempotency_key'] ?? ''));
    $idempotencyHash = $idempotencyKey === ''
        ? null
        : (function_exists('mg_creator_campaign_idempotency_hash')
            ? mg_creator_campaign_idempotency_hash($idempotencyKey)
            : hash('sha256', $idempotencyKey));

    if ($idempotencyHash !== null) {
        $existing = $pdo->prepare(
            'SELECT public_id FROM creator_campaign_participation_events
             WHERE campaign_id=? AND idempotency_hash=? LIMIT 1'
        );
        $existing->execute([$campaignId, $idempotencyHash]);
        $publicId = (string) ($existing->fetchColumn() ?: '');
        if ($publicId !== '') return ['public_id' => $publicId, 'idempotent_replay' => true];
    }

    $publicId = mg_creator_campaign_public_id('ccpe');
    $context = $event['context'] ?? null;
    $contextJson = $context === null
        ? null
        : (function_exists('mg_creator_campaign_json_encode')
            ? mg_creator_campaign_json_encode($context)
            : json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO creator_campaign_participation_events
             (public_id,campaign_id,application_id,invitation_id,participant_id,actor_user_id,
              event_type,from_status,to_status,reason,context_json,idempotency_hash,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
        );
        $stmt->execute([
            $publicId,
            $campaignId,
            isset($event['application_id']) ? (int) $event['application_id'] : null,
            isset($event['invitation_id']) ? (int) $event['invitation_id'] : null,
            isset($event['participant_id']) ? (int) $event['participant_id'] : null,
            $actorUserId,
            $eventType,
            isset($event['from_status']) ? (string) $event['from_status'] : null,
            isset($event['to_status']) ? (string) $event['to_status'] : null,
            isset($event['reason']) ? substr(trim((string) $event['reason']), 0, 1000) : null,
            $contextJson,
            $idempotencyHash,
        ]);
    } catch (PDOException $error) {
        if ($idempotencyHash === null || (string) $error->getCode() !== '23000') throw $error;
        $existing = $pdo->prepare(
            'SELECT public_id FROM creator_campaign_participation_events
             WHERE campaign_id=? AND idempotency_hash=? LIMIT 1'
        );
        $existing->execute([$campaignId, $idempotencyHash]);
        $replayPublicId = (string) ($existing->fetchColumn() ?: '');
        if ($replayPublicId === '') throw $error;
        return ['public_id' => $replayPublicId, 'idempotent_replay' => true];
    }

    return ['public_id' => $publicId, 'idempotent_replay' => false];
}

function mg_creator_campaign_participation_creator_by_public_id(PDO $pdo, string $creatorPublicId): array
{
    $creatorPublicId = trim($creatorPublicId);
    if ($creatorPublicId === '') throw new InvalidArgumentException('creator_profile_id is required.');
    $stmt = $pdo->prepare(
        'SELECT cp.id creator_profile_id,cp.public_id creator_profile_public_id,cp.user_id,
                cp.display_name,cp.slug,cp.bio,cp.status creator_profile_status,cp.metadata_json,
                u.email,u.display_name user_display_name,u.full_name,u.status user_status,
                pp.headline,pp.avatar_url,pp.location_label,pp.website_url,pp.completion_score
         FROM creator_profiles cp
         INNER JOIN users u ON u.id=cp.user_id
         LEFT JOIN public_profiles pp ON pp.user_id=cp.user_id
         WHERE cp.public_id=? LIMIT 1'
    );
    $stmt->execute([$creatorPublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator profile not found.');
    $row['metadata'] = mg_creator_campaign_participation_decode_json($row['metadata_json'] ?? null) ?: [];
    unset($row['metadata_json']);
    return $row;
}

function mg_creator_campaign_participation_creator_snapshot(PDO $pdo, int $creatorUserId): array
{
    if ($creatorUserId < 1) throw new InvalidArgumentException('creator_user_id is required.');
    $stmt = $pdo->prepare(
        'SELECT cp.id creator_profile_id,cp.public_id creator_profile_public_id,cp.user_id,
                cp.display_name,cp.slug,cp.bio,cp.status creator_profile_status,cp.metadata_json,
                u.email,u.display_name user_display_name,u.full_name,u.status user_status,
                pp.headline,pp.avatar_url,pp.location_label,pp.website_url,pp.completion_score
         FROM creator_profiles cp
         INNER JOIN users u ON u.id=cp.user_id
         LEFT JOIN public_profiles pp ON pp.user_id=cp.user_id
         WHERE cp.user_id=? LIMIT 1'
    );
    $stmt->execute([$creatorUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator profile not found.');
    $row['metadata'] = mg_creator_campaign_participation_decode_json($row['metadata_json'] ?? null) ?: [];
    unset($row['metadata_json']);
    return $row;
}
