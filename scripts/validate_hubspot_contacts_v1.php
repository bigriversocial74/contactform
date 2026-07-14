<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    ['includes/integrations/providers/hubspot.php', 'final class MgHubSpotProvider'],
    ['includes/integrations/providers/hubspot.php', "return ['oauth', 'crm.objects.contacts.read']"],
    ['includes/integrations/providers/hubspot.php', 'https://app.hubspot.com/oauth/authorize'],
    ['includes/integrations/providers/hubspot.php', 'https://api.hubapi.com/oauth/v1/token'],
    ['includes/integrations/providers/hubspot.php', '/oauth/v1/access-tokens/'],
    ['includes/integrations/providers/hubspot.php', '/crm/v3/objects/contacts?'],
    ['includes/integrations/providers/hubspot.php', "'archived' => 'false'"],
    ['includes/integrations/providers/hubspot.php', "'properties' => implode(',', ['email', 'firstname', 'lastname', 'lifecyclestage', 'createdate', 'lastmodifieddate'])"],
    ['includes/integrations/providers/hubspot.php', 'Authorization: Bearer '],
    ['includes/integrations/providers/hubspot.php', 'Content-Type: application/x-www-form-urlencoded'],
    ['includes/integrations/hubspot-contacts.php', 'function mg_hubspot_begin_oauth'],
    ['includes/integrations/hubspot-contacts.php', "hash('sha256', \$state)"],
    ['includes/integrations/hubspot-contacts.php', 'oauth_state_expires_at'],
    ['includes/integrations/hubspot-contacts.php', 'function mg_hubspot_complete_oauth'],
    ['includes/integrations/hubspot-contacts.php', 'mg_integration_encrypt_secret($accessToken)'],
    ['includes/integrations/hubspot-contacts.php', 'mg_integration_encrypt_secret($refreshToken)'],
    ['includes/integrations/hubspot-contacts.php', 'function mg_hubspot_refresh_credentials'],
    ['includes/integrations/hubspot-contacts.php', 'refresh_lock_token'],
    ['includes/integrations/hubspot-contacts.php', "status='reauthorization_required'"],
    ['includes/integrations/hubspot-contacts.php', 'function mg_hubspot_contact_preview'],
    ['includes/integrations/hubspot-contacts.php', 'function mg_hubspot_sync_contacts'],
    ['includes/integrations/hubspot-contacts.php', 'function mg_hubspot_import_contact'],
    ['includes/integrations/hubspot-contacts.php', 'mg_hubspot_contact_match'],
    ['includes/integrations/hubspot-contacts.php', 'mg_crm_identity_alias_contact'],
    ['includes/integrations/hubspot-contacts.php', "entity_type='contact'"],
    ['includes/integrations/hubspot-contacts.php', "'pending_review'"],
    ['includes/integrations/hubspot-contacts.php', "'conflict'"],
    ['includes/integrations/hubspot-contacts.php', "'lifecycle_stage'"],
    ['includes/integrations/hubspot-contacts.php', "'marketing_consent_inferred' => false"],
    ['includes/integrations/hubspot-contacts.php', "'subscription_preferences_imported' => false"],
    ['includes/integrations/hubspot-contacts.php', "'addresses_excluded' => true"],
    ['includes/integrations/hubspot-contacts.php', "'phone_numbers_excluded' => true"],
    ['includes/integrations/hubspot-contacts.php', "resource_key='contacts'"],
    ['includes/integrations/hubspot-contacts.php', 'cursor_value'],
    ['includes/integrations/hubspot-contacts.php', 'processed_count'],
    ['merchant-integrations-hubspot-callback.php', 'mg_hubspot_complete_oauth'],
    ['merchant-integrations-hubspot-callback.php', "'subscription_preferences_enabled' => false"],
    ['api/merchant/integrations.php', "'hubspot_contacts' => mg_hubspot_contacts_status"],
    ['api/merchant/integrations.php', "\$action === 'begin_hubspot_oauth'"],
    ['api/merchant/integrations.php', 'mg_hubspot_contact_preview'],
    ['api/merchant/integrations.php', 'mg_hubspot_sync_contacts'],
    ['assets/js/merchant-integrations-hubspot.js', "action: 'begin_hubspot_oauth'"],
    ['assets/js/merchant-integrations-hubspot.js', 'consent unknown'],
    ['assets/js/merchant-integrations-hubspot.js', 'hubspotSignature'],
    ['assets/css/merchant-integrations-hubspot.css', '.mg-hubspot-connect-form'],
    ['merchant-integrations.php', '/assets/js/merchant-integrations-hubspot.js?v=1.0.0'],
    ['merchant-integrations.php', '/assets/css/merchant-integrations-hubspot.css?v=1.0.0'],
];

$failed = [];
foreach ($checks as [$path, $needle]) {
    $full = $root . '/' . $path;
    $content = is_file($full) ? file_get_contents($full) : false;
    if (!is_string($content) || !str_contains($content, $needle)) $failed[] = $path . ' :: ' . $needle;
}
if ($failed) {
    fwrite(STDERR, "HubSpot Contacts v1 contract failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}
echo 'HubSpot Contacts v1 contract passed (' . count($checks) . " checks).\n";
