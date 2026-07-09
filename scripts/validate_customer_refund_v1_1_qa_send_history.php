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

$qaApi = $read('api/merchant/customer-refund-qa.php');
$sendApi = $read('api/merchant/customer-refund-send.php');
$landingJs = $read('assets/js/stage12-campaign-landing-qa.js');
$workflow = $read('.github/workflows/stage12-campaigns-validation.yml');
$sql = $read('database/customer_refund_v1_1_qa_send_history.sql');

$assert('Customer Refund v1.1 QA API exists', $qaApi !== '');
$assert('QA API requires merchant campaign view permission', str_contains($qaApi, 'merchant.campaigns.view'));
$assert('QA API filters customer_refund campaigns', str_contains($qaApi, "c.campaign_type='customer_refund'"));
$assert('QA API returns campaign readiness checks', str_contains($qaApi, 'send_ready') && str_contains($qaApi, 'failure_messages') && str_contains($qaApi, 'campaign_inventory') && str_contains($qaApi, 'reward_inventory'));
$assert('QA API returns reward and campaign inventory', str_contains($qaApi, 'campaign_remaining') && str_contains($qaApi, 'reward_remaining'));
$assert('QA API returns send history', str_contains($qaApi, 'send_history') && str_contains($qaApi, "wi.source_type='customer_refund'"));
$assert('QA API returns wallet/customer/campaign fields', str_contains($qaApi, 'wallet_item_id') && str_contains($qaApi, 'customer_email') && str_contains($qaApi, 'campaign_title') && str_contains($qaApi, 'wallet_status'));
$assert('QA API documents email invite as future-only TODO', str_contains($qaApi, 'customer_refund_invite_by_email') && str_contains($qaApi, 'not enabled because outbound email sending is not active yet'));

$assert('Send API has duplicate active voucher guard', str_contains($sendApi, 'activeDuplicate') && str_contains($sendApi, 'already has an active Customer Refund voucher'));
$assert('Send API keeps account-required no-email boundary', str_contains($sendApi, 'Invite-by-email is planned for a future release') && str_contains($sendApi, 'email sending is not enabled yet'));

$assert('Campaign QA JS injects Refund QA tab', str_contains($landingJs, 'data-customer-refund-qa-tab') && str_contains($landingJs, 'Refund QA'));
$assert('Campaign QA JS injects Refund QA panel', str_contains($landingJs, 'data-customer-refund-qa-panel') && str_contains($landingJs, 'Make-Good QA + Send History'));
$assert('Campaign QA JS calls Customer Refund QA API', str_contains($landingJs, '/api/merchant/customer-refund-qa.php'));
$assert('Campaign QA JS renders readiness and send history', str_contains($landingJs, 'renderRefundCampaign') && str_contains($landingJs, 'renderRefundHistory'));
$assert('Campaign QA JS renders invite-by-email TODO', str_contains($landingJs, 'data-customer-refund-todo') && str_contains($landingJs, 'Invite-by-email remains a future enhancement'));

$assert('Stage 12 workflow lints v1.1 QA API', str_contains($workflow, 'api/merchant/customer-refund-qa.php'));
$assert('Stage 12 workflow runs v1.1 validator', str_contains($workflow, 'validate_customer_refund_v1_1_qa_send_history.php'));
$assert('No Customer Refund v1.1 SQL migration required', $sql === '');

foreach ($checks as [$label, $passed]) echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
if ($failures) {
    echo PHP_EOL . 'Customer Refund v1.1 QA + Send History validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Customer Refund v1.1 QA + Send History validation passed.' . PHP_EOL;
