<?php
declare(strict_types=1);

/**
 * Admin export queue monitor / worker scaffold for Design Studio.
 *
 * GET  /api/admin/design-export-worker.php
 *      Lists queue counts and recent jobs.
 *
 * POST /api/admin/design-export-worker.php
 *      action=claim_next     Claim one queued job and mark it running.
 *      action=release_stale  Release stale running jobs back to queued.
 *      action=mark_failed    Mark a claimed/running job failed.
 *
 * This file intentionally does not generate final PDF/PNG/SVG/ZIP output yet.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../merchant/_design_studio_guard.php';

function mg_design_export_admin_user_can(array $user): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (array_intersect($roles, ['admin', 'super_admin'])) return true;
    return mg_api_user_has_permission($user, 'merchant.design_ai.admin') || mg_api_user_has_permission($user, 'merchant.design_assets.manage');
}

function mg_design_export_admin_require(array $user): void
{
    if (!mg_design_export_admin_user_can($user)) {
        mg_security_log('warning', 'admin.design_export_worker.denied', 'Design export worker access refused.', [], (int) ($user['id'] ?? 0));
        mg_fail('Access refused.', 403);
    }
}

function mg_design_export_worker_uuid(): string
{
    $host = substr(preg_replace('/[^A-Za-z0-9_.-]/', '-', gethostname() ?: 'web'), 0, 40);
    return 'admin-web-' . $host . '-' . bin2hex(random_bytes(4));
}

function mg_design_export_job_public(array $row): array
{
    return [
        'id' => (string) $row['public_id'],
        'workspace_id' => (int) $row['workspace_id'],
        'merchant_user_id' => (int) $row['merchant_user_id'],
        'project_internal_id' => $row['project_id'] !== null ? (int) $row['project_id'] : null,
        'output_asset_internal_id' => $row['output_asset_id'] !== null ? (int) $row['output_asset_id'] : null,
        'export_type' => (string) $row['export_type'],
        'status' => (string) $row['status'],
        'priority' => isset($row['priority']) ? (int) $row['priority'] : 5,
        'attempt_count' => isset($row['attempt_count']) ? (int) $row['attempt_count'] : 0,
        'max_attempts' => isset($row['max_attempts']) ? (int) $row['max_attempts'] : 3,
        'locked_at' => $row['locked_at'] ?? null,
        'locked_by' => $row['locked_by'] ?? null,
        'next_attempt_at' => $row['next_attempt_at'] ?? null,
        'last_attempt_at' => $row['last_attempt_at'] ?? null,
        'failed_at' => $row['failed_at'] ?? null,
        'failure_code' => $row['failure_code'] ?? null,
        'renderer_version' => $row['renderer_version'] ?? null,
        'error_message' => $row['error_message'] ?? null,
        'options' => json_decode((string) ($row['options_json'] ?? ''), true) ?: [],
        'manifest' => json_decode((string) ($row['manifest_json'] ?? ''), true) ?: [],
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_design_export_worker_counts(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT status, export_type, COUNT(*) job_count FROM merchant_design_export_jobs GROUP BY status, export_type ORDER BY status, export_type');
    return array_map(static fn(array $row): array => [
        'status' => (string) $row['status'],
        'export_type' => (string) $row['export_type'],
        'count' => (int) $row['job_count'],
    ], $stmt->fetchAll());
}

function mg_design_export_recent_jobs(PDO $pdo, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->query('SELECT * FROM merchant_design_export_jobs ORDER BY created_at DESC LIMIT ' . $limit);
    return array_map('mg_design_export_job_public', $stmt->fetchAll());
}

function mg_design_export_claim_next(PDO $pdo, string $workerId): ?array
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->query("SELECT * FROM merchant_design_export_jobs WHERE status='queued' AND attempt_count < max_attempts AND (next_attempt_at IS NULL OR next_attempt_at <= NOW()) AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)) ORDER BY priority ASC, created_at ASC LIMIT 1 FOR UPDATE");
        $job = $stmt->fetch();
        if (!$job) {
            $pdo->commit();
            return null;
        }

        $update = $pdo->prepare("UPDATE merchant_design_export_jobs SET status='running', attempt_count=attempt_count+1, locked_at=NOW(), locked_by=?, last_attempt_at=NOW(), error_message=NULL, failure_code=NULL, updated_at=NOW() WHERE id=? AND status='queued'");
        $update->execute([$workerId, (int) $job['id']]);

        $reload = $pdo->prepare('SELECT * FROM merchant_design_export_jobs WHERE id=? LIMIT 1');
        $reload->execute([(int) $job['id']]);
        $updated = $reload->fetch();
        $pdo->commit();
        return is_array($updated) ? $updated : null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function mg_design_export_release_stale(PDO $pdo, int $minutes): int
{
    $minutes = max(5, min(240, $minutes));
    $stmt = $pdo->prepare("UPDATE merchant_design_export_jobs SET status='queued', locked_at=NULL, locked_by=NULL, next_attempt_at=NOW(), error_message='Released stale worker lock.', updated_at=NOW() WHERE status='running' AND locked_at IS NOT NULL AND locked_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->execute([$minutes]);
    return $stmt->rowCount();
}

function mg_design_export_mark_failed(PDO $pdo, string $publicId, string $failureCode, string $message): ?array
{
    $publicId = trim($publicId);
    if ($publicId === '') mg_fail('job_id is required.', 422);
    $failureCode = preg_replace('/[^a-z0-9_.-]/i', '_', trim($failureCode)) ?: 'renderer_failed';
    $failureCode = substr($failureCode, 0, 80);
    $message = mb_substr(trim($message) !== '' ? trim($message) : 'Renderer job failed.', 0, 500);

    $stmt = $pdo->prepare("UPDATE merchant_design_export_jobs SET status='failed', failed_at=NOW(), locked_at=NULL, locked_by=NULL, failure_code=?, error_message=?, updated_at=NOW() WHERE public_id=? AND status IN ('running','queued') LIMIT 1");
    $stmt->execute([$failureCode, $message, $publicId]);
    if ($stmt->rowCount() < 1) return null;

    $reload = $pdo->prepare('SELECT * FROM merchant_design_export_jobs WHERE public_id=? LIMIT 1');
    $reload->execute([$publicId]);
    $row = $reload->fetch();
    return is_array($row) ? $row : null;
}

$user = mg_require_api_user();
mg_design_export_admin_require($user);
$pdo = mg_db();
mg_design_studio_require_tables($pdo, mg_design_studio_core_tables());

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    mg_ok([
        'counts' => mg_design_export_worker_counts($pdo),
        'recent_jobs' => mg_design_export_recent_jobs($pdo, 75),
        'renderer_status' => 'scaffold_ready',
        'renderer_note' => 'Jobs can be claimed/released/failed, but final file rendering is not implemented in this endpoint.',
    ]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);

$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string) ($input['action'] ?? 'claim_next')));
$workerId = mg_design_export_worker_uuid();

if ($action === 'claim_next') {
    $job = mg_design_export_claim_next($pdo, $workerId);
    if (!$job) {
        mg_ok(['claimed' => false, 'job' => null, 'worker_id' => $workerId], 'No queued export jobs available.');
    }
    mg_audit('admin.design_export_job_claimed', 'merchant_design_export_job', ['job_id' => (string) $job['public_id'], 'worker_id' => $workerId], (int) $user['id']);
    mg_ok(['claimed' => true, 'job' => mg_design_export_job_public($job), 'worker_id' => $workerId], 'Export job claimed.');
}

if ($action === 'release_stale') {
    $minutes = isset($input['minutes']) ? (int) $input['minutes'] : 15;
    $released = mg_design_export_release_stale($pdo, $minutes);
    mg_audit('admin.design_export_stale_released', 'merchant_design_export_job', ['released' => $released, 'minutes' => $minutes], (int) $user['id']);
    mg_ok(['released' => $released, 'minutes' => max(5, min(240, $minutes))], 'Stale export locks released.');
}

if ($action === 'mark_failed') {
    $job = mg_design_export_mark_failed($pdo, (string) ($input['job_id'] ?? ''), (string) ($input['failure_code'] ?? 'renderer_failed'), (string) ($input['message'] ?? 'Renderer job failed.'));
    if (!$job) mg_fail('Export job was not found or is not markable.', 404);
    mg_audit('admin.design_export_job_failed', 'merchant_design_export_job', ['job_id' => (string) $job['public_id'], 'failure_code' => (string) $job['failure_code']], (int) $user['id']);
    mg_ok(['job' => mg_design_export_job_public($job)], 'Export job marked failed.');
}

mg_fail('Unsupported worker action.', 422);
