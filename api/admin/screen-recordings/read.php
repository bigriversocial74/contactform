<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/admin-screen-recording-stage3.php';

mg_require_method('GET');
$user = mg_screen_recordings_require_api(false);
$pdo = mg_db();
mg_screen_recordings_require_schema($pdo);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.read', 'user:' . (int)$user['id'], 120, 60);

$recordingId = max(0, (int)($_GET['id'] ?? 0));
if ($recordingId < 1) mg_fail('Recording id is required.', 422);
$row = mg_screen_recordings_fetch_for_user($pdo, $recordingId, $user, false);
$stage3 = mg_screen_recording_stage3_schema_ready($pdo);
$data = ['recording' => mg_screen_recordings_public_record($row), 'stage3_schema' => $stage3];
if ($stage3['ready']) {
    $data['audio_tracks'] = mg_screen_recording_stage3_list_audio_tracks($pdo, $recordingId, $user);
    $data['export_jobs'] = mg_screen_recording_stage3_list_jobs($pdo, $recordingId, $user);
    $data['latest_tutorial'] = mg_screen_recording_stage3_latest_tutorial($pdo, $recordingId, $user);
}
mg_ok($data, 'Recording loaded.');
