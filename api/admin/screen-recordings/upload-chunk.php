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
mg_screen_recordings_fetch($pdo, $recordingId);

try {
    mg_screen_recordings_prepare_storage();
    $file = $_FILES['video_chunk'];
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) mg_fail('Unable to upload recording chunk.', 422);
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) mg_fail('Recording chunk upload is invalid.', 422);
    $dir = mg_screen_recordings_base_dir() . '/temp/' . $recordingId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) mg_fail('Unable to prepare chunk storage.', 500);
    $target = $dir . '/chunk-' . str_pad((string)$chunkIndex, 6, '0', STR_PAD_LEFT) . '.webm';
    if (!move_uploaded_file($tmp, $target)) mg_fail('Unable to store recording chunk.', 500);
    @chmod($target, 0640);
    $pdo->prepare("UPDATE admin_screen_recordings SET status = 'processing', updated_at = NOW() WHERE id = ? LIMIT 1")->execute([$recordingId]);
    mg_ok(['recording_id' => $recordingId, 'chunk_index' => $chunkIndex], 'Recording chunk uploaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'admin.screen_recordings.chunk_failed', 'Unable to upload screen recording chunk.', ['recording_id' => $recordingId, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail($error->getMessage(), 422);
}
