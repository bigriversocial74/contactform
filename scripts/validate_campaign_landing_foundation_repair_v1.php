<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$failures = [];
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (string)file_get_contents($full) : '';
};
$assert = static function (string $label, bool $passed) use (&$checks, &$failures): void {
    $checks[] = [$label, $passed];
    if (!$passed) $failures[] = $label;
};

require_once $root . '/includes/campaign-types.php';
$registry = mg_campaign_type_registry();
$publicTypes = array_filter($registry, static fn(array $type): bool => !empty($type['public_enabled']));
$foundation = $read('includes/campaign-landing-foundation.php');
$renderer = $read('includes/public-campaign-page.php');
$header = $read('includes/header.php');
$foundationCss = $read('assets/css/campaign-landing-foundation.css');
$simpleCss = $read('assets/css/public-campaign-rl-landing-v1.css');

$assert('Campaign registry exposes 13 public campaign types', count($publicTypes) === 13);
$assert('Customer Refund remains internal-only', isset($registry['customer_refund']) && empty($registry['customer_refund']['public_enabled']) && !empty($registry['customer_refund']['internal_only']));

foreach ($publicTypes as $type => $definition) {
    $path = ltrim((string)($definition['public_path'] ?? ''), '/');
    $assert('Public route exists for ' . $type, $path !== '' && is_file($root . '/' . $path));
    $assert('Submit endpoint is registered for ' . $type, trim((string)($definition['submit_endpoint'] ?? '')) !== '');
}

foreach ([
    'mg_campaign_landing_load',
    'mg_campaign_landing_state',
    'mg_campaign_landing_profile',
    'mg_campaign_landing_meta',
    'mg_campaign_landing_bootstrap',
    'mg_campaign_landing_render_profile',
    'mg_campaign_landing_render_bottom_cards',
] as $function) {
    $assert('Foundation defines ' . $function, str_contains($foundation, 'function ' . $function));
}

$assert('Header loads the scoped campaign foundation stylesheet', str_contains($header, 'campaign-landing-foundation.css'));
$assert('Header no longer injects the broad campaign override for every campaign page', !str_contains($header, "<?php if (!\$is_app_page && \$page_section === 'campaign'): ?><link rel=\"stylesheet\" href=\"/assets/css/public-campaign-unified-layout-v2.css\""));
$assert('Legacy campaign layout allowlist has been retired', !str_contains($header, 'legacy_campaign_layout_pages') && !str_contains($header, 'public-campaign-unified-layout-v2.css'));
$assert('Foundation CSS owns shared trust and state UI', str_contains($foundationCss, '.mg-rl-campaign-foundation') && str_contains($foundationCss, '[data-campaign-closed-state]'));

$simplePages = [
    'newsletter-signup.php' => 'newsletter_signup',
    'contest.php' => 'contest_giveaway',
    'qr-reward.php' => 'qr_reward_drop',
    'referral-reward.php' => 'referral_reward',
    'birthday-vip.php' => 'birthday_vip',
    'agent-offer.php' => 'agent_offer',
];
foreach ($simplePages as $file => $type) {
    $source = $read($file);
    $bootstrapPosition = strpos($source, 'mg_campaign_landing_bootstrap');
    $headerPosition = strpos($source, "require __DIR__ . '/includes/header.php'");
    $assert($file . ' uses the canonical bootstrap', $bootstrapPosition !== false);
    $assert($file . ' loads campaign data before the header', $bootstrapPosition !== false && $headerPosition !== false && $bootstrapPosition < $headerPosition);
    $assert($file . ' pins its expected registry type', str_contains($source, "\$mgCampaignExpectedType = '" . $type . "'"));
    $assert($file . ' emits dynamic page metadata', str_contains($source, "\$page_meta = is_array(\$mgCampaignBootstrap['page_meta'])"));
}

$assert('Shared renderer consumes canonical state evaluation', str_contains($renderer, 'mg_campaign_landing_state'));
$assert('Shared renderer consumes canonical profile component', str_contains($renderer, 'mg_campaign_landing_render_profile'));
$assert('Shared renderer consumes canonical bottom cards', str_contains($renderer, 'mg_campaign_landing_render_bottom_cards'));
$assert('Signup Reward lower cards are omitted structurally', str_contains($renderer, "'hidden' => \$campaignType === 'newsletter_signup'"));
$assert('Shared renderer prefers campaign artwork before reward fallback', str_contains($renderer, 'mg_campaign_landing_campaign_image') && str_contains($renderer, 'mg_campaign_landing_reward_cover'));
$assert('Simple campaign CSS no longer needs to own global layout behavior', !str_contains($simpleCss, 'public-campaign-unified-layout-v2.css'));

foreach ($checks as [$label, $passed]) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}
if ($failures) {
    echo PHP_EOL . 'Campaign Landing Foundation Repair validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}
echo PHP_EOL . 'Campaign Landing Foundation Repair validation passed.' . PHP_EOL;
