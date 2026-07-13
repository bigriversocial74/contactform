<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/package-entitlements.php';
require_once __DIR__ . '/includes/merchant-integrations.php';

$user = mg_current_user();
if (!$user) {
    $return = rawurlencode('/merchant-integrations.php?oauth=signin_required');
    header('Location: /signin.php?return=' . $return, true, 302);
    exit;
}

$pdo = mg_db();
$merchantUserId = (int)$user['id'];
if (function_exists('mg_user_has_merchant_access') && !mg_user_has_merchant_access($user, $pdo)) {
    header('Location: /merchant-integrations.php?oauth=merchant_access_required', true, 302);
    exit;
}

$error = trim((string)($_GET['error'] ?? ''));
$state = trim((string)($_GET['state'] ?? ''));
$code = trim((string)($_GET['code'] ?? ''));

if ($error !== '') {
    mg_integration_mark_error($pdo, $merchantUserId, 'squarespace', 'oauth_' . $error, 'Squarespace authorization was not completed.');
    header('Location: /merchant-integrations.php?oauth=denied&provider=squarespace', true, 302);
    exit;
}

try {
    if ($state === '' || $code === '') throw new RuntimeException('Squarespace did not return a valid authorization response.');
    $connection = mg_integration_complete_oauth($pdo, $merchantUserId, 'squarespace', $state, $code);
    mg_audit('merchant.integration.connected', 'merchant_integration', ['provider' => 'squarespace', 'connection_id' => $connection['id'] ?? null], $merchantUserId);
    mg_security_log('notice', 'merchant.integration.connected', 'Squarespace integration connected.', ['provider' => 'squarespace'], $merchantUserId);
    header('Location: /merchant-integrations.php?oauth=connected&provider=squarespace', true, 302);
    exit;
} catch (Throwable $failure) {
    mg_integration_mark_error($pdo, $merchantUserId, 'squarespace', $failure::class, $failure->getMessage());
    mg_security_log('warning', 'merchant.integration.oauth_callback_failed', 'Squarespace OAuth callback failed.', ['exception_class' => $failure::class], $merchantUserId);
    header('Location: /merchant-integrations.php?oauth=failed&provider=squarespace', true, 302);
    exit;
}
