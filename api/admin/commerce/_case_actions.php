<?php
declare(strict_types=1);

require_once __DIR__ . '/_detail.php';

function mg_admin_commerce_priority(mixed $value): string
{
    $priority = strtolower(trim((string)$value));
    if (!in_array($priority, ['low','normal','high','urgent'], true)) {
        throw new MgAdminCommerceException('Invalid commerce case priority.', 422);
    }
    return $priority;
}

function mg_admin_commerce_resolution(mixed $value): string
{
    $resolution = strtolower(trim((string)$value));
    if ($resolution === '') return 'resolved';
    if (preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $resolution) !== 1) {
        throw new MgAdminCommerceException('Invalid commerce case resolution.', 422);
    }
    return $resolution;
}

function mg_admin_commerce_assignee(PDO $pdo, mixed $value, int $actorId): ?int
{
    $raw = trim((string)$value);
    if ($raw === '') return null;
    if ($raw === 'self') return $actorId;
    $id = mg_admin_commerce_user_id($raw);
    if ($id === null || !mg_admin_commerce_scalar($pdo, "SELECT id FROM users WHERE id=? AND status='active' LIMIT 1", [$id])) {
        throw new MgAdminCommerceException('Assigned operator was not found or is inactive.', 422);
    }
    return $id;
}

function mg_admin_commerce_case_event(PDO $pdo, int $caseId, string $action, ?string $from, ?string $to, int $actorId, ?string $note, array $metadata = []): void
{
    $pdo->prepare('INSERT INTO commerce_operation_case_events
        (public_id,case_id,action_type,from_status,to_status,actor_user_id,note,metadata_json,created_at)
        VALUES (?,?,?,?,?,?,?,?,NOW())')
        ->execute([
            mg_public_uuid(), $caseId, $action, $from, $to, $actorId,
            $note !== null ? mb_substr($note, 0, 1000) : null,
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
}

function mg_admin_commerce_case_locked(PDO $pdo, int $caseId): array
{
    $row = mg_admin_commerce_one($pdo, 'SELECT * FROM commerce_operation_cases WHERE id=? LIMIT 1 FOR UPDATE', [$caseId]);
    if (!$row) throw new MgAdminCommerceException('Commerce review case not found.', 404);
    return $row;
}

function mg_admin_commerce_open_case(PDO $pdo, int $actorId, string $type, string $reference, string $priority, string $summary, string $reason): array
{
    if (!mg_admin_commerce_subject_exists($pdo, $type, $reference, true)) {
        throw new MgAdminCommerceException('Commerce subject not found.', 404);
    }
    $existing = mg_admin_commerce_one($pdo,
        "SELECT * FROM commerce_operation_cases WHERE subject_type=? AND subject_reference=? AND status IN ('open','reviewing') LIMIT 1 FOR UPDATE",
        [$type, $reference]
    );
    if ($existing) throw new MgAdminCommerceException('An active review case already exists for this subject.', 409);

    $publicId = mg_public_uuid();
    $pdo->prepare("INSERT INTO commerce_operation_cases
        (public_id,subject_type,subject_reference,status,priority,summary,latest_note,opened_by_user_id,opened_at,created_at,updated_at)
        VALUES (?,?,?,'open',?,?,?,?,NOW(),NOW(),NOW())")
        ->execute([$publicId, $type, $reference, $priority, mb_substr($summary, 0, 240), mb_substr($reason, 0, 1000), $actorId]);
    $caseId = (int)$pdo->lastInsertId();
    mg_admin_commerce_case_event($pdo, $caseId, 'opened', null, 'open', $actorId, $reason, ['priority'=>$priority]);
    return ['case_id'=>$caseId,'case_public_id'=>$publicId,'status'=>'open'];
}

function mg_admin_commerce_assign_case(PDO $pdo, int $actorId, int $caseId, ?int $assignee, string $reason): array
{
    $case = mg_admin_commerce_case_locked($pdo, $caseId);
    if (in_array((string)$case['status'], ['resolved','dismissed'], true)) {
        throw new MgAdminCommerceException('Closed cases must be reopened before assignment.', 409);
    }
    $from = (string)$case['status'];
    $to = $assignee !== null ? 'reviewing' : 'open';
    $pdo->prepare('UPDATE commerce_operation_cases SET assigned_user_id=?,status=?,latest_note=?,updated_at=NOW() WHERE id=?')
        ->execute([$assignee, $to, mb_substr($reason,0,1000), $caseId]);
    mg_admin_commerce_case_event($pdo, $caseId, 'assigned', $from, $to, $actorId, $reason, ['assigned_user_id'=>$assignee]);
    return ['case_id'=>$caseId,'status'=>$to,'assigned_user_id'=>$assignee];
}

function mg_admin_commerce_note_case(PDO $pdo, int $actorId, int $caseId, string $reason): array
{
    $case = mg_admin_commerce_case_locked($pdo, $caseId);
    $pdo->prepare('UPDATE commerce_operation_cases SET latest_note=?,updated_at=NOW() WHERE id=?')
        ->execute([mb_substr($reason,0,1000), $caseId]);
    mg_admin_commerce_case_event($pdo, $caseId, 'note_added', (string)$case['status'], (string)$case['status'], $actorId, $reason);
    return ['case_id'=>$caseId,'status'=>(string)$case['status']];
}

function mg_admin_commerce_close_case(PDO $pdo, int $actorId, int $caseId, string $status, string $resolution, string $reason): array
{
    $case = mg_admin_commerce_case_locked($pdo, $caseId);
    if (in_array((string)$case['status'], ['resolved','dismissed'], true)) {
        throw new MgAdminCommerceException('Commerce review case is already closed.', 409);
    }
    $from = (string)$case['status'];
    $pdo->prepare('UPDATE commerce_operation_cases SET status=?,latest_note=?,resolved_by_user_id=?,resolution_code=?,resolved_at=NOW(),updated_at=NOW() WHERE id=?')
        ->execute([$status, mb_substr($reason,0,1000), $actorId, $resolution, $caseId]);
    mg_admin_commerce_case_event($pdo, $caseId, $status === 'resolved' ? 'resolved' : 'dismissed', $from, $status, $actorId, $reason, ['resolution_code'=>$resolution]);
    return ['case_id'=>$caseId,'status'=>$status,'resolution_code'=>$resolution];
}

function mg_admin_commerce_reopen_case(PDO $pdo, int $actorId, int $caseId, string $reason): array
{
    $case = mg_admin_commerce_case_locked($pdo, $caseId);
    if (!in_array((string)$case['status'], ['resolved','dismissed'], true)) {
        throw new MgAdminCommerceException('Only a closed case can be reopened.', 409);
    }
    $from = (string)$case['status'];
    $pdo->prepare("UPDATE commerce_operation_cases SET status='open',latest_note=?,assigned_user_id=NULL,resolved_by_user_id=NULL,resolution_code=NULL,resolved_at=NULL,updated_at=NOW() WHERE id=?")
        ->execute([mb_substr($reason,0,1000), $caseId]);
    mg_admin_commerce_case_event($pdo, $caseId, 'reopened', $from, 'open', $actorId, $reason);
    return ['case_id'=>$caseId,'status'=>'open'];
}
