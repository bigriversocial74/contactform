<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/admin-screen-recordings.php';

mg_require_method('POST');
$user = mg_screen_recordings_require_api(true);
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();
mg_screen_recordings_require_schema($pdo);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.save_edit', 'user:' . (int)$user['id'], 60, 60);

$recordingId = max(0, (int)($input['recording_id'] ?? $input['id'] ?? 0));
if ($recordingId < 1) mg_fail('Recording id is required.', 422);
$manifest = $input['edit_manifest'] ?? $input['manifest'] ?? [];
if (is_string($manifest)) {
    $decoded = json_decode($manifest, true);
    $manifest = is_array($decoded) ? $decoded : [];
}
if (!is_array($manifest)) mg_fail('Edit manifest must be an object.', 422);

try {
    $row = mg_screen_recordings_save_manifest($pdo, $recordingId, $manifest);
    mg_audit('admin_screen_recording.save_edit', 'admin_screen_recording', ['recording_id' => $recordingId, 'overlays' => count($manifest['text_overlays'] ?? [])], (int)$user['id']);
    mg_ok(['recording' => mg_screen_recordings_public_record($row)], 'Edit draft saved.');
} catch (Throwable $error) {
    mg_security_log('warning', 'admin.screen_recordings.save_edit_failed', 'Unable to save screen recording edit manifest.', ['recording_id' => $recordingId, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail($error->getMessage(), 422);
}
