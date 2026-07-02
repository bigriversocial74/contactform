<?php
declare(strict_types=1);

if(session_status()!==PHP_SESSION_ACTIVE){session_start();}
require_once __DIR__.'/csrf.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/permissions.php';
require_once __DIR__.'/package-entitlements.php';

if(!function_exists('mg_env')){
function mg_env(string $key,mixed $default=null): mixed{$value=getenv($key);return $value===false||$value===''?$default:$value;}
}
if(!function_exists('mg_app_config')){
function mg_app_config(): array{static $config=null;if(is_array($config))return $config;$path=dirname(__DIR__).'/api/config.php';$loaded=is_file($path)?require $path:[];$config=is_array($loaded)?$loaded:[];return $config;}
}
if(!function_exists('mg_config_value')){
function mg_config_value(string $section,string $key,mixed $default=null): mixed{$config=function_exists('mg_api_config')?mg_api_config():mg_app_config();return $config[$section][$key]??$default;}
}
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