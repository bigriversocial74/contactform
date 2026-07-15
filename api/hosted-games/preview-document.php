<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/hosted-game-preview.php';

mg_require_method('GET');$user=mg_require_api_user();$pdo=mg_db();
$sessionId=trim((string)($_GET['session']??''));$bridgeToken=trim((string)($_GET['bridge']??''));
if(!mg_hosted_game_standard_valid_bridge_token($bridgeToken)){http_response_code(404);exit('Preview not found.');}
try{
    $session=mg_hosted_game_preview_session_by_public_id($pdo,$user,$sessionId);
    $manifest=mg_hosted_game_preview_manifest($session);
    $root=mg_hosted_game_storage_path((string)$session['storage_key']);
    $entry=(string)($session['entry_file']??$manifest['entry']??'index.html');
    $entryPath=realpath($root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$entry));
    if($entryPath===false||!is_file($entryPath)||!str_starts_with($entryPath,$root.DIRECTORY_SEPARATOR))throw new MgHostedGameException('Preview entry not found.');
    $html=file_get_contents($entryPath);if(!is_string($html)||strlen($html)>20971520)throw new MgHostedGameException('Preview entry is unavailable.');
    $config=json_encode(['gameId'=>(string)$session['game_public_id'],'slug'=>(string)$session['game_slug'],'name'=>(string)$session['game_name'],'bridgeVersion'=>'1.1.0','bridgeToken'=>$bridgeToken,'manifest'=>mg_hosted_game_standard_public_manifest($manifest),'preview'=>true,'releaseId'=>(string)$session['release_public_id'],'releaseVersion'=>(int)$session['version_number']],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if(!is_string($config))$config='{}';
    $inject='<base href="/hosted-game-preview-assets/'.rawurlencode($sessionId).'/">'
        .'<script>window.MicrogifterHostedGameConfig='.$config.';</script>'
        .'<script src="/assets/js/hosted-game-child-bridge.js?v=1.2.0"></script>';
    if(preg_match('/<head\b[^>]*>/i',$html)===1)$html=preg_replace('/<head\b([^>]*)>/i','<head$1>'.$inject,$html,1)??($inject.$html);else $html=$inject.$html;
    mg_hosted_game_preview_event($pdo,$session,null,'system','preview_document_loaded',['entry'=>$entry,'release_id'=>(string)$session['release_public_id']]);
    header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('Cross-Origin-Resource-Policy: same-origin');header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), hid=()');header('Content-Security-Policy: '.mg_hosted_game_standard_csp($manifest));echo $html;
}catch(InvalidArgumentException|MgHostedGameException){http_response_code(404);header('Cache-Control: no-store');exit('Preview not found.');}
