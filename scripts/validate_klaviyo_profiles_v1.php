<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    ['includes/integrations/providers/klaviyo.php', 'final class MgKlaviyoProvider'],
    ['includes/integrations/providers/klaviyo.php', "return ['accounts:read', 'profiles:read']"],
    ['includes/integrations/providers/klaviyo.php', 'https://www.klaviyo.com/oauth/authorize'],
    ['includes/integrations/providers/klaviyo.php', 'https://a.klaviyo.com/oauth/token'],
    ['includes/integrations/providers/klaviyo.php', "'code_challenge_method' => 'S256'"],
    ['includes/integrations/providers/klaviyo.php', "'code_challenge' => \$codeChallenge"],
    ['includes/integrations/providers/klaviyo.php', "'code_verifier' => \$codeVerifier"],
    ['includes/integrations/providers/klaviyo.php', 'Authorization: Basic '],
    ['includes/integrations/providers/klaviyo.php', "'grant_type' => 'refresh_token'"],
    ['includes/integrations/providers/klaviyo.php', '/api/accounts?'],
    ['includes/integrations/providers/klaviyo.php', "'fields[account]' => implode(',', ['id', 'public_api_key', 'timezone', 'locale', 'test_account'])"],
    ['includes/integrations/providers/klaviyo.php', '/api/profiles?'],
    ['includes/integrations/providers/klaviyo.php', "'additional-fields[profile]' => 'subscriptions'"],
    ['includes/integrations/providers/klaviyo.php', "'fields[profile]' => implode(',', \$fields)"],
    ['includes/integrations/providers/klaviyo.php', 'subscriptions.email.marketing.can_receive_email_marketing'],
    ['includes/integrations/providers/klaviyo.php', 'subscriptions.email.marketing.consent'],
    ['includes/integrations/providers/klaviyo.php', 'subscriptions.email.marketing.suppression'],
    ['includes/integrations/providers/klaviyo.php', "'page[cursor]'"],
    ['includes/integrations/providers/klaviyo.php', "'page[size]'"],
    ['includes/integrations/providers/klaviyo.php', "'revision: ' . \$this->revision()"],
    ['includes/integrations/providers/klaviyo.php', '2026-04-15'],
    ['includes/integrations/providers/klaviyo.php', 'Authorization: Bearer '],
    ['includes/integrations/klaviyo-profile-core.php', 'function mg_klaviyo_pkce_pair'],
    ['includes/integrations/klaviyo-profile-core.php', "hash('sha256', \$verifier, true)"],
    ['includes/integrations/klaviyo-profile-core.php', 'strlen($verifier) < 43'],
    ['includes/integrations/klaviyo-profile-core.php', 'function mg_klaviyo_begin_oauth'],
    ['includes/integrations/klaviyo-profile-core.php', "hash('sha256', \$state)"],
    ['includes/integrations/klaviyo-profile-core.php', 'oauth_state_expires_at'],
    ['includes/integrations/klaviyo-profile-core.php', 'mg_integration_encrypt_secret($pkce[\'verifier\'])'],
    ['includes/integrations/klaviyo-profile-core.php', 'function mg_klaviyo_complete_oauth'],
    ['includes/integrations/klaviyo-profile-core.php', 'mg_integration_decrypt_secret($pending[\'api_key_ciphertext\']'],
    ['includes/integrations/klaviyo-profile-core.php', 'api_key_ciphertext=NULL'],
    ['includes/integrations/klaviyo-profile-core.php', 'mg_integration_encrypt_secret($accessToken)'],
    ['includes/integrations/klaviyo-profile-core.php', 'mg_integration_encrypt_secret($refreshToken)'],
    ['includes/integrations/klaviyo-profile-core.php', 'function mg_klaviyo_refresh_credentials'],
    ['includes/integrations/klaviyo-profile-core.php', 'refresh_lock_token'],
    ['includes/integrations/klaviyo-profile-core.php', "status='reauthorization_required'"],
    ['includes/integrations/klaviyo-profile-core.php', "\$consent === 'SUBSCRIBED' && \$canReceive"],
    ['includes/integrations/klaviyo-profile-core.php', "'inferred' => false"],
    ['includes/integrations/klaviyo-profile-core.php', "'suppressions' => \$suppressions"],
    ['includes/integrations/klaviyo-profile-core.php', "'list_suppressions' => \$listSuppressions"],
    ['includes/integrations/klaviyo-profile-core.php', "'addresses_excluded' => true"],
    ['includes/integrations/klaviyo-profile-core.php', "'phone_numbers_excluded' => true"],
    ['includes/integrations/klaviyo-profile-core.php', "'location_excluded' => true"],
    ['includes/integrations/klaviyo-profile-core.php', "'custom_properties_excluded' => true"],
    ['includes/integrations/klaviyo-profile-import.php', 'function mg_klaviyo_profile_preview'],
    ['includes/integrations/klaviyo-profile-import.php', 'function mg_klaviyo_import_profile'],
    ['includes/integrations/klaviyo-profile-import.php', 'mg_klaviyo_profile_match'],
    ['includes/integrations/klaviyo-profile-import.php', 'mg_crm_identity_alias_contact'],
    ['includes/integrations/klaviyo-profile-import.php', "entity_type='contact'"],
    ['includes/integrations/klaviyo-profile-import.php', "'pending_review'"],
    ['includes/integrations/klaviyo-profile-import.php', "'conflict'"],
    ['includes/integrations/klaviyo-profile-sync.php', 'function mg_klaviyo_sync_profiles'],
    ['includes/integrations/klaviyo-profile-sync.php', "resource_key='profiles'"],
    ['includes/integrations/klaviyo-profile-sync.php', 'cursor_value'],
    ['includes/integrations/klaviyo-profile-sync.php', 'processed_count'],
    ['merchant-integrations-klaviyo-callback.php', 'mg_klaviyo_complete_oauth'],
    ['merchant-integrations-klaviyo-callback.php', "'pkce_method' => 'S256'"],
    ['merchant-integrations-klaviyo-callback.php', "'custom_properties_enabled' => false"],
    ['api/merchant/integrations.php', "'klaviyo_profiles' => mg_klaviyo_profiles_status"],
    ['api/merchant/integrations.php', "\$action === 'begin_klaviyo_oauth'"],
    ['api/merchant/integrations.php', "\$action === 'preview_profiles'"],
    ['api/merchant/integrations.php', "\$action === 'sync_profiles'"],
    ['api/merchant/integrations.php', 'mg_klaviyo_profile_preview'],
    ['api/merchant/integrations.php', 'mg_klaviyo_sync_profiles'],
    ['assets/js/merchant-integrations-klaviyo.js', "action: 'begin_klaviyo_oauth'"],
    ['assets/js/merchant-integrations-klaviyo.js', "action: 'preview_profiles'"],
    ['assets/js/merchant-integrations-klaviyo.js', "action: 'sync_profiles'"],
    ['assets/js/merchant-integrations-klaviyo.js', 'subscription evidence preserved'],
    ['assets/js/merchant-integrations-klaviyo.js', 'klaviyoSignature'],
    ['assets/css/merchant-integrations-klaviyo.css', '.mg-klaviyo-connect-form'],
    ['merchant-integrations.php', '/assets/js/merchant-integrations-klaviyo.js?v=1.0.0'],
    ['merchant-integrations.php', '/assets/css/merchant-integrations-klaviyo.css?v=1.0.0'],
    ['includes/merchant-integrations-view.php', 'Mailchimp, and Klaviyo'],
];

$failed = [];
foreach ($checks as [$path, $needle]) {
    $full = $root . '/' . $path;
    $content = is_file($full) ? file_get_contents($full) : false;
    if (!is_string($content) || !str_contains($content, $needle)) $failed[] = $path . ' :: ' . $needle;
}

require_once $root . '/includes/integrations/klaviyo-profiles.php';
$pair = mg_klaviyo_pkce_pair();
$semantic = [
    'PKCE verifier meets RFC length' => strlen($pair['verifier']) >= 43 && strlen($pair['verifier']) <= 128,
    'PKCE challenge is base64url' => preg_match('/^[A-Za-z0-9_-]+$/', $pair['challenge']) === 1,
    'subscribed and receivable maps to consent' => mg_klaviyo_marketing(['subscriptions' => ['email' => ['marketing' => ['consent' => 'SUBSCRIBED', 'can_receive_email_marketing' => true]]]])['accepts_marketing'] === true,
    'subscribed but suppressed does not map to consent' => mg_klaviyo_marketing(['subscriptions' => ['email' => ['marketing' => ['consent' => 'SUBSCRIBED', 'can_receive_email_marketing' => false]]]])['accepts_marketing'] === false,
    'unknown consent is conservative' => mg_klaviyo_marketing([])['accepts_marketing'] === false,
];
foreach ($semantic as $label => $passed) if (!$passed) $failed[] = $label;

if ($failed) {
    fwrite(STDERR, "Klaviyo Profiles v1 contract failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}
echo 'Klaviyo Profiles v1 contract passed (' . (count($checks) + count($semantic)) . " checks).\n";
