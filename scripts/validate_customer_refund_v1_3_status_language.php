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

$campaignsApi = $read('api/merchant/customer-refund-campaigns.php');
$sendApi = $read('api/merchant/customer-refund-send.php');
$qaApi = $read('api/merchant/customer-refund-qa.php');
$sendJs = $read('assets/js/merchant-customer-refund-send.js');
$qaJs = $read('assets/js/stage12-campaign-landing-qa.js');
$workflow = $read('.github/workflows/stage12-campaigns-validation.yml');
$sql = $read('database/customer_refund_v1_3_status_language.sql');

$assert('Campaign picker exposes simplified status language', str_contains($campaignsApi, "'status_language' => ['sent', 'open', 'claimed']"));
$assert('Campaign picker combines claimed and redeemed into claimed_count', str_contains($campaignsApi, "wi.status IN ('claimed','redeemed')") && str_contains($campaignsApi, 'claimed_count'));
$assert('Campaign picker no longer returns redeemed_count', !str_contains($campaignsApi, 'redeemed_count'));

$assert('Send API exposes simplified status language', str_contains($sendApi, "'status_language' => ['sent', 'open', 'claimed']"));
$assert('Send API combines claimed and redeemed into claimed_count', str_contains($sendApi, "wi.status IN ('claimed','redeemed')") && str_contains($sendApi, 'claimed_count'));
$assert('Send API no longer returns redeemed_count', !str_contains($sendApi, 'redeemed_count'));

$assert('Refund QA API exposes simplified status language', str_contains($qaApi, "'status_language' => ['sent', 'open', 'claimed']"));
$assert('Refund QA API combines claimed and redeemed wallet states', str_contains($qaApi, "wi.status IN ('claimed','redeemed')") && str_contains($qaApi, 'mg_crqa_customer_status_label'));
$assert('Refund QA API no longer exposes redeemed totals', !str_contains($qaApi, "'redeemed' =>") && !str_contains($qaApi, 'wallet_redeemed'));

$assert('Customer Profile JS shows sent open claimed only', str_contains($sendJs, "' sent · '") && str_contains($sendJs, "' open · '") && str_contains($sendJs, "' claimed'"));
$assert('Customer Profile JS no longer displays redeemed', !str_contains($sendJs, 'redeemed'));
$assert('Refund QA JS summary shows Open and Claimed only', str_contains($qaJs, "['Open',totals.open]") && str_contains($qaJs, "['Claimed',totals.claimed]") && !str_contains($qaJs, "['Redeemed'"));
$assert('Refund QA JS history displays claimed only', str_contains($qaJs, 'Sent: ') && str_contains($qaJs, 'Claimed: ') && !str_contains($qaJs, 'Redeemed:'));

$assert('Stage 12 workflow runs v1.3 validator', str_contains($workflow, 'validate_customer_refund_v1_3_status_language.php'));
$assert('No Customer Refund v1.3 SQL migration required', $sql === '');

foreach ($checks as [$label, $passed]) echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
if ($failures) {
    echo PHP_EOL . 'Customer Refund v1.3 status language validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Customer Refund v1.3 status language validation passed.' . PHP_EOL;
