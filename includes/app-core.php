<?php
declare(strict_types=1);

if (!function_exists('mg_detect_https_for_session')) {
function mg_detect_https_for_session(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') return true;
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') return true;
    return false;
}
}

if (!function_exists('mg_harden_session_start')) {
function mg_harden_session_start(): void
{
    $secure = mg_detect_https_for_session();
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_secure', $secure ? '1' : '0');
    @ini_set('session.cookie_samesite', 'Lax');

    $params = session_get_cookie_params();
    $cookieParams = [
        'lifetime' => (int)($params['lifetime'] ?? 0),
        'path' => (string)($params['path'] ?? '/'),
        'domain' => (string)($params['domain'] ?? ''),
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(
            $cookieParams['lifetime'],
            $cookieParams['path'] . '; samesite=Lax',
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }

    session_start();

    if (session_id() !== '' && !headers_sent()) {
        if (PHP_VERSION_ID >= 70300) {
            setcookie(session_name(), session_id(), $cookieParams);
        } else {
            setcookie(
                session_name(),
                session_id(),
                $cookieParams['lifetime'] > 0 ? time() + $cookieParams['lifetime'] : 0,
                $cookieParams['path'] . '; samesite=Lax',
                $cookieParams['domain'],
                $cookieParams['secure'],
                $cookieParams['httponly']
            );
        }
    }
}
}

mg_harden_session_start();
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