<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    ['includes/integrations/providers/square.php', 'final class MgSquareProvider'],
    ['includes/integrations/providers/square.php', "'CUSTOMERS_READ'"],
    ['includes/integrations/providers/square.php', "'MERCHANT_PROFILE_READ'"],
    ['includes/integrations/providers/square.php', "'/authorize?'"],
    ['includes/integrations/providers/square.php', "'/token'"],
    ['includes/integrations/providers/square.php', "'authorization_code'"],
    ['includes/integrations/providers/square.php', "'refresh_token'"],
    ['includes/integrations/providers/square.php', "'use_jwt' => true"],
    ['includes/integrations/providers/square.php', 'function refreshAccessToken'],
    ['includes/integrations/providers/square.php', 'function listCustomers'],
    ['includes/integrations/providers/square.php', 'Authorization: Bearer'],
    ['includes/integrations/providers/square.php', 'Square-Version:'],
    ['includes/integrations/square-contacts.php', 'function mg_square_begin_oauth'],
    ['includes/integrations/square-contacts.php', 'oauth_state_expires_at'],
    ['includes/integrations/square-contacts.php', 'function mg_square_complete_oauth'],
    ['includes/integrations/square-contacts.php', 'mg_integration_encrypt_secret($accessToken)'],
    ['includes/integrations/square-contacts.php', 'mg_integration_encrypt_secret($refreshToken)'],
    ['includes/integrations/square-contacts.php', 'function mg_square_refresh_credentials'],
    ['includes/integrations/square-contacts.php', 'refresh_lock_token'],
    ['includes/integrations/square-contacts.php', "status='reauthorization_required'"],
    ['includes/integrations/square-contacts.php', "'email_unsubscribed'"],
    ['includes/integrations/square-contacts.php', "'UNSUBSCRIBED' : 'UNKNOWN'"],
    ['includes/integrations/square-contacts.php', "'accepts_marketing' => false"],
    ['includes/integrations/square-contacts.php', "'addresses_excluded' => true"],
    ['includes/integrations/square-contacts.php', "'phone_numbers_excluded' => true"],
    ['includes/integrations/square-contacts.php', "'birthdays_excluded' => true"],
    ['includes/integrations/square-contacts.php', "'notes_excluded' => true"],
    ['includes/integrations/square-contacts.php', 'function mg_square_contact_preview'],
    ['includes/integrations/square-contacts.php', 'function mg_square_sync_contacts'],
    ['includes/integrations/square-contacts.php', 'function mg_square_import_contact'],
    ['includes/integrations/square-contacts.php', "'pending_review'"],
    ['includes/integrations/square-contacts.php', "'conflict'"],
    ['merchant-integrations-square-callback.php', 'mg_square_complete_oauth'],
    ['api/merchant/integrations.php', "'square_contacts' => mg_square_contacts_status"],
    ['api/merchant/integrations.php', "\$action === 'begin_square_oauth'"],
    ['api/merchant/integrations.php', 'mg_square_contact_preview'],
    ['api/merchant/integrations.php', 'mg_square_sync_contacts'],
    ['api/merchant/integrations.php', "provider_key='square'"],
    ['assets/js/merchant-integrations-square.js', "action: 'begin_square_oauth'"],
    ['assets/js/merchant-integrations-square.js', 'unsubscribe preserved'],
    ['assets/js/merchant-integrations-square.js', 'squareSignature'],
    ['assets/css/merchant-integrations-square.css', '.mg-square-connect-form'],
    ['merchant-integrations.php', '/assets/js/merchant-integrations-square.js?v=1.0.0'],
    ['merchant-integrations.php', '/assets/css/merchant-integrations-square.css?v=1.0.0'],
];

$failed = [];
foreach ($checks as [$path, $needle]) {
    $full = $root . '/' . $path;
    $content = is_file($full) ? file_get_contents($full) : false;
    if (!is_string($content) || !str_contains($content, $needle)) $failed[] = $path . ' :: ' . $needle;
}

if ($failed) {
    fwrite(STDERR, "Square Contacts v1 contract failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo 'Square Contacts v1 contract passed (' . count($checks) . " checks).\n";
