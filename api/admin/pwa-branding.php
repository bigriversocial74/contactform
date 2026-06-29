<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/pwa-branding.php';
require_once dirname(__DIR__, 2) . '/includes/pwa-push.php';

function mg_pwa_admin_vapid_status(): array
{
    $config = function_exists('mg_pwa_push_config') ? mg_pwa_push_config() : [];
    $public = trim((string)($config['public_key'] ?? ''));
    $private = trim((string)($config['private_key'] ?? ''));
    $subject = trim((string)($config['subject'] ?? 'mailto:admin@microgifter.com'));
    $providerAvailable = (bool)($config['provider_available'] ?? false);
    $generatorAvailable = class_exists('\\Minishlink\\WebPush\\VAPID') && method_exists('\\Minishlink\\WebPush\\VAPID', 'createVapidKeys');

    return [
        'enabled' => (bool)($config['enabled'] ?? true),
        'public_key_configured' => $public !== '',
        'private_key_configured' => $private !== '',
        'subject_configured' => $subject !== '',
        'subject' => $subject !== '' ? $subject : 'mailto:admin@microgifter.com',
        'public_key_preview' => $public !== '' ? substr($public, 0, 12) . '…' . substr($public, -8) : '',
        'provider_available' => $providerAvailable,
        'generator_available' => $generatorAvailable,
        'env_names' => [
            'MG_ENABLE_PWA_PUSH',
            'MG_PWA_VAPID_PUBLIC_KEY',
            'MG_PWA_VAPID_PRIVATE_KEY',
            'MG_PWA_VAPID_SUBJECT',
        ],
    ];
}

function mg_pwa_admin_generate_vapid_keys(): array
{
    if (!class_exists('\\Minishlink\\WebPush\\VAPID') || !method_exists('\\Minishlink\\WebPush\\VAPID', 'createVapidKeys')) {
        mg_fail('The WebPush VAPID generator is not available. Install or load the Minishlink WebPush library, then try again.', 500);
    }

    $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
    $public = trim((string)($keys['publicKey'] ?? ''));
    $private = trim((string)($keys['privateKey'] ?? ''));
    if ($public === '' || $private === '') {
        mg_fail('Unable to generate a complete VAPID key pair.', 500);
    }

    $subject = trim((string)(function_exists('mg_config_value') ? mg_config_value('pwa_push', 'vapid_subject', '') : getenv('MG_PWA_VAPID_SUBJECT')));
    if ($subject === '') $subject = 'mailto:admin@microgifter.com';

    return [
        'public_key' => $public,
        'private_key' => $private,
        'subject' => $subject,
        'env_block' => "MG_ENABLE_PWA_PUSH=true\nMG_PWA_VAPID_PUBLIC_KEY={$public}\nMG_PWA_VAPID_PRIVATE_KEY={$private}\nMG_PWA_VAPID_SUBJECT={$subject}",
        'warning' => 'Copy this now into server environment config. The private key is not stored and will not be shown again.',
    ];
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
$userId = (int)$user['id'];
$pdo = mg_db();
$canView = (function_exists('mg_api_user_has_permission') && (mg_api_user_has_permission($user,'admin.pwa_branding.view') || mg_api_user_has_permission($user,'admin.pwa_branding.manage') || mg_api_user_has_permission($user,'admin.settings.manage'))) || in_array('super_admin',(array)($user['roles'] ?? []),true);
$canManage = (function_exists('mg_api_user_has_permission') && (mg_api_user_has_permission($user,'admin.pwa_branding.manage') || mg_api_user_has_permission($user,'admin.settings.manage'))) || in_array('super_admin',(array)($user['roles'] ?? []),true);
if (!$canView && !$canManage) mg_fail('You do not have access to PWA branding settings.',403);

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.pwa_branding.read','user:' . $userId,120,60);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok(mg_pwa_branding_payload($pdo) + ['can_manage'=>$canManage, 'vapid'=>mg_pwa_admin_vapid_status()], 'PWA branding loaded.');
    }
    if ($method === 'POST') {
        if (!$canManage) mg_fail('You do not have permission to update PWA branding.',403);
        mg_rate_limit('admin.pwa_branding.write','user:' . $userId,30,300);
        $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
        if (stripos($contentType, 'multipart/form-data') !== false) {
            mg_require_csrf_for_write($_POST);
            if (strtolower(trim((string)($_POST['action'] ?? 'upload'))) !== 'upload') mg_fail('Invalid PWA branding action.',422);
            if (!isset($_FILES['file']) || !is_array($_FILES['file'])) mg_fail('No PWA image was provided.',422);
            $payload = mg_pwa_branding_upload($pdo, $_FILES['file'], strtolower(trim((string)($_POST['role'] ?? ''))), $userId);
            header('Cache-Control: private, no-store, max-age=0');
            mg_ok($payload + ['can_manage'=>true, 'vapid'=>mg_pwa_admin_vapid_status()], 'PWA branding image uploaded.', 201);
        }
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $action = strtolower(trim((string)($input['action'] ?? 'save_settings')));
        if ($action === 'generate_vapid_keys') {
            header('Cache-Control: private, no-store, max-age=0');
            mg_ok(mg_pwa_branding_payload($pdo) + ['can_manage'=>true, 'vapid'=>mg_pwa_admin_vapid_status(), 'generated_vapid_keys'=>mg_pwa_admin_generate_vapid_keys()], 'VAPID key pair generated.');
        }
        if ($action !== 'save_settings') mg_fail('Invalid PWA branding action.',422);
        $settings = is_array($input['settings'] ?? null) ? $input['settings'] : $input;
        $payload = mg_pwa_branding_save_settings($pdo, $settings, $userId);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok($payload + ['can_manage'=>true, 'vapid'=>mg_pwa_admin_vapid_status()], 'PWA branding settings saved.');
    }
    mg_fail('Method not allowed.',405);
} catch (Throwable $e) {
    mg_security_log('error','admin.pwa_branding.request_failed','PWA branding admin request failed.',['exception_class'=>$e::class],$userId);
    mg_fail('Unable to update PWA branding.',500);
}
