<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/payments/_readiness.php';

$user = mg_require_permission('admin.settings.manage');
$userId = (int)$user['id'];
$pdo = mg_db();

function mg_admin_payment_settings_payload(PDO $pdo, string $mode): array
{
    $payload = mg_payment_readiness($pdo, 'stripe', $mode);
    $config = mg_payment_platform_config($pdo, 'stripe', $mode);
    $payload['provider']['publishable_key'] = (string)$config['publishable_key'];
    $payload['provider']['connect_client_id'] = (string)$config['connect_client_id'];
    return $payload;
}

function mg_admin_payment_readiness_blockers(array $readiness): array
{
    $blockers = [];
    foreach (['publishable_key', 'secret_key', 'webhook_secret'] as $key) {
        $check = $readiness['checks'][$key] ?? null;
        if (is_array($check) && empty($check['ok'])) {
            $blockers[] = (string)($check['label'] ?? $key) . ': ' . (string)($check['detail'] ?? 'Not ready.');
        }
    }
    return $blockers;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    mg_rate_limit('admin.payment_settings.read', 'user:' . $userId, 120, 60);
    $mode = (string)($_GET['mode'] ?? mg_payment_mode()) === 'live' ? 'live' : 'test';
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok(mg_admin_payment_settings_payload($pdo, $mode));
}

mg_require_method('POST');
mg_rate_limit('admin.payment_settings.write', 'user:' . $userId, 30, 300);
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $mode = (string)($input['mode'] ?? 'test') === 'live' ? 'live' : 'test';
    $hasNewSecret = trim((string)($input['secret_key'] ?? '')) !== '' || trim((string)($input['webhook_secret'] ?? '')) !== '';
    if ($hasNewSecret && mg_payment_credential_master_key() === null) {
        mg_fail('Payment credential encryption is not configured on the server. Public Stripe settings can be saved by leaving secret fields blank.', 422);
    }

    $pdo->beginTransaction();
    $existing = mg_payment_platform_credential_row($pdo, 'stripe', $mode, true);
    if (trim((string)($input['publishable_key'] ?? '')) === '' && $existing) {
        $input['publishable_key'] = (string)($existing['publishable_key'] ?? '');
    }

    $input['provider_key'] = 'stripe';
    $saved = mg_payment_save_platform_config($pdo, $input, $userId);
    $pdo->commit();

    $readiness = mg_admin_payment_settings_payload($pdo, (string)$saved['mode']);
    $blockers = mg_admin_payment_readiness_blockers($readiness);
    if ($blockers) {
        $readiness['save_warning'] = 'Settings were saved, but ' . $saved['mode'] . ' mode is still not ready. ' . implode(' ', $blockers);
    }
    mg_audit('admin.payment_settings_updated', 'payment_platform_credentials', [
        'provider' => 'stripe',
        'mode' => $saved['mode'],
        'enabled' => (bool)$saved['enabled'],
        'platform_fee_bps' => (int)$saved['platform_fee_bps'],
        'credential_source' => $saved['credential_source'],
        'ready' => $readiness['ready'],
    ], $userId);
    mg_security_log('info', 'admin.payment_settings.updated', 'Payment settings updated.', [
        'provider' => 'stripe',
        'mode' => $saved['mode'],
        'enabled' => (bool)$saved['enabled'],
        'ready' => $readiness['ready'],
    ], $userId);

    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok($readiness, $blockers ? 'Settings saved, but Stripe is still not ready for this mode.' : 'Stripe payment settings saved.');
} catch (InvalidArgumentException|MgPaymentCredentialException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'admin.payment_settings.failed', 'Payment settings save failed.', [
        'exception_class' => $error::class,
    ], $userId);
    mg_fail('Unable to save payment settings right now.', 500);
}
