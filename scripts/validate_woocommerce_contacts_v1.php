<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    ['includes/integrations/providers/woocommerce.php', 'final class MgWooCommerceProvider'],
    ['includes/integrations/providers/woocommerce.php', "return 'api_key'"],
    ['includes/integrations/providers/woocommerce.php', 'CURLOPT_HTTPAUTH => CURLAUTH_BASIC'],
    ['includes/integrations/providers/woocommerce.php', 'WooCommerce store URL must use HTTPS'],
    ['includes/integrations/woocommerce-contacts.php', 'function mg_woocommerce_connect'],
    ['includes/integrations/woocommerce-contacts.php', 'mg_integration_encrypt_secret'],
    ['includes/integrations/woocommerce-contacts.php', "'marketing_consent_inferred' => false"],
    ['includes/integrations/woocommerce-contacts.php', "'addresses_excluded' => true"],
    ['includes/integrations/woocommerce-contacts.php', 'function mg_woocommerce_contact_preview'],
    ['includes/integrations/woocommerce-contacts.php', 'function mg_woocommerce_sync_contacts'],
    ['includes/integrations/woocommerce-contacts.php', 'function mg_woocommerce_import_contact'],
    ['includes/integrations/woocommerce-contacts.php', "'pending_review'"],
    ['includes/integrations/woocommerce-contacts.php', "'conflict'"],
    ['api/merchant/integrations.php', "require_once dirname(__DIR__, 2) . '/includes/integrations/woocommerce-contacts.php'"],
    ['api/merchant/integrations.php', "'woocommerce_contacts' => mg_woocommerce_contacts_status"],
    ['api/merchant/integrations.php', "\$action === 'connect_api_key'"],
    ['api/merchant/integrations.php', 'mg_woocommerce_contact_preview'],
    ['api/merchant/integrations.php', 'mg_woocommerce_sync_contacts'],
    ['assets/js/merchant-integrations-woocommerce.js', "action: 'connect_api_key'"],
    ['assets/js/merchant-integrations-woocommerce.js', 'consent not inferred'],
    ['assets/js/merchant-integrations-woocommerce.js', 'wooSignature'],
    ['assets/css/merchant-integrations-woocommerce.css', '.mg-integration-key-form'],
    ['merchant-integrations.php', '/assets/js/merchant-integrations-woocommerce.js?v=1.0.0'],
    ['merchant-integrations.php', '/assets/css/merchant-integrations-woocommerce.css?v=1.0.0'],
];

$failed = [];
foreach ($checks as [$path, $needle]) {
    $full = $root . '/' . $path;
    $content = is_file($full) ? file_get_contents($full) : false;
    if (!is_string($content) || !str_contains($content, $needle)) {
        $failed[] = $path . ' :: ' . $needle;
    }
}

if ($failed) {
    fwrite(STDERR, "WooCommerce Contacts v1 contract failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo 'WooCommerce Contacts v1 contract passed (' . count($checks) . " checks).\n";
