<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-integrations.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm.php';
require_once dirname(__DIR__, 2) . '/includes/integrations/squarespace-contacts.php';
require_once dirname(__DIR__, 2) . '/includes/integrations/woocommerce-contacts.php';
require_once dirname(__DIR__, 2) . '/includes/integrations/shopify-contacts.php';
require_once dirname(__DIR__, 2) . '/includes/integrations/square-contacts.php';
require_once dirname(__DIR__, 2) . '/includes/integrations/hubspot-contacts.php';
require_once dirname(__DIR__, 2) . '/includes/integrations/mailchimp-contacts.php';
require_once dirname(__DIR__, 2) . '/includes/integrations/klaviyo-profiles.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'POST'
    ? mg_merchant_require_permission('merchant.campaigns.manage')
    : mg_merchant_require_permission('merchant.campaigns.view');
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantUserId = (int)$workspace['merchant_user_id'];

if ($method === 'GET') {
    $providers = mg_integration_provider_catalog();
    $providers = mg_woocommerce_provider_catalog($providers);
    $providers = mg_shopify_provider_catalog($providers);
    $providers = mg_square_provider_catalog($providers);
    $providers = mg_hubspot_provider_catalog($providers);
    $providers = mg_mailchimp_provider_catalog($providers);
    $providers = mg_klaviyo_provider_catalog($providers);
    mg_ok([
        'schema_ready' => mg_integration_schema_ready($pdo),
        'migration' => 'merchant_integrations_foundation_v1.sql',
        'credential_encryption_ready' => mg_integration_credential_master_key() !== null,
        'providers' => $providers,
        'connections' => mg_integration_connections($pdo, $merchantUserId),
        'squarespace_contacts' => mg_squarespace_contacts_status($pdo, $merchantUserId),
        'woocommerce_contacts' => mg_woocommerce_contacts_status($pdo, $merchantUserId),
        'shopify_contacts' => mg_shopify_contacts_status($pdo, $merchantUserId),
        'square_contacts' => mg_square_contacts_status($pdo, $merchantUserId),
        'hubspot_contacts' => mg_hubspot_contacts_status($pdo, $merchantUserId),
        'mailchimp_contacts' => mg_mailchimp_contacts_status($pdo, $merchantUserId),
        'klaviyo_profiles' => mg_klaviyo_profiles_status($pdo, $merchantUserId),
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

    if ($providerKey === 'square' && $action === 'begin_square_oauth') {
        mg_rate_limit('merchant.integrations.square.oauth', 'user:' . $merchantUserId, 8, 300);
        $existing = mg_integration_connection_row($pdo, $merchantUserId, 'square', false);
        $wasActive = is_array($existing) && (string)($existing['status'] ?? '') === 'active';
        $result = mg_square_begin_oauth($pdo, $merchantUserId);
        if ($wasActive) {
            $pdo->prepare("UPDATE merchant_integration_connections SET status='active',updated_at=NOW() WHERE merchant_user_id=? AND provider_key='square' ORDER BY id DESC LIMIT 1")
                ->execute([$merchantUserId]);
        }
        mg_audit('merchant.integration.oauth_started', 'merchant_integration', ['provider' => 'square', 'environment' => $result['environment'] ?? null], $merchantUserId);
        mg_ok($result, 'Square authorization is ready.');
    }

    if ($providerKey === 'hubspot' && $action === 'begin_hubspot_oauth') {
        mg_rate_limit('merchant.integrations.hubspot.oauth', 'user:' . $merchantUserId, 8, 300);
        $existing = mg_integration_connection_row($pdo, $merchantUserId, 'hubspot', false);
        $wasActive = is_array($existing) && (string)($existing['status'] ?? '') === 'active';
        $result = mg_hubspot_begin_oauth($pdo, $merchantUserId);
        if ($wasActive) {
            $pdo->prepare("UPDATE merchant_integration_connections SET status='active',updated_at=NOW() WHERE merchant_user_id=? AND provider_key='hubspot' ORDER BY id DESC LIMIT 1")
                ->execute([$merchantUserId]);
        }
        mg_audit('merchant.integration.oauth_started', 'merchant_integration', ['provider' => 'hubspot'], $merchantUserId);
        mg_ok($result, 'HubSpot authorization is ready.');
    }

    if ($providerKey === 'mailchimp' && $action === 'begin_mailchimp_oauth') {
        mg_rate_limit('merchant.integrations.mailchimp.oauth', 'user:' . $merchantUserId, 8, 300);
        $existing = mg_integration_connection_row($pdo, $merchantUserId, 'mailchimp', false);
        $wasActive = is_array($existing) && (string)($existing['status'] ?? '') === 'active';
        $result = mg_mailchimp_begin_oauth($pdo, $merchantUserId);
        if ($wasActive) {
            $pdo->prepare("UPDATE merchant_integration_connections SET status='active',updated_at=NOW() WHERE merchant_user_id=? AND provider_key='mailchimp' ORDER BY id DESC LIMIT 1")
                ->execute([$merchantUserId]);
        }
        mg_audit('merchant.integration.oauth_started', 'merchant_integration', ['provider' => 'mailchimp'], $merchantUserId);
        mg_ok($result, 'Mailchimp authorization is ready.');
    }

    if ($providerKey === 'klaviyo' && $action === 'begin_klaviyo_oauth') {
        mg_rate_limit('merchant.integrations.klaviyo.oauth', 'user:' . $merchantUserId, 8, 300);
        $existing = mg_integration_connection_row($pdo, $merchantUserId, 'klaviyo', false);
        $wasActive = is_array($existing) && (string)($existing['status'] ?? '') === 'active';
        $result = mg_klaviyo_begin_oauth($pdo, $merchantUserId);
        if ($wasActive) {
            $pdo->prepare("UPDATE merchant_integration_connections SET status='active',updated_at=NOW() WHERE merchant_user_id=? AND provider_key='klaviyo' ORDER BY id DESC LIMIT 1")
                ->execute([$merchantUserId]);
        }
        mg_audit('merchant.integration.oauth_started', 'merchant_integration', ['provider' => 'klaviyo', 'pkce_method' => $result['pkce_method'] ?? null], $merchantUserId);
        mg_ok($result, 'Klaviyo authorization is ready.');
    }

    if ($providerKey === 'mailchimp' && $action === 'list_audiences') {
        mg_rate_limit('merchant.integrations.mailchimp.audiences', 'user:' . $merchantUserId, 12, 300);
        mg_ok(mg_mailchimp_audiences($pdo, $merchantUserId), 'Mailchimp audiences loaded.');
    }

    if ($providerKey === 'mailchimp' && $action === 'select_audience') {
        mg_rate_limit('merchant.integrations.mailchimp.select_audience', 'user:' . $merchantUserId, 12, 300);
        $result = mg_mailchimp_select_audience($pdo, $merchantUserId, (string)($input['audience_id'] ?? ''));
        mg_audit('merchant.integration.audience_selected', 'merchant_integration', ['provider' => 'mailchimp', 'audience_id' => $result['selected_audience']['id'] ?? null], $merchantUserId);
        mg_ok($result, 'Mailchimp audience selected.');
    }

    if ($providerKey === 'woocommerce' && $action === 'connect_api_key') {
        mg_rate_limit('merchant.integrations.woocommerce.connect', 'user:' . $merchantUserId, 8, 300);
        $result = mg_woocommerce_connect($pdo, $merchantUserId, (string)($input['site_url'] ?? ''), (string)($input['consumer_key'] ?? ''), (string)($input['consumer_secret'] ?? ''));
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
            mg_ok(mg_squarespace_contact_preview($pdo, $merchantUserId, trim((string)($input['cursor'] ?? '')) ?: null, max(1, min(100, (int)($input['page_size'] ?? 25)))), 'Squarespace contact preview loaded.');
        }
        if ($providerKey === 'woocommerce') {
            mg_ok(mg_woocommerce_contact_preview($pdo, $merchantUserId, trim((string)($input['cursor'] ?? '')) ?: null, max(1, min(100, (int)($input['page_size'] ?? 25)))), 'WooCommerce customer preview loaded.');
        }
        if ($providerKey === 'shopify') {
            mg_ok(mg_shopify_contact_preview($pdo, $merchantUserId, trim((string)($input['cursor'] ?? '')) ?: null, max(1, min(250, (int)($input['page_size'] ?? 25)))), 'Shopify customer preview loaded.');
        }
        if ($providerKey === 'square') {
            mg_ok(mg_square_contact_preview($pdo, $merchantUserId, trim((string)($input['cursor'] ?? '')) ?: null, max(1, min(100, (int)($input['page_size'] ?? 25)))), 'Square customer preview loaded.');
        }
        if ($providerKey === 'hubspot') {
            mg_ok(mg_hubspot_contact_preview($pdo, $merchantUserId, trim((string)($input['cursor'] ?? '')) ?: null, max(1, min(100, (int)($input['page_size'] ?? 25)))), 'HubSpot contact preview loaded.');
        }
        if ($providerKey === 'mailchimp') {
            mg_ok(mg_mailchimp_contact_preview($pdo, $merchantUserId, trim((string)($input['cursor'] ?? '')) ?: null, max(1, min(1000, (int)($input['page_size'] ?? 25)))), 'Mailchimp audience preview loaded.');
        }
    }

    if ($providerKey === 'klaviyo' && $action === 'preview_profiles') {
        mg_rate_limit('merchant.integrations.klaviyo.preview', 'user:' . $merchantUserId, 12, 300);
        mg_ok(mg_klaviyo_profile_preview($pdo, $merchantUserId, trim((string)($input['cursor'] ?? '')) ?: null, max(1, min(100, (int)($input['page_size'] ?? 25)))), 'Klaviyo profile preview loaded.');
    }

    if ($action === 'sync_contacts') {
        if ($providerKey === 'squarespace') {
            mg_rate_limit('merchant.integrations.squarespace.sync', 'user:' . $merchantUserId, 6, 300);
            $result = mg_squarespace_sync_contacts($pdo, $merchantUserId, !empty($input['reset']), max(1, min(250, (int)($input['page_size'] ?? 100))), max(1, min(10, (int)($input['max_pages'] ?? 5))));
            mg_audit('merchant.integration.contacts_synced', 'merchant_integration', ['provider' => 'squarespace', 'counts' => $result['counts'] ?? []], $merchantUserId);
            mg_ok($result, 'Squarespace contacts synchronized.');
        }
        if ($providerKey === 'woocommerce') {
            mg_rate_limit('merchant.integrations.woocommerce.sync', 'user:' . $merchantUserId, 6, 300);
            $result = mg_woocommerce_sync_contacts($pdo, $merchantUserId, !empty($input['reset']), max(1, min(100, (int)($input['page_size'] ?? 100))), max(1, min(10, (int)($input['max_pages'] ?? 5))));
            mg_audit('merchant.integration.contacts_synced', 'merchant_integration', ['provider' => 'woocommerce', 'counts' => $result['counts'] ?? []], $merchantUserId);
            mg_ok($result, 'WooCommerce customers synchronized.');
        }
        if ($providerKey === 'shopify') {
            mg_rate_limit('merchant.integrations.shopify.sync', 'user:' . $merchantUserId, 6, 300);
            $result = mg_shopify_sync_contacts($pdo, $merchantUserId, !empty($input['reset']), max(1, min(250, (int)($input['page_size'] ?? 100))), max(1, min(10, (int)($input['max_pages'] ?? 5))));
            mg_audit('merchant.integration.contacts_synced', 'merchant_integration', ['provider' => 'shopify', 'counts' => $result['counts'] ?? []], $merchantUserId);
            mg_ok($result, 'Shopify customers synchronized.');
        }
        if ($providerKey === 'square') {
            mg_rate_limit('merchant.integrations.square.sync', 'user:' . $merchantUserId, 6, 300);
            $result = mg_square_sync_contacts($pdo, $merchantUserId, !empty($input['reset']), max(1, min(100, (int)($input['page_size'] ?? 100))), max(1, min(10, (int)($input['max_pages'] ?? 5))));
            mg_audit('merchant.integration.contacts_synced', 'merchant_integration', ['provider' => 'square', 'counts' => $result['counts'] ?? []], $merchantUserId);
            mg_ok($result, 'Square customers synchronized.');
        }
        if ($providerKey === 'hubspot') {
            mg_rate_limit('merchant.integrations.hubspot.sync', 'user:' . $merchantUserId, 6, 300);
            $result = mg_hubspot_sync_contacts($pdo, $merchantUserId, !empty($input['reset']), max(1, min(100, (int)($input['page_size'] ?? 100))), max(1, min(10, (int)($input['max_pages'] ?? 5))));
            mg_audit('merchant.integration.contacts_synced', 'merchant_integration', ['provider' => 'hubspot', 'counts' => $result['counts'] ?? []], $merchantUserId);
            mg_ok($result, 'HubSpot contacts synchronized.');
        }
        if ($providerKey === 'mailchimp') {
            mg_rate_limit('merchant.integrations.mailchimp.sync', 'user:' . $merchantUserId, 6, 300);
            $result = mg_mailchimp_sync_contacts($pdo, $merchantUserId, !empty($input['reset']), max(1, min(1000, (int)($input['page_size'] ?? 250))), max(1, min(10, (int)($input['max_pages'] ?? 5))));
            mg_audit('merchant.integration.contacts_synced', 'merchant_integration', ['provider' => 'mailchimp', 'audience' => $result['audience'] ?? null, 'counts' => $result['counts'] ?? []], $merchantUserId);
            mg_ok($result, 'Mailchimp audience synchronized.');
        }
    }

    if ($providerKey === 'klaviyo' && $action === 'sync_profiles') {
        mg_rate_limit('merchant.integrations.klaviyo.sync', 'user:' . $merchantUserId, 6, 300);
        $result = mg_klaviyo_sync_profiles($pdo, $merchantUserId, !empty($input['reset']), max(1, min(100, (int)($input['page_size'] ?? 100))), max(1, min(10, (int)($input['max_pages'] ?? 5))));
        mg_audit('merchant.integration.profiles_synced', 'merchant_integration', ['provider' => 'klaviyo', 'counts' => $result['counts'] ?? []], $merchantUserId);
        mg_ok($result, 'Klaviyo profiles synchronized.');
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
    if (!in_array($action, ['preview_contacts', 'sync_contacts', 'preview_profiles', 'sync_profiles', 'configure_contact_webhook'], true)) {
        mg_integration_mark_error($pdo, $merchantUserId, $providerKey, $error::class, $error->getMessage());
    }
    mg_security_log('warning', 'merchant.integration.action_failed', 'Merchant integration action failed.', ['provider' => $providerKey, 'action' => $action, 'exception_class' => $error::class], $merchantUserId);
    mg_fail($error->getMessage() !== '' ? $error->getMessage() : 'Unable to update the integration.', 500);
}
