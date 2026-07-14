<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-games.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$path = str_replace('\\','/',trim((string)($_GET['path'] ?? '')));
$path = ltrim(preg_replace('#/+#','/',$path) ?? '', '/');
if ($slug === '' || $path === '' || str_contains($path,"\0")) mg_fail('Hosted game asset not found.',404);
$parts = [];
foreach (explode('/',$path) as $part) {
    if ($part === '' || $part === '.') continue;
    if ($part === '..' || str_starts_with($part,'.')) mg_fail('Hosted game asset not found.',404);
    $parts[] = $part;
}
$path = implode('/',$parts);
if ($path === '' || strlen($path) > 700) mg_fail('Hosted game asset not found.',404);

$pdo = mg_db();
$game = mg_hosted_game_by_slug($pdo,$slug,false);
if (!$game || empty($game['storage_key'])) mg_fail('Hosted game asset not found.',404);
try {
    $releaseRoot = mg_hosted_game_storage_path((string)$game['storage_key']);
} catch (Throwable) {
    mg_fail('Hosted game asset not found.',404);
}
$candidate = $releaseRoot . DIRECTORY_SEPARATOR . str_replace('/',DIRECTORY_SEPARATOR,$path);
$real = realpath($candidate);
if ($real === false || !is_file($real) || !str_starts_with($real,$releaseRoot . DIRECTORY_SEPARATOR)) mg_fail('Hosted game asset not found.',404);

function mg_hosted_game_asset_type(string $path): array
{
    $encoding = null;
    $inner = $path;
    $extension = strtolower(pathinfo($path,PATHINFO_EXTENSION));
    if ($extension === 'br' || $extension === 'gz') {
        $encoding = $extension === 'br' ? 'br' : 'gzip';
        $inner = substr($path,0,-strlen($extension)-1);
        $extension = strtolower(pathinfo($inner,PATHINFO_EXTENSION));
    }
    $types = [
        'html'=>'text/html; charset=utf-8','htm'=>'text/html; charset=utf-8','css'=>'text/css; charset=utf-8',
        'js'=>'application/javascript; charset=utf-8','mjs'=>'application/javascript; charset=utf-8','json'=>'application/json; charset=utf-8',
        'map'=>'application/json; charset=utf-8','txt'=>'text/plain; charset=utf-8','xml'=>'application/xml; charset=utf-8','csv'=>'text/csv; charset=utf-8',
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml','ico'=>'image/x-icon','avif'=>'image/avif',
        'mp3'=>'audio/mpeg','m4a'=>'audio/mp4','aac'=>'audio/aac','wav'=>'audio/wav','ogg'=>'audio/ogg','oga'=>'audio/ogg','flac'=>'audio/flac',
        'mp4'=>'video/mp4','webm'=>'video/webm','mov'=>'video/quicktime','ogv'=>'video/ogg',
        'woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf','otf'=>'font/otf','eot'=>'application/vnd.ms-fontobject',
        'wasm'=>'application/wasm','data'=>'application/octet-stream','mem'=>'application/octet-stream','bin'=>'application/octet-stream','dat'=>'application/octet-stream',
        'unityweb'=>'application/octet-stream','bundle'=>'application/octet-stream','glb'=>'model/gltf-binary','gltf'=>'model/gltf+json',
        'obj'=>'text/plain; charset=utf-8','mtl'=>'text/plain; charset=utf-8','fbx'=>'application/octet-stream','dae'=>'model/vnd.collada+xml','3ds'=>'application/octet-stream',
        'vtt'=>'text/vtt; charset=utf-8','srt'=>'text/plain; charset=utf-8','lrc'=>'text/plain; charset=utf-8','pdf'=>'application/pdf',
    ];
    return [$types[$extension] ?? 'application/octet-stream',$encoding];
}

$size = filesize($real);
if ($size === false) mg_fail('Hosted game asset not found.',404);
$mtime = filemtime($real) ?: time();
$etag = '"' . hash('sha256',(string)$game['package_checksum'] . '|' . $path . '|' . $size . '|' . $mtime) . '"';
header('Access-Control-Allow-Origin: *');
header('Cross-Origin-Resource-Policy: cross-origin');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');
header('ETag: ' . $etag);
header('Cache-Control: public, max-age=31536000, immutable');
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}
[$contentType,$contentEncoding] = mg_hosted_game_asset_type($path);
header('Content-Type: ' . $contentType);
if ($contentEncoding !== null) header('Content-Encoding: ' . $contentEncoding);

$start = 0;
$end = max(0,(int)$size - 1);
$status = 200;
$range = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/',$range,$matches) === 1) {
    if ($matches[1] === '' && $matches[2] !== '') {
        $suffix = min((int)$matches[2],(int)$size);
        $start = max(0,(int)$size - $suffix);
    } else {
        $start = $matches[1] !== '' ? (int)$matches[1] : 0;
        $end = $matches[2] !== '' ? min((int)$matches[2],(int)$size - 1) : (int)$size - 1;
    }
    if ($start > $end || $start >= (int)$size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    $status = 206;
}
$length = $end - $start + 1;
http_response_code($status);
if ($status === 206) header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
header('Content-Length: ' . $length);
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') exit;
$handle = fopen($real,'rb');
if (!is_resource($handle)) mg_fail('Hosted game asset could not be opened.',500);
if ($start > 0) fseek($handle,$start);
$remaining = $length;
while ($remaining > 0 && !feof($handle)) {
    $chunk = fread($handle,min(1048576,$remaining));
    if ($chunk === false) break;
    echo $chunk;
    $remaining -= strlen($chunk);
    if (function_exists('fastcgi_finish_request') && connection_aborted()) break;
}
fclose($handle);
