<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/pwa-branding.php';

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
        mg_ok(mg_pwa_branding_payload($pdo) + ['can_manage'=>$canManage], 'PWA branding loaded.');
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
            mg_ok($payload + ['can_manage'=>true], 'PWA branding image uploaded.', 201);
        }
        $input = mg_input();
        mg_require_csrf_for_write($input);
        if (strtolower(trim((string)($input['action'] ?? 'save_settings'))) !== 'save_settings') mg_fail('Invalid PWA branding action.',422);
        $settings = is_array($input['settings'] ?? null) ? $input['settings'] : $input;
        $payload = mg_pwa_branding_save_settings($pdo, $settings, $userId);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok($payload + ['can_manage'=>true], 'PWA branding settings saved.');
    }
    mg_fail('Method not allowed.',405);
} catch (Throwable $e) {
    mg_security_log('error','admin.pwa_branding.request_failed','PWA branding admin request failed.',['exception_class'=>$e::class],$userId);
    mg_fail('Unable to update PWA branding.',500);
}
