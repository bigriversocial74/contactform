<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'includes/public-profile-community.php',
    'api/public/profile-investment.php',
    'profile.php',
    'assets/js/public-profile-community-v1.js',
    'assets/css/public-profile-community-v1.css',
    'scripts/test_public_profile_community_mysql.php',
    'tests/phpunit/PublicProfileCommunityContractTest.php',
    '.github/workflows/public-profile-community-v1.yml',
];
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (string)file_get_contents($root . '/' . $path) : '';
$ok = true;
$checks = [];
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    $checks['file:' . $path] = $exists;
    $ok = $ok && $exists;
}
$core = $read('includes/public-profile-community.php');
$api = $read('api/public/profile-investment.php');
$page = $read('profile.php');
$js = $read('assets/js/public-profile-community-v1.js');
$css = $read('assets/css/public-profile-community-v1.css');
$contracts = [
    'merchant_scoped' => substr_count($core, 'merchant_user_id=?') >= 5,
    'canonical_lifecycle' => str_contains($core, 'INNER JOIN wallet_items wallet')
        && str_contains($core, 'INNER JOIN pppm_items pppm')
        && str_contains($core, 'INNER JOIN microgift_instances microgift'),
    'unique_accounts' => str_contains($core, 'GROUP BY assignment.community_user_id')
        && str_contains($core, 'COUNT(DISTINCT assignment.campaign_id)'),
    'history_states' => str_contains($core, "campaign.status IN ('active','paused','ended')")
        && str_contains($core, "return 'completed'")
        && str_contains($core, "return 'paused'")
        && str_contains($core, "return 'active'"),
    'active_filter' => str_contains($core, 'mg_public_profile_community_enrich_campaign_items')
        && str_contains($core, "if (\$type !== 'public_donation')")
        && str_contains($core, 'continue;'),
    'dedicated_route' => str_contains($core, "'/public-donations.php?campaign='")
        && str_contains($core, "'action_label' =") === false,
    'privacy' => str_contains($core, "'final_recipient_identity_exposed' => false")
        && !str_contains($core, 'recipient.display_name')
        && !str_contains($core, 'downstream_user')
        && !str_contains($core, 'claim_code')
        && !str_contains($core, 'internal_note'),
    'api_enrichment' => str_contains($api, "\$payload['community_support']")
        && str_contains($api, 'mg_public_profile_community_enrich_campaign_items'),
    'community_tab' => str_contains($page, 'data-invest-tab="community"')
        && str_contains($page, 'data-profile-community-summary')
        && str_contains($page, 'data-profile-community-campaigns')
        && str_contains($page, 'data-profile-community-accounts'),
    'safe_dom' => str_contains($js, 'document.createElement')
        && !str_contains($js, '.innerHTML')
        && !str_contains($js, 'document.write')
        && !str_contains($js, 'eval('),
    'query_tab' => str_contains($js, "get('tab')") && str_contains($js, "requested === 'community'"),
    'active_card_artwork' => str_contains($js, 'mg-profile-campaign-media')
        && str_contains($js, 'gross rewards')
        && str_contains($js, 'net allocated'),
    'responsive' => str_contains($css, '.mg-profile-community-summary')
        && str_contains($css, '@media(max-width:780px)')
        && str_contains($css, '@media(max-width:520px)'),
];
foreach ($contracts as $name => $passed) {
    $checks[$name] = (bool)$passed;
    $ok = $ok && (bool)$passed;
}
echo json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
