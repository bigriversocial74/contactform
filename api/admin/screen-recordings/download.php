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

$row = mg_screen_recordings_fetch_for_user($pdo, $recordingId, $user, false);
$pathKey = $type === 'edited' ? 'edited_path' : 'original_path';
$nameKey = $type === 'edited' ? 'edited_filename' : 'original_filename';
$relative = (string)($row[$pathKey] ?? '');
$path = mg_screen_recordings_abs_path($relative);
if (!$path || !is_file($path) || !is_readable($path)) mg_fail('Recording file is unavailable.', 404);

$mime = (string)($row['mime_type'] ?? 'video/webm');
if ($mime === '') $mime = 'video/webm';
$filename = basename((string)($row[$nameKey] ?? ('screen-recording-' . $recordingId . '.webm')));
$disposition = $inline ? 'inline' : 'attachment';
$size = filesize($path);
if ($size === false || $size < 1) mg_fail('Recording file is unavailable.', 404);

mg_audit('admin_screen_recording.download', 'admin_screen_recording', ['recording_id' => $recordingId, 'type' => $type, 'stream' => $inline], (int)$user['id']);
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $filename) . '"');
header('Cache-Control: private, no-store, max-age=0');
header('Accept-Ranges: bytes');

$start = 0;
$end = $size - 1;
$status = 200;
$range = (string)($_SERVER['HTTP_RANGE'] ?? '');
if ($inline && preg_match('/bytes=(\d*)-(\d*)/', $range, $matches) === 1) {
    if ($matches[1] !== '') $start = max(0, (int)$matches[1]);
    if ($matches[2] !== '') $end = min($size - 1, (int)$matches[2]);
    if ($matches[1] === '' && $matches[2] !== '') {
        $suffix = min($size, max(0, (int)$matches[2]));
        $start = $size - $suffix;
        $end = $size - 1;
    }
    if ($start > $end || $start >= $size) {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header('Content-Range: bytes */' . $size);
        exit;
    }
    $status = 206;
}

$length = $end - $start + 1;
if ($status === 206) {
    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
header('Content-Length: ' . (string)$length);

$handle = fopen($path, 'rb');
if (!$handle) exit;
fseek($handle, $start);
$remaining = $length;
while ($remaining > 0 && !feof($handle)) {
    $chunk = fread($handle, min(8192, $remaining));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    $remaining -= strlen($chunk);
    if (function_exists('fastcgi_finish_request')) {
        flush();
    }
}
fclose($handle);
exit;
