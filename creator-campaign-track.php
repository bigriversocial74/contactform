<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/creator-campaigns.php';

$code=strtolower(trim((string)($_GET['c']??'')));
try{
    $pdo=mg_db();
    mg_creator_campaign_tracking_assert_schema($pdo);
    $source=mg_creator_campaign_tracking_source_by_code($pdo,$code);
    $secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
    $session=(string)($_COOKIE['mg_cc_session']??'');
    $visitor=(string)($_COOKIE['mg_cc_visitor']??'');
    if($session===''){$session=bin2hex(random_bytes(16));setcookie('mg_cc_session',$session,['expires'=>time()+1800,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);}
    if($visitor===''){$visitor=bin2hex(random_bytes(16));setcookie('mg_cc_visitor',$visitor,['expires'=>time()+31536000,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);}
    $ref=(string)($_SERVER['HTTP_REFERER']??'');
    $referrerHost=$ref!==''?(string)(parse_url($ref,PHP_URL_HOST)??''):'';
    $fingerprint=(string)($_SERVER['REMOTE_ADDR']??'').'|'.(string)($_SERVER['HTTP_USER_AGENT']??'').'|'.$code.'|'.(string)floor(time()/10);
    try{
        mg_creator_campaign_tracking_record_by_code($pdo,$code,[
            'event_type'=>'click',
            'event_key'=>'click.'.substr(hash('sha256',$code.'|'.$session.'|'.microtime(true).'|'.random_int(1,PHP_INT_MAX)),0,48),
            'session_key'=>$session,
            'visitor_key'=>$visitor,
            'request_key'=>$fingerprint,
            'target_path'=>(string)$source['destination_path'],
            'referrer_host'=>$referrerHost,
            'metadata'=>['redirect'=>true],
        ]);
    }catch(Throwable $trackingError){
        error_log('Creator campaign click recording failed: '.$trackingError->getMessage());
    }
    header('Cache-Control: no-store');
    header('Location: '.mg_creator_campaign_tracking_internal_path($source['destination_path']),true,302);
    exit;
}catch(Throwable){
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Creator campaign link not found.';
}
