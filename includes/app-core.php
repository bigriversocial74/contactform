<?php
declare(strict_types=1);

if(!function_exists('mg_env')){
function mg_env(string $key,mixed $default=null): mixed{$value=getenv($key);return $value===false||$value===''?$default:$value;}
}
if(!function_exists('mg_app_config')){
function mg_app_config(): array{static $config=null;if(is_array($config))return $config;$path=dirname(__DIR__).'/api/config.php';$loaded=is_file($path)?require $path:[];$config=is_array($loaded)?$loaded:[];return $config;}
}
if(!function_exists('mg_config_value')){
function mg_config_value(string $section,string $key,mixed $default=null): mixed{$config=function_exists('mg_api_config')?mg_api_config():mg_app_config();return $config[$section][$key]??$default;}
}
if(!function_exists('mg_is_https_request')){
function mg_is_https_request(): bool{return (!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||((bool)mg_config_value('app','trust_proxy',false)&&strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))==='https');}
}
if(!function_exists('mg_configure_session_cookie')){
function mg_configure_session_cookie(): void{
    if(headers_sent())return;
    $sessionName=trim((string)mg_config_value('security','session_name','mg_session'));
    if($sessionName!=='')session_name($sessionName);
    $days=max(1,(int)mg_config_value('security','session_days',30));
    $lifetime=$days*86400;
    ini_set('session.gc_maxlifetime',(string)$lifetime);
    ini_set('session.cookie_lifetime',(string)$lifetime);
    ini_set('session.cookie_httponly','1');
    ini_set('session.cookie_samesite','Lax');
    if(mg_is_https_request())ini_set('session.cookie_secure','1');
    session_set_cookie_params([
        'lifetime'=>$lifetime,
        'path'=>'/',
        'secure'=>mg_is_https_request(),
        'httponly'=>true,
        'samesite'=>'Lax',
    ]);
}
}
if(!function_exists('mg_expire_session_cookie')){
function mg_expire_session_cookie(): void{
    if(headers_sent()||!ini_get('session.use_cookies'))return;
    $params=session_get_cookie_params();
    $options=[
        'expires'=>time()-42000,
        'path'=>(string)($params['path']?:'/'),
        'secure'=>(bool)($params['secure']??false),
        'httponly'=>(bool)($params['httponly']??true),
        'samesite'=>(string)($params['samesite']?:'Lax'),
    ];
    if(!empty($params['domain']))$options['domain']=(string)$params['domain'];
    setcookie(session_name(),'', $options);
}
}

if(session_status()!==PHP_SESSION_ACTIVE){mg_configure_session_cookie();session_start();}
require_once __DIR__.'/csrf.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/permissions.php';
require_once __DIR__.'/package-entitlements.php';

if(!function_exists('mg_public_uuid')){
function mg_public_uuid(): string{$bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&0x0f)|0x40);$bytes[8]=chr((ord($bytes[8])&0x3f)|0x80);$hex=bin2hex($bytes);return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20,12);}
}

require_once __DIR__.'/runtime.php';
require_once __DIR__.'/storage.php';
require_once __DIR__.'/mail.php';

if(!function_exists('mg_e')){
function mg_e(?string $value): string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
}
if(!function_exists('mg_asset')){
function mg_asset(string $path): string{return '/'.ltrim($path,'/');}
}
if(!function_exists('mg_page_context')){
function mg_page_context(string $section='core'): array{return ['section'=>$section,'user'=>mg_current_user(),'csrf'=>mg_csrf_token(),'runtime'=>mg_runtime_public_payload()];}
}
