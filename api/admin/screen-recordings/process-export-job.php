<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/admin-screen-recording-stage3.php';

mg_require_method('POST');
$user = mg_screen_recordings_require_api(true);
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();
mg_screen_recordings_require_schema($pdo);
mg_screen_recording_stage3_require_schema($pdo);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.process_export', 'user:' . (int)$user['id'], 10, 300);

$jobId = max(0, (int)($input['job_id'] ?? 0));
if ($jobId < 1) mg_fail('Export job id is required.', 422);

try {
    $job = mg_screen_recording_stage3_process_export_job($pdo, $jobId, $user);
} catch (Throwable $error) {
    mg_security_log('warning', 'admin.screen_recordings.process_export_failed', 'Unable to process export job.', ['job_id' => $jobId, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail('Unable to process export job. Check renderer diagnostics and server logs.', 422);
}

$row = mg_screen_recordings_fetch_for_user($pdo, (int)$job['recording_id'], $user, true);
mg_ok([
    'recording' => mg_screen_recordings_public_record($row),
    'export_job' => $job,
    'export_jobs' => mg_screen_recording_stage3_list_jobs($pdo, (int)$job['recording_id'], $user),
], $job['status'] === 'exported' ? 'Export rendered.' : 'Export job processed.');
