<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    ['includes/integrations/providers/mailchimp.php', 'final class MgMailchimpProvider'],
    ['includes/integrations/providers/mailchimp.php', 'https://login.mailchimp.com/oauth2/authorize'],
    ['includes/integrations/providers/mailchimp.php', 'https://login.mailchimp.com/oauth2/token'],
    ['includes/integrations/providers/mailchimp.php', 'https://login.mailchimp.com/oauth2/metadata'],
    ['includes/integrations/providers/mailchimp.php', "'response_type' => 'code'"],
    ['includes/integrations/providers/mailchimp.php', 'Authorization: OAuth '],
    ['includes/integrations/providers/mailchimp.php', '/lists?'],
    ['includes/integrations/providers/mailchimp.php', '/members?'],
    ['includes/integrations/providers/mailchimp.php', 'members.merge_fields.FNAME'],
    ['includes/integrations/providers/mailchimp.php', 'members.merge_fields.LNAME'],
    ['includes/integrations/providers/mailchimp.php', "'members.status'"],
    ['includes/integrations/providers/mailchimp.php', "'members.tags'"],
    ['includes/integrations/providers/mailchimp.php', "\.api\.mailchimp\.com"],
    ['includes/integrations/mailchimp-contact-core.php', 'function mg_mailchimp_begin_oauth'],
    ['includes/integrations/mailchimp-contact-core.php', "hash('sha256', \$state)"],
    ['includes/integrations/mailchimp-contact-core.php', 'oauth_state_expires_at'],
    ['includes/integrations/mailchimp-contact-core.php', 'function mg_mailchimp_complete_oauth'],
    ['includes/integrations/mailchimp-contact-core.php', 'mg_integration_encrypt_secret($accessToken)'],
    ['includes/integrations/mailchimp-contact-core.php', "'access_token_non_expiring' => true"],
    ['includes/integrations/mailchimp-contact-core.php', 'refresh_token_ciphertext=NULL'],
    ['includes/integrations/mailchimp-contact-core.php', 'function mg_mailchimp_audiences'],
    ['includes/integrations/mailchimp-contact-core.php', 'function mg_mailchimp_select_audience'],
    ['includes/integrations/mailchimp-contact-core.php', 'selected_audience_id'],
    ['includes/integrations/mailchimp-contact-import.php', 'function mg_mailchimp_contact_preview'],
    ['includes/integrations/mailchimp-contact-sync.php', 'function mg_mailchimp_sync_contacts'],
    ['includes/integrations/mailchimp-contact-import.php', 'function mg_mailchimp_import_contact'],
    ['includes/integrations/mailchimp-contact-core.php', "'subscribed' => ['accepts_marketing' => true, 'status' => 'SUBSCRIBED']"],
    ['includes/integrations/mailchimp-contact-core.php', "'unsubscribed' => ['accepts_marketing' => false, 'status' => 'UNSUBSCRIBED']"],
    ['includes/integrations/mailchimp-contact-core.php', "'cleaned' => ['accepts_marketing' => false, 'status' => 'CLEANED']"],
    ['includes/integrations/mailchimp-contact-core.php', "'pending' => ['accepts_marketing' => false, 'status' => 'PENDING']"],
    ['includes/integrations/mailchimp-contact-core.php', "'transactional' => ['accepts_marketing' => false, 'status' => 'TRANSACTIONAL']"],
    ['includes/integrations/mailchimp-contact-core.php', "'inferred' => false"],
    ['includes/integrations/mailchimp-contact-core.php', "'addresses_excluded' => true"],
    ['includes/integrations/mailchimp-contact-core.php', "'phone_numbers_excluded' => true"],
    ['includes/integrations/mailchimp-contact-core.php', "'non_name_merge_fields_excluded' => true"],
    ['includes/integrations/mailchimp-contact-import.php', 'mg_mailchimp_contact_match'],
    ['includes/integrations/mailchimp-contact-import.php', 'mg_crm_identity_alias_contact'],
    ['includes/integrations/mailchimp-contact-import.php', "entity_type='contact'"],
    ['includes/integrations/mailchimp-contact-import.php', "'pending_review'"],
    ['includes/integrations/mailchimp-contact-import.php', "'conflict'"],
    ['includes/integrations/mailchimp-contact-sync.php', "'contacts:' . \$audience['id']"],
    ['includes/integrations/mailchimp-contact-sync.php', 'cursor_value'],
    ['includes/integrations/mailchimp-contact-sync.php', 'processed_count'],
    ['merchant-integrations-mailchimp-callback.php', 'mg_mailchimp_complete_oauth'],
    ['merchant-integrations-mailchimp-callback.php', "'marketing_status_preserved' => true"],
    ['api/merchant/integrations.php', "'mailchimp_contacts' => mg_mailchimp_contacts_status"],
    ['api/merchant/integrations.php', "\$action === 'begin_mailchimp_oauth'"],
    ['api/merchant/integrations.php', "\$action === 'list_audiences'"],
    ['api/merchant/integrations.php', "\$action === 'select_audience'"],
    ['api/merchant/integrations.php', 'mg_mailchimp_contact_preview'],
    ['api/merchant/integrations.php', 'mg_mailchimp_sync_contacts'],
    ['assets/js/merchant-integrations-mailchimp.js', "action: 'begin_mailchimp_oauth'"],
    ['assets/js/merchant-integrations-mailchimp.js', "action: 'list_audiences'"],
    ['assets/js/merchant-integrations-mailchimp.js', "action: 'select_audience'"],
    ['assets/js/merchant-integrations-mailchimp.js', 'explicit member status preserved'],
    ['assets/js/merchant-integrations-mailchimp.js', 'mailchimpSignature'],
    ['assets/css/merchant-integrations-mailchimp.css', '.mg-mailchimp-audience'],
    ['merchant-integrations.php', '/assets/js/merchant-integrations-mailchimp.js?v=1.0.0'],
    ['merchant-integrations.php', '/assets/css/merchant-integrations-mailchimp.css?v=1.0.0'],
    ['includes/merchant-integrations-view.php', 'Mailchimp'],
];

$failed = [];
foreach ($checks as [$path, $needle]) {
    $full = $root . '/' . $path;
    $content = is_file($full) ? file_get_contents($full) : false;
    if (!is_string($content) || !str_contains($content, $needle)) $failed[] = $path . ' :: ' . $needle;
}

require_once $root . '/includes/integrations/mailchimp-contacts.php';
$statusChecks = [
    'subscribed maps to explicit consent' => mg_mailchimp_status('subscribed')['accepts_marketing'] === true,
    'unsubscribed never maps to consent' => mg_mailchimp_status('unsubscribed')['accepts_marketing'] === false,
    'cleaned never maps to consent' => mg_mailchimp_status('cleaned')['status'] === 'CLEANED',
    'unknown status is conservative' => mg_mailchimp_status('unexpected')['status'] === 'UNKNOWN',
];
foreach ($statusChecks as $label => $passed) if (!$passed) $failed[] = $label;

if ($failed) {
    fwrite(STDERR, "Mailchimp Contacts v1 contract failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}
echo 'Mailchimp Contacts v1 contract passed (' . (count($checks) + count($statusChecks)) . " checks).\n";
