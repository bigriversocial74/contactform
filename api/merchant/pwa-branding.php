<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-pwa.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_merchant_require_permission($method === 'GET' ? 'merchant.pwa.view' : 'merchant.pwa.manage');
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$userId = (int)$user['id'];

try {
    if ($method === 'GET') {
        mg_rate_limit('merchant.pwa.read', 'user:' . $userId, 120, 60);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok(mg_merchant_pwa_payload_for_workspace($pdo, $workspace, $userId), 'Merchant PWA branding loaded.');
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);

    mg_rate_limit('merchant.pwa.write', 'user:' . $userId, 30, 300);
    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');

    if (stripos($contentType, 'multipart/form-data') !== false) {
        mg_require_csrf_for_write($_POST);
        if (strtolower(trim((string)($_POST['action'] ?? 'upload'))) !== 'upload') mg_fail('Invalid merchant PWA action.', 422);
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) mg_fail('No merchant PWA image was provided.', 422);
        $role = strtolower(trim((string)($_POST['role'] ?? '')));
        $payload = mg_merchant_pwa_upload($pdo, $workspace, $_FILES['file'], $role, $userId);
        header('Cache-Control: private, no-store, max-age=0');
        mg_ok($payload, 'Merchant PWA image uploaded.', 201);
    }

    $input = mg_input();
    mg_require_csrf_for_write($input);
    $action = strtolower(trim((string)($input['action'] ?? 'save_profile')));
    if ($action !== 'save_profile') mg_fail('Invalid merchant PWA action.', 422);
    $settings = is_array($input['profile'] ?? null) ? $input['profile'] : $input;
    $payload = mg_merchant_pwa_save_profile($pdo, $workspace, $settings, $userId);
    header('Cache-Control: private, no-store, max-age=0');
    mg_ok($payload, 'Merchant PWA branding saved.');
} catch (Throwable $e) {
    if (function_exists('mg_security_log')) {
        mg_security_log('error', 'merchant.pwa.request_failed', 'Merchant PWA branding request failed.', ['exception_class' => $e::class], $userId);
    }
    mg_fail('Unable to update merchant PWA branding.', 500);
}
