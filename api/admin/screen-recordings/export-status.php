<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/admin-screen-recording-stage3.php';

mg_require_method('GET');
$user = mg_screen_recordings_require_api(false);
$pdo = mg_db();
mg_screen_recordings_require_schema($pdo);
mg_screen_recording_stage3_require_schema($pdo);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.export_status', 'user:' . (int)$user['id'], 120, 60);

$recordingId = max(0, (int)($_GET['recording_id'] ?? 0));
$jobId = max(0, (int)($_GET['job_id'] ?? 0));
if ($jobId > 0) {
    $job = mg_screen_recording_stage3_fetch_job_for_user($pdo, $jobId, $user, false);
    $recordingId = (int)$job['recording_id'];
} elseif ($recordingId > 0) {
    $job = mg_screen_recording_stage3_latest_job($pdo, $recordingId, $user);
} else {
    mg_fail('Recording id or job id is required.', 422);
}

$row = mg_screen_recordings_fetch_for_user($pdo, $recordingId, $user, false);
mg_ok([
    'recording' => mg_screen_recordings_public_record($row),
    'export_job' => $job,
    'export_jobs' => mg_screen_recording_stage3_list_jobs($pdo, $recordingId, $user),
    'latest_tutorial' => mg_screen_recording_stage3_latest_tutorial($pdo, $recordingId, $user),
], 'Export status loaded.');
