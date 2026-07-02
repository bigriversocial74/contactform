<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-screen-recording-stage3.php';

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    exit;
}
$pdo = mg_db();
if (!mg_screen_recording_stage3_schema_ready($pdo)['ready']) {
    http_response_code(503);
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM public_tutorials WHERE slug = ? AND status IN ('published','unlisted') AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$slug]);
$tutorial = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tutorial || empty($tutorial['video_path'])) {
    http_response_code(404);
    exit;
}
$path = mg_screen_recordings_abs_path((string)$tutorial['video_path']);
if (!$path || !is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}
$size = filesize($path);
if ($size === false || $size < 1) {
    http_response_code(404);
    exit;
}
$mime = str_ends_with((string)$tutorial['video_path'], '.mp4') ? 'video/mp4' : 'video/webm';
$filename = str_replace(['"', "\r", "\n"], '', basename((string)$tutorial['video_path']));
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: public, max-age=3600');
header('Accept-Ranges: bytes');
$start = 0;
$end = $size - 1;
$status = 200;
$range = (string)($_SERVER['HTTP_RANGE'] ?? '');
if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches) === 1) {
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
    flush();
}
fclose($handle);
exit;
