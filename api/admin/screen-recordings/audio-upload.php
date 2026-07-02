<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/admin-screen-recording-stage3.php';

mg_require_method('POST');
$user = mg_screen_recordings_require_api(true);
$input = $_POST;
mg_require_csrf_for_write($input);
$pdo = mg_db();
mg_screen_recordings_require_schema($pdo);
mg_screen_recording_stage3_require_schema($pdo);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.audio_upload', 'user:' . (int)$user['id'], 30, 300);

$recordingId = max(0, (int)($input['recording_id'] ?? 0));
if ($recordingId < 1) mg_fail('Recording id is required.', 422);
if (empty($_FILES['audio_file']) || !is_array($_FILES['audio_file'])) mg_fail('Voiceover audio file is required.', 422);

try {
    $track = mg_screen_recording_stage3_store_audio($pdo, $recordingId, $user, $_FILES['audio_file'], $input);
} catch (Throwable $error) {
    mg_security_log('warning', 'admin.screen_recordings.audio_upload_failed', 'Unable to save voiceover audio.', ['recording_id' => $recordingId, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail('Unable to save voiceover audio. Check upload limits and server logs.', 422);
}

mg_audit('admin_screen_recording.audio_upload', 'admin_screen_recording', ['recording_id' => $recordingId, 'track_id' => (int)$track['id']], (int)$user['id']);
mg_ok([
    'audio_track' => $track,
    'audio_tracks' => mg_screen_recording_stage3_list_audio_tracks($pdo, $recordingId, $user),
], 'Voiceover audio saved.');
