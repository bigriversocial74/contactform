<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

function mg_content_review_access(array $user): array
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    $super = in_array('super_admin', $roles, true);
    $view = $super || count(array_intersect([
        'admin.moderation.view',
        'admin.moderation.manage',
        'social.moderate',
        'admin.profiles.moderation.view',
        'admin.profiles.moderation.manage',
    ], $permissions)) > 0;
    $manage = $super || count(array_intersect([
        'admin.moderation.manage',
        'social.moderate',
        'admin.profiles.moderation.manage',
    ], $permissions)) > 0;
    return ['view'=>$view, 'manage'=>$manage, 'super_admin'=>$super];
}

function mg_content_review_require(bool $manage = false): array
{
    $user = mg_require_api_user();
    $access = mg_content_review_access($user);
    if (!$access['view'] || ($manage && !$access['manage'])) {
        mg_security_log('warning', 'admin.content_review.denied', 'Content review access denied.', [
            'manage_required'=>$manage,
        ], (int)$user['id']);
        mg_fail('Permission denied.', 403);
    }
    $user['content_review_access'] = $access;
    return $user;
}

function mg_content_review_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]{1,64}$/', $table) !== 1) return false;
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function mg_content_review_reference(mixed $value): string
{
    $reference = strtolower(trim((string)$value));
    if ($reference === '' || mb_strlen($reference) > 190 || preg_match('/[\x00-\x1F\x7F]/', $reference) === 1) {
        throw new InvalidArgumentException('Invalid report identifier.');
    }
    return $reference;
}

function mg_content_review_json(?string $value): array
{
    if ($value === null || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_content_review_public_user(array $row, string $prefix): ?array
{
    $id = (int)($row[$prefix . '_id'] ?? 0);
    if ($id < 1) return null;
    $name = trim((string)($row[$prefix . '_display_name'] ?? $row[$prefix . '_full_name'] ?? $row[$prefix . '_email'] ?? 'Member'));
    return [
        'id'=>$id,
        'public_id'=>(string)($row[$prefix . '_public_id'] ?? ''),
        'name'=>$name !== '' ? $name : 'Member',
        'email'=>(string)($row[$prefix . '_email'] ?? ''),
        'status'=>(string)($row[$prefix . '_status'] ?? ''),
    ];
}

function mg_content_review_report(PDO $pdo, string $publicId, bool $lock = false): array
{
    $suffix = $lock ? ' FOR UPDATE' : '';
    $stmt = $pdo->prepare(
        "SELECT r.*,
                reporter.public_id reporter_public_id,reporter.display_name reporter_display_name,
                reporter.full_name reporter_full_name,reporter.email reporter_email,reporter.status reporter_status,
                subject.public_id subject_public_id,subject.display_name subject_display_name,
                subject.full_name subject_full_name,subject.email subject_email,subject.status subject_status,
                assignee.public_id assignee_public_id,assignee.display_name assignee_display_name,
                assignee.full_name assignee_full_name,assignee.email assignee_email,assignee.status assignee_status
         FROM social_reports r
         LEFT JOIN users reporter ON reporter.id=r.reporter_user_id
         LEFT JOIN users subject ON subject.id=r.subject_user_id
         LEFT JOIN users assignee ON assignee.id=r.assigned_user_id
         WHERE r.public_id=? LIMIT 1{$suffix}"
    );
    $stmt->execute([$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Report not found.');
    return $row;
}

function mg_content_review_report_public(array $row): array
{
    return [
        'id'=>(string)$row['public_id'],
        'status'=>(string)$row['status'],
        'severity'=>(string)$row['severity'],
        'source'=>(string)$row['source'],
        'subject_type'=>(string)$row['subject_type'],
        'subject_reference'=>(string)$row['subject_reference'],
        'reason_code'=>(string)$row['reason_code'],
        'details'=>(string)($row['details'] ?? ''),
        'resolution_note'=>(string)($row['resolution_note'] ?? ''),
        'reporter'=>mg_content_review_public_user($row, 'reporter'),
        'subject_user'=>mg_content_review_public_user($row, 'subject'),
        'assigned_to'=>mg_content_review_public_user($row, 'assignee'),
        'snapshot'=>mg_content_review_json($row['subject_snapshot_json'] ?? null),
        'created_at'=>$row['created_at'] ?? null,
        'reviewed_at'=>$row['reviewed_at'] ?? null,
        'updated_at'=>$row['updated_at'] ?? null,
    ];
}
