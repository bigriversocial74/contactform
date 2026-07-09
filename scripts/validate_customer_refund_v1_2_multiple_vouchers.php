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
$sendJs = $read('assets/js/merchant-customer-refund-send.js');
$workflow = $read('.github/workflows/stage12-campaigns-validation.yml');
$sql = $read('database/customer_refund_v1_2_multiple_vouchers.sql');

$assert('Campaign picker returns customer status', str_contains($campaignsApi, 'mg_customer_refund_contact_status_map') && str_contains($campaignsApi, 'customer_status'));
$assert('Campaign picker supports contact_id lookup', str_contains($campaignsApi, "$_GET['contact_id']") || str_contains($campaignsApi, 'contact_id'));
$assert('Campaign picker states multiple vouchers are allowed', str_contains($campaignsApi, 'multiple_vouchers_allowed') && str_contains($campaignsApi, 'Multiple make-good vouchers are allowed'));
$assert('Campaign picker returns sent/open/claimed/redeemed counts', str_contains($campaignsApi, 'sent_count') && str_contains($campaignsApi, 'open_count') && str_contains($campaignsApi, 'claimed_count') && str_contains($campaignsApi, 'redeemed_count'));

$assert('Send API removed active duplicate block', !str_contains($sendApi, 'activeDuplicate') && !str_contains($sendApi, 'already has an active Customer Refund voucher'));
$assert('Send API does not enforce per-user public campaign limit for Customer Refund', !str_contains($sendApi, 'mg_public_campaign_enforce_reward_limits'));
$assert('Send API returns updated customer status', str_contains($sendApi, 'mg_customer_refund_send_customer_status') && str_contains($sendApi, 'customer_status'));
$assert('Send API still keeps idempotency protection', str_contains($sendApi, 'crm_idempotency_key') && str_contains($sendApi, 'Customer Refund voucher already issued for this request'));
$assert('Send API declares multiple vouchers allowed', str_contains($sendApi, 'multiple_vouchers_allowed'));

$assert('Customer Profile JS passes contact_id to picker', str_contains($sendJs, 'contact_id=') && str_contains($sendJs, 'encodeURIComponent(cid)'));
$assert('Customer Profile JS renders customer history before send', str_contains($sendJs, 'customerStatusText') && str_contains($sendJs, 'Customer history'));
$assert('Customer Profile JS confirms multiple sends are allowed', str_contains($sendJs, 'Multiple vouchers are allowed') || str_contains($sendJs, 'Multiple make-good vouchers'));
$assert('Customer Profile JS shows updated totals after send', str_contains($sendJs, 'Customer totals') && str_contains($sendJs, 'claimed') && str_contains($sendJs, 'redeemed'));

$assert('Stage 12 workflow runs v1.2 validator', str_contains($workflow, 'validate_customer_refund_v1_2_multiple_vouchers.php'));
$assert('No Customer Refund v1.2 SQL migration required', $sql === '');

foreach ($checks as [$label, $passed]) echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
if ($failures) {
    echo PHP_EOL . 'Customer Refund v1.2 multiple voucher validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Customer Refund v1.2 multiple voucher validation passed.' . PHP_EOL;
