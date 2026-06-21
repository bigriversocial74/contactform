<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/payments/_readiness.php';

$user = mg_require_permission('admin.settings.manage');
$pdo = mg_db();

function mg_admin_cash_status(PDO $pdo, string $mode): array
{
    $row = mg_payment_platform_credential_row($pdo, 'cash', $mode, false);
    return [
        'provider_key' => 'cash',
        'mode' => $mode,
        'enabled' => (bool)($row['enabled'] ?? false),
        'label' => 'Pay with cash',
        'description' => 'Manual local testing payment option.',
    ];
}

function mg_admin_payment_settings_payload(PDO $pdo, string $mode): array
{
    try {
        $payload = mg_payment_readiness($pdo, 'stripe', $mode);
        $config = mg_payment_platform_config($pdo, 'stripe', $mode);
        $payload['provider']['publishable_key'] = (string)$config['publishable_key'];
        $payload['provider']['connect_client_id'] = (string)$config['connect_client_id'];
    } catch (MgPaymentCredentialException $error) {
        $row = mg_payment_platform_credential_row($pdo, 'stripe', $mode, false) ?: [];
        $provider = [
            'provider_key' => 'stripe',
            'mode' => $mode,
            'enabled' => (bool)($row['enabled'] ?? false),
            'credential_source' => $row ? 'database' : 'missing',
            'publishable_key' => (string)($row['publishable_key'] ?? ''),
            'connect_client_id' => (string)($row['connect_client_id'] ?? ''),
            'publishable_configured' => trim((string)($row['publishable_key'] ?? '')) !== '',
            'secret_configured' => trim((string)($row['secret_key_ciphertext'] ?? '')) !== '',
            'webhook_configured' => trim((string)($row['webhook_secret_ciphertext'] ?? '')) !== '',
            'platform_fee_bps' => (int)($row['platform_fee_bps'] ?? 1500),
            'fixed_fee_cents' => (int)($row['fixed_fee_cents'] ?? 0),
            'database_encryption_ready' => mg_payment_credential_master_key() !== null,
        ];
        $payload = [
            'provider' => $provider,
            'checks' => [
                'credential_encryption' => ['ok' => false, 'label' => 'Credential encryption', 'detail' => $error->getMessage()],
            ],
            'ready' => false,
            'launch_ready' => false,
            'connected_accounts' => ['total' => 0, 'ready' => 0],
            'webhook_url' => '/api/payments/webhook.php?provider=stripe',
        ];
    }
    $payload['cash_payments'] = mg_admin_cash_status($pdo, $mode);
    return $payload;
}

function mg_admin_save_stripe_row(PDO $pdo, array $input, int $userId): array
{
    $mode = (string)($input['mode'] ?? 'test') === 'live' ? 'live' : 'test';
    $row = mg_payment_platform_credential_row($pdo, 'stripe', $mode, true);
    $publishable = trim((string)($input['publishable_key'] ?? ''));
    $secret = trim((string)($input['secret_key'] ?? ''));
    $webhook = trim((string)($input['webhook_secret'] ?? ''));
    $clientId = trim((string)($input['connect_client_id'] ?? ''));
    if ($row) {
        if ($publishable === '') $publishable = (string)($row['publishable_key'] ?? '');
        if ($clientId === '') $clientId = (string)($row['connect_client_id'] ?? '');
    }
    $secretCipher = $secret !== '' ? mg_payment_encrypt_secret($secret) : (string)($row['secret_key_ciphertext'] ?? '');
    $webhookCipher = $webhook !== '' ? mg_payment_encrypt_secret($webhook) : (string)($row['webhook_secret_ciphertext'] ?? '');
    $feeBps = max(0, min(10000, (int)($input['platform_fee_bps'] ?? 1500)));
    $fixedFee = max(0, (int)($input['fixed_fee_cents'] ?? 0));
    $enabled = !empty($input['enabled']) ? 1 : 0;
    if ($row) {
        $pdo->prepare('UPDATE payment_platform_credentials SET publishable_key=?,secret_key_ciphertext=?,webhook_secret_ciphertext=?,connect_client_id=?,platform_fee_bps=?,fixed_fee_cents=?,enabled=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')
            ->execute([$publishable ?: null, $secretCipher ?: null, $webhookCipher ?: null, $clientId ?: null, $feeBps, $fixedFee, $enabled, $userId, (int)$row['id']]);
    } else {
        $pdo->prepare('INSERT INTO payment_platform_credentials (public_id,provider_key,mode,publishable_key,secret_key_ciphertext,webhook_secret_ciphertext,connect_client_id,platform_fee_bps,fixed_fee_cents,enabled,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
            ->execute([mg_public_uuid(), 'stripe', $mode, $publishable ?: null, $secretCipher ?: null, $webhookCipher ?: null, $clientId ?: null, $feeBps, $fixedFee, $enabled, $userId]);
    }
    return ['mode' => $mode, 'enabled' => (bool)$enabled, 'platform_fee_bps' => $feeBps, 'credential_source' => 'database'];
}

function mg_admin_save_cash_row(PDO $pdo, string $mode, bool $enabled, int $userId): void
{
    $row = mg_payment_platform_credential_row($pdo, 'cash', $mode, true);
    if ($row) {
        $pdo->prepare('UPDATE payment_platform_credentials SET enabled=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$enabled ? 1 : 0, $userId, (int)$row['id']]);
        return;
    }
    $pdo->prepare('INSERT INTO payment_platform_credentials (public_id,provider_key,mode,platform_fee_bps,fixed_fee_cents,enabled,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())')
        ->execute([mg_public_uuid(), 'cash', $mode, 0, 0, $enabled ? 1 : 0, $userId]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $mode = (string)($_GET['mode'] ?? mg_payment_mode()) === 'live' ? 'live' : 'test';
    mg_ok(mg_admin_payment_settings_payload($pdo, $mode));
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
try {
    $mode = (string)($input['mode'] ?? 'test') === 'live' ? 'live' : 'test';
    $hasNewSecret = trim((string)($input['secret_key'] ?? '')) !== '' || trim((string)($input['webhook_secret'] ?? '')) !== '';
    if ($hasNewSecret && mg_payment_credential_master_key() === null) {
        mg_fail('Payment credential encryption is not configured on the server. Public Stripe settings can be saved by leaving secret fields blank.', 422);
    }
    $pdo->beginTransaction();
    $saved = mg_admin_save_stripe_row($pdo, $input, (int)$user['id']);
    mg_admin_save_cash_row($pdo, $mode, !empty($input['cash_enabled']), (int)$user['id']);
    $pdo->commit();
    $readiness = mg_admin_payment_settings_payload($pdo, $mode);
    mg_audit('admin.payment_settings_updated', 'payment_platform_credentials', ['provider' => 'stripe', 'mode' => $saved['mode'], 'enabled' => (bool)$saved['enabled'], 'cash_enabled' => !empty($input['cash_enabled']), 'platform_fee_bps' => (int)$saved['platform_fee_bps'], 'credential_source' => $saved['credential_source'], 'ready' => $readiness['ready']], (int)$user['id']);
    mg_ok($readiness, 'Payment settings saved.');
} catch (InvalidArgumentException|MgPaymentCredentialException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to save payment settings: ' . $error->getMessage(), 500);
}