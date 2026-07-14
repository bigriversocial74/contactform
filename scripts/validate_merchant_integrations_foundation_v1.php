<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'migration' => 'database/merchant_integrations_foundation_v1.sql',
    'service' => 'includes/merchant-integrations.php',
    'contacts_loader' => 'includes/integrations/squarespace-contacts.php',
    'contacts_auth' => 'includes/integrations/squarespace-contact-auth.php',
    'contacts_import' => 'includes/integrations/squarespace-contact-import.php',
    'contacts_sync' => 'includes/integrations/squarespace-contact-sync.php',
    'contacts_webhooks' => 'includes/integrations/squarespace-contact-webhooks.php',
    'provider_contract' => 'includes/integrations/provider-interface.php',
    'squarespace' => 'includes/integrations/providers/squarespace.php',
    'api' => 'api/merchant/integrations.php',
    'callback' => 'merchant-integrations-squarespace-callback.php',
    'webhook' => 'webhooks/squarespace-contacts.php',
    'page' => 'merchant-integrations.php',
    'view' => 'includes/merchant-integrations-view.php',
    'js' => 'assets/js/merchant-integrations.js',
    'css' => 'assets/css/merchant-integrations.css',
    'manifest' => 'config/migrations.php',
    'navigation' => 'includes/merchant-navigation.php',
    'router' => 'includes/merchant-view.php',
];
$files = [];
foreach ($paths as $key => $path) {
    $full = $root . '/' . $path;
    $content = is_file($full) ? file_get_contents($full) : false;
    if (!is_string($content) || trim($content) === '') {
        fwrite(STDERR, "Missing validation target: {$path}\n");
        exit(1);
    }
    $files[$key] = $content;
}

require_once $root . '/includes/integrations/providers/squarespace.php';
$provider = new MgSquarespaceProvider();
$capabilities = $provider->capabilities();
$files['contacts_service'] = implode("\n", [
    $files['contacts_loader'],
    $files['contacts_auth'],
    $files['contacts_import'],
    $files['contacts_sync'],
    $files['contacts_webhooks'],
]);

$checks = [
    'migration is registered in the canonical manifest' => str_contains($files['manifest'], "'merchant_integrations_foundation_v1.sql'"),
    'provider-neutral connection table exists' => str_contains($files['migration'], 'CREATE TABLE IF NOT EXISTS merchant_integration_connections'),
    'credentials are isolated from connection metadata' => str_contains($files['migration'], 'CREATE TABLE IF NOT EXISTS merchant_integration_credentials'),
    'external records link to canonical local entities' => str_contains($files['migration'], 'CREATE TABLE IF NOT EXISTS merchant_integration_entity_links')
        && str_contains($files['migration'], 'local_entity_type')
        && str_contains($files['migration'], 'local_entity_id'),
    'sync runs and cursor state are durable' => str_contains($files['migration'], 'merchant_integration_sync_runs')
        && str_contains($files['migration'], 'merchant_integration_sync_state'),
    'webhook inbox has provider deduplication' => str_contains($files['migration'], 'merchant_integration_webhook_events')
        && str_contains($files['migration'], 'uq_merchant_integration_webhook_events_dedupe'),
    'provider contract separates OAuth capability' => str_contains($files['provider_contract'], 'interface MgMerchantIntegrationOAuthProvider'),
    'Squarespace provider uses official authorization endpoint' => str_contains($files['squarespace'], 'https://login.squarespace.com/api/1/login/oauth/provider/authorize'),
    'Squarespace provider uses official token endpoint' => str_contains($files['squarespace'], 'https://login.squarespace.com/api/1/login/oauth/provider/tokens'),
    'Squarespace provider retrieves owning website' => str_contains($files['squarespace'], 'https://api.squarespace.com/1.0/authorization/website'),
    'Squarespace contacts endpoint uses v1 contacts API' => str_contains($files['squarespace'], 'https://api.squarespace.com/v1/contacts'),
    'Squarespace webhook endpoint uses subscriptions API' => str_contains($files['squarespace'], 'https://api.squarespace.com/1.0/webhook_subscriptions'),
    'contact webhook topics exclude address topics' => $provider->contactWebhookTopics() === ['contact.create', 'contact.update', 'contact.delete']
        && !str_contains(implode(',', $provider->contactWebhookTopics()), 'address.'),
    'provider capabilities exclude address import' => !in_array('addresses.read', $capabilities, true)
        && !in_array('webhooks.addresses', $capabilities, true),
    'Squarespace scope enables contact webhooks but code remains import-only' => in_array('website.contacts', $provider->scopes(), true)
        && !str_contains($files['contacts_service'], "requestJson('PATCH'")
        && !str_contains($files['contacts_service'], "requestJson('DELETE'"),
    'offline access is requested for rotating refresh tokens' => str_contains($files['squarespace'], "'access_type' => 'offline'"),
    'OAuth requests carry a User-Agent' => str_contains($files['squarespace'], 'User-Agent: Microgifter/1.0 AppConnect'),
    'integration tokens use Sodium secretbox encryption' => str_contains($files['service'], 'sodium_crypto_secretbox')
        && str_contains($files['service'], 'MG_INTEGRATION_CREDENTIAL_KEY'),
    'OAuth state is hashed and expires' => str_contains($files['service'], "hash('sha256', \$state)")
        && str_contains($files['service'], 'oauth_state_expires_at'),
    'OAuth state is cleared after successful connection' => str_contains($files['service'], 'oauth_state_hash=NULL')
        && str_contains($files['service'], 'oauth_state_expires_at=NULL'),
    'disconnect removes stored credentials but preserves connection history' => str_contains($files['service'], "status='disconnected'")
        && str_contains($files['service'], 'access_token_ciphertext=NULL'),
    'rotating refresh tokens are stored atomically' => str_contains($files['contacts_service'], 'refresh_lock_token')
        && str_contains($files['contacts_service'], 'refresh_token_ciphertext=?')
        && str_contains($files['contacts_service'], 'refresh_lock_expires_at'),
    'contact normalization ignores address fields' => str_contains($files['contacts_service'], "'addresses_excluded' => true")
        && !str_contains($files['contacts_service'], "defaultShippingAddress]['address'")
        && !str_contains($files['contacts_service'], 'phoneNumber'),
    'marketing consent source and dates are preserved' => str_contains($files['contacts_service'], "'accepts_marketing'")
        && str_contains($files['contacts_service'], "'marketing_joined_on'")
        && str_contains($files['contacts_service'], "'marketing_left_on'")
        && str_contains($files['contacts_service'], "'consent_source' => 'squarespace'"),
    'contact preview classifies create link update unchanged and review' => str_contains($files['contacts_service'], "'action' => \$action")
        && str_contains($files['contacts_service'], "'unchanged'")
        && str_contains($files['contacts_service'], "'pending_review'"),
    'exact email match links to canonical CRM contact' => str_contains($files['contacts_service'], 'mg_squarespace_contact_match')
        && str_contains($files['contacts_service'], 'mg_crm_identity_alias_contact'),
    'external identity link uses immutable Squarespace contact ID' => str_contains($files['contacts_service'], "entity_type='contact'")
        && str_contains($files['contacts_service'], 'external_entity_id'),
    'external deletion preserves Microgifter contact' => str_contains($files['contacts_service'], "status='deleted_external'")
        && str_contains($files['contacts_service'], "['deletion_policy'] = 'preserve_microgifter_contact'"),
    'cursor pagination is saved after each page' => str_contains($files['contacts_service'], 'nextPageCursor')
        && str_contains($files['contacts_service'], "resource_key='contacts'")
        && str_contains($files['contacts_service'], 'cursor_value'),
    'sync run counters are durable' => str_contains($files['contacts_service'], 'processed_count')
        && str_contains($files['contacts_service'], 'created_count')
        && str_contains($files['contacts_service'], 'failed_count'),
    'webhook secret is encrypted at rest' => str_contains($files['contacts_service'], 'webhook_secret_ciphertext')
        && str_contains($files['contacts_service'], 'mg_integration_encrypt_secret($secret)'),
    'webhook signature uses decoded hex secret and constant-time comparison' => str_contains($files['contacts_service'], 'hex2bin($secretHex)')
        && str_contains($files['contacts_service'], "hash_hmac('sha256', \$rawBody, \$secret)")
        && str_contains($files['contacts_service'], 'hash_equals($expected, $signature)'),
    'webhook intake is idempotent' => str_contains($files['contacts_service'], "'status' => 'duplicate'")
        && str_contains($files['contacts_service'], 'merchant_integration_webhook_events'),
    'contact update can arrive before create' => str_contains($files['contacts_service'], "mg_squarespace_import_contact(\$pdo, \$connection, \$contact, 'webhook')"),
    'merchant API requires CSRF for writes' => str_contains($files['api'], 'mg_require_csrf_for_write'),
    'merchant API supports preview sync and webhook setup' => str_contains($files['api'], "'preview_contacts'")
        && str_contains($files['api'], "'sync_contacts'")
        && str_contains($files['api'], "'configure_contact_webhook'"),
    'OAuth callback configures contact webhook without address topics' => str_contains($files['callback'], 'mg_squarespace_setup_contact_webhook')
        && str_contains($files['callback'], "'addresses_enabled' => false"),
    'public webhook route does not require merchant session' => str_contains($files['webhook'], 'php://input')
        && str_contains($files['webhook'], 'HTTP_SQUARESPACE_SIGNATURE')
        && !str_contains($files['webhook'], 'mg_require_api_user'),
    'Connected Apps page is routed through merchant workspace' => str_contains($files['page'], "\$merchantView = 'integrations'")
        && str_contains($files['router'], "\$merchantView==='integrations'"),
    'merchant navigation exposes Connected Apps' => str_contains($files['navigation'], "'integrations' => ['Connected Apps'"),
    'UI explicitly excludes addresses' => str_contains($files['view'], 'Addresses excluded')
        && str_contains($files['view'], 'address-derived phone numbers are not imported'),
    'frontend exposes preview import and webhook controls' => str_contains($files['js'], "action: 'preview_contacts'")
        && str_contains($files['js'], "action: 'sync_contacts'")
        && str_contains($files['js'], "action: 'configure_contact_webhook'"),
    'responsive contact preview presentation exists' => str_contains($files['css'], '.mg-integration-preview-row')
        && str_contains($files['css'], '@media(max-width:720px)'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}
if ($failed !== []) {
    fwrite(STDERR, 'Merchant App Connect Contacts v1 validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}
echo 'Merchant App Connect Contacts v1 contract: ' . count($checks) . '/' . count($checks) . " checks passed.\n";
