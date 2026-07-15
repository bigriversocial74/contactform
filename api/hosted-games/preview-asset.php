<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/hosted-game-preview.php';

mg_require_method('GET');
$user=mg_require_api_user();$pdo=mg_db();
$sessionId=trim((string)($_GET['session']??''));$relativePath=trim((string)($_GET['path']??''));
try{
    $session=mg_hosted_game_preview_session_by_public_id($pdo,$user,$sessionId);
    $path=mg_hosted_game_preview_asset_path($session,$relativePath);
    $extension=strtolower(pathinfo($path,PATHINFO_EXTENSION));
    $types=[
        'html'=>'text/html; charset=utf-8','htm'=>'text/html; charset=utf-8','css'=>'text/css; charset=utf-8','js'=>'application/javascript; charset=utf-8','mjs'=>'application/javascript; charset=utf-8','json'=>'application/json; charset=utf-8','map'=>'application/json; charset=utf-8','txt'=>'text/plain; charset=utf-8','xml'=>'application/xml; charset=utf-8','csv'=>'text/csv; charset=utf-8',
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml','ico'=>'image/x-icon','avif'=>'image/avif',
        'mp3'=>'audio/mpeg','m4a'=>'audio/mp4','aac'=>'audio/aac','wav'=>'audio/wav','ogg'=>'audio/ogg','oga'=>'audio/ogg','flac'=>'audio/flac',
        'mp4'=>'video/mp4','webm'=>'video/webm','mov'=>'video/quicktime','ogv'=>'video/ogg','woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf','otf'=>'font/otf','eot'=>'application/vnd.ms-fontobject','wasm'=>'application/wasm','pdf'=>'application/pdf','br'=>'application/octet-stream','gz'=>'application/gzip'
    ];
    header('Content-Type: '.($types[$extension]??'application/octet-stream'));
    header('Content-Length: '.(string)filesize($path));header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');
    readfile($path);exit;
}catch(InvalidArgumentException|MgHostedGameException){http_response_code(404);header('Cache-Control: no-store');exit('Preview asset not found.');}
