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
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.export_edit', 'user:' . (int)$user['id'], 20, 300);

$recordingId = max(0, (int)($input['recording_id'] ?? $input['id'] ?? 0));
if ($recordingId < 1) mg_fail('Recording id is required.', 422);
$row = mg_screen_recordings_fetch_for_user($pdo, $recordingId, $user, true);
$manifest = $input['edit_manifest'] ?? $input['manifest'] ?? mg_screen_recordings_decode_manifest($row['edit_manifest_json'] ?? null);
if (is_string($manifest)) {
    $decoded = json_decode($manifest, true);
    $manifest = is_array($decoded) ? $decoded : [];
}
if (!is_array($manifest)) $manifest = mg_screen_recordings_decode_manifest($row['edit_manifest_json'] ?? null);

try {
    $job = mg_screen_recording_stage3_create_export_job($pdo, $recordingId, $user, $manifest, [
        'format' => $input['format'] ?? 'webm',
        'burn_overlays' => !empty($input['burn_overlays']),
        'include_audio' => array_key_exists('include_audio', $input) ? !empty($input['include_audio']) : true,
        'mute_original_audio' => !empty($input['mute_original_audio']),
        'original_audio_volume' => $input['original_audio_volume'] ?? 1,
        'voiceover_volume' => $input['voiceover_volume'] ?? 1,
    ]);

    if (!empty($input['process_now'])) {
        $job = mg_screen_recording_stage3_process_export_job($pdo, (int)$job['id'], $user);
    }
} catch (Throwable $error) {
    mg_security_log('warning', 'admin.screen_recordings.export_edit_failed', 'Unable to queue screen recording export.', ['recording_id' => $recordingId, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail('Unable to queue export request. Check diagnostics or server logs.', 422);
}

mg_audit('admin_screen_recording.export_request', 'admin_screen_recording', ['recording_id' => $recordingId, 'format' => (string)$job['requested_format'], 'job_id' => (int)$job['id']], (int)$user['id']);
$row = mg_screen_recordings_fetch_for_user($pdo, $recordingId, $user, true);
mg_ok([
    'recording' => mg_screen_recordings_public_record($row),
    'export_job' => $job,
    'export_jobs' => mg_screen_recording_stage3_list_jobs($pdo, $recordingId, $user),
    'message_detail' => $job['status'] === 'exported' ? 'Export rendered successfully.' : 'Export job was queued. Use status polling or Process export to finish rendering.',
], 'Export job queued.');
