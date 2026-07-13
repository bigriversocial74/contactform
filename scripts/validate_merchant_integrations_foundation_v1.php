<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'migration' => 'database/merchant_integrations_foundation_v1.sql',
    'service' => 'includes/merchant-integrations.php',
    'provider_contract' => 'includes/integrations/provider-interface.php',
    'squarespace' => 'includes/integrations/providers/squarespace.php',
    'api' => 'api/merchant/integrations.php',
    'callback' => 'merchant-integrations-squarespace-callback.php',
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
    'Squarespace requests read-only CRM and commerce scopes' => $provider->scopes() === [
        'website.contacts.read',
        'website.orders.read',
        'website.products.read',
        'website.inventory.read',
    ],
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
    'merchant API requires CSRF for writes' => str_contains($files['api'], 'mg_require_csrf_for_write'),
    'merchant API supports connect and disconnect actions' => str_contains($files['api'], "'begin_oauth'")
        && str_contains($files['api'], "'disconnect'"),
    'callback verifies current merchant account through hashed state lookup' => str_contains($files['callback'], 'mg_integration_complete_oauth')
        && str_contains($files['service'], 'mg_integration_find_oauth_connection'),
    'Connected Apps page is routed through merchant workspace' => str_contains($files['page'], "\$merchantView = 'integrations'")
        && str_contains($files['router'], "\$merchantView==='integrations'"),
    'merchant navigation exposes Connected Apps' => str_contains($files['navigation'], "'integrations' => ['Connected Apps'"),
    'UI preserves consent and source-of-truth policy' => str_contains($files['view'], 'Consent stays explicit')
        && str_contains($files['view'], 'Source-of-truth policy'),
    'frontend begins OAuth through protected merchant API' => str_contains($files['js'], "action: 'begin_oauth'")
        && str_contains($files['js'], 'window.location.assign(data.authorization_url)'),
    'responsive Connected Apps presentation exists' => str_contains($files['css'], '@media(max-width:720px)')
        && str_contains($files['css'], '.mg-integrations-grid'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}
if ($failed !== []) {
    fwrite(STDERR, 'Merchant App Connect Foundation v1 validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}
echo 'Merchant App Connect Foundation v1 contract: ' . count($checks) . '/' . count($checks) . " checks passed.\n";
