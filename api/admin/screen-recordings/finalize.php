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
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.finalize', 'user:' . (int)$user['id'], 20, 300);

$recordingId = max(0, (int)($input['recording_id'] ?? 0));
if ($recordingId < 1) mg_fail('Recording id is required.', 422);
if (empty($_FILES['video_file']) || !is_array($_FILES['video_file'])) mg_fail('Recording video file is required.', 422);

try {
    $row = mg_screen_recordings_store_original($pdo, $recordingId, $_FILES['video_file'], $input);
    mg_audit('admin_screen_recording.finalize', 'admin_screen_recording', ['recording_id' => $recordingId], (int)$user['id']);
    mg_ok(['recording' => mg_screen_recordings_public_record($row)], 'Recording saved.');
} catch (Throwable $error) {
    try {
        $stmt = $pdo->prepare("UPDATE admin_screen_recordings SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
        $stmt->execute([substr($error->getMessage(), 0, 255), $recordingId]);
    } catch (Throwable) {}
    mg_security_log('warning', 'admin.screen_recordings.finalize_failed', 'Unable to finalize screen recording.', ['recording_id' => $recordingId, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail($error->getMessage(), 422);
}
