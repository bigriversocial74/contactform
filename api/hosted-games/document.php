<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-games.php';

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    exit('Hosted game not found.');
}
$pdo = mg_db();
$game = mg_hosted_game_by_slug($pdo,$slug,false);
if (!$game || empty($game['storage_key'])) {
    http_response_code(404);
    exit('Hosted game not found.');
}
try {
    $releaseRoot = mg_hosted_game_storage_path((string)$game['storage_key']);
} catch (Throwable) {
    http_response_code(404);
    exit('Hosted game release not found.');
}
$entry = str_replace('\\','/',trim((string)$game['entry_file']));
$entry = ltrim($entry,'/');
if ($entry === '' || str_contains($entry,'../') || str_contains($entry,"\0")) {
    http_response_code(500);
    exit('Hosted game entry is invalid.');
}
$entryPath = realpath($releaseRoot . DIRECTORY_SEPARATOR . str_replace('/',DIRECTORY_SEPARATOR,$entry));
if ($entryPath === false || !is_file($entryPath) || !str_starts_with($entryPath,$releaseRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('Hosted game entry not found.');
}
$size = filesize($entryPath);
if ($size === false || $size < 1 || $size > 20971520) {
    http_response_code(422);
    exit('Hosted game entry is too large.');
}
$html = file_get_contents($entryPath);
if (!is_string($html)) {
    http_response_code(500);
    exit('Hosted game entry could not be read.');
}
$html = preg_replace('/^\xEF\xBB\xBF/','',$html) ?? $html;
$html = preg_replace('#<base\b[^>]*>#i','',$html) ?? $html;
$config = json_encode([
    'gameId'=>(string)$game['public_id'],
    'slug'=>(string)$game['slug'],
    'name'=>(string)$game['name'],
    'bridgeVersion'=>'1.0.0',
],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
if (!is_string($config)) $config = '{}';
$inject = '<base href="/games/' . rawurlencode((string)$game['slug']) . '/">'
    . '<script>window.MicrogifterHostedGameConfig=' . $config . ';</script>'
    . '<script src="/assets/js/hosted-game-child-bridge.js?v=1.0.0"></script>';
if (preg_match('/<head\b[^>]*>/i',$html) === 1) {
    $html = preg_replace('/<head\b([^>]*)>/i','<head$1>' . $inject,$html,1) ?? ($inject . $html);
} else {
    $html = $inject . $html;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src * data: blob:; script-src * 'unsafe-inline' 'unsafe-eval' blob:; style-src * 'unsafe-inline'; img-src * data: blob:; media-src * data: blob:; font-src * data:; connect-src * data: blob:; worker-src * blob:; child-src * blob:; frame-ancestors 'self'; object-src 'none'; base-uri 'self'");
echo $html;
