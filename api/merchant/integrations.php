<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-integrations.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm.php';
require_once dirname(__DIR__, 2) . '/includes/integrations/squarespace-contacts.php';
require_once dirname(__DIR__, 2) . '/includes/integrations/woocommerce-contacts.php';
require_once dirname(__DIR__, 2) . '/includes/integrations/shopify-contacts.php';

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
        'providers' => mg_shopify_provider_catalog(mg_woocommerce_provider_catalog(mg_integration_provider_catalog())),
        'connections' => mg_integration_connections($pdo, $merchantUserId),
        'squarespace_contacts' => mg_squarespace_contacts_status($pdo, $merchantUserId),
        'woocommerce_contacts' => mg_woocommerce_contacts_status($pdo, $merchantUserId),
        'shopify_contacts' => mg_shopify_contacts_status($pdo, $merchantUserId),
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
        $existing = mg_integration_connection_row($pdo, $merchantUserId, $providerKey, false);
        $wasActive = is_array($existing) && (string)($existing['status'] ?? '') === 'active';
        $result = mg_integration_begin_oauth($pdo, $merchantUserId, $providerKey, (string)($input['external_account_hint'] ?? ''));
        if ($wasActive) {
            $pdo->prepare("UPDATE merchant_integration_connections SET status='active',updated_at=NOW() WHERE merchant_user_id=? AND provider_key=? ORDER BY id DESC LIMIT 1")
                ->execute([$merchantUserId, $providerKey]);
        }
        mg_audit('merchant.integration.oauth_started', 'merchant_integration', ['provider' => $providerKey], $merchantUserId);
        mg_ok($result, 'Authorization is ready.');
    }

    if ($providerKey === 'shopify' && $action === 'begin_shopify_oauth') {
        mg_rate_limit('merchant.integrations.shopify.oauth', 'user:' . $merchantUserId, 8, 300);
        $existing = mg_integration_connection_row($pdo, $merchantUserId, 'shopify', false);
        $wasActive = is_array($existing) && (string)($existing['status'] ?? '') === 'active';
        $result = mg_shopify_begin_oauth($pdo, $merchantUserId, (string)($input['shop_domain'] ?? ''));
        if ($wasActive) {
            $pdo->prepare("UPDATE merchant_integration_connections SET status='active',updated_at=NOW() WHERE merchant_user_id=? AND provider_key='shopify' ORDER BY id DESC LIMIT 1")
                ->execute([$merchantUserId]);
        }
        mg_audit('merchant.integration.oauth_started', 'merchant_integration', ['provider' => 'shopify', 'shop_domain' => $result['shop_domain'] ?? null], $merchantUserId);
        mg_ok($result, 'Shopify authorization is ready.');
    }

    if ($providerKey === 'woocommerce' && $action === 'connect_api_key') {
        mg_rate_limit('merchant.integrations.woocommerce.connect', 'user:' . $merchantUserId, 8, 300);
        $result = mg_woocommerce_connect(
            $pdo,
            $merchantUserId,
            (string)($input['site_url'] ?? ''),
            (string)($input['consumer_key'] ?? ''),
            (string)($input['consumer_secret'] ?? '')
        );
        mg_audit('merchant.integration.api_key_connected', 'merchant_integration', ['provider' => 'woocommerce'], $merchantUserId);
        mg_ok(['connection' => $result], 'WooCommerce connected.');
    }

    if ($action === 'disconnect') {
        $result = mg_integration_disconnect($pdo, $merchantUserId, $providerKey);
        mg_audit('merchant.integration.disconnected', 'merchant_integration', ['provider' => $providerKey], $merchantUserId);
        mg_ok(['connection' => $result], 'Integration disconnected.');
    }

    if ($action === 'preview_contacts') {
        if ($providerKey === 'squarespace') {
            $result = mg_squarespace_contact_preview(
                $pdo,
                $merchantUserId,
                trim((string)($input['cursor'] ?? '')) ?: null,
                max(1, min(100, (int)($input['page_size'] ?? 25)))
            );
            mg_ok($result, 'Squarespace contact preview loaded.');
        }
        if ($providerKey === 'woocommerce') {
            $result = mg_woocommerce_contact_preview(
                $pdo,
                $merchantUserId,
                trim((string)($input['cursor'] ?? '')) ?: null,
                max(1, min(100, (int)($input['page_size'] ?? 25)))
            );
            mg_ok($result, 'WooCommerce customer preview loaded.');
        }
        if ($providerKey === 'shopify') {
            $result = mg_shopify_contact_preview(
                $pdo,
                $merchantUserId,
                trim((string)($input['cursor'] ?? '')) ?: null,
                max(1, min(250, (int)($input['page_size'] ?? 25)))
            );
            mg_ok($result, 'Shopify customer preview loaded.');
        }
    }

    if ($action === 'sync_contacts') {
        if ($providerKey === 'squarespace') {
            mg_rate_limit('merchant.integrations.squarespace.sync', 'user:' . $merchantUserId, 6, 300);
            $result = mg_squarespace_sync_contacts(
                $pdo,
                $merchantUserId,
                !empty($input['reset']),
                max(1, min(250, (int)($input['page_size'] ?? 100))),
                max(1, min(10, (int)($input['max_pages'] ?? 5)))
            );
            mg_audit('merchant.integration.contacts_synced', 'merchant_integration', ['provider' => 'squarespace', 'counts' => $result['counts'] ?? []], $merchantUserId);
            mg_ok($result, 'Squarespace contacts synchronized.');
        }
        if ($providerKey === 'woocommerce') {
            mg_rate_limit('merchant.integrations.woocommerce.sync', 'user:' . $merchantUserId, 6, 300);
            $result = mg_woocommerce_sync_contacts(
                $pdo,
                $merchantUserId,
                !empty($input['reset']),
                max(1, min(100, (int)($input['page_size'] ?? 100))),
                max(1, min(10, (int)($input['max_pages'] ?? 5)))
            );
            mg_audit('merchant.integration.contacts_synced', 'merchant_integration', ['provider' => 'woocommerce', 'counts' => $result['counts'] ?? []], $merchantUserId);
            mg_ok($result, 'WooCommerce customers synchronized.');
        }
        if ($providerKey === 'shopify') {
            mg_rate_limit('merchant.integrations.shopify.sync', 'user:' . $merchantUserId, 6, 300);
            $result = mg_shopify_sync_contacts(
                $pdo,
                $merchantUserId,
                !empty($input['reset']),
                max(1, min(250, (int)($input['page_size'] ?? 100))),
                max(1, min(10, (int)($input['max_pages'] ?? 5)))
            );
            mg_audit('merchant.integration.contacts_synced', 'merchant_integration', ['provider' => 'shopify', 'counts' => $result['counts'] ?? []], $merchantUserId);
            mg_ok($result, 'Shopify customers synchronized.');
        }
    }

    if ($providerKey === 'squarespace' && $action === 'configure_contact_webhook') {
        $result = mg_squarespace_setup_contact_webhook($pdo, $merchantUserId);
        mg_audit('merchant.integration.webhook_configured', 'merchant_integration', ['provider' => 'squarespace', 'configured' => $result['configured'] ?? false], $merchantUserId);
        mg_ok($result, !empty($result['configured']) ? 'Squarespace contact webhook configured.' : 'Squarespace webhook setup needs attention.');
    }

    mg_fail('Unsupported integration action.', 422);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (MgIntegrationCredentialException $error) {
    mg_security_log('error', 'merchant.integration.encryption_unavailable', 'Integration credential encryption is unavailable.', ['provider' => $providerKey], $merchantUserId);
    mg_fail($error->getMessage(), 503);
} catch (Throwable $error) {
    if (!in_array($action, ['preview_contacts', 'sync_contacts', 'configure_contact_webhook'], true)) {
        mg_integration_mark_error($pdo, $merchantUserId, $providerKey, $error::class, $error->getMessage());
    }
    mg_security_log('warning', 'merchant.integration.action_failed', 'Merchant integration action failed.', ['provider' => $providerKey, 'action' => $action, 'exception_class' => $error::class], $merchantUserId);
    mg_fail($error->getMessage() !== '' ? $error->getMessage() : 'Unable to update the integration.', 500);
}
