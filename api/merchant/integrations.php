<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-integrations.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'POST'
    ? mg_merchant_require_permission('merchant.campaigns.manage')
    : mg_merchant_require_permission('merchant.campaigns.view');
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantUserId = (int)$workspace['merchant_user_id'];

if ($method === 'GET') {
    mg_ok([
        'schema_ready' => mg_integration_schema_ready($pdo),
        'migration' => 'merchant_integrations_foundation_v1.sql',
        'credential_encryption_ready' => mg_integration_credential_master_key() !== null,
        'providers' => mg_integration_provider_catalog(),
        'connections' => mg_integration_connections($pdo, $merchantUserId),
    ]);
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
mg_rate_limit('merchant.integrations.write', 'user:' . $merchantUserId, 30, 300);
$action = strtolower(trim((string)($input['action'] ?? '')));
$providerKey = strtolower(trim((string)($input['provider'] ?? '')));
if ($providerKey === '') mg_fail('Integration provider is required.', 422);

try {
    if ($action === 'begin_oauth') {
        $result = mg_integration_begin_oauth($pdo, $merchantUserId, $providerKey, (string)($input['external_account_hint'] ?? ''));
        mg_audit('merchant.integration.oauth_started', 'merchant_integration', ['provider' => $providerKey], $merchantUserId);
        mg_ok($result, 'Authorization is ready.');
    }
    if ($action === 'disconnect') {
        $result = mg_integration_disconnect($pdo, $merchantUserId, $providerKey);
        mg_audit('merchant.integration.disconnected', 'merchant_integration', ['provider' => $providerKey], $merchantUserId);
        mg_ok(['connection' => $result], 'Integration disconnected.');
    }
    mg_fail('Unsupported integration action.', 422);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (MgIntegrationCredentialException $error) {
    mg_security_log('error', 'merchant.integration.encryption_unavailable', 'Integration credential encryption is unavailable.', ['provider' => $providerKey], $merchantUserId);
    mg_fail($error->getMessage(), 503);
} catch (Throwable $error) {
    mg_integration_mark_error($pdo, $merchantUserId, $providerKey, $error::class, $error->getMessage());
    mg_security_log('warning', 'merchant.integration.action_failed', 'Merchant integration action failed.', ['provider' => $providerKey, 'action' => $action, 'exception_class' => $error::class], $merchantUserId);
    mg_fail($error->getMessage() !== '' ? $error->getMessage() : 'Unable to update the integration.', 500);
}
