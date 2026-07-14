<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    ['includes/integrations/providers/shopify.php', 'final class MgShopifyProvider'],
    ['includes/integrations/providers/shopify.php', "return ['read_customers']"],
    ['includes/integrations/providers/shopify.php', "'/admin/oauth/authorize?'"],
    ['includes/integrations/providers/shopify.php', "'/admin/oauth/access_token'"],
    ['includes/integrations/providers/shopify.php', 'verifyCallbackHmac'],
    ['includes/integrations/providers/shopify.php', "hash_hmac('sha256'"],
    ['includes/integrations/providers/shopify.php', "'.myshopify.com'"],
    ['includes/integrations/providers/shopify.php', "'2026-07'"],
    ['includes/integrations/providers/shopify.php', 'defaultEmailAddress'],
    ['includes/integrations/providers/shopify.php', 'marketingState'],
    ['includes/integrations/providers/shopify.php', 'pageInfo'],
    ['includes/integrations/providers/shopify.php', 'X-Shopify-Access-Token'],
    ['includes/integrations/shopify-contacts.php', 'function mg_shopify_begin_oauth'],
    ['includes/integrations/shopify-contacts.php', 'oauth_state_expires_at'],
    ['includes/integrations/shopify-contacts.php', 'function mg_shopify_complete_oauth'],
    ['includes/integrations/shopify-contacts.php', 'mg_integration_encrypt_secret($accessToken)'],
    ['includes/integrations/shopify-contacts.php', "'marketing_consent_inferred' => false"],
    ['includes/integrations/shopify-contacts.php', "'marketing_consent_preserved' => true"],
    ['includes/integrations/shopify-contacts.php', "'addresses_excluded' => true"],
    ['includes/integrations/shopify-contacts.php', "'phone_numbers_excluded' => true"],
    ['includes/integrations/shopify-contacts.php', 'function mg_shopify_contact_preview'],
    ['includes/integrations/shopify-contacts.php', 'function mg_shopify_sync_contacts'],
    ['includes/integrations/shopify-contacts.php', 'function mg_shopify_import_contact'],
    ['includes/integrations/shopify-contacts.php', "'pending_review'"],
    ['includes/integrations/shopify-contacts.php', "'conflict'"],
    ['merchant-integrations-shopify-callback.php', 'mg_shopify_complete_oauth'],
    ['api/merchant/integrations.php', "'shopify_contacts' => mg_shopify_contacts_status"],
    ['api/merchant/integrations.php', "\$action === 'begin_shopify_oauth'"],
    ['api/merchant/integrations.php', 'mg_shopify_contact_preview'],
    ['api/merchant/integrations.php', 'mg_shopify_sync_contacts'],
    ['api/merchant/integrations.php', "provider_key='shopify'"],
    ['assets/js/merchant-integrations-shopify.js', "action: 'begin_shopify_oauth'"],
    ['assets/js/merchant-integrations-shopify.js', 'consent preserved'],
    ['assets/js/merchant-integrations-shopify.js', 'shopifySignature'],
    ['assets/css/merchant-integrations-shopify.css', '.mg-shopify-connect-form'],
    ['merchant-integrations.php', '/assets/js/merchant-integrations-shopify.js?v=1.0.0'],
    ['merchant-integrations.php', '/assets/css/merchant-integrations-shopify.css?v=1.0.0'],
];

$failed = [];
foreach ($checks as [$path, $needle]) {
    $full = $root . '/' . $path;
    $content = is_file($full) ? file_get_contents($full) : false;
    if (!is_string($content) || !str_contains($content, $needle)) $failed[] = $path . ' :: ' . $needle;
}

if ($failed) {
    fwrite(STDERR, "Shopify Contacts v1 contract failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo 'Shopify Contacts v1 contract passed (' . count($checks) . " checks).\n";
