<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'public-donations.php',
    'includes/public-donations-public.php',
    'includes/public-donations-public-view.php',
    'api/public/public-donations.php',
    'assets/css/public-donations-public-v1.css',
    'scripts/test_public_donations_public_campaign_mysql.php',
    'tests/phpunit/PublicDonationsPublicCampaignContractTest.php',
    '.github/workflows/public-donations-public-campaign-v1.yml',
];

$read = static fn(string $path): string => is_file($root . '/' . $path)
    ? (string)file_get_contents($root . '/' . $path)
    : '';
$ok = true;
$checks = [];
foreach ($required as $path) {
    $exists = is_file($root . '/' . $path);
    $checks['file:' . $path] = $exists;
    $ok = $ok && $exists;
}

$page = $read('public-donations.php');
$core = $read('includes/public-donations-public.php');
$view = $read('includes/public-donations-public-view.php');
$api = $read('api/public/public-donations.php');
$css = $read('assets/css/public-donations-public-v1.css');

$contracts = [
    'dedicated_public_route' => str_contains($page, 'public-donations-public.php')
        && str_contains($page, 'public-donations-public-view.php')
        && !str_contains($page, 'public-campaign-page.php'),
    'dedicated_get_endpoint' => str_contains($api, "mg_require_method('GET')")
        && str_contains($api, 'mg_public_donations_public_payload')
        && !preg_match('/\b(INSERT|UPDATE|DELETE)\b/i', $api),
    'active_public_campaign_only' => str_contains($core, "campaign.campaign_type='public_donation'")
        && str_contains($core, "campaign.status='active'")
        && str_contains($core, 'campaign.starts_at<=NOW()')
        && str_contains($core, 'campaign.ends_at>=NOW()'),
    'public_display_consent' => substr_count($core, "assignment.public_display_status='approved'") >= 2
        && substr_count($core, "profile.status='active'") >= 2
        && substr_count($core, "profile.visibility IN ('public','unlisted')") >= 2,
    'anonymous_aggregate' => str_contains($core, "'anonymous_accounts'")
        && str_contains($core, 'max(0, $supportedAccounts - $visibleAccounts)')
        && str_contains($view, 'included anonymously in totals'),
    'reconciled_impact' => str_contains($core, "'gross_allocated'")
        && str_contains($core, "'recalled'")
        && str_contains($core, "'net_allocated'")
        && str_contains($core, "'stated_value_by_currency'"),
    'no_private_lifecycle_joins' => !preg_match('/\bJOIN\s+(wallet_items|pppm_items|microgift_instances|inbox_items)\b/i', $core)
        && !str_contains($core, 'internal_note')
        && !str_contains($core, 'claim_code'),
    'non_transactional_payload' => str_contains($core, "'public_transactional' => false")
        && str_contains($core, "'public_purchase_available' => false")
        && str_contains($core, "'public_request_available' => false"),
    'no_transaction_controls' => !preg_match('/<(form|input|textarea|select|button)\b/i', $view)
        && !str_contains($view, 'data-submit-endpoint')
        && !str_contains($view, 'name="email"')
        && !str_contains($view, 'name="quantity"'),
    'accurate_governance_copy' => str_contains($view, 'Merchant-funded promotional rewards—not cash donations')
        && str_contains($view, 'not cash, a charitable receipt, or a tax-deductible contribution')
        && str_contains($core, "'tax_deductible_contribution' => false")
        && str_contains($core, "'cash_donation' => false"),
    'seo_visibility' => str_contains($core, "'indexable'")
        && str_contains($core, "'index,follow'")
        && str_contains($core, "'noindex,nofollow'")
        && str_contains($page, "'robots' =>"),
    'safe_unexpected_errors' => str_contains($api, 'mg_fail_unexpected(')
        && !str_contains($api, '$error->getMessage()'),
    'page_links' => str_contains($core, "'profile_url'")
        && str_contains($core, "'community_url'")
        && str_contains($core, "'offers_url'"),
    'responsive_styles' => str_contains($css, '.mg-pd-public__community-grid')
        && str_contains($css, '@media(max-width:820px)')
        && str_contains($css, '@media(max-width:580px)'),
];

foreach ($contracts as $name => $passed) {
    $checks[$name] = (bool)$passed;
    $ok = $ok && (bool)$passed;
}

echo json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
