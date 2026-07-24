<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }
$root = dirname(__DIR__);
$read = static function(string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value) || trim($value) === '') throw new RuntimeException('Missing file: ' . $path);
    return $value;
};
$registry = $read('includes/campaign-types.php');
$definition = $read('includes/public-donations-campaign-type.php');
$feature = $read('includes/public-donations-feature.php');
$merchant = $read('api/merchant/campaigns-core.php');
$publicPage = $read('includes/public-campaign-page.php');
$detail = $read('api/public/campaigns/detail.php');
$engage = $read('api/public/campaigns/engage.php');
$market = $read('includes/market/merchant-market-engine.php');
$profileJs = $read('assets/js/public-profile-investment.js');
$route = $read('public-donations.php');

$must = static function(string $text, array $needles, string $label): void {
    foreach ($needles as $needle) if (!str_contains($text, $needle)) throw new RuntimeException($label . ' missing: ' . $needle);
};
$must($definition, ["'key' => 'public_donation'", "'label' => 'Public Donations'", "'category' => 'community_support'", "'public_transactional' => false", "'public_mode' => 'informational'", "'wallet_issue_mode' => 'merchant_initiated_bulk'"], 'definition');
$must($registry, ['mg_public_donations_campaign_definition()', 'mg_campaign_type_public_transactional', 'mg_campaign_type_public_mode'], 'registry');
$must($feature, ['disabled', 'admin_only', 'selected_merchants', 'enabled', 'MG_PUBLIC_DONATIONS_FEATURE_STATE', 'MG_PUBLIC_DONATIONS_MERCHANT_IDS'], 'feature gate');
$must($merchant, ["\$campaignType === 'public_donation'", 'mg_public_donations_is_enabled_for', 'mg_public_donations_campaign_type_options'], 'merchant API');
$must($publicPage, ['mg_campaign_type_public_transactional', 'mg-public-donations-info', 'These rewards are not available for public purchase or request.'], 'public renderer');
$must($detail, ["'public_transactional'", "'public_mode'", 'mg_public_donations_is_enabled_for'], 'public detail');
$must($engage, ['engage-core.php', 'does not accept public requests', 'mg_campaign_type_public_transactional'], 'engagement guard');
$must($market, ['community_accounts_supported', 'rewards_allocated', "'card_variant'=>\$campaignType === 'public_donation' ? 'public_donation' : 'standard'"], 'profile campaign data');
$must($profileJs, ['mg-profile-campaign-badge', 'Community accounts supported', 'View Campaign'], 'profile card renderer');
$must($route, ["\$mgCampaignExpectedType = 'public_donation'", '/assets/css/public-donations-campaign-v1.css'], 'public route');
if (str_contains($publicPage, 'data-campaign-form data-public-donations')) throw new RuntimeException('Public Donations must not render a public form.');
echo "Public Donations campaign foundation contract valid.\n";
