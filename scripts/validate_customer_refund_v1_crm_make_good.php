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
$profilePage = $read('merchant-customer.php');
$profileView = $read('includes/merchant-customer-profile-view.php');
$refundJs = $read('assets/js/merchant-customer-refund-send.js');
$campaignView = $read('includes/merchant-campaigns-view.php');
$campaignJs = $read('assets/js/stage12-campaigns.js');
$workflow = $read('.github/workflows/stage12-campaigns-validation.yml');
$sql = $read('database/customer_refund_v1_crm_make_good.sql');

$assert('Customer Refund campaign picker API exists', $campaignsApi !== '');
$assert('Customer Refund campaign picker API filters customer_refund campaigns', str_contains($campaignsApi, "campaign_type='customer_refund'") || str_contains($campaignsApi, "campaign_type = 'customer_refund'"));
$assert('Customer Refund campaign picker returns eligibility and inventory', str_contains($campaignsApi, 'eligible') && str_contains($campaignsApi, 'campaign_remaining') && str_contains($campaignsApi, 'reward_remaining'));
$assert('Customer Refund campaign picker can save internal campaigns', str_contains($campaignsApi, 'Customer Refund campaign created') && str_contains($campaignsApi, "'customer_refund'"));

$assert('Customer Refund send API exists', $sendApi !== '');
$assert('Customer Refund send API requires merchant manage permission and CSRF', str_contains($sendApi, 'merchant.campaigns.manage') && str_contains($sendApi, 'mg_require_csrf_for_write'));
$assert('Customer Refund send API requires active customer_refund campaign', str_contains($sendApi, "c.campaign_type=\'customer_refund\'") && str_contains($sendApi, "Customer Refund campaign must be active"));
$assert('Customer Refund send API creates/refers campaign contact source customer_refund', str_contains($sendApi, "'customer_refund', 'opted_in'"));
$assert('Customer Refund send API creates wallet source_type customer_refund', str_contains($sendApi, "source_type='customer_refund'") && str_contains($sendApi, "'customer_refund', (string)\$refundContact['public_id']"));
$assert('Customer Refund send API records wallet and CRM events', str_contains($sendApi, 'wallet_item.issued') && str_contains($sendApi, 'crm.customer_refund.sent') && str_contains($sendApi, 'mg_merchant_crm_record_event'));
$assert('Customer Refund send API bridges to PPPM wallet flow', str_contains($sendApi, 'mg_zero_reward_issue_from_wallet') && str_contains($sendApi, "'source_type' => 'customer_refund'"));
$assert('Customer Refund send API debits reward stamp', str_contains($sendApi, 'mg_public_campaign_debit_reward_stamp'));

$assert('Customer profile page loads refund send JS', str_contains($profilePage, 'merchant-customer-refund-send.js'));
$assert('Customer profile has reward action panel', str_contains($profileView, 'data-cp-action-panel="reward"') && str_contains($profileView, 'data-cp-reward-form'));
$assert('Customer refund JS loads campaigns API', str_contains($refundJs, '/api/merchant/customer-refund-campaigns.php'));
$assert('Customer refund JS sends through refund API', str_contains($refundJs, '/api/merchant/customer-refund-send.php'));
$assert('Customer refund JS previews campaign readiness and requires confirmation', str_contains($refundJs, 'data-cp-refund-preview') && str_contains($refundJs, 'data-cp-refund-confirm') && str_contains($refundJs, 'Review make-good'));
$assert('Customer refund JS uses make-good wording', str_contains($refundJs, 'Send Make-Good') && str_contains($refundJs, 'make-good voucher'));

$hasDirectInternalRegistry = str_contains($campaignView, 'mg_campaign_type_options(true)')
    && str_contains($campaignView, 'mg_campaign_type_client_registry(true)');
$hasFeatureGatedInternalRegistry = str_contains($campaignView, 'mg_public_donations_campaign_type_options')
    && str_contains($campaignView, 'mg_public_donations_client_registry')
    && str_contains($campaignView, ', true)');
$assert('Merchant campaign builder includes internal campaign types', $hasDirectInternalRegistry || $hasFeatureGatedInternalRegistry);
$assert('Merchant campaign builder exposes Customer Refund quick action', str_contains($campaignView, 'data-campaign-type-preset="customer_refund"') && str_contains($campaignView, 'Create Customer Refund'));
$assert('Merchant campaign builder explains internal-only Customer Refund behavior', str_contains($campaignView, 'data-campaign-type-fields="customer_refund"') && str_contains($campaignView, 'does not create a public landing page'));
$assert('Campaign JS can render customer_refund from registry', str_contains($campaignJs, 'window.MicrogifterCampaignTypes') && str_contains($campaignJs, 'registryMap'));

$assert('Stage 12 workflow lints Customer Refund APIs and validator', str_contains($workflow, 'api/merchant/customer-refund-campaigns.php') && str_contains($workflow, 'api/merchant/customer-refund-send.php') && str_contains($workflow, 'validate_customer_refund_v1_crm_make_good.php'));
$assert('No Customer Refund v1 SQL migration required', $sql === '');

foreach ($checks as [$label, $passed]) echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
if ($failures) {
    echo PHP_EOL . 'Customer Refund v1 CRM make-good validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Customer Refund v1 CRM make-good validation passed.' . PHP_EOL;
