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
$format = in_array((string)($input['format'] ?? 'webm'), ['webm', 'mp4'], true) ? (string)$input['format'] : 'webm';
$manifest['export'] = [
    'format' => $format,
    'burn_overlays' => !empty($input['burn_overlays']),
    'requested_at' => gmdate('c'),
    'renderer' => 'ffmpeg_required',
    'status' => 'queued_metadata_only',
];
$json = json_encode(array_replace_recursive(mg_screen_recordings_manifest_default(), $manifest), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE admin_screen_recordings SET edit_manifest_json = ?, status = 'export_pending', updated_at = NOW() WHERE id = ? LIMIT 1")->execute([$json, $recordingId]);
    $version = $pdo->prepare("INSERT INTO admin_screen_recording_versions (recording_id, admin_user_id, version_label, edit_manifest_json, status, created_at) VALUES (?, ?, ?, ?, 'export_pending', NOW())");
    $version->execute([$recordingId, (int)$user['id'], strtoupper($format) . ' export request', $json]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('warning', 'admin.screen_recordings.export_edit_failed', 'Unable to queue screen recording export.', ['recording_id' => $recordingId, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail('Unable to queue export request. Check diagnostics or server logs.', 422);
}

mg_audit('admin_screen_recording.export_request', 'admin_screen_recording', ['recording_id' => $recordingId, 'format' => $format], (int)$user['id']);
$row = mg_screen_recordings_fetch_for_user($pdo, $recordingId, $user, true);
mg_ok([
    'recording' => mg_screen_recordings_public_record($row),
    'export_rendering' => 'deferred_ffmpeg_required',
    'message_detail' => 'Edit manifest was saved and export was queued as metadata. Server-side FFmpeg rendering is required to burn trims and text overlays into a new video file.',
], 'Export request saved.');
