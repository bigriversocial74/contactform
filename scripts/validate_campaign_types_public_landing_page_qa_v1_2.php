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

$api = $read('api/merchant/campaign-landing-page-qa.php');
$view = $read('includes/merchant-campaigns-view.php');
$js = $read('assets/js/stage12-campaign-landing-qa.js');
$tabs = $read('assets/js/stage12-campaigns.js');
$toolsApi = $read('api/merchant/campaign-public-tools.php');
$detailApi = $read('api/merchant/campaign-detail.php');
$workflow = $read('.github/workflows/stage12-campaigns-validation.yml');
$sql = $read('database/campaign_types_public_landing_page_qa_v1_2.sql');

$assert('Landing page QA API exists', $api !== '');
$assert('Landing page QA API requires merchant view permission', str_contains($api, 'merchant.campaigns.view'));
$assert('Landing page QA API uses campaign type registry', str_contains($api, '/includes/campaign-types.php') && str_contains($api, 'mg_campaign_type_get'));
$assert('Landing page QA API generates registry public URLs', str_contains($api, 'mg_clpqa_public_url') && str_contains($api, 'public_path'));
$assert('Landing page QA API checks public_enabled/internal_only', str_contains($api, 'public_enabled') && str_contains($api, 'internal_only'));
$assert('Landing page QA API checks status/reward/endpoint/QR token', str_contains($api, 'status_active') && str_contains($api, 'reward_attached') && str_contains($api, 'submit_endpoint') && str_contains($api, 'qr_token'));
$assert('Landing page QA API returns expected fields', str_contains($api, 'expected_fields') && str_contains($api, 'birthday_month') && str_contains($api, 'offer_interest'));
$assert('Landing page QA API returns totals', str_contains($api, "'totals'") && str_contains($api, 'needs_attention'));

$assert('Merchant Campaigns view has Landing QA tab', str_contains($view, 'data-campaign-tab="landing_qa"') && str_contains($view, 'Public landing page QA'));
$assert('Merchant Campaigns view has QA targets', str_contains($view, 'data-campaign-landing-page-qa') && str_contains($view, 'data-campaign-landing-qa-summary'));
$assert('Merchant Campaigns view loads QA script', str_contains($view, 'stage12-campaign-landing-qa.js'));

$assert('Campaign tab controller registers landing_qa', str_contains($tabs, "'campaign-landing-qa':'landing_qa'") && str_contains($tabs, "landing_qa:'landing_qa'"));

$assert('Landing QA JS exists', $js !== '');
$assert('Landing QA JS calls QA API', str_contains($js, '/api/merchant/campaign-landing-page-qa.php'));
$assert('Landing QA JS renders summary', str_contains($js, 'data-campaign-landing-qa-summary') && str_contains($js, 'setSummary'));
$assert('Landing QA JS renders checks', str_contains($js, 'renderChecks') && str_contains($js, 'Needs attention'));
$assert('Landing QA JS renders open/copy actions', str_contains($js, 'Open landing page') && str_contains($js, 'data-copy-landing-url'));
$assert('Landing QA JS renders internal-only state', str_contains($js, 'Internal-only'));

$assert('Campaign public tools API uses registry', str_contains($toolsApi, '/includes/campaign-types.php') && str_contains($toolsApi, 'mg_campaign_type_get') && str_contains($toolsApi, 'submit_endpoint'));
$assert('Campaign detail API uses registry URLs', str_contains($detailApi, '/includes/campaign-types.php') && str_contains($detailApi, 'mg_campaign_type_get') && str_contains($detailApi, 'submit_endpoint'));
$assert('Workflow lints v1.2 API and validator', str_contains($workflow, 'api/merchant/campaign-landing-page-qa.php') && str_contains($workflow, 'validate_campaign_types_public_landing_page_qa_v1_2.php'));
$assert('No v1.2 SQL migration required', $sql === '');

foreach ($checks as [$label, $passed]) echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
if ($failures) {
    echo PHP_EOL . 'Campaign Types v1.2 Public Landing Page QA validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Campaign Types v1.2 Public Landing Page QA validation passed.' . PHP_EOL;
