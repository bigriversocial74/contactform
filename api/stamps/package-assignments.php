<?php
declare(strict_types=1);
require_once __DIR__ . '/_renewals.php';
$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
$pdo = mg_db();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function mg_stamp_package_assignment_row(array $row): array
{
    return [
        'id' => (string)$row['public_id'],
        'account_user_id' => (int)$row['account_user_id'],
        'package_id' => (string)$row['package_id'],
        'monthly_stamps_included' => mg_stamp_plan_allowance((string)$row['package_id']),
        'status' => (string)$row['status'],
        'started_at' => $row['started_at'] ?? null,
        'renews_at' => $row['renews_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

if ($method === 'GET') {
    try {
        $stmt = $pdo->query('SELECT public_id,account_user_id,package_id,status,started_at,renews_at,updated_at FROM account_package_assignments ORDER BY updated_at DESC,id DESC LIMIT 100');
        mg_ok(['assignments' => array_map('mg_stamp_package_assignment_row', $stmt->fetchAll())]);
    } catch (Throwable $error) {
        mg_security_log('warning','stamps.package_assignments_list_failed','Unable to list package assignments.', ['exception_class'=>$error::class], (int)$user['id']);
        mg_ok(['assignments'=>[], 'schema_ready'=>false], 'Package assignments unavailable until migration is installed.');
    }
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$accountUserId = max(1, (int)($input['account_user_id'] ?? 0));
$packageId = strtolower(trim((string)($input['package_id'] ?? '')));
$status = strtolower(trim((string)($input['status'] ?? 'active')));
$assignmentId = trim((string)($input['assignment_id'] ?? $input['id'] ?? ''));
$renewsAt = trim((string)($input['renews_at'] ?? ''));
if ($accountUserId < 1 || $packageId === '') mg_fail('account_user_id and package_id are required.', 422);
if (!in_array($status, ['active','paused','cancelled','archived'], true)) mg_fail('Invalid assignment status.', 422);
if (mg_stamp_plan_allowance($packageId) < 1 && $packageId !== 'enterprise') mg_fail('Unknown or unsupported package.', 422);

try {
    $pdo->beginTransaction();
    if ($status === 'active') {
        $pdo->prepare("UPDATE account_package_assignments SET status='archived',updated_at=NOW() WHERE account_user_id=? AND status='active' AND (?='' OR public_id<>?)")
            ->execute([$accountUserId, $assignmentId, $assignmentId]);
    }
    if ($assignmentId === '') {
        $assignmentId = mg_public_uuid();
        $pdo->prepare('INSERT INTO account_package_assignments (public_id,account_user_id,package_id,status,started_at,renews_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,NOW(),NULLIF(?,\'\'),JSON_OBJECT(),NOW(),NOW())')
            ->execute([$assignmentId,$accountUserId,$packageId,$status,$renewsAt]);
    } else {
        $pdo->prepare('UPDATE account_package_assignments SET account_user_id=?,package_id=?,status=?,renews_at=NULLIF(?,\'\'),updated_at=NOW() WHERE public_id=?')
            ->execute([$accountUserId,$packageId,$status,$renewsAt,$assignmentId]);
    }
    $stmt = $pdo->prepare('SELECT public_id,account_user_id,package_id,status,started_at,renews_at,updated_at FROM account_package_assignments WHERE public_id=? LIMIT 1');
    $stmt->execute([$assignmentId]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('Package assignment could not be loaded.');
    $pdo->commit();
    mg_audit('stamps.package_assignment_saved','account_package_assignment',['assignment_id'=>$assignmentId,'account_user_id'=>$accountUserId,'package_id'=>$packageId,'status'=>$status],(int)$user['id']);
    mg_ok(['assignment'=>mg_stamp_package_assignment_row($row)], 'Package assignment saved.', 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.package_assignment_save_failed','Unable to save package assignment.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to save package assignment.', 500);
}
