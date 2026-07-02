<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/admin-screen-recordings.php';

mg_require_method('POST');
$user = mg_screen_recordings_require_api(true);
$input = $_POST;
mg_require_csrf_for_write($input);
$pdo = mg_db();
mg_screen_recordings_require_schema($pdo);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.chunk', 'user:' . (int)$user['id'], 240, 300);

$recordingId = max(0, (int)($input['recording_id'] ?? 0));
$chunkIndex = max(0, (int)($input['chunk_index'] ?? 0));
if ($recordingId < 1) mg_fail('Recording id is required.', 422);
if (empty($_FILES['video_chunk']) || !is_array($_FILES['video_chunk'])) mg_fail('Recording chunk is required.', 422);
mg_screen_recordings_fetch_for_user($pdo, $recordingId, $user, true);

try {
    mg_screen_recordings_store_chunk($pdo, $recordingId, $_FILES['video_chunk'], $chunkIndex);
    mg_ok(['recording_id' => $recordingId, 'chunk_index' => $chunkIndex], 'Recording chunk uploaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'admin.screen_recordings.chunk_failed', 'Unable to upload screen recording chunk.', ['recording_id' => $recordingId, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail('Unable to upload recording chunk. Check diagnostics or server logs.', 422);
}
