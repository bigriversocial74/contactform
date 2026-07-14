<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/package-entitlements.php';
require_once __DIR__ . '/includes/merchant-integrations.php';
require_once __DIR__ . '/includes/merchant-crm.php';
require_once __DIR__ . '/includes/integrations/mailchimp-contacts.php';

$user = mg_current_user();
if (!$user) {
    $return = rawurlencode('/merchant-integrations.php?mailchimp_oauth=signin_required');
    header('Location: /signin.php?return=' . $return, true, 302);
    exit;
}

$pdo = mg_db();
$merchantUserId = (int)$user['id'];
if (function_exists('mg_user_has_merchant_access') && !mg_user_has_merchant_access($user, $pdo)) {
    header('Location: /merchant-integrations.php?mailchimp_oauth=merchant_access_required', true, 302);
    exit;
}

$error = trim((string)($_GET['error'] ?? ''));
if ($error !== '') {
    if ($error !== 'access_denied') {
        mg_integration_mark_error($pdo, $merchantUserId, 'mailchimp', 'oauth_' . $error, trim((string)($_GET['error_description'] ?? 'Mailchimp authorization was not completed.')));
    }
    header('Location: /merchant-integrations.php?mailchimp_oauth=denied', true, 302);
    exit;
}

try {
    $connection = mg_mailchimp_complete_oauth(
        $pdo,
        $merchantUserId,
        trim((string)($_GET['state'] ?? '')),
        trim((string)($_GET['code'] ?? ''))
    );
    mg_audit('merchant.integration.connected', 'merchant_integration', [
        'provider' => 'mailchimp',
        'connection_id' => $connection['id'] ?? null,
        'marketing_status_preserved' => true,
        'addresses_enabled' => false,
        'phone_numbers_enabled' => false,
        'non_name_merge_fields_enabled' => false,
    ], $merchantUserId);
    mg_security_log('notice', 'merchant.integration.connected', 'Mailchimp integration connected.', ['provider' => 'mailchimp'], $merchantUserId);
    header('Location: /merchant-integrations.php?mailchimp_oauth=connected', true, 302);
    exit;
} catch (Throwable $failure) {
    mg_integration_mark_error($pdo, $merchantUserId, 'mailchimp', $failure::class, $failure->getMessage());
    mg_security_log('warning', 'merchant.integration.oauth_callback_failed', 'Mailchimp OAuth callback failed.', ['exception_class' => $failure::class], $merchantUserId);
    header('Location: /merchant-integrations.php?mailchimp_oauth=failed', true, 302);
    exit;
}
