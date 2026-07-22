<?php
declare(strict_types=1);

function mg_creator_campaign_deliverable_decode_json(mixed $value): array
{
    $decoded = mg_creator_campaign_participation_decode_json($value);
    return is_array($decoded) ? $decoded : [];
}

function mg_creator_campaign_deliverable_by_public_id(PDO $pdo, string $publicId, ?int $workspaceId = null, bool $forUpdate = false): array
{
    $publicId = trim($publicId);
    if ($publicId === '') throw new InvalidArgumentException('deliverable_id is required.');
    $sql = 'SELECT d.*,cc.public_id campaign_public_id,cc.title campaign_title,cc.workspace_id FROM creator_campaign_deliverables d INNER JOIN creator_campaigns cc ON cc.id=d.campaign_id WHERE d.public_id=?';
    $params = [$publicId];
    if ($workspaceId !== null) { $sql .= ' AND cc.workspace_id=?'; $params[] = $workspaceId; }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator campaign deliverable not found.');
    $row['required_talking_points'] = mg_creator_campaign_deliverable_decode_json($row['required_talking_points_json'] ?? null);
    $row['required_disclosures'] = mg_creator_campaign_deliverable_decode_json($row['required_disclosures_json'] ?? null);
    unset($row['required_talking_points_json'], $row['required_disclosures_json']);
    return $row;
}

function mg_creator_campaign_participant_deliverable_by_public_id(PDO $pdo, string $publicId, ?int $workspaceId = null, ?int $creatorUserId = null, bool $forUpdate = false): array
{
    $publicId = trim($publicId);
    if ($publicId === '') throw new InvalidArgumentException('assignment_id is required.');
    $sql = 'SELECT pd.*,d.public_id deliverable_public_id,d.title deliverable_title,d.description deliverable_description,d.deliverable_type,d.platform deliverable_platform,d.content_format,d.instructions,d.required_talking_points_json,d.required_disclosures_json,d.publication_required,d.proof_required,d.merchant_review_required,d.revision_limit,cc.public_id campaign_public_id,cc.title campaign_title,cc.workspace_id,mw.merchant_user_id,mw.display_name merchant_name,p.status participant_status,a.latest_accepted_version_id FROM creator_campaign_participant_deliverables pd INNER JOIN creator_campaign_deliverables d ON d.id=pd.campaign_deliverable_id INNER JOIN creator_campaigns cc ON cc.id=pd.campaign_id INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id INNER JOIN creator_campaign_participants p ON p.id=pd.participant_id INNER JOIN creator_campaign_agreements a ON a.participant_id=p.id WHERE pd.public_id=?';
    $params = [$publicId];
    if ($workspaceId !== null) { $sql .= ' AND cc.workspace_id=?'; $params[] = $workspaceId; }
    if ($creatorUserId !== null) { $sql .= ' AND pd.creator_user_id=?'; $params[] = $creatorUserId; }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator campaign deliverable assignment not found.');
    $row['required_talking_points'] = mg_creator_campaign_deliverable_decode_json($row['required_talking_points_json'] ?? null);
    $row['required_disclosures'] = mg_creator_campaign_deliverable_decode_json($row['required_disclosures_json'] ?? null);
    unset($row['required_talking_points_json'], $row['required_disclosures_json']);
    return $row;
}

function mg_creator_campaign_submission_by_public_id(PDO $pdo, string $publicId, ?int $workspaceId = null, ?int $creatorUserId = null, bool $forUpdate = false): array
{
    $publicId = trim($publicId);
    if ($publicId === '') throw new InvalidArgumentException('submission_id is required.');
    $sql = 'SELECT s.*,pd.public_id assignment_public_id,pd.status assignment_status,d.public_id deliverable_public_id,d.title deliverable_title,d.revision_limit,d.publication_required,d.proof_required,cc.public_id campaign_public_id,cc.title campaign_title,cc.workspace_id,mw.merchant_user_id,mw.display_name merchant_name,p.status participant_status,cp.display_name creator_name FROM creator_campaign_submissions s INNER JOIN creator_campaign_participant_deliverables pd ON pd.id=s.participant_deliverable_id INNER JOIN creator_campaign_deliverables d ON d.id=pd.campaign_deliverable_id INNER JOIN creator_campaigns cc ON cc.id=s.campaign_id INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id INNER JOIN creator_campaign_participants p ON p.id=s.participant_id INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id WHERE s.public_id=?';
    $params = [$publicId];
    if ($workspaceId !== null) { $sql .= ' AND cc.workspace_id=?'; $params[] = $workspaceId; }
    if ($creatorUserId !== null) { $sql .= ' AND s.creator_user_id=?'; $params[] = $creatorUserId; }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator campaign submission not found.');
    return $row;
}

function mg_creator_campaign_submission_snapshot(array $submission): array
{
    return [
        'status' => $submission['status'] ?? null,
        'caption_text' => $submission['caption_text'] ?? null,
        'content_url' => $submission['content_url'] ?? null,
        'platform' => $submission['platform'] ?? null,
        'disclosure_text' => $submission['disclosure_text'] ?? null,
        'creator_note' => $submission['creator_note'] ?? null,
        'merchant_feedback' => $submission['merchant_feedback'] ?? null,
        'publication_url' => $submission['publication_url'] ?? null,
        'publication_platform' => $submission['publication_platform'] ?? null,
        'publication_identifier' => $submission['publication_identifier'] ?? null,
        'captured_at' => gmdate('c'),
    ];
}

function mg_creator_campaign_submission_revision(PDO $pdo, array $submission, int $actorUserId, string $changeType, ?string $feedback = null): array
{
    $next = (int) ($submission['current_revision_number'] ?? 0) + 1;
    $publicId = mg_creator_campaign_public_id('ccsr');
    $stmt = $pdo->prepare('INSERT INTO creator_campaign_submission_revisions(public_id,submission_id,participant_deliverable_id,agreement_version_id,revision_number,actor_user_id,change_type,content_snapshot_json,feedback,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');
    $stmt->execute([$publicId,(int)$submission['id'],(int)$submission['participant_deliverable_id'],(int)$submission['agreement_version_id'],$next,$actorUserId,$changeType,mg_creator_campaign_json_encode(mg_creator_campaign_submission_snapshot($submission)),$feedback]);
    $pdo->prepare('UPDATE creator_campaign_submissions SET current_revision_number=? WHERE id=?')->execute([$next,(int)$submission['id']]);
    return ['public_id'=>$publicId,'revision_number'=>$next];
}

function mg_creator_campaign_deliverable_due_at(array $deliverable, array $campaign): ?string
{
    $fixed = trim((string) ($deliverable['due_at'] ?? ''));
    if ($fixed !== '') return $fixed;
    $offset = isset($deliverable['due_offset_days']) ? (int) $deliverable['due_offset_days'] : 0;
    $base = trim((string) ($campaign['starts_at'] ?? ''));
    if ($base === '') $base = gmdate('Y-m-d H:i:s');
    try { return (new DateTimeImmutable($base, new DateTimeZone('UTC')))->modify('+' . max(0,$offset) . ' days')->format('Y-m-d H:i:s'); }
    catch (Throwable) { return null; }
}

function mg_creator_campaign_deliverable_event(PDO $pdo, array $assignment, int $actorUserId, string $eventType, ?string $fromStatus, ?string $toStatus, ?string $reason = null, array $context = []): void
{
    mg_creator_campaign_participation_event($pdo, [
        'campaign_id'=>(int)$assignment['campaign_id'],
        'participant_id'=>(int)$assignment['participant_id'],
        'actor_user_id'=>$actorUserId,
        'event_type'=>$eventType,
        'from_status'=>$fromStatus,
        'to_status'=>$toStatus,
        'reason'=>$reason,
        'context'=>$context + ['assignment_id'=>$assignment['public_id'] ?? null,'deliverable_id'=>$assignment['deliverable_public_id'] ?? null],
    ]);
}
