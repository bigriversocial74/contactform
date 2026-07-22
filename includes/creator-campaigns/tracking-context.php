<?php
declare(strict_types=1);

function mg_creator_campaign_tracking_assert_schema(PDO $pdo): void
{
    mg_creator_campaign_participation_assert_schema($pdo);
    mg_creator_campaign_deliverable_assert_schema($pdo);
    if (!mg_creator_campaign_tracking_installed($pdo)) {
        throw new RuntimeException('Creator Tracking schema is incomplete. Import database/20260722_creator_campaign_tracking_attribution_v5.sql.');
    }
}

function mg_creator_campaign_tracking_merchant_context(PDO $pdo, array $user, string $permission): array
{
    mg_creator_campaign_tracking_assert_schema($pdo);
    return mg_creator_campaign_participation_merchant_context($pdo, $user, $permission);
}

function mg_creator_campaign_tracking_creator_context(PDO $pdo, array $user, string $permission): array
{
    mg_creator_campaign_tracking_assert_schema($pdo);
    return mg_creator_campaign_creator_context($pdo, $user, $permission);
}

function mg_creator_campaign_tracking_participant_by_public_id(
    PDO $pdo,
    string $publicId,
    ?int $workspaceId = null,
    ?int $creatorUserId = null,
    bool $forUpdate = false
): array {
    $publicId = trim($publicId);
    if ($publicId === '') throw new InvalidArgumentException('participant_id is required.');
    $sql = "SELECT p.*,cc.public_id campaign_public_id,cc.title campaign_title,cc.status campaign_status,
                   cc.workspace_id,a.latest_accepted_version_id,mw.display_name merchant_name
            FROM creator_campaign_participants p
            INNER JOIN creator_campaigns cc ON cc.id=p.campaign_id
            INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
            LEFT JOIN creator_campaign_agreements a ON a.participant_id=p.id
            WHERE p.public_id=?";
    $params = [$publicId];
    if ($workspaceId !== null) { $sql .= ' AND cc.workspace_id=?'; $params[] = $workspaceId; }
    if ($creatorUserId !== null) { $sql .= ' AND p.creator_user_id=?'; $params[] = $creatorUserId; }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator campaign participant not found.');
    if ((string) $row['status'] !== 'active' || (int) ($row['latest_accepted_version_id'] ?? 0) < 1) {
        throw new DomainException('An active accepted Creator Campaign agreement is required for tracking.');
    }
    return $row;
}
