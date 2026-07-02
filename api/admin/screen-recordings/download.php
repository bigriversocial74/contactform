<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/admin-screen-recordings.php';

mg_require_method('GET');
$user = mg_screen_recordings_require_api(false);
$pdo = mg_db();
mg_screen_recordings_require_schema($pdo);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.download', 'user:' . (int)$user['id'], 80, 60);

$recordingId = max(0, (int)($_GET['id'] ?? 0));
$type = trim((string)($_GET['type'] ?? 'original'));
$inline = !empty($_GET['stream']);
if ($recordingId < 1) mg_fail('Recording id is required.', 422);
if (!in_array($type, ['original', 'edited'], true)) mg_fail('Invalid recording download type.', 422);

$row = mg_screen_recordings_fetch($pdo, $recordingId);
$pathKey = $type === 'edited' ? 'edited_path' : 'original_path';
$nameKey = $type === 'edited' ? 'edited_filename' : 'original_filename';
$relative = (string)($row[$pathKey] ?? '');
$path = mg_screen_recordings_abs_path($relative);
if (!$path || !is_file($path) || !is_readable($path)) mg_fail('Recording file is unavailable.', 404);

$mime = (string)($row['mime_type'] ?? 'video/webm');
if ($mime === '') $mime = 'video/webm';
$filename = basename((string)($row[$nameKey] ?? ('screen-recording-' . $recordingId . '.webm')));
$disposition = $inline ? 'inline' : 'attachment';

mg_audit('admin_screen_recording.download', 'admin_screen_recording', ['recording_id' => $recordingId, 'type' => $type], (int)$user['id']);
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $filename) . '"');
header('Cache-Control: private, no-store, max-age=0');
readfile($path);
exit;
